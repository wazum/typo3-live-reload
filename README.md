<h1 align="center">Content Live Reload</h1>
<p align="center"><em>Save a record in the backend. The right browser tabs reload. Nothing else moves.</em></p>
<br>

[![Tests](https://github.com/wazum/typo3-content-live-reload/workflows/Tests/badge.svg)](https://github.com/wazum/typo3-content-live-reload/actions)
[![PHP](https://img.shields.io/badge/PHP-8.2%20|%208.3%20|%208.4-blue.svg)](https://www.php.net/)
[![TYPO3](https://img.shields.io/badge/TYPO3-13.4%20|%2014.3%2B-orange.svg)](https://typo3.org/)
[![Total Downloads](https://img.shields.io/packagist/dt/wazum/typo3-content-live-reload.svg)](https://packagist.org/packages/wazum/typo3-content-live-reload)
[![License](https://img.shields.io/badge/License-GPL%20v2-blue.svg)](LICENSE)

When an editor saves a content element, a page, or a news record, every open frontend tab that shows this record reloads by itself. Tabs that show other pages keep their scroll position, form state, and open dialogs. TYPO3's own cache tags decide which tabs are affected: each page knows the tags it rendered, each save knows the tags it flushed, and each tab compares the two.

How the change reaches the browser is decided by the environment, not by you: when a Vite dev server is running it is pushed over the WebSocket Vite already holds open; when none runs — a plain local install, or a shared Staging environment — each open tab polls a small endpoint instead. No file watchers, no disabled caches.

After `composer require` it works in development right away. A Vite dev server is only a nice option — when you run one, the push transport needs a single line in `vite.config.ts` (no npm package, and no TypoScript with [vite-asset-collector](https://packagist.org/packages/praetorius/vite-asset-collector)). For shared environments like Staging, see [Reload for editors](#reload-for-editors-without-a-dev-server).

![A record is saved in the TYPO3 backend, only the browser tab showing that record reloads, a second tab stays untouched](Documentation/demo.gif)

## Installation

```bash
composer require --dev wazum/typo3-content-live-reload
```

That is the whole install. In the Development context it is active at once: open some frontend pages, edit content in the backend, and the right tabs reload. Without a Vite dev server the tabs poll a small endpoint on their own.

If you do run a Vite dev server, add the bundled plugin to `vite.config.ts` and the reload is pushed instead of polled (no npm package needed — the compiled plugin is part of the Composer package):

```ts
import { defineConfig } from 'vite'
import { contentLiveReload } from './vendor/wazum/typo3-content-live-reload/Resources/Private/Vite/dist/index.js'

export default defineConfig({
    plugins: [
        // ...your other plugins
        contentLiveReload(),
    ],
})
```

The import path is relative to `vite.config.ts` — adjust it if your config file is not next to `vendor/`.

## What You Get

**Targeted reloads** – Only tabs that show the changed content reload. TYPO3's cache tags decide this — the same mechanism that clears the page cache knows exactly which pages changed.

**Nothing for visitors** – The extension is only active in the configured application contexts (default: `Development`), and a bare `Production` context can never activate. Outside Development a valid backend session is required, so anonymous visitors get nothing and never see the endpoint. Nothing of this reaches production.

**Safe by design** – Saving in the backend is never slowed down: the change is broadcast after the editor's response is already sent, and a failed broadcast is silent — a save never breaks. A page without tag data reloads on every change instead of missing one.

**Scroll position stays** – Browsers restore it on reload by default; when a framework (for example Turbo) sets `history.scrollRestoration = 'manual'`, the client restores it itself.

**You can take over the reload** – A cancelable `typo3:content-changed` DOM event fires before each reload. Cancel it and update the DOM with Turbo instead of reloading.

**Extra tags per event** – A PSR-14 event lets you broadcast tags that other extensions flush on their own, for example `tx_news_uid_*`.

**Works with CSP** – Injected scripts get TYPO3's CSP nonce automatically, including the `csp-nonce` meta element that Vite's own client expects.

**Admin Panel module** – Status, the page's cache tags, a live broadcast feed, and a pause switch for your session. See [Admin Panel](#admin-panel).

## How It Works

```
┌──────────────────────────────┐          ┌──────────────────────────────┐
│          TYPO3 (PHP)         │          │       Vite dev server        │
│                              │          │                              │
│  DataHandler save/delete     │   POST   │  contentLiveReload() plugin  │
│   └─ flushed cache tags ────────────────▶   debounce → broadcast       │
│                              │          │        over HMR ws           │
│  middleware injects the      │          │           │                  │
│  page's own cache tags       │  HMR ws  │           ▼                  │
│  + the client module    ◀────────────── virtual:content-live-reload    │
└──────────────────────────────┘          └──────────────────────────────┘
                                                       │
                                                       ▼
                                     each tab: broadcast ∩ own tags ≠ ∅ ?
                                     → cancelable event → reload
```

1. **In:** a middleware reads the cache tags of the current page (from TYPO3's frontend cache data collector, plus a `pageId_<uid>` fallback) and writes them into the page as `window.__contentLiveReload`, together with a `<script type="module">` that the Vite dev server serves.
2. **Out:** a `clearCachePostProc` hook collects the tags TYPO3 flushes for a saved record and posts them to the dev server — after the editor's response is already sent.
3. The dev server broadcasts once per save batch; every tab compares the tags and reloads only when they overlap.

## Content Changes vs. File Changes

This extension only handles **content** changes (records in the database). **File** changes — including watching your Fluid templates — are the job of your own Vite setup, and both work side by side over the same dev server:

| You change | What happens | Handled by |
|---|---|---|
| A record in the backend | Affected tabs reload | this extension |
| CSS / TypeScript | Hot update, often without reload | Vite HMR (via vite-asset-collector) |
| A Fluid template | Nothing, by default! | your own Vite setup — see the [bonus below](#bonus-template-reloads-in-a-few-lines) |

Note that TYPO3 caches compiled Fluid templates. In Development context the cache normally notices changed files by itself; if a change does not show up (this happens most often with partials), clear the TYPO3 caches.

### Bonus: Template Reloads in a Few Lines

You do not need an extra package for template reloads — a few lines in `vite.config.ts` are enough:

```ts
import { defineConfig, Plugin } from 'vite'

function fluidReload(directories: string[]): Plugin {
    return {
        name: 'fluid-reload',
        apply: 'serve',
        configureServer(server) {
            server.watcher.add(directories)
            server.watcher.on('change', (file) => {
                if (!file.endsWith('.html')) return
                server.ws.send({ type: 'full-reload', path: '*' })
            })
        },
    }
}

export default defineConfig({
    plugins: [
        fluidReload(['packages']),
        // contentLiveReload(), ...
    ],
})
```

Three details make this work reliably:

- `server.watcher.add(directories)` is needed because Vite only watches directories that contain modules — your template folders are not among them. Pass plain directory paths, not globs (Vite's watcher ignores globs since Vite 6).
- `path: '*'` is needed for server-rendered pages: with a file path instead, the browser only reloads when the URL matches that file, which is never true for TYPO3 URLs.
- The snippet reuses Vite's own watcher, so your `server.watch` options apply — for example `usePolling: true` in Docker setups where file events do not cross the mount.

## Requirements

- TYPO3 `^13.4 || ^14.3`, PHP `^8.2`, Vite `>=5.1` (only for the dev-server transport)

> [!IMPORTANT]
> Enable the **`frontend.cache.autoTagging`** feature toggle. It is on by default only for **new** TYPO3 installations — upgraded sites must set it themselves:
>
> ```php
> $GLOBALS['TYPO3_CONF_VARS']['SYS']['features']['frontend.cache.autoTagging'] = true;
> ```
>
> Without it, TYPO3 does not tag rendered content with `<table>_<uid>` tags, and reloads only happen for edits on the exact page you are looking at.

## Configuration

Extension Configuration (`content_live_reload`) or `$GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS']['content_live_reload']`:

| Setting | Default | Purpose |
|---|---|---|
| `activeContexts` | `Development` | Application contexts (comma list) where the extension is active; an entry matches itself and its subcontexts (`Development` also covers `Development/Docker`); a bare `Production` entry is ignored — name the exact subcontext instead |
| `reloadMode` | `tagged` | `tagged` = only affected tabs reload; `always` = every connected tab |
| `viteServerInternalUrl` | `http://localhost:5173` | Dev server URL reachable from PHP (broadcast target) |
| `viteServerPublicUrl` | *(empty)* | Dev server URL reachable from the browser; empty = resolve automatically |
| `pollInterval` | `3000` | Milliseconds between polls when the [editor reload](#reload-for-editors-without-a-dev-server) transport is active; minimum `1000` |
| `retention` | `300` | Seconds a broadcast stays answerable for polling tabs; minimum `60` |

The browser-facing URL is resolved in this order: the explicit setting → vite-asset-collector's `auto` chain (which understands, for example, `ddev-vite-sidecar`'s `VITE_SERVER_URI`) → none. With none, the extension stays inactive for that request.

`viteServerInternalUrl` is where **PHP** posts the flushed tags. In Docker/DDEV setups `http://localhost:5173` is only correct when Vite runs in the same container as PHP-FPM. Broadcast failures are silent on purpose (a save must never break), so when reloads do not happen, first check the URL from the PHP side:

```bash
ddev exec 'curl -s -o /dev/null -w "%{http_code}" -X POST http://localhost:5173/__typo3-content-changed -H "Content-Type: application/json" -d "{\"tags\":[]}"'
```

`204` means PHP can reach the dev server. The Admin Panel's Status tab (see below) shows both URLs at one glance.

The Vite plugin accepts a `debounceMs` option (default `200`) — how long broadcasts are collected before they go to the browser. An `endpoint` option also exists, but the PHP side always posts to `/__typo3-content-changed`; changing the endpoint only makes sense when a proxy rewrites that path.

## Reload for Editors (Without a Dev Server)

The same reload also works where no Vite dev server runs — typically a Staging environment. An editor saves a record in the backend, and every preview tab of a logged-in backend user that shows this record reloads. Only the transport changes: instead of the dev server's WebSocket, each tab asks a small endpoint every few seconds whether something changed. Tag matching, reload modes, the `typo3:content-changed` events, and the Admin Panel module all work exactly as described above — the Status tab shows which transport is active.

For this, install the package as a regular dependency instead of `--dev`, so it ships with your release:

```bash
composer require wazum/typo3-content-live-reload
```

Then name the **exact** application context of the environment in `activeContexts`:

```
activeContexts = Development,Production/Staging
```

An entry matches itself and its subcontexts, and a bare `Production` entry is silently ignored — so a staging configuration that ends up on a real production system (context `Production`) activates nothing. The Development context keeps its Vite transport; every other allowed context polls automatically. There is no transport setting.

Outside the Development context, a valid backend user session is required — this is not configurable:

- Without a backend session, nothing is injected: no configuration, no tag data, no script. Anonymous visitors get the exact page they would get without the extension.
- The poll endpoint (`/__content-live-reload/poll`) answers a bare 404 without a backend session, and in contexts that are not allowed at all it is not even claimed — the path behaves like any other unknown URL on your site.

`pollInterval` controls how often each tab asks for changes (so a reload arrives within that many milliseconds after a save), and `retention` controls how long a broadcast stays answerable — a tab that was hidden longer than that simply reloads once to catch up. The defaults are fine for editing workflows.

## Turbo Instead of Reload

The injected client fires a **cancelable** `CustomEvent` on `document` before each reload:

```js
document.addEventListener('typo3:content-changed', (event) => {
    event.preventDefault()
    Turbo.visit(window.location.href, { action: 'replace' })
})
```

`event.detail.tags` contains the broadcast tags. Without a listener (or without `preventDefault()`), the tab does a full reload.

## Broadcasting Tags from Other Extensions

Some extensions flush extra cache tags directly through the `CacheManager`. Those tags are invisible to the DataHandler's tag list. Add them back with a `ModifyBroadcastTagsEvent` listener — for example for [georgringer/news](https://extensions.typo3.org/extension/news):

```php
use TYPO3\CMS\Core\Attribute\AsEventListener;
use Wazum\ContentLiveReload\Event\ModifyBroadcastTagsEvent;

#[AsEventListener(identifier: 'news/broadcast-tags')]
final class NewsBroadcastTagsListener
{
    public function __invoke(ModifyBroadcastTagsEvent $event): void
    {
        if ($event->getTable() !== 'tx_news_domain_model_news') {
            return;
        }

        $event->addTags('tx_news_uid_' . $event->getUid(), 'tx_news_pid_' . $event->getUidPage());
    }
}
```

The event has `getTable()`, `getUid()`, `getUidPage()`, `getTags()`, and `addTags(string ...$tags)`.

Matching works as an intersection: a broadcast tag only reloads tabs whose **rendered page** also carries that tag. Tags like `tx_news_uid_*` exist on pages that display the record (the news extension adds them while rendering) — a tag that only exists on the broadcast side will never match anything.

## Admin Panel

With [EXT:adminpanel](https://packagist.org/packages/typo3/cms-adminpanel) installed, a **Live Reload** module appears in the frontend Admin Panel.

The panel bar itself shows the essentials at one glance, without opening the module: a connection dot (green and gently pulsing while the dev server is connected, a red ring when the connection was lost, with a short flash for every received broadcast), the reload mode — but only when it differs from the normal `tagged` — and the time of this tab's last update. The everyday healthy state is just the green dot and a time like `21:58`; anything unusual (`paused`, `always`, a lost connection) announces itself by appearing. Animations respect `prefers-reduced-motion`.

A **gray ring** means the page never connected. This happens when the page was loaded while the dev server was down: without a dev server there is nothing to inject, so such a tab cannot hear any broadcast — including the one that would reload it. Reload the tab once after the dev server is back; from then on it heals itself.

The module itself has three tabs:

**Status** – Is the extension active in this context? Which reload mode is in effect? Which dev-server URL was resolved, and by which step? Is `frontend.cache.autoTagging` on? Everything you would otherwise check with curl.

**Cache tags** – The full tag list collected for the current page. Useful for cache debugging in general, not only for live reload.

**Broadcasts** – A live feed of the broadcasts this tab received, newest first: time, verdict (`matched → reload`, `no overlap`, or the paused variants), and the tags. Entries survive the reloads they cause (stored per tab in `sessionStorage`, maximum 20).

The panel's settings form adds a mode override for your session:

<img src="Documentation/admin-panel-settings.png" alt="Reload mode override in the Admin Panel settings" width="435">

*paused* keeps the tab connected and the feed running, but stops all reloads — useful during demos or while you inspect a temporary DOM state. The override only affects your backend user's session; the extension configuration stays as it is.

## Content Security Policy

Nonces are handled for you, end to end:

- Both injected script elements and the `csp-nonce` meta element (which Vite's own client reads) get TYPO3's per-request nonce.
- The extension adds `'nonce-proxy'` to `script-src` and `script-src-elem` for its active contexts, so the nonce also appears in the emitted CSP header — including on sites whose own policy defines `script-src-elem` without a nonce.
- TYPO3 normally drops the nonce from the header on cacheable pages when nothing consumed it during rendering. The extension declares its nonce usage on the policy bag, so the header keeps the nonce whenever the scripts are injected.

One thing remains for you when your development CSP is strict and does not use `'strict-dynamic'`: allow the dev server in `connect-src` for the HMR WebSocket — use the `ws://` or `wss://` form of the dev-server origin, matching your protocol.

## Limitations

- Only changes that go through TYPO3's `DataHandler` are broadcast — direct database writes are not seen.
- Tags that extensions flush outside the DataHandler's list need the event listener above.
- Content rendered from an external index (for example Solr) updates on reindex, not on save.

## License

GPL-2.0-or-later, see [LICENSE](LICENSE).
