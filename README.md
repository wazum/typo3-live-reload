<h1 align="center">Content Live Reload</h1>
<p align="center"><em>Save a record in the backend. The right browser tabs reload. Nothing else moves.</em></p>
<br>

[![Tests](https://github.com/wazum/typo3-content-live-reload/workflows/Tests/badge.svg)](https://github.com/wazum/typo3-content-live-reload/actions)
[![PHP](https://img.shields.io/badge/PHP-8.2%20|%208.3%20|%208.4-blue.svg)](https://www.php.net/)
[![TYPO3](https://img.shields.io/badge/TYPO3-13.4%20|%2014.3%2B-orange.svg)](https://typo3.org/)
[![Total Downloads](https://img.shields.io/packagist/dt/wazum/typo3-content-live-reload.svg)](https://packagist.org/packages/wazum/typo3-content-live-reload)
[![License](https://img.shields.io/badge/License-GPL%20v2-blue.svg)](LICENSE)

Vite already reloads the browser when a *file* changes. This extension adds the missing part: when an editor saves a content element, a page, or a news record, every open frontend tab that shows this record reloads — over your existing Vite dev server. Tabs that show other pages keep their scroll position, form state, and open dialogs.

No polling, no file watchers, no disabled caches. TYPO3's own cache tags decide which tabs are affected: each page knows the tags it rendered, each save knows the tags it flushed, and each tab compares the two.

One `composer require` plus one line in `vite.config.ts` — no TYPO3 configuration needed when you use [vite-asset-collector](https://packagist.org/packages/praetorius/vite-asset-collector).

## Installation

```bash
composer require --dev wazum/typo3-content-live-reload
```

Add the bundled Vite plugin to `vite.config.ts` (no npm package needed — the compiled plugin is part of the Composer package):

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

That's it. Start `vite`, open some frontend pages, edit content in the backend.

## What You Get

**Targeted reloads** – Only tabs that show the changed content reload. TYPO3's cache tags decide this — the same mechanism that clears the page cache knows exactly which pages changed.

**Nothing for visitors** – The PHP side is only active in the configured application contexts (default: `Development`), the Vite plugin only on the dev server (`apply: 'serve'`). Nothing of this reaches production.

**Safe by design** – Vite not running? Then nothing is injected and nothing breaks. Saving in the backend is never slowed down. A page without tag data reloads on every change instead of missing one.

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
| A Fluid template | Nothing, by default! | your own Vite setup |

Note that TYPO3 caches compiled Fluid templates. In Development context the cache normally notices changed files by itself; if a change does not show up (this happens most often with partials), clear the TYPO3 caches.

## Requirements

- TYPO3 `^13.4 || ^14.3`, PHP `^8.2`, Vite `>=5`

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
| `activeContexts` | `Development` | Application contexts (comma list, prefix match) where the extension is active |
| `reloadMode` | `tagged` | `tagged` = only affected tabs reload; `always` = every connected tab |
| `viteServerInternalUrl` | `http://localhost:5173` | Dev server URL reachable from PHP (broadcast target) |
| `viteServerPublicUrl` | *(empty)* | Dev server URL reachable from the browser; empty = resolve automatically |

The browser-facing URL is resolved in this order: the explicit setting → vite-asset-collector's `auto` chain (which understands, for example, `ddev-vite-sidecar`'s `VITE_SERVER_URI`) → none. With none, the extension stays inactive for that request.

`viteServerInternalUrl` is where **PHP** posts the flushed tags. In Docker/DDEV setups `http://localhost:5173` is only correct when Vite runs in the same container as PHP-FPM. Broadcast failures are silent on purpose (a save must never break), so when reloads do not happen, first check the URL from the PHP side:

```bash
ddev exec 'curl -s -o /dev/null -w "%{http_code}" -X POST http://localhost:5173/__typo3-content-changed -H "Content-Type: application/json" -d "{\"tags\":[]}"'
```

`204` means PHP can reach the dev server. The Admin Panel's Status tab (see below) shows both URLs at one glance.

The Vite plugin accepts a `debounceMs` option (default `200`) — how long broadcasts are collected before they go to the browser. An `endpoint` option also exists, but the PHP side always posts to `/__typo3-content-changed`; changing the endpoint only makes sense when a proxy rewrites that path.

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

With [EXT:adminpanel](https://packagist.org/packages/typo3/cms-adminpanel) installed, a **Content Live Reload** module appears in the frontend Admin Panel.

The panel bar itself shows the essentials at one glance, without opening the module: whether the dev-server connection is alive (`●` or `○ disconnected`), the active reload mode, and when this tab last updated — for example `● · tagged · updated 21:58:57`.

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
