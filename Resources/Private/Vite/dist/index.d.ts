import type { Plugin } from 'vite';
export declare const VIRTUAL_MODULE_ID = "virtual:content-live-reload";
export interface ContentLiveReloadOptions {
    endpoint?: string;
    debounceMs?: number;
}
export declare function contentLiveReload(options?: ContentLiveReloadOptions): Plugin;
export default contentLiveReload;
