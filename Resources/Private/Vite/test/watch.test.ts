import { mkdirSync, mkdtempSync, rmSync, symlinkSync, writeFileSync } from 'node:fs'
import { tmpdir } from 'node:os'
import { join } from 'node:path'
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { liveReload } from '../src/index'

type WatchHandler = (file: string) => void

function fakeServer() {
    const send = vi.fn()
    const add = vi.fn()
    const handlers: Record<string, WatchHandler[]> = {}
    const server = {
        middlewares: { use: vi.fn() },
        ws: { send },
        config: { logger: { info: vi.fn() } },
        watcher: {
            add,
            on(event: string, handler: WatchHandler) {
                ;(handlers[event] ??= []).push(handler)
            },
        },
    }
    const emit = (event: string, file: string) => {
        for (const handler of handlers[event] ?? []) handler(file)
    }
    return { server, send, add, emit }
}

describe('liveReload watch', () => {
    let projectRoot: string

    beforeEach(() => {
        vi.useFakeTimers()
        projectRoot = mkdtempSync(join(tmpdir(), 'live-reload-watch-'))
        mkdirSync(join(projectRoot, 'templates', 'Partials'), { recursive: true })
    })

    afterEach(() => {
        vi.useRealTimers()
        rmSync(projectRoot, { recursive: true, force: true })
    })

    function configuredPlugin(paths = ['templates']) {
        const plugin = liveReload({ debounceMs: 200, watch: { paths, projectRoot } })
        const fake = fakeServer()
        ;(plugin.configureServer as any)(fake.server)
        return { plugin, ...fake }
    }

    it('registers the watch paths with the vite watcher', () => {
        const { add } = configuredPlugin()
        expect(add).toHaveBeenCalledWith(join(projectRoot, 'templates'))
    })

    it('broadcasts a project-relative file: tag when a watched template changes', async () => {
        const file = join(projectRoot, 'templates', 'Partials', 'Box.html')
        writeFileSync(file, '<div/>')
        const { send, emit } = configuredPlugin()

        emit('change', file)
        await vi.runAllTimersAsync()

        expect(send).toHaveBeenCalledWith({
            type: 'custom',
            event: 'typo3:live-reload',
            data: { tags: ['file:templates/Partials/Box.html'] },
        })
    })

    it('resolves symlinked watch locations to their real project path', async () => {
        const file = join(projectRoot, 'templates', 'Partials', 'Box.html')
        writeFileSync(file, '<div/>')
        symlinkSync(join(projectRoot, 'templates'), join(projectRoot, 'linked'))
        const { send, emit } = configuredPlugin(['linked'])

        emit('change', join(projectRoot, 'linked', 'Partials', 'Box.html'))
        await vi.runAllTimersAsync()

        expect(send).toHaveBeenCalledWith({
            type: 'custom',
            event: 'typo3:live-reload',
            data: { tags: ['file:templates/Partials/Box.html'] },
        })
    })

    it('broadcasts deletions of watched files', async () => {
        const { send, emit } = configuredPlugin()

        emit('unlink', join(projectRoot, 'templates', 'Partials', 'Gone.html'))
        await vi.runAllTimersAsync()

        expect(send).toHaveBeenCalledWith({
            type: 'custom',
            event: 'typo3:live-reload',
            data: { tags: ['file:templates/Partials/Gone.html'] },
        })
    })

    it('ignores files outside the watched paths and non-matching extensions', async () => {
        mkdirSync(join(projectRoot, 'other'))
        const outside = join(projectRoot, 'other', 'Elsewhere.html')
        writeFileSync(outside, '<div/>')
        const stylesheet = join(projectRoot, 'templates', 'style.css')
        writeFileSync(stylesheet, 'body{}')
        const { send, emit } = configuredPlugin()

        emit('change', outside)
        emit('change', stylesheet)
        await vi.runAllTimersAsync()

        expect(send).not.toHaveBeenCalled()
    })

    it('keeps umlauts and spaces in broadcast file: tags', async () => {
        mkdirSync(join(projectRoot, 'templates', 'Übersicht Ordner'), { recursive: true })
        const file = join(projectRoot, 'templates', 'Übersicht Ordner', 'Kopfzeile Größe.html')
        writeFileSync(file, '<div/>')
        const { send, emit } = configuredPlugin()

        emit('change', file)
        await vi.runAllTimersAsync()

        expect(send).toHaveBeenCalledWith({
            type: 'custom',
            event: 'typo3:live-reload',
            data: { tags: ['file:templates/Übersicht Ordner/Kopfzeile Größe.html'] },
        })
    })

    it('honours a custom extension list', async () => {
        const helper = join(projectRoot, 'templates', 'Helper.php')
        writeFileSync(helper, '<?php')
        const { send, emit } = configuredPlugin()

        emit('change', helper)
        await vi.runAllTimersAsync()

        expect(send).toHaveBeenCalledWith({
            type: 'custom',
            event: 'typo3:live-reload',
            data: { tags: ['file:templates/Helper.php'] },
        })
    })

    it('debounces watcher events together with endpoint broadcasts', async () => {
        const first = join(projectRoot, 'templates', 'A.html')
        const second = join(projectRoot, 'templates', 'B.html')
        writeFileSync(first, 'a')
        writeFileSync(second, 'b')
        const { send, emit } = configuredPlugin()

        emit('change', first)
        emit('change', second)
        emit('change', first)
        await vi.runAllTimersAsync()

        expect(send).toHaveBeenCalledTimes(1)
        expect(send).toHaveBeenCalledWith({
            type: 'custom',
            event: 'typo3:live-reload',
            data: { tags: ['file:templates/A.html', 'file:templates/B.html'] },
        })
    })

    it('suppresses vite default hot handling for watched files only', () => {
        const file = join(projectRoot, 'templates', 'Partials', 'Box.html')
        writeFileSync(file, '<div/>')
        const { plugin } = configuredPlugin()

        const handleHotUpdate = plugin.handleHotUpdate as any
        expect(handleHotUpdate({ file, modules: ['m'] })).toEqual([])
        expect(handleHotUpdate({ file: join(projectRoot, 'other.ts'), modules: ['m'] })).toBeUndefined()
    })

    it('does not register watcher hooks without watch options', () => {
        const plugin = liveReload()
        const fake = fakeServer()
        ;(plugin.configureServer as any)(fake.server)

        expect(fake.add).not.toHaveBeenCalled()
        expect((plugin.handleHotUpdate as any)?.({ file: 'x.html', modules: ['m'] })).toBeUndefined()
    })
})
