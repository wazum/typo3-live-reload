# Configuration

Set these in the extension configuration (`live_reload`) or in
`$GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS']['live_reload']`:

| Setting | Default | What it does |
|---|---|---|
| `activeContexts` | `Development` | Application contexts where the extension runs, as a comma list. An entry matches itself and its subcontexts, so `Development` also covers `Development/Docker`. A plain `Production` entry is ignored — name the exact subcontext instead. An empty value turns the extension off everywhere. |
| `reloadMode` | `tagged` | `tagged` reloads only the affected tabs. `always` reloads every connected tab. |
| `fileReload` | `1` | Records the rendered files and reloads on template and ViewHelper changes. Turns off the frontend page cache in the Development context ([the page cache](file-reloads.md#the-page-cache)). |
| `viteServerInternalUrl` | `http://localhost:5173` | Dev server URL that PHP can reach. PHP sends broadcasts here. |
| `viteServerPublicUrl` | *(empty)* | Dev server URL that the browser can reach. Empty means the extension finds it by itself. |
| `viteSharedSecret` | *(empty)* | Secret sent with each broadcast. Set the same value as the Vite plugin's `secret` option, so the endpoint rejects posts without it. |
| `pollInterval` | `3000` | Milliseconds between polls when the [poll transport](content-reloads.md#reloads-for-editors-without-a-dev-server) is active. Minimum `1000`. |
| `retention` | `300` | Seconds a broadcast stays available for polling tabs. Minimum `60`. |

## The browser's dev server URL

The extension looks for the browser-facing URL in this order:

1. the `viteServerPublicUrl` setting,
2. vite-asset-collector's `auto` chain, which understands for example the
   `VITE_SERVER_URI` of `ddev-vite-sidecar`,
3. nothing.

If it finds nothing, the page uses the poll transport. Content reloads keep working. Only
file reloads need the dev server. The Admin Panel's Status tab shows which transport the
page uses.

## The internal dev server URL

`viteServerInternalUrl` is where PHP posts the flushed tags. In Docker or DDEV setups,
`http://localhost:5173` is only correct when Vite runs in the same container as PHP-FPM.

Failed broadcasts are silent on purpose, because a save must never break. So when reloads
don't happen, first check the URL from the PHP side:

```bash
ddev exec 'curl -s -o /dev/null -w "%{http_code}" -X POST http://localhost:5173/__typo3-live-reload -H "Content-Type: application/json" -d "{\"tags\":[]}"'
```

`204` means PHP can reach the dev server. The Admin Panel's Status tab shows both URLs.

## Vite plugin options

| Option | Default | What it does |
|---|---|---|
| `watch` | *(none)* | Turns on file reloads ([the `watch` option](file-reloads.md#the-watch-option)). |
| `debounceMs` | `200` | How long the plugin collects broadcasts before it sends them to the browser. File changes and posted tags share the same batch. |
| `secret` | *(empty)* | Must match `viteSharedSecret`. |
| `endpoint` | `/__typo3-live-reload` | PHP always posts to `/__typo3-live-reload`. Change this only when a proxy rewrites that path. |

## Shared secret

When the Vite dev server is reachable beyond your own machine, for example on a LAN address
or a proxied DDEV port, set `viteSharedSecret` and the plugin's `secret` option to the same
value:

```ts
liveReload({ secret: '…' })
```

The broadcast endpoint then answers `401` to every post without the matching
`X-Live-Reload-Secret` header.

The extension also cleans tags when they arrive. It removes control characters, and it
drops tags that are too long and anything after the first 1000 tags of a request.
