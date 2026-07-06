import { expect, test, type BrowserContext, type Page } from '@playwright/test'
import { execFileSync } from 'node:child_process'
import { readFileSync } from 'node:fs'
import { resolve } from 'node:path'

const stagingBase = 'http://127.0.0.1:8081'
const e2eDir = resolve(import.meta.dirname, '..')
const appDir = resolve(e2eDir, '.app')
const seed = JSON.parse(readFileSync(resolve(e2eDir, '.seed.json'), 'utf-8'))

function updateContent(uid: number, header: string) {
    const environment = { ...process.env, TYPO3_CONTEXT: 'Production/Staging' }
    delete environment.VITE_SERVER_URI
    execFileSync('php', ['vendor/bin/typo3', 'e2e:update-content', String(uid), header], {
        cwd: appDir,
        env: environment,
    })
}

async function logInBackendUser(context: BrowserContext): Promise<Page> {
    const page = await context.newPage()
    await page.goto(`${stagingBase}/typo3/`)
    await page.fill('input[name="username"]', seed.editorUsername)
    await page.fill('input[name="p_field"]', seed.editorPassword)
    await page.click('button[type="submit"]')
    await expect
        .poll(async () => (await context.cookies()).some((cookie) => cookie.name === 'be_typo_user'))
        .toBe(true)
    return page
}

test('anonymous visitors get no configuration and no client script', async ({ page }) => {
    await page.goto(`${stagingBase}/`)
    expect(await page.evaluate(() => (window as any).__liveReload)).toBeUndefined()
    expect(await page.locator('script[src*="poll-client"]').count()).toBe(0)
    expect(await page.locator('script[src*="virtual:live-reload"]').count()).toBe(0)
})

test('the poll endpoint answers 404 without a backend session', async ({ request }) => {
    const response = await request.get(`${stagingBase}/__live-reload/poll?since=0`)
    expect(response.status()).toBe(404)
})

test('the poll endpoint answers JSON for a logged-in backend user', async ({ browser }) => {
    const context = await browser.newContext()
    await logInBackendUser(context)

    const response = await context.request.get(`${stagingBase}/__live-reload/poll?since=0`)

    expect(response.status()).toBe(200)
    expect(response.headers()['content-type']).toContain('application/json')
    const payload = await response.json()
    expect(typeof payload.sequence).toBe('number')

    await context.close()
})

test('saving a record reloads only the affected editor tab', async ({ browser }) => {
    const context = await browser.newContext()
    const backendPage = await logInBackendUser(context)
    await backendPage.close()
    const homePage = await context.newPage()
    const otherPage = await context.newPage()
    await homePage.goto(`${stagingBase}/editor-home`)
    await otherPage.goto(`${stagingBase}/editor-other`)
    await expect(homePage.locator('body')).toContainText('Editor home content')
    await expect(otherPage.locator('body')).toContainText('Editor other content')

    const configuration = await homePage.evaluate(() => (window as any).__liveReload)
    expect(configuration.transport).toBe('poll')
    expect(configuration.endpoint).toBe('/__live-reload/poll')
    expect(configuration.tags).toContain(`pageId_${seed.editorHomePageUid}`)

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

    const headline = `Editor reload ${Date.now()}`
    updateContent(seed.editorHomeContentUid, headline)

    await expect(homePage.locator('body')).toContainText(headline, { timeout: 15000 })
    expect(await otherBroadcast).toEqual({ matched: false })
    expect(otherReloads).toBe(0)

    await context.close()
})
