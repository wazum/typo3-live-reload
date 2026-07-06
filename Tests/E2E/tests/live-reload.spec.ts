import { expect, test } from '@playwright/test'
import { execFileSync } from 'node:child_process'
import { readFileSync } from 'node:fs'
import { resolve } from 'node:path'

const e2eDir = resolve(import.meta.dirname, '..')
const appDir = resolve(e2eDir, '.app')
const seed = JSON.parse(readFileSync(resolve(e2eDir, '.seed.json'), 'utf-8'))

function updateContent(uid: number, header: string) {
    execFileSync('php', ['vendor/bin/typo3', 'e2e:update-content', String(uid), header], {
        cwd: appDir,
        env: { ...process.env, TYPO3_CONTEXT: 'Development' },
    })
}

test('pages carry their cache tags and the client module', async ({ page }) => {
    await page.goto('/')
    const config = await page.evaluate(() => (window as any).__liveReload)
    expect(config.mode).toBe('tagged')
    expect(config.tags).toContain('pageId_1')
    expect(config.tags).toContain(`tt_content_${seed.homeContentUid}`)
    const moduleSource = await page.getAttribute('script[src*="virtual:live-reload"]', 'src')
    expect(moduleSource).toBe('http://127.0.0.1:5273/@id/virtual:live-reload')
})

test('editing a record reloads only the affected tab', async ({ browser }) => {
    const contextHome = await browser.newContext()
    const contextOther = await browser.newContext()
    const homePage = await contextHome.newPage()
    const otherPage = await contextOther.newPage()
    await homePage.goto('/')
    await otherPage.goto('/other')
    await expect(homePage.locator('body')).toContainText('Home content')
    await expect(otherPage.locator('body')).toContainText('Other content')

    let otherReloads = 0
    otherPage.on('load', () => {
        otherReloads += 1
    })
    const otherBroadcast = otherPage.evaluate(
        () =>
            new Promise<{ matched: boolean }>((resolveBroadcast) => {
                document.addEventListener(
                    'typo3:live-reload:broadcast',
                    (event) => resolveBroadcast({ matched: (event as CustomEvent).detail.matched }),
                    { once: true },
                )
            }),
    )

    updateContent(seed.homeContentUid, 'Home content updated')

    await expect(homePage.locator('body')).toContainText('Home content updated', { timeout: 15000 })
    expect(await otherBroadcast).toEqual({ matched: false })
    expect(otherReloads).toBe(0)

    await contextHome.close()
    await contextOther.close()
})

