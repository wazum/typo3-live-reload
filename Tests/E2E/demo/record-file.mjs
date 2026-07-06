import { chromium } from '@playwright/test'
import { mkdirSync, readFileSync, writeFileSync } from 'node:fs'
import { dirname, resolve } from 'node:path'
import { fileURLToPath } from 'node:url'

const demoDir = dirname(fileURLToPath(import.meta.url))
const videoDir = resolve(demoDir, 'video')
mkdirSync(videoDir, { recursive: true })

const baseUrl = process.env.DEMO_BASE_URL ?? 'http://web:8080'
const partialFile = resolve(demoDir, '../.app/packages/e2e_fixture/Resources/Private/PageView/Partials/HomeTeaser.html')
const newLine = '<p class="notice">Now with targeted live reload ✨</p>'

const frontendPolish = `
    body > div[style*='width: 800px'] { display: none !important; }
    body { font-family: -apple-system, 'Segoe UI', Roboto, sans-serif; background: #f4f5f7; }
    .frame { max-width: 44rem; margin: 1.4rem auto 0; background: #fff; border-radius: 10px; padding: 1.6rem 2.6rem; box-shadow: 0 1px 4px rgba(0,0,0,.08); }
    h2 { font-weight: 650; color: #1a1a2e; margin: 0; font-size: 1.5rem; }
    [data-fixture='home-teaser'] { max-width: 44rem; margin: 3.2rem auto 0; font-size: 1.1rem; font-weight: 650; color: #1a1a2e; }
    [data-fixture='badge'], [data-fixture='stamp'] { display: none; }
    .notice { max-width: 41rem; margin: .9rem auto 0; background: #e6f7ec; border: 1px solid #34c176; color: #14683c; padding: .8rem 1.5rem; border-radius: 8px; font-weight: 600; }
`

const label = (text) => `
    body::after {
        content: '${text}';
        position: fixed;
        top: 0;
        left: 0;
        padding: 6px 14px;
        background: #1a1a2e;
        color: #fff;
        font-size: 14px;
        font-family: -apple-system, 'Segoe UI', Roboto, sans-serif;
        border-bottom-right-radius: 8px;
        z-index: 99999;
    }
`

const originalPartial = readFileSync(partialFile, 'utf-8')
const browser = await chromium.launch()

try {
    const editorSize = { width: 1000, height: 300 }
    const frontendSize = { width: 1000, height: 380 }
    const contextEditor = await browser.newContext({ recordVideo: { dir: videoDir, size: editorSize }, viewport: editorSize })
    const contextTop = await browser.newContext({ recordVideo: { dir: videoDir, size: frontendSize }, viewport: frontendSize, ignoreHTTPSErrors: true })
    const contextBottom = await browser.newContext({ recordVideo: { dir: videoDir, size: frontendSize }, viewport: frontendSize, ignoreHTTPSErrors: true })

    const pageEditor = await contextEditor.newPage()
    const pageTop = await contextTop.newPage()
    const pageBottom = await contextBottom.newPage()

    const styleTop = frontendPolish + label('Frontend tab 1 — renders this partial')
    const styleBottom = frontendPolish + label('Frontend tab 2 — a different page')
    const applyStyles = (page, style) => page.addStyleTag({ content: style }).catch(() => {})

    await pageEditor.goto('file://' + resolve(demoDir, 'editor.html'))
    await pageTop.goto(baseUrl + '/')
    await pageBottom.goto(baseUrl + '/other')
    await applyStyles(pageTop, styleTop)
    await applyStyles(pageBottom, styleBottom)
    pageTop.on('load', () => applyStyles(pageTop, styleTop))
    pageBottom.on('load', () => applyStyles(pageBottom, styleBottom))

    await pageTop.waitForTimeout(2500)

    await pageEditor.evaluate((line) => window.typeLine(line), newLine)
    await pageEditor.waitForFunction(() => window.typingDone)
    await pageEditor.waitForTimeout(500)

    await pageEditor.evaluate(() => window.flashSaved())
    writeFileSync(partialFile, originalPartial.replace('</html>', `${newLine}\n</html>`))

    await pageTop.waitForTimeout(6500)

    const videos = {
        editor: await pageEditor.video().path(),
        top: await pageTop.video().path(),
        bottom: await pageBottom.video().path(),
    }
    await contextEditor.close()
    await contextTop.close()
    await contextBottom.close()
    console.log(JSON.stringify(videos))
} finally {
    writeFileSync(partialFile, originalPartial)
    await browser.close()
}
