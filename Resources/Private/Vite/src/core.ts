const scrollStorageKey = 'live-reload:scroll'

export interface ClientConfiguration {
    tags?: unknown
    mode?: string
    connection?: { connected: boolean; mode?: string }
}

export interface ClientCore {
    announceConnection(connected: boolean): void
    handleBroadcast(payload: unknown): void
    forceReload(): void
}

export function restoreScrollPosition(): void {
    try {
        const storedScroll = sessionStorage.getItem(scrollStorageKey)
        if (!storedScroll) return
        sessionStorage.removeItem(scrollStorageKey)
        const position = JSON.parse(storedScroll) as { x: number; y: number; href: string }
        if (position.href !== window.location.href) return
        const restore = () => {
            if (history.scrollRestoration === 'manual') window.scrollTo(position.x, position.y)
        }
        if (document.readyState === 'complete') restore()
        else window.addEventListener('load', restore, { once: true })
    } catch {}
}

export function createClientCore(configuration: ClientConfiguration): ClientCore {
    let missedWhilePaused = false

    const announceConnection = (connected: boolean) => {
        configuration.connection = { connected, mode: configuration.mode }
        document.dispatchEvent(
            new CustomEvent('typo3:live-reload:connection', {
                detail: { connected, mode: configuration.mode },
            }),
        )
    }

    const storeScrollPosition = () => {
        try {
            sessionStorage.setItem(
                scrollStorageKey,
                JSON.stringify({ x: window.scrollX, y: window.scrollY, href: window.location.href }),
            )
        } catch {}
    }

    const handleBroadcast = (payload: unknown) => {
        const received = payload as { tags?: unknown } | null | undefined
        const broadcastTags = Array.isArray(received?.tags) ? (received.tags as string[]) : []
        const ownTags = Array.isArray(configuration.tags) ? (configuration.tags as string[]) : []
        const affected =
            configuration.mode === 'always' ||
            ownTags.length === 0 ||
            broadcastTags.some((tag) => ownTags.includes(tag))
        document.dispatchEvent(
            new CustomEvent('typo3:live-reload:broadcast', {
                detail: { tags: broadcastTags, matched: affected, mode: configuration.mode },
            }),
        )
        if (affected && configuration.mode === 'paused') missedWhilePaused = true
        if (!affected || configuration.mode === 'paused') return
        const notice = new CustomEvent('typo3:live-reload', { cancelable: true, detail: { tags: broadcastTags } })
        if (!document.dispatchEvent(notice)) return
        storeScrollPosition()
        window.location.reload()
    }

    const forceReload = () => {
        if (configuration.mode === 'paused') {
            missedWhilePaused = true
            return
        }
        storeScrollPosition()
        window.location.reload()
    }

    if (typeof BroadcastChannel !== 'undefined') {
        new BroadcastChannel('live-reload').addEventListener('message', (event) => {
            const data = event.data as { mode?: unknown } | null | undefined
            const mode = data && data.mode
            if (mode !== 'tagged' && mode !== 'always' && mode !== 'paused') {
                window.location.reload()
                return
            }
            if (mode !== 'paused' && missedWhilePaused) {
                window.location.reload()
                return
            }
            configuration.mode = mode
            announceConnection(configuration.connection ? configuration.connection.connected : true)
        })
    }

    return { announceConnection, handleBroadcast, forceReload }
}
