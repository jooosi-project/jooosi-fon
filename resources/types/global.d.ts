interface JooosiFonConfig {
    _version: string;
    _wpnonce: string;
    option_namespace: string;
    text_domain: string;
    web_history: string;
    rest_api: {
        nonce: string;
        root: string;
        namespace: string;
        url: string;
    };
    assets: {
        url: string;
    };
    lite_edition: boolean;
}

interface Window {
    jooosiFon: JooosiFonConfig;
    wp?: {
        media?: WpMediaFactory;
    };
}

interface WpMediaAttachment {
    id: number | string;
    url: string;
    filename?: string;
    subtype?: string;
    mime?: string;
    filesizeInBytes?: number;
}

interface WpMediaSelection {
    map: <T>(callback: (model: { toJSON: () => WpMediaAttachment }) => T) => T[];
}

interface WpMediaFrame {
    on: (event: 'open' | 'select' | 'uploader:ready', callback: () => void) => void;
    open: () => void;
    state: () => { get: (key: 'selection') => WpMediaSelection };
    uploader?: { uploader?: WpUploader };
}

interface WpUploader {
    param?: (name: string, value: unknown) => void;
    uploader?: {
        getOption?: (name: 'filters') => Record<string, unknown> | undefined;
        setOption?: (name: 'filters', value: Record<string, unknown>) => void;
    };
}

interface WpMediaFactory {
    (options: {
        title: string;
        multiple: boolean;
        library: { type: string; uploadedTo: null };
    }): WpMediaFrame;
}

declare module '*.svg?url' {
    const src: string;
    export default src;
}

declare module 'opentype.js' {
    export interface ParsedFont {
        names: Record<string, Record<string, Record<string, string>> | undefined>;
        tables: {
            fvar?: {
                axes: Array<{
                    tag: string;
                    name?: string;
                    minValue: number;
                    defaultValue: number;
                    maxValue: number;
                }>;
            };
            os2?: {
                usWeightClass?: number;
                fsSelection?: number;
            };
            post?: {
                italicAngle?: number;
            };
        };
        getEnglishName?: (name: string) => string | undefined;
    }

    export function parse(buffer: ArrayBuffer, options?: { lowMemory?: boolean }): ParsedFont;
}
