import type { Plugin } from 'vite';
export declare const VIRTUAL_MODULE_ID = "virtual:live-reload";
export interface LiveReloadOptions {
    endpoint?: string;
    debounceMs?: number;
}
export declare function liveReload(options?: LiveReloadOptions): Plugin;
export default liveReload;
