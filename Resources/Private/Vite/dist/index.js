import { readFileSync } from 'node:fs';
import { dirname, join } from 'node:path';
import { fileURLToPath } from 'node:url';
export const VIRTUAL_MODULE_ID = 'virtual:live-reload';
const EVENT_NAME = 'typo3:live-reload';
const DEFAULT_ENDPOINT = '/__typo3-live-reload';
const DEFAULT_DEBOUNCE_MS = 200;
const clientFilePath = join(dirname(fileURLToPath(import.meta.url)), '..', 'dist', 'vite-client.js');
export function liveReload(options = {}) {
    const endpoint = options.endpoint ?? DEFAULT_ENDPOINT;
    const debounceMs = options.debounceMs ?? DEFAULT_DEBOUNCE_MS;
    const pendingTags = new Set();
    let timer = null;
    const flush = (server) => {
        const tags = [...pendingTags];
        pendingTags.clear();
        timer = null;
        if (tags.length === 0)
            return;
        server.ws.send({ type: 'custom', event: EVENT_NAME, data: { tags } });
        server.config.logger.info(`[live-reload] broadcast: ${tags.join(', ')}`);
    };
    return {
        name: 'live-reload',
        apply: 'serve',
        resolveId(id) {
            return id === VIRTUAL_MODULE_ID ? VIRTUAL_MODULE_ID : undefined;
        },
        load(id) {
            return id === VIRTUAL_MODULE_ID ? readFileSync(clientFilePath, 'utf-8') : undefined;
        },
        configureServer(server) {
            server.middlewares.use((request, response, next) => {
                if (!request.url || !request.url.startsWith(endpoint)) {
                    next();
                    return;
                }
                if (request.method !== 'POST') {
                    response.statusCode = 405;
                    response.end();
                    return;
                }
                void (async () => {
                    try {
                        const chunks = [];
                        for await (const chunk of request) {
                            chunks.push(Buffer.from(chunk));
                        }
                        const payload = JSON.parse(Buffer.concat(chunks).toString('utf-8'));
                        if (!Array.isArray(payload.tags) ||
                            payload.tags.some((tag) => typeof tag !== 'string')) {
                            response.statusCode = 400;
                            response.end();
                            return;
                        }
                        for (const tag of payload.tags)
                            pendingTags.add(tag);
                        if (timer)
                            clearTimeout(timer);
                        timer = setTimeout(() => flush(server), debounceMs);
                        response.statusCode = 204;
                        response.end();
                    }
                    catch {
                        response.statusCode = 400;
                        response.end();
                    }
                })();
            });
        },
    };
}
export default liveReload;
