import type { Plugin, ViteDevServer } from 'vite'

export const VIRTUAL_MODULE_ID = 'virtual:content-live-reload'
const EVENT_NAME = 'typo3:content-changed'
const DEFAULT_ENDPOINT = '/__typo3-content-changed'
const DEFAULT_DEBOUNCE_MS = 200

const clientCode = `const configuration = window.__contentLiveReload
const scrollStorageKey = 'content-live-reload:scroll'
try {
    const storedScroll = sessionStorage.getItem(scrollStorageKey)
    if (storedScroll) {
        sessionStorage.removeItem(scrollStorageKey)
        const position = JSON.parse(storedScroll)
        if (position.href === window.location.href) {
            const restore = () => {
                if (history.scrollRestoration === 'manual') window.scrollTo(position.x, position.y)
            }
            if (document.readyState === 'complete') restore()
            else window.addEventListener('load', restore, { once: true })
        }
    }
} catch {}
if (configuration && import.meta.hot) {
    const announceConnection = (connected) => {
        configuration.connection = { connected, mode: configuration.mode }
        document.dispatchEvent(
            new CustomEvent('${EVENT_NAME}:connection', {
                detail: { connected, mode: configuration.mode },
            }),
        )
    }
    announceConnection(true)
    import.meta.hot.on('vite:ws:disconnect', () => announceConnection(false))
    import.meta.hot.on('vite:ws:connect', () => announceConnection(true))
    import.meta.hot.on('${EVENT_NAME}', (payload) => {
        const broadcastTags = Array.isArray(payload && payload.tags) ? payload.tags : []
        const ownTags = Array.isArray(configuration.tags) ? configuration.tags : []
        const affected =
            configuration.mode === 'always' ||
            ownTags.length === 0 ||
            broadcastTags.some((tag) => ownTags.includes(tag))
        document.dispatchEvent(
            new CustomEvent('${EVENT_NAME}:broadcast', {
                detail: { tags: broadcastTags, matched: affected, mode: configuration.mode },
            }),
        )
        if (!affected || configuration.mode === 'paused') return
        const notice = new CustomEvent('${EVENT_NAME}', { cancelable: true, detail: { tags: broadcastTags } })
        if (!document.dispatchEvent(notice)) return
        try {
            sessionStorage.setItem(
                scrollStorageKey,
                JSON.stringify({ x: window.scrollX, y: window.scrollY, href: window.location.href }),
            )
        } catch {}
        window.location.reload()
    })
}
`

export interface ContentLiveReloadOptions {
    endpoint?: string
    debounceMs?: number
}

export function contentLiveReload(options: ContentLiveReloadOptions = {}): Plugin {
    const endpoint = options.endpoint ?? DEFAULT_ENDPOINT
    const debounceMs = options.debounceMs ?? DEFAULT_DEBOUNCE_MS
    const pendingTags = new Set<string>()
    let timer: ReturnType<typeof setTimeout> | null = null

    const flush = (server: ViteDevServer) => {
        const tags = [...pendingTags]
        pendingTags.clear()
        timer = null
        if (tags.length === 0) return
        server.ws.send({ type: 'custom', event: EVENT_NAME, data: { tags } })
        server.config.logger.info(`[content-live-reload] broadcast: ${tags.join(', ')}`)
    }

    return {
        name: 'content-live-reload',
        apply: 'serve',
        resolveId(id) {
            return id === VIRTUAL_MODULE_ID ? VIRTUAL_MODULE_ID : undefined
        },
        load(id) {
            return id === VIRTUAL_MODULE_ID ? clientCode : undefined
        },
        configureServer(server) {
            server.middlewares.use((request, response, next) => {
                if (!request.url || !request.url.startsWith(endpoint)) {
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
                        for await (const chunk of request) {
                            chunks.push(Buffer.from(chunk))
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

export default contentLiveReload
