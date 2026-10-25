# File reloads

When you save a Fluid template, partial, layout or component, or a ViewHelper class, every
open tab whose page rendered that file reloads. Other tabs stay as they are. Each page
carries a fingerprint of the files its render used, recorded while it renders.

File reloads need the Vite plugin with the `watch` option, as the
[installation](../README.md#installation) shows.

## How files are recorded

In the Development context, the extension decorates TYPO3's view factory. Every Fluid view
records:

- the template, partial and layout files it resolves,
- every ViewHelper class it creates (reflection gives the class file),
- the template files of Fluid v4 component collections, through a wrapped resolver delegate.

The extension turns these absolute paths into paths relative to the project. It resolves
symlinks first, so an extension installed from a Composer path repository under `vendor/`
maps back to its real source in `packages/`. It drops files inside the real vendor
directory. The result goes into the page as `file:` tags, next to the cache tags.

On the Vite side, the plugin hands `watch.paths` to the dev server's watcher. A changed
`.html` or `.php` file becomes the same `file:` tag. Both sides read the same repository
layout, so the tags match even when PHP runs in a container and Vite runs on the host. The
plugin turns off Vite's default full reload for these files and sends its own targeted
broadcast instead.

## The `watch` option

```ts
liveReload({
    watch: {
        paths: ['packages'],          // directories to watch, relative to projectRoot (or absolute)
        extensions: ['.html', '.php'], // default
        projectRoot: process.cwd(),    // default; the path project-relative tags are computed against
    },
})
```

`paths` uses Vite's own watcher, so your `server.watch` options apply. For example, set
`usePolling: true` in Docker setups where file events don't cross the mount.

## The page cache

TYPO3 doesn't render a page that comes from its page cache. Such a page carries no file
fingerprint and would not react to template changes. So while file reloads are on, the
extension turns off the frontend page cache in the Development context, and every render
uses the current files.

If you want to keep the page cache and only need content reloads, switch file reloads off:

```
fileReload = 0
```

This removes the recording and leaves the page cache alone. Content reloads still work on
cached pages, because TYPO3 restores the cache tags from the cache entry.

## Sharp fingerprints

Fluid parses whole template files. A ViewHelper used anywhere in a parsed file counts as
used by every page that renders that file, even inside a condition branch that didn't run.
Partials are different: Fluid parses them only when it renders them.

If a ViewHelper should only reload the pages of one page type, use it inside a partial that
only those pages render. Mistakes go in the safe direction either way — at worst, a tab
reloads that didn't need to.

## Limits

- File reloads need the Vite dev server. The poll transport has no file watcher.
- A new partial is not in any open tab's fingerprint yet. After you create a file, the
  first change may need one manual reload.
- The extension only sees Fluid views created through TYPO3's view factory. That covers
  `PAGEVIEW`, `FLUIDTEMPLATE`, Extbase and fluid_styled_content. It doesn't see a
  `TemplateView` you create by hand, and such a view renders as it would without the
  extension.
- Saving a PHP file triggers the reload. Whether you see the change also depends on
  opcache: set `opcache.revalidate_freq=0` in development. DDEV does this by default.
