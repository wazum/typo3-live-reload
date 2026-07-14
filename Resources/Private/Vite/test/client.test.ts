// @vitest-environment happy-dom
import { readFileSync } from 'node:fs'
import { join } from 'node:path'
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { liveReload, VIRTUAL_MODULE_ID } from '../src/index'

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

async function flushMicrotasks() {
    for (let iteration = 0; iteration < 10; iteration++) await Promise.resolve()
}

interface BootedClient {
    deliverBroadcast(tags: string[]): Promise<void>
}

async function bootViteClient(configuration: Record<string, unknown>): Promise<BootedClient> {
    const handlers = new Map<string, ((payload: unknown) => void)[]>()
    const hot = {
        on(event: string, handler: (payload: unknown) => void) {
            handlers.set(event, [...(handlers.get(event) ?? []), handler])
        },
    }
    ;(window as any).__liveReload = configuration
    const plugin = liveReload()
    const code = plugin.load!.call({} as any, VIRTUAL_MODULE_ID) as string
    new Function('__hot__', code.replaceAll('import.meta.hot', '__hot__'))(hot)
    return {
        deliverBroadcast: async (tags) => {
            for (const handler of handlers.get('typo3:live-reload') ?? []) handler({ tags })
        },
        emit(event: string, payload?: unknown) {
            for (const handler of handlers.get(event) ?? []) handler(payload)
        },
    } as BootedClient & { emit(event: string, payload?: unknown): void }
}

const pollInterval = 3000

function createPollServer(initialSequence: number) {
    let sequence = initialSequence
    let queued: { sequence: number; tags: string[] }[] = []
    let failing = false
    let staleNext = false
    const fetchMock = vi.fn(async () => {
        if (failing) throw new TypeError('network error')
        if (staleNext) {
            staleNext = false
            return { ok: true, json: async () => ({ sequence, stale: true }) }
        }
        const broadcasts = queued
        queued = []
        const payload = broadcasts.length > 0 ? { sequence, broadcasts } : { sequence }
        return { ok: true, json: async () => payload }
    })
    return {
        fetchMock,
        broadcast(tags: string[]) {
            sequence += 1
            queued.push({ sequence, tags })
        },
        fail(state: boolean) {
            failing = state
        },
        stale() {
            sequence += 1
            staleNext = true
        },
    }
}

type PollServer = ReturnType<typeof createPollServer>

async function bootPollClientWithServer(
    configuration: Record<string, unknown>,
): Promise<BootedClient & { server: PollServer; advance(milliseconds: number): Promise<void> }> {
    vi.useFakeTimers()
    const initialSequence = (configuration.sequence as number | undefined) ?? 5
    const server = createPollServer(initialSequence)
    vi.stubGlobal('fetch', server.fetchMock)
    ;(window as any).__liveReload = {
        transport: 'poll',
        endpoint: '/__live-reload/poll',
        interval: pollInterval,
        sequence: initialSequence,
        ...configuration,
    }
    const code = readFileSync(join(process.cwd(), '..', '..', 'Public', 'JavaScript', 'poll-client.js'), 'utf-8')
    const addEventListenerSpy = vi.spyOn(document, 'addEventListener')
    new Function(code)()
    for (const call of addEventListenerSpy.mock.calls) {
        if (call[0] === 'visibilitychange') visibilityListeners.push(call[1] as EventListener)
    }
    addEventListenerSpy.mockRestore()
    await flushMicrotasks()
    return {
        server,
        deliverBroadcast: async (tags) => {
            server.broadcast(tags)
            await vi.advanceTimersByTimeAsync(pollInterval)
            await flushMicrotasks()
        },
        advance: async (milliseconds) => {
            await vi.advanceTimersByTimeAsync(milliseconds)
            await flushMicrotasks()
        },
    }
}

function setVisibility(state: 'visible' | 'hidden') {
    Object.defineProperty(document, 'visibilityState', { configurable: true, get: () => state })
    document.dispatchEvent(new Event('visibilitychange'))
}

const visibilityListeners: EventListener[] = []

const transports: { name: string; boot: (configuration: Record<string, unknown>) => Promise<BootedClient> }[] = [
    { name: 'vite', boot: bootViteClient },
    { name: 'poll', boot: bootPollClientWithServer },
]

let reload: ReturnType<typeof vi.fn>

beforeEach(() => {
    sessionStorage.clear()
    FakeBroadcastChannel.instances = []
    vi.stubGlobal('BroadcastChannel', FakeBroadcastChannel)
    reload = vi.fn()
    vi.spyOn(window.location, 'reload').mockImplementation(reload)
})

afterEach(() => {
    for (const listener of visibilityListeners.splice(0)) {
        document.removeEventListener('visibilitychange', listener)
    }
    vi.useRealTimers()
    vi.unstubAllGlobals()
    delete (document as any).visibilityState
})

