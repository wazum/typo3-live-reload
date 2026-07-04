(() => {
    const feedStorageKey = 'content-live-reload:broadcasts'
    const updateStorageKey = 'content-live-reload:last-update'
    const state = {
        connected: null,
        mode: null,
        lastUpdate: sessionStorage.getItem(updateStorageKey),
    }

    const statusElement = () => {
        const pane = document.querySelector('[data-typo3-tab-id="content_live_reload_status"]')
        const moduleGroup = pane?.closest('.typo3-adminPanel-module-group')
        const trigger = moduleGroup?.querySelector('[data-typo3-role="typo3-adminPanel-module-trigger"]')
        return trigger?.querySelector('.typo3-adminPanel-module-trigger-information') ?? null
    }

    const renderStatus = () => {
        const element = statusElement()
        if (!element) return
        const connection = state.connected === null ? '…' : state.connected ? '●' : '○ disconnected'
        const parts = [connection]
        if (state.mode) parts.push(state.mode)
        parts.push(state.lastUpdate ? 'updated ' + state.lastUpdate : 'no updates yet')
        element.textContent = parts.join(' · ')
    }

    const readEntries = () => {
        try {
            const stored = JSON.parse(sessionStorage.getItem(feedStorageKey) ?? '[]')
            return Array.isArray(stored) ? stored : []
        } catch {
            return []
        }
    }

    const writeEntries = (entries) => {
        try {
            sessionStorage.setItem(feedStorageKey, JSON.stringify(entries.slice(0, 20)))
        } catch {}
    }

    const renderFeed = (entries) => {
        const list = document.getElementById('content-live-reload-broadcasts')
        if (!list) return
        list.replaceChildren()
        if (entries.length === 0) {
            const row = document.createElement('tr')
            const cell = document.createElement('td')
            cell.textContent = 'Waiting for broadcasts — save a record in the backend.'
            row.appendChild(cell)
            list.appendChild(row)
            return
        }
        for (const entry of entries) {
            const row = document.createElement('tr')
            const time = document.createElement('td')
            time.textContent = entry.time
            const verdict = document.createElement('td')
            verdict.textContent = entry.verdict
            const tags = document.createElement('td')
            const code = document.createElement('code')
            code.textContent = entry.tags
            tags.appendChild(code)
            row.append(time, verdict, tags)
            list.appendChild(row)
        }
    }

    document.addEventListener('typo3:content-changed:connection', (event) => {
        state.connected = event.detail.connected
        state.mode = event.detail.mode
        renderStatus()
    })

    document.addEventListener('typo3:content-changed:broadcast', (event) => {
        const time = new Date().toLocaleTimeString()
        const verdict =
            event.detail.mode === 'paused'
                ? (event.detail.matched ? 'matched (paused)' : 'no overlap (paused)')
                : (event.detail.matched ? 'matched → reload' : 'no overlap')
        if (event.detail.matched && event.detail.mode !== 'paused') {
            state.lastUpdate = time
            try {
                sessionStorage.setItem(updateStorageKey, time)
            } catch {}
        }
        const entries = [{ time, verdict, tags: event.detail.tags.join(', ') }, ...readEntries()]
        writeEntries(entries)
        renderFeed(entries)
        renderStatus()
    })

    const initialize = () => {
        const connection = window.__contentLiveReload?.connection
        if (connection) {
            state.connected = connection.connected
            state.mode = connection.mode
        } else if (window.__contentLiveReload) {
            state.mode = window.__contentLiveReload.mode
        }
        renderFeed(readEntries())
        renderStatus()
    }
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initialize, { once: true })
    } else {
        initialize()
    }
})()
