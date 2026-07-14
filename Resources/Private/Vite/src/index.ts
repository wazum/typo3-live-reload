import { createHash, timingSafeEqual } from 'node:crypto'
import { readFileSync, realpathSync } from 'node:fs'
import { basename, dirname, isAbsolute, join, sep } from 'node:path'
import { fileURLToPath } from 'node:url'
import type { Plugin, ViteDevServer } from 'vite'

export const VIRTUAL_MODULE_ID = 'virtual:live-reload'
const EVENT_NAME = 'typo3:live-reload'
const DEFAULT_ENDPOINT = '/__typo3-live-reload'
const DEFAULT_DEBOUNCE_MS = 200
const MAXIMUM_BODY_BYTES = 256 * 1024
const MAXIMUM_TAG_LENGTH = 500
const MAXIMUM_TAGS_PER_REQUEST = 1000
const MAXIMUM_LOG_LENGTH = 500
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
    secret?: string
    watch?: LiveReloadWatchOptions
}

function matchesSecret(provided: unknown, secret: string): boolean {
    if (typeof provided !== 'string') return false
    // Hashing both sides gives equal lengths, so the comparison time
    // never depends on the secret.
    return timingSafeEqual(
        createHash('sha256').update(provided).digest(),
        createHash('sha256').update(secret).digest(),
    )
}

function sanitizeTags(tags: string[]): string[] {
    return tags
        .map((tag) => tag.replace(/[\u0000-\u001f\u007f]/g, '').trim())
        .filter((tag) => tag.length > 0 && tag.length <= MAXIMUM_TAG_LENGTH)
        .slice(0, MAXIMUM_TAGS_PER_REQUEST)
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
    const secret = options.secret ?? ''
    const watchMatcher = options.watch ? createWatchMatcher(options.watch) : null
    const pendingTags = new Set<string>()
    let timer: ReturnType<typeof setTimeout> | null = null

    const flush = (server: ViteDevServer) => {
        const tags = [...pendingTags]
        pendingTags.clear()
        timer = null
        if (tags.length === 0) return
        server.ws.send({ type: 'custom', event: EVENT_NAME, data: { tags } })
        const logLine = tags.join(', ')
        server.config.logger.info(
            `[live-reload] broadcast: ${logLine.length > MAXIMUM_LOG_LENGTH ? logLine.slice(0, MAXIMUM_LOG_LENGTH) + '…' : logLine}`,
        )
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
                // TYPO3 posts server-side and never sends Sec-Fetch-Site;
                // a browser script on a foreign origin always does.
                if (request.headers?.['sec-fetch-site'] === 'cross-site') {
                    response.statusCode = 403
                    response.end()
                    return
                }
                const contentType = request.headers?.['content-type']
                if (typeof contentType !== 'string' || !contentType.toLowerCase().includes('application/json')) {
                    response.statusCode = 415
                    response.end()
                    return
                }
                if (secret !== '' && !matchesSecret(request.headers?.['x-live-reload-secret'], secret)) {
                    response.statusCode = 401
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
                        for (const tag of sanitizeTags(payload.tags)) pendingTags.add(tag)
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
