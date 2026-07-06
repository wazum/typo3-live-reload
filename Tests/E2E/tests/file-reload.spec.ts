import { expect, test, type Browser, type Page } from '@playwright/test'
import { appendFileSync } from 'node:fs'
import { resolve } from 'node:path'

const e2eDir = resolve(import.meta.dirname, '..')
const appDir = resolve(e2eDir, '.app')

const layoutFile = 'packages/e2e_fixture/Resources/Private/PageView/Layouts/Default.html'
const templateFile = 'packages/e2e_fixture/Resources/Private/PageView/Pages/Default.html'
const partialFile = 'packages/e2e_fixture/Resources/Private/PageView/Partials/HomeTeaser.html'
const viewHelperFile = 'packages/e2e_fixture/Classes/ViewHelpers/StampViewHelper.php'

function touchTemplate(relativePath: string) {
    appendFileSync(resolve(appDir, relativePath), `\n<!-- touched ${Date.now()} -->\n`)
}

function touchPhpFile(relativePath: string) {
    appendFileSync(resolve(appDir, relativePath), `\n// touched ${Date.now()}\n`)
}

async function openPages(browser: Browser): Promise<{ homePage: Page; otherPage: Page; close: () => Promise<void> }> {
    const contextHome = await browser.newContext()
    const contextOther = await browser.newContext()
    const homePage = await contextHome.newPage()
    const otherPage = await contextOther.newPage()
    await homePage.goto('/')
    await otherPage.goto('/other')

    return {
        homePage,
        otherPage,
        close: async () => {
            await contextHome.close()
            await contextOther.close()
        },
    }
}

function nonMatchingBroadcast(page: Page): Promise<{ matched: boolean }> {
    return page.evaluate(
        () =>
            new Promise<{ matched: boolean }>((resolveBroadcast) => {
                document.addEventListener(
                    'typo3:live-reload:broadcast',
                    (event) => resolveBroadcast({ matched: (event as CustomEvent).detail.matched }),
                    { once: true },
                )
            }),
    )
}

test('pages expose the rendered layout, template, partial and view helper as file: tags', async ({ page }) => {
    await page.goto('/')
    const homeTags = await page.evaluate(() => (window as any).__liveReload.tags)
    expect(homeTags).toContain(`file:${templateFile}`)
    expect(homeTags).toContain(`file:${layoutFile}`)
    expect(homeTags).toContain(`file:${partialFile}`)
    expect(homeTags).not.toContain(`file:${viewHelperFile}`)

    await page.goto('/other')
    const otherTags = await page.evaluate(() => (window as any).__liveReload.tags)
    expect(otherTags).toContain(`file:${templateFile}`)
    expect(otherTags).toContain(`file:${layoutFile}`)
    expect(otherTags).toContain(`file:${viewHelperFile}`)
    expect(otherTags).not.toContain(`file:${partialFile}`)
})

test('editing a partial reloads only the page that rendered it', async ({ browser }) => {
    const { homePage, otherPage, close } = await openPages(browser)

    let otherReloads = 0
    otherPage.on('load', () => {
        otherReloads += 1
    })
    const otherBroadcast = nonMatchingBroadcast(otherPage)
    const homeReloaded = homePage.waitForEvent('load', { timeout: 15000 })

    touchTemplate(partialFile)

    await homeReloaded
    expect(await otherBroadcast).toEqual({ matched: false })
    expect(otherReloads).toBe(0)

    await close()
})

test('editing a view helper class reloads only the page that used it', async ({ browser }) => {
    const { homePage, otherPage, close } = await openPages(browser)

    let homeReloads = 0
    homePage.on('load', () => {
        homeReloads += 1
    })
    const homeBroadcast = nonMatchingBroadcast(homePage)
    const otherReloaded = otherPage.waitForEvent('load', { timeout: 15000 })

    touchPhpFile(viewHelperFile)

    await otherReloaded
    expect(await homeBroadcast).toEqual({ matched: false })
    expect(homeReloads).toBe(0)

    await close()
})

test('editing the shared layout reloads all pages that rendered it', async ({ browser }) => {
    const { homePage, otherPage, close } = await openPages(browser)

    const homeReloaded = homePage.waitForEvent('load', { timeout: 15000 })
    const otherReloaded = otherPage.waitForEvent('load', { timeout: 15000 })

    touchTemplate(layoutFile)

    await Promise.all([homeReloaded, otherReloaded])

    await close()
})

test('editing the shared page template reloads all pages that rendered it', async ({ browser }) => {
    const { homePage, otherPage, close } = await openPages(browser)

    const homeReloaded = homePage.waitForEvent('load', { timeout: 15000 })
    const otherReloaded = otherPage.waitForEvent('load', { timeout: 15000 })

    touchTemplate(templateFile)

    await Promise.all([homeReloaded, otherReloaded])

    await close()
})
