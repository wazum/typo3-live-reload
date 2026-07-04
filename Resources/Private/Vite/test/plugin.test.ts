import { describe, expect, it, vi } from 'vitest'
import { contentLiveReload, VIRTUAL_MODULE_ID } from '../src/index'

type Handler = (req: any, res: any, next: () => void) => void

function fakeServer() {
    let handler: Handler = () => undefined
    const send = vi.fn()
    const server = {
        middlewares: {
            use(fn: Handler) {
                handler = fn
            },
        },
        ws: { send },
        config: { logger: { info: vi.fn() } },
    }
    return { server, send, request: (req: any, res: any) => handler(req, res, () => res.end('next')) }
}

function post(url: string, body: string) {
    const chunks = [Buffer.from(body)]
    const req = {
        url,
        method: 'POST',
        async *[Symbol.asyncIterator]() {
            yield* chunks
        },
    }
    const res = {
        statusCode: 0,
        ended: false,
        end(payload?: string) {
            this.ended = true
            this.payload = payload
        },
    } as any
    return { req, res }
}

describe('contentLiveReload', () => {
    it('serves the virtual client module referencing import.meta.hot', () => {
        const plugin = contentLiveReload()
        expect(plugin.resolveId!.call({} as any, VIRTUAL_MODULE_ID, undefined, {} as any)).toBe(VIRTUAL_MODULE_ID)
        const code = plugin.load!.call({} as any, VIRTUAL_MODULE_ID) as string
        expect(code).toContain('import.meta.hot')
        expect(code).toContain('typo3:content-changed')
        expect(code).toContain('window.__contentLiveReload')
        expect(code).toContain('content-live-reload:scroll')
        expect(code).toContain("history.scrollRestoration === 'manual'")
        expect(code).toContain('sessionStorage.setItem')
        expect(code).toContain('typo3:content-changed:broadcast')
        expect(code).toContain("configuration.mode === 'paused'")
    })

    it('broadcasts debounced deduplicated tags', async () => {
        vi.useFakeTimers()
        const plugin = contentLiveReload({ debounceMs: 200 })
        const { server, send, request } = fakeServer()
        ;(plugin.configureServer as any)(server)

        const first = post('/__typo3-content-changed', JSON.stringify({ tags: ['tt_content_5', 'pageId_42'] }))
        request(first.req, first.res)
        const second = post('/__typo3-content-changed', JSON.stringify({ tags: ['pageId_42', 'tt_content'] }))
        request(second.req, second.res)
        await vi.runAllTimersAsync()

        expect(send).toHaveBeenCalledTimes(1)
        expect(send).toHaveBeenCalledWith({
            type: 'custom',
            event: 'typo3:content-changed',
            data: { tags: ['tt_content_5', 'pageId_42', 'tt_content'] },
        })
        expect(first.res.statusCode).toBe(204)
        vi.useRealTimers()
    })

    it('rejects invalid payloads and wrong methods', async () => {
        const plugin = contentLiveReload()
        const { server, request } = fakeServer()
        ;(plugin.configureServer as any)(server)

        const invalid = post('/__typo3-content-changed', '{"tags": "nope"}')
        request(invalid.req, invalid.res)
        await vi.waitFor(() => {
            expect(invalid.res.ended).toBe(true)
        })
        expect(invalid.res.statusCode).toBe(400)

        const wrongMethod = post('/__typo3-content-changed', '{}')
        wrongMethod.req.method = 'GET'
        request(wrongMethod.req, wrongMethod.res)
        expect(wrongMethod.res.statusCode).toBe(405)

        const otherPath = post('/anything-else', '{}')
        request(otherPath.req, otherPath.res)
        expect(otherPath.res.payload).toBe('next')
    })
})
