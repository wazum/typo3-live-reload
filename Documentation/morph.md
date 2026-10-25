# Morph instead of reload

Before each reload, the client fires a cancelable `typo3:live-reload` event on `document`.
Cancel it and update the page yourself. With Turbo:

```js
document.addEventListener('typo3:live-reload', (event) => {
    event.preventDefault()
    Turbo.visit(window.location.href, { action: 'replace' })
})
```

`event.detail.tags` holds the broadcast tags, including `file:` tags, so you can react
differently to template changes. Without a listener, or without `preventDefault()`, the tab
does a full reload.

## With idiomorph

Without Turbo, [idiomorph](https://github.com/bigskysoftware/idiomorph) — the morph engine
behind Turbo 8 page refreshes — updates the page in place in a few lines. It only swaps the
DOM nodes that changed, so the scroll position, focus, open dialogs and JavaScript state
survive a change:

```js
document.addEventListener('typo3:live-reload', (event) => {
    event.preventDefault()
    void (async () => {
        try {
            const { Idiomorph } = await import('idiomorph')
            const response = await fetch(window.location.href, { cache: 'no-store' })
            if (!response.ok) throw new Error(`Unexpected response status ${response.status}`)
            const next = new DOMParser().parseFromString(await response.text(), 'text/html')
            Idiomorph.morph(document.body, next.body, {
                callbacks: {
                    // keep the live Admin Panel (connection status, broadcast feed):
                    // the fetched page only contains its server-rendered placeholder
                    beforeNodeMorphed: (oldNode) => oldNode.id !== 'TSFE_ADMIN_PANEL_FORM',
                },
            })
        } catch (error) {
            console.warn('[live-reload] morph failed, falling back to reload:', error)
            window.location.reload()
        }
    })()
})
```

Three notes from real projects:

- Register the listener only when `window.__liveReload` exists, so production never runs it.
- Add `optimizeDeps: { include: ['idiomorph'] }` to your Vite config. Otherwise Vite finds
  the dependency on first use and answers with its own full reload.
- Leave widgets that keep state in the browser, such as the Admin Panel, out of the morph
  (the callback above does this). The fetched HTML only has their first, server-rendered
  state.
