import type { Plugin } from 'vite';
export declare const VIRTUAL_MODULE_ID = "virtual:live-reload";
export interface LiveReloadWatchOptions {
    paths: string[];
    extensions?: string[];
    projectRoot?: string;
}
export interface LiveReloadOptions {
    endpoint?: string;
    debounceMs?: number;
    watch?: LiveReloadWatchOptions;
}
export declare function liveReload(options?: LiveReloadOptions): Plugin;
export default liveReload;
