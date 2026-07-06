import { createClientCore, restoreScrollPosition } from './core'
import type { ClientConfiguration } from './core'

interface PollConfiguration extends ClientConfiguration {
    endpoint?: string
    interval?: number
    sequence?: number
}

restoreScrollPosition()
const configuration = (window as Window & { __liveReload?: PollConfiguration }).__liveReload
if (configuration && typeof configuration.endpoint === 'string' && typeof configuration.interval === 'number') {
    const endpoint = configuration.endpoint
    const interval = configuration.interval
    const core = createClientCore(configuration)
    let lastSequence = typeof configuration.sequence === 'number' ? configuration.sequence : 0
    let connected = true
    let timer: ReturnType<typeof setTimeout> | undefined

    const announceConnectionChange = (state: boolean) => {
        if (state === connected) return
        connected = state
        core.announceConnection(state)
    }

    const schedulePoll = () => {
        timer = setTimeout(() => {
            void poll()
        }, interval)
    }

    const poll = async () => {
        if (document.visibilityState === 'hidden') return
        try {
            const response = await fetch(endpoint + '?since=' + lastSequence, { credentials: 'same-origin' })
            if (!response.ok) throw new Error(String(response.status))
            const payload = (await response.json()) as {
                sequence: number
                broadcasts?: { sequence: number; tags: string[] }[]
                stale?: boolean
            }
            lastSequence = payload.sequence
            announceConnectionChange(true)
            if (payload.stale) core.forceReload()
            else if (Array.isArray(payload.broadcasts)) {
                for (const broadcast of payload.broadcasts) core.handleBroadcast(broadcast)
            }
        } catch {
            announceConnectionChange(false)
        }
        schedulePoll()
    }

    document.addEventListener('visibilitychange', () => {
        if (document.visibilityState !== 'visible') return
        if (timer !== undefined) clearTimeout(timer)
        void poll()
    })

    core.announceConnection(true)
    void poll()
}
