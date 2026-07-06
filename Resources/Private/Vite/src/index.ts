import { readFileSync, realpathSync } from 'node:fs'
import { basename, dirname, isAbsolute, join, sep } from 'node:path'
import { fileURLToPath } from 'node:url'
import type { Plugin, ViteDevServer } from 'vite'

export const VIRTUAL_MODULE_ID = 'virtual:live-reload'
const EVENT_NAME = 'typo3:live-reload'
const DEFAULT_ENDPOINT = '/__typo3-live-reload'
const DEFAULT_DEBOUNCE_MS = 200
const MAXIMUM_BODY_BYTES = 256 * 1024
const DEFAULT_WATCH_EXTENSIONS = ['.html', '.php']
const WATCH_EVENTS = ['change', 'add', 'unlink'] as const

const clientFilePath = join(dirname(fileURLToPath(import.meta.url)), '..', 'dist', 'vite-client.js')

export interface LiveReloadWatchOptions {
    paths: string[]
    extensions?: string[]
    projectRoot?: string
}

export interface LiveReloadOptions {
    endpoint?: string
    debounceMs?: number
    watch?: LiveReloadWatchOptions
}

function resolveExisting(path: string): string {
    try {
        return realpathSync(path)
    } catch {
        try {
            return join(realpathSync(dirname(path)), basename(path))
        } catch {
            return path
        }
    }
}

function createWatchMatcher(watch: LiveReloadWatchOptions) {
    const projectRoot = resolveExisting(watch.projectRoot ?? process.cwd())
    const extensions = watch.extensions ?? DEFAULT_WATCH_EXTENSIONS
    const absolutePaths = watch.paths.map((path) => (isAbsolute(path) ? path : join(projectRoot, path)))
    const realPaths = absolutePaths.map(resolveExisting)

    const fileTag = (file: string): string | null => {
        if (!extensions.some((extension) => file.endsWith(extension))) return null
        const resolved = resolveExisting(file)
        const contained = realPaths.some((path) => resolved === path || resolved.startsWith(path + sep))
        if (!contained || !resolved.startsWith(projectRoot + sep)) return null
        return 'file:' + resolved.slice(projectRoot.length + 1).split(sep).join('/')
    }

    return { absolutePaths, fileTag }
}

export function liveReload(options: LiveReloadOptions = {}): Plugin {
    const endpoint = options.endpoint ?? DEFAULT_ENDPOINT
    const debounceMs = options.debounceMs ?? DEFAULT_DEBOUNCE_MS
    const watchMatcher = options.watch ? createWatchMatcher(options.watch) : null
    const pendingTags = new Set<string>()
    let timer: ReturnType<typeof setTimeout> | null = null

    const flush = (server: ViteDevServer) => {
        const tags = [...pendingTags]
        pendingTags.clear()
        timer = null
        if (tags.length === 0) return
        server.ws.send({ type: 'custom', event: EVENT_NAME, data: { tags } })
        server.config.logger.info(`[live-reload] broadcast: ${tags.join(', ')}`)
    }

    return {
        name: 'live-reload',
        apply: 'serve',
        resolveId(id) {
            return id === VIRTUAL_MODULE_ID ? VIRTUAL_MODULE_ID : undefined
        },
        load(id) {
            return id === VIRTUAL_MODULE_ID ? readFileSync(clientFilePath, 'utf-8') : undefined
        },
        handleHotUpdate(context) {
            // Watched server-side files are handled by our own broadcast; suppress
            // vite's default full reload for them.
            return watchMatcher?.fileTag(context.file) != null ? [] : undefined
        },
        configureServer(server) {
            if (watchMatcher) {
                for (const path of watchMatcher.absolutePaths) server.watcher.add(path)
                const broadcast = (file: string) => {
                    const tag = watchMatcher.fileTag(file)
                    if (tag === null) return
                    pendingTags.add(tag)
                    if (timer) clearTimeout(timer)
                    timer = setTimeout(() => flush(server), debounceMs)
                }
                for (const event of WATCH_EVENTS) server.watcher.on(event, broadcast)
            }
            server.middlewares.use((request, response, next) => {
                const pathname = request.url ? new URL(request.url, 'http://localhost').pathname : ''
                if (pathname !== endpoint) {
                    next()
                    return
                }
                if (request.method !== 'POST') {
                    response.statusCode = 405
                    response.end()
                    return
                }
                void (async () => {
                    try {
                        const chunks: Buffer[] = []
                        let bodySize = 0
                        for await (const chunk of request) {
                            const buffer = Buffer.from(chunk)
                            bodySize += buffer.length
                            if (bodySize > MAXIMUM_BODY_BYTES) {
                                response.statusCode = 413
                                response.end()
                                return
                            }
                            chunks.push(buffer)
                        }
                        const payload = JSON.parse(Buffer.concat(chunks).toString('utf-8'))
                        if (
                            !Array.isArray(payload.tags) ||
                            payload.tags.some((tag: unknown) => typeof tag !== 'string')
                        ) {
                            response.statusCode = 400
                            response.end()
                            return
                        }
                        for (const tag of payload.tags) pendingTags.add(tag)
                        if (timer) clearTimeout(timer)
                        timer = setTimeout(() => flush(server), debounceMs)
                        response.statusCode = 204
                        response.end()
                    } catch {
                        response.statusCode = 400
                        response.end()
                    }
                })()
            })
        },
    }
}

export default liveReload
