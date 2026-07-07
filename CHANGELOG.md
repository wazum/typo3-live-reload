# Changelog

All notable changes to Live Reload are documented here. The format is based on
[Keep a Changelog](https://keepachangelog.com/), and this project follows
[Semantic Versioning](https://semver.org/).

## 2.1.0 - 2026-07-07

### Added

- Optional shared-secret authentication between TYPO3 and the Vite dev server: set `viteSharedSecret`
  in the extension configuration and the same value in the vite plugin (`liveReload({ secret: '…' })`);
  the broadcast endpoint then answers `401` to posts without the matching `X-Live-Reload-Secret` header.
  Incoming tags are sanitized either way: control characters stripped, oversized tags and anything
  beyond 1000 tags per request dropped, log output capped.

### Fixed

- The poll response cursor now covers every broadcast actually returned, so an entry appended while
  the response was being built is no longer delivered twice.
- The Vite broadcast endpoint matches its path exactly (previously any path with the endpoint as
  prefix was accepted) and rejects request bodies over 256 KB.
- File capture and the development page-cache bypass respect `activeContexts`: a configuration
  without `Development` leaves development requests completely untouched.

## 2.0.0 - 2026-07-06

The package is now **`wazum/typo3-live-reload`** (previously `wazum/typo3-content-live-reload`), because
it no longer only reloads on content changes: editing a Fluid template, partial, layout, or ViewHelper
class now reloads exactly the tabs whose pages rendered that file.

### Added

- Targeted file reloads: in the Development context, every Fluid render records the template, partial,
  and layout files it resolves and the ViewHelper classes it instantiates; the paths are injected as
  `file:` tags next to the cache tags. The Vite plugin's new `watch` option
  (`liveReload({ watch: { paths: ['packages'] } })`) turns changed files into the same tags — the
  existing tag intersection decides which tabs reload. No PHP round trip; the watcher is the signal.
- `fileReload` extension setting (default on). While enabled, the frontend page cache is disabled in
  the Development context so every render reflects the current files; set `fileReload = 0` to keep the
  page cache and content-only reloads.

### Changed (breaking — the rename)

| 1.x | 2.0 |
|---|---|
| `wazum/typo3-content-live-reload` | `wazum/typo3-live-reload` |
| extension key `content_live_reload` | `live_reload` (settings move to `EXTENSIONS/live_reload`) |
| namespace `Wazum\ContentLiveReload` | `Wazum\LiveReload` |
| table `tx_contentlivereload_broadcast` | `tx_livereload_broadcast` (recreate via Database Analyzer; the table only holds transient broadcasts) |
| vite plugin import `…/typo3-content-live-reload/…` | `…/typo3-live-reload/…` |
| virtual module `virtual:content-live-reload` | `virtual:live-reload` |
| endpoint `/__typo3-content-changed` | `/__typo3-live-reload` |
| events `typo3:content-changed[:*]` | `typo3:live-reload[:*]` |
| `window.__contentLiveReload` | `window.__liveReload` |

## 1.0.0 - 2026-07-06

The first stable release. When an editor saves a record, the open frontend tabs that show it reload
by themselves — pushed over a Vite dev server during development, or polled by logged-in editors on
shared environments where no dev server runs. TYPO3's cache tags decide which tabs are affected, so
only the changed content reloads and everything else keeps its state.

### Added

- Targeted reloads driven by TYPO3 cache tags: a middleware writes each page's rendered tags into
  the page, a `clearCachePostProc` hook collects the tags a save flushes, and each open tab reloads
  only when the two overlap. A page without tag data reloads on every change instead of missing one.
- Dev-server transport: a bundled Vite plugin broadcasts changes over the HMR WebSocket. No npm
  package, and with [vite-asset-collector](https://packagist.org/packages/praetorius/vite-asset-collector)
  no TypoScript — one line in `vite.config.ts`.
- Polling transport for shared environments: where no dev server runs, each logged-in backend user's
  tab polls a small endpoint. In the Development context it also serves as an automatic fallback when
  no dev server is resolvable, so the extension works with or without Vite.
- `reloadMode`: `tagged` (only affected tabs) or `always` (every connected tab), plus a per-session
  `paused` override from the Admin Panel.
- Cancelable `typo3:live-reload` DOM event to take over the reload — for example a Turbo visit
  instead of a full reload.
- `ModifyBroadcastTagsEvent` (PSR-14) to add tags that other extensions flush on their own, such as
  `tx_news_uid_*` for [georgringer/news](https://extensions.typo3.org/extension/news).
- Admin Panel module: connection status, the page's cache tags, a live broadcast feed, and the pause
  switch. The status shows which transport is active.
- Scroll position kept across the reload, including when a framework sets
  `history.scrollRestoration = 'manual'`.
- Automatic CSP nonce handling for the injected scripts and the `csp-nonce` meta element.

### Security

- The extension is inactive outside the configured application contexts (default: `Development`), and
  a bare `Production` context can never activate — production-like environments must name their exact
  subcontext, for example `Production/Staging`.
- Outside Development a valid backend session is required for both the injection and the poll endpoint;
  anonymous visitors receive nothing and the endpoint answers like any unknown URL. Broadcasts carry
  cache-tag names only, never content.
- Saving is never slowed down: the broadcast is sent after the editor's response, and a failed
  broadcast is silent.

### Requirements

- TYPO3 `^13.4 || ^14.3`, PHP `^8.2`. The dev-server transport additionally needs Vite `>=5.1`.