describe.each(transports)('client reload behavior via $name', ({ boot }) => {
    it('reloads when a broadcast tag overlaps the own tags', async () => {
        const client = await boot({ tags: ['pageId_1', 'tt_content_5'], mode: 'tagged' })
        await client.deliverBroadcast(['tt_content_5'])
        expect(reload).toHaveBeenCalledTimes(1)
    })

    it('stores the scroll position before reloading', async () => {
        const client = await boot({ tags: ['pageId_1'], mode: 'tagged' })
        await client.deliverBroadcast(['pageId_1'])
        const stored = JSON.parse(sessionStorage.getItem('live-reload:scroll') ?? 'null')
        expect(stored).toMatchObject({ href: window.location.href })
    })

    it('does not reload without tag overlap and reports the verdict', async () => {
        const verdicts: { matched: boolean; mode: string }[] = []
        document.addEventListener(
            'typo3:live-reload:broadcast',
            (event) => verdicts.push((event as CustomEvent).detail),
            { once: true },
        )
        const client = await boot({ tags: ['pageId_1'], mode: 'tagged' })
        await client.deliverBroadcast(['tt_content_99'])
        expect(reload).not.toHaveBeenCalled()
        expect(verdicts).toEqual([{ tags: ['tt_content_99'], matched: false, mode: 'tagged' }])
    })

    it('reloads on any broadcast when the page has no own tags', async () => {
        const client = await boot({ tags: [], mode: 'tagged' })
        await client.deliverBroadcast(['tt_content_99'])
        expect(reload).toHaveBeenCalledTimes(1)
    })

    it('reloads without tag overlap in always mode', async () => {
        const client = await boot({ tags: ['pageId_1'], mode: 'always' })
        await client.deliverBroadcast(['tt_content_99'])
        expect(reload).toHaveBeenCalledTimes(1)
    })

    it('never reloads in paused mode', async () => {
        const client = await boot({ tags: ['pageId_1'], mode: 'paused' })
        await client.deliverBroadcast(['pageId_1'])
        expect(reload).not.toHaveBeenCalled()
    })

    it('catches up on missed updates when leaving paused mode', async () => {
        const client = await boot({ tags: ['pageId_1'], mode: 'paused' })
        await client.deliverBroadcast(['pageId_1'])
        expect(reload).not.toHaveBeenCalled()
        receiveChannelMessage({ mode: 'tagged' })
        expect(reload).toHaveBeenCalledTimes(1)
    })

    it('switches mode without reloading when nothing was missed', async () => {
        const client = await boot({ tags: ['pageId_1'], mode: 'paused' })
        await client.deliverBroadcast(['tt_content_99'])
        receiveChannelMessage({ mode: 'tagged' })
        expect(reload).not.toHaveBeenCalled()
        await client.deliverBroadcast(['pageId_1'])
        expect(reload).toHaveBeenCalledTimes(1)
    })

    it('lets a listener cancel the reload', async () => {
        document.addEventListener('typo3:live-reload', (event) => event.preventDefault(), { once: true })
        const client = await boot({ tags: ['pageId_1'], mode: 'tagged' })
        await client.deliverBroadcast(['pageId_1'])
        expect(reload).not.toHaveBeenCalled()
    })

    it('restores the stored scroll position after a reload', async () => {
        history.scrollRestoration = 'manual'
        sessionStorage.setItem(
            'live-reload:scroll',
            JSON.stringify({ x: 0, y: 250, href: window.location.href }),
        )
        const scrollTo = vi.spyOn(window, 'scrollTo').mockImplementation(() => {})
        await boot({ tags: ['pageId_1'], mode: 'tagged' })
        expect(scrollTo).toHaveBeenCalledWith(0, 250)
        expect(sessionStorage.getItem('live-reload:scroll')).toBeNull()
    })
})

describe('vite client transport', () => {
    it('announces connection changes from the vite websocket', async () => {
        const states: { connected: boolean }[] = []
        const record = (event: Event) => states.push((event as CustomEvent).detail)
        document.addEventListener('typo3:live-reload:connection', record)
        const client = (await bootViteClient({ tags: ['pageId_1'], mode: 'tagged' })) as BootedClient & {
            emit(event: string, payload?: unknown): void
        }
        client.emit('vite:ws:disconnect')
        client.emit('vite:ws:connect')
        document.removeEventListener('typo3:live-reload:connection', record)
        expect(states.map((state) => state.connected)).toEqual([true, false, true])
    })
})

