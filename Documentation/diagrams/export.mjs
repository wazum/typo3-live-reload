#!/usr/bin/env node
/**
 * GitHub can't run the container queries that switch between the variants. The
 * README picks a variant with <picture media>, so each one must be its own file.
 */
import * as fs from 'fs'
import * as path from 'path'
import { fileURLToPath } from 'url'

const here = path.dirname(fileURLToPath(import.meta.url))

const EXPORTS = {
    'how-it-works': { '': 'tablet', '-narrow': 'narrow' },
}

function variant(source, name) {
    const open = new RegExp(`<svg class="${name}"`).exec(source)
    if (!open) {
        throw new Error(`no <svg class="${name}">`)
    }

    const end = source.indexOf('</svg>', open.index)

    return source.slice(open.index, end + '</svg>'.length)
}

// Without width and height, GitHub shows the image at its own default size.
// XML knows only five entities. An HTML one like &middot; works in the source page
// but breaks the exported file, and GitHub then shows a missing image.
const XML_ENTITIES = /&(?!(amp|lt|gt|quot|apos|#\d+|#x[0-9a-fA-F]+);)[^;\s]*;/

function standalone(markup) {
    const stray = XML_ENTITIES.exec(markup)
    if (stray) {
        throw new Error(`${stray[0]} is not an XML entity — write the character itself`)
    }

    const [, width, height] = /viewBox="[\d.-]+ [\d.-]+ ([\d.]+) ([\d.]+)"/.exec(markup)

    return `<?xml version="1.0" encoding="UTF-8"?>\n${markup.replace(
        /^<svg class="\w+"/,
        `<svg width="${width}" height="${height}"`,
    )}\n`
}

let written = 0
for (const [diagram, files] of Object.entries(EXPORTS)) {
    const source = fs.readFileSync(path.join(here, `${diagram}.html`), 'utf-8')
    for (const [suffix, name] of Object.entries(files)) {
        const target = path.join(here, `${diagram}${suffix}.svg`)
        fs.writeFileSync(target, standalone(variant(source, name)))
        console.log(`${path.basename(target)} — ${name}`)
        written += 1
    }
}

console.log(`${written} file(s)`)
