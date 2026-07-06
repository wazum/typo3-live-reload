# Changelog

All notable changes to Content Live Reload are documented here. The format is based on
[Keep a Changelog](https://keepachangelog.com/), and this project follows
[Semantic Versioning](https://semver.org/).

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
- Cancelable `typo3:content-changed` DOM event to take over the reload — for example a Turbo visit
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
