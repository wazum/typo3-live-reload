# Admin Panel

With [EXT:adminpanel](https://packagist.org/packages/typo3/cms-adminpanel) installed, a
Live Reload module appears in the frontend Admin Panel.

## The panel bar

The panel bar shows the most important state without opening the module:

- a connection dot: green and slowly pulsing while the dev server is connected, a red ring
  when the connection was lost, with a short flash for every broadcast the tab receives,
- the reload mode, but only when it is not the normal `tagged`,
- the time of the tab's last update.

On a normal day you see only the green dot and a time like `21:58`. Anything unusual, such
as `paused`, `always` or a lost connection, shows up by itself. The animations respect
`prefers-reduced-motion`.

A gray ring means the page never connected. This happens when the extension found a dev
server URL but the server itself was down when the page loaded. The page then loads the
client from the dev server, which fails, so the tab can't hear any broadcast, including the
one that would reload it. Reload the tab once after the dev server is back. From then on, it
recovers by itself. When no dev server URL is found at all, the page uses the poll transport
and still gets content reloads.

## The three tabs

Status shows whether the extension is active in this context, which reload mode is in
effect, which dev server URL the extension found and how, and whether
`frontend.cache.autoTagging` is on. You would otherwise check all of this with curl.

Cache tags lists all tags collected for the current page. This also helps with cache
debugging in general.

Broadcasts is a live feed of the broadcasts this tab received, newest first. Each entry
shows the time, the result (`matched → reload`, `no overlap`, or the paused variants) and
the tags. Entries survive the reloads they cause: the tab keeps up to 20 of them in
`sessionStorage`.

## Pausing reloads

The panel's settings form can override the reload mode for your session:

<img src="admin-panel-settings.png" alt="Reload mode override in the Admin Panel settings" width="435">

`paused` keeps the tab connected and the feed running, but stops all reloads. This helps
during demos, or while you look at a DOM state that a reload would reset. The override only
affects your own backend session. The extension configuration stays as it is.
