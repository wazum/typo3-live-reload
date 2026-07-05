// @vitest-environment happy-dom
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { contentLiveReload, VIRTUAL_MODULE_ID } from '../src/index'

type Listener = (event: { data: unknown }) => void

class FakeBroadcastChannel {
    static instances: FakeBroadcastChannel[] = []
    listeners: Listener[] = []

    constructor(public name: string) {
        FakeBroadcastChannel.instances.push(this)
    }

    addEventListener(_type: string, listener: Listener) {
        this.listeners.push(listener)
    }

    postMessage(_data: unknown) {}
}

function receiveChannelMessage(data: unknown) {
    for (const channel of FakeBroadcastChannel.instances) {
        for (const listener of channel.listeners) listener({ data })
    }
}

function bootClient(configuration: Record<string, unknown>) {
    const handlers = new Map<string, ((payload: unknown) => void)[]>()
    const hot = {
        on(event: string, handler: (payload: unknown) => void) {
            handlers.set(event, [...(handlers.get(event) ?? []), handler])
        },
    }
    ;(window as any).__contentLiveReload = configuration
    const plugin = contentLiveReload()
    const code = plugin.load!.call({} as any, VIRTUAL_MODULE_ID) as string
    new Function('__hot__', code.replaceAll('import.meta.hot', '__hot__'))(hot)
    return {
        emit(event: string, payload?: unknown) {
            for (const handler of handlers.get(event) ?? []) handler(payload)
        },
    }
}

describe('client reload behavior', () => {
    let reload: ReturnType<typeof vi.fn>

    beforeEach(() => {
        sessionStorage.clear()
        FakeBroadcastChannel.instances = []
        vi.stubGlobal('BroadcastChannel', FakeBroadcastChannel)
        reload = vi.fn()
        vi.spyOn(window.location, 'reload').mockImplementation(reload)
    })

    it('reloads when a broadcast tag overlaps the own tags', () => {
        const client = bootClient({ tags: ['pageId_1', 'tt_content_5'], mode: 'tagged' })
        client.emit('typo3:content-changed', { tags: ['tt_content_5'] })
        expect(reload).toHaveBeenCalledTimes(1)
    })

    it('stores the scroll position before reloading', () => {
        const client = bootClient({ tags: ['pageId_1'], mode: 'tagged' })
        client.emit('typo3:content-changed', { tags: ['pageId_1'] })
        const stored = JSON.parse(sessionStorage.getItem('content-live-reload:scroll') ?? 'null')
        expect(stored).toMatchObject({ href: window.location.href })
    })

    it('does not reload without tag overlap and reports the verdict', () => {
        const verdicts: { matched: boolean; mode: string }[] = []
        document.addEventListener(
            'typo3:content-changed:broadcast',
            (event) => verdicts.push((event as CustomEvent).detail),
            { once: true },
        )
        const client = bootClient({ tags: ['pageId_1'], mode: 'tagged' })
        client.emit('typo3:content-changed', { tags: ['tt_content_99'] })
        expect(reload).not.toHaveBeenCalled()
        expect(verdicts).toEqual([{ tags: ['tt_content_99'], matched: false, mode: 'tagged' }])
    })

    it('reloads on any broadcast when the page has no own tags', () => {
        const client = bootClient({ tags: [], mode: 'tagged' })
        client.emit('typo3:content-changed', { tags: ['tt_content_99'] })
        expect(reload).toHaveBeenCalledTimes(1)
    })

    it('reloads without tag overlap in always mode', () => {
        const client = bootClient({ tags: ['pageId_1'], mode: 'always' })
        client.emit('typo3:content-changed', { tags: ['tt_content_99'] })
        expect(reload).toHaveBeenCalledTimes(1)
    })

    it('never reloads in paused mode', () => {
        const client = bootClient({ tags: ['pageId_1'], mode: 'paused' })
        client.emit('typo3:content-changed', { tags: ['pageId_1'] })
        expect(reload).not.toHaveBeenCalled()
    })

    it('catches up on missed updates when leaving paused mode', () => {
        const client = bootClient({ tags: ['pageId_1'], mode: 'paused' })
        client.emit('typo3:content-changed', { tags: ['pageId_1'] })
        expect(reload).not.toHaveBeenCalled()
        receiveChannelMessage({ mode: 'tagged' })
        expect(reload).toHaveBeenCalledTimes(1)
    })

    it('switches mode without reloading when nothing was missed', () => {
        const client = bootClient({ tags: ['pageId_1'], mode: 'paused' })
        client.emit('typo3:content-changed', { tags: ['tt_content_99'] })
        receiveChannelMessage({ mode: 'tagged' })
        expect(reload).not.toHaveBeenCalled()
        client.emit('typo3:content-changed', { tags: ['pageId_1'] })
        expect(reload).toHaveBeenCalledTimes(1)
    })

    it('lets a listener cancel the reload', () => {
        document.addEventListener('typo3:content-changed', (event) => event.preventDefault(), { once: true })
        const client = bootClient({ tags: ['pageId_1'], mode: 'tagged' })
        client.emit('typo3:content-changed', { tags: ['pageId_1'] })
        expect(reload).not.toHaveBeenCalled()
    })

    it('announces connection changes from the vite websocket', () => {
        const states: { connected: boolean }[] = []
        const record = (event: Event) => states.push((event as CustomEvent).detail)
        document.addEventListener('typo3:content-changed:connection', record)
        const client = bootClient({ tags: ['pageId_1'], mode: 'tagged' })
        client.emit('vite:ws:disconnect')
        client.emit('vite:ws:connect')
        document.removeEventListener('typo3:content-changed:connection', record)
        expect(states.map((state) => state.connected)).toEqual([true, false, true])
    })

    it('restores the stored scroll position after a reload', () => {
        history.scrollRestoration = 'manual'
        sessionStorage.setItem(
            'content-live-reload:scroll',
            JSON.stringify({ x: 0, y: 250, href: window.location.href }),
        )
        const scrollTo = vi.spyOn(window, 'scrollTo').mockImplementation(() => {})
        bootClient({ tags: ['pageId_1'], mode: 'tagged' })
        expect(scrollTo).toHaveBeenCalledWith(0, 250)
        expect(sessionStorage.getItem('content-live-reload:scroll')).toBeNull()
    })
})