describe('poll client transport', () => {
    it('starts polling from the configured sequence with same-origin credentials', async () => {
        const client = await bootPollClientWithServer({ tags: ['pageId_1'], mode: 'tagged', sequence: 7 })
        expect(client.server.fetchMock).toHaveBeenCalledTimes(1)
        expect(client.server.fetchMock).toHaveBeenCalledWith('/__live-reload/poll?since=7', {
            credentials: 'same-origin',
        })
        expect(reload).not.toHaveBeenCalled()
    })

    it('continues from the sequence returned by the server', async () => {
        const client = await bootPollClientWithServer({ tags: ['pageId_1'], mode: 'tagged', sequence: 5 })
        await client.deliverBroadcast(['tt_content_99'])
        await client.advance(pollInterval)
        expect(client.server.fetchMock).toHaveBeenLastCalledWith('/__live-reload/poll?since=6', {
            credentials: 'same-origin',
        })
    })

    it('announces a lost connection after a failed poll and recovery on the next success', async () => {
        const states: boolean[] = []
        const record = (event: Event) => states.push((event as CustomEvent).detail.connected)
        document.addEventListener('typo3:live-reload:connection', record)
        const client = await bootPollClientWithServer({ tags: ['pageId_1'], mode: 'tagged' })
        client.server.fail(true)
        await client.advance(pollInterval)
        client.server.fail(false)
        await client.advance(pollInterval)
        document.removeEventListener('typo3:live-reload:connection', record)
        expect(states).toEqual([true, false, true])
    })

    it('pauses polling while the tab is hidden and catches up when it becomes visible', async () => {
        const client = await bootPollClientWithServer({ tags: ['pageId_1'], mode: 'tagged' })
        const pollsWhileVisible = client.server.fetchMock.mock.calls.length
        setVisibility('hidden')
        await client.advance(pollInterval * 3)
        expect(client.server.fetchMock).toHaveBeenCalledTimes(pollsWhileVisible)
        setVisibility('visible')
        await flushMicrotasks()
        expect(client.server.fetchMock).toHaveBeenCalledTimes(pollsWhileVisible + 1)
    })

    it('reloads on a stale response', async () => {
        const client = await bootPollClientWithServer({ tags: ['pageId_1'], mode: 'tagged' })
        client.server.stale()
        await client.advance(pollInterval)
        expect(reload).toHaveBeenCalledTimes(1)
    })

    it('does not start a second poll while one is still in flight', async () => {
        const client = await bootPollClientWithServer({ tags: ['pageId_1'], mode: 'tagged' })
        let resolvePending: (value: unknown) => void = () => {}
        const pendingFetch = vi.fn(() => new Promise((resolve) => (resolvePending = resolve)))
        vi.stubGlobal('fetch', pendingFetch)
        await client.advance(pollInterval)
        expect(pendingFetch).toHaveBeenCalledTimes(1)
        setVisibility('hidden')
        setVisibility('visible')
        await flushMicrotasks()
        expect(pendingFetch).toHaveBeenCalledTimes(1)
        resolvePending({ ok: true, json: async () => ({ sequence: 6 }) })
        await flushMicrotasks()
    })

    it('ignores a response with an older sequence instead of replaying it', async () => {
        const client = await bootPollClientWithServer({ tags: ['pageId_1'], mode: 'tagged', sequence: 7 })
        const staleAnswer = vi.fn(async () => ({
            ok: true,
            json: async () => ({ sequence: 3, broadcasts: [{ sequence: 3, tags: ['pageId_1'] }] }),
        }))
        vi.stubGlobal('fetch', staleAnswer)
        await client.advance(pollInterval)
        expect(reload).not.toHaveBeenCalled()
        vi.stubGlobal('fetch', client.server.fetchMock)
        await client.advance(pollInterval)
        expect(client.server.fetchMock).toHaveBeenLastCalledWith('/__live-reload/poll?since=7', {
            credentials: 'same-origin',
        })
    })

    it('treats a payload without a numeric sequence as a failed poll', async () => {
        const states: boolean[] = []
        const record = (event: Event) => states.push((event as CustomEvent).detail.connected)
        document.addEventListener('typo3:live-reload:connection', record)
        const client = await bootPollClientWithServer({ tags: ['pageId_1'], mode: 'tagged', sequence: 5 })
        const malformedAnswer = vi.fn(async () => ({ ok: true, json: async () => ({}) }))
        vi.stubGlobal('fetch', malformedAnswer)
        await client.advance(pollInterval)
        vi.stubGlobal('fetch', client.server.fetchMock)
        await client.advance(pollInterval)
        document.removeEventListener('typo3:live-reload:connection', record)
        expect(reload).not.toHaveBeenCalled()
        expect(states).toEqual([true, false, true])
        expect(client.server.fetchMock).toHaveBeenLastCalledWith('/__live-reload/poll?since=5', {
            credentials: 'same-origin',
        })
    })

    it('records a stale response while paused and reloads when leaving paused mode', async () => {
        const client = await bootPollClientWithServer({ tags: ['pageId_1'], mode: 'paused' })
        client.server.stale()
        await client.advance(pollInterval)
        expect(reload).not.toHaveBeenCalled()
        receiveChannelMessage({ mode: 'tagged' })
        expect(reload).toHaveBeenCalledTimes(1)
    })
})
