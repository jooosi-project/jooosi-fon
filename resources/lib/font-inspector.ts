import type { ParsedFont } from 'opentype.js';

import type { CustomFontFace, FontFile, FontVariationAxis } from '@/types/fonts';

const MAX_INSPECTION_BYTES = 20 * 1024 * 1024;
const INSPECTION_TIMEOUT_MS = 15_000;
const PARSABLE_EXTENSIONS = new Set(['woff2', 'woff', 'ttf', 'otf']);
const FONT_FORMAT_PRECEDENCE: Record<string, number> = {
    woff2: 1,
    woff: 2,
    ttf: 3,
    otf: 4,
    eot: 5,
};

type FontVariant = Pick<CustomFontFace, 'weight' | 'width' | 'style'> & {
    family: string;
};

export interface InspectedFontFile extends FontVariant {
    file: FontFile;
    axes: FontVariationAxis[];
    metadataDetected: boolean;
}

export type FontInspectionProgress = (
    inspected: InspectedFontFile | null,
    completed: number,
    total: number,
    active: number,
) => void;

const filenameWeightPatterns: Array<{ value: number | string; pattern: RegExp }> = [
    { value: '100 900', pattern: /variablefont|\[[^\]]*(?:wght|opsz)[^\]]*\]|(?:^|[-_.\s])(?:wght|opsz,wght)(?=$|[-_.\s])/i },
    { value: 200, pattern: /(?:^|[-_.\s])(?:200|extra[-_ ]?light|ultra[-_ ]?light)(?=$|[-_.\s]|italic|oblique)/i },
    { value: 800, pattern: /(?:^|[-_.\s])(?:800|extra[-_ ]?bold|ultra[-_ ]?bold)(?=$|[-_.\s]|italic|oblique)/i },
    { value: 600, pattern: /(?:^|[-_.\s])(?:600|semi[-_ ]?bold|demi[-_ ]?bold)(?=$|[-_.\s]|italic|oblique)/i },
    { value: 100, pattern: /(?:^|[-_.\s])(?:100|thin|hairline)(?=$|[-_.\s]|italic|oblique)/i },
    { value: 300, pattern: /(?:^|[-_.\s])(?:300|light)(?=$|[-_.\s]|italic|oblique)/i },
    { value: 400, pattern: /(?:^|[-_.\s])(?:400|normal|regular|book)(?=$|[-_.\s]|italic|oblique)/i },
    { value: 500, pattern: /(?:^|[-_.\s])(?:500|medium)(?=$|[-_.\s]|italic|oblique)/i },
    { value: 700, pattern: /(?:^|[-_.\s])(?:700|bold)(?=$|[-_.\s]|italic|oblique)/i },
    { value: 900, pattern: /(?:^|[-_.\s])(?:900|black|heavy)(?=$|[-_.\s]|italic|oblique)/i },
];

const filenameStylePatterns: Array<{ value: string; pattern: RegExp }> = [
    { value: 'italic', pattern: /italic(?=$|[-_.\s])/i },
    { value: 'oblique', pattern: /oblique(?=$|[-_.\s])/i },
];

function formatNumber(value: number) {
    return Number.isInteger(value) ? String(value) : String(Number(value.toFixed(3)));
}

function toArrayBuffer(value: ArrayBuffer | ArrayBufferView): ArrayBuffer {
    if (value instanceof ArrayBuffer) {
        return value;
    }

    return value.buffer.slice(value.byteOffset, value.byteOffset + value.byteLength) as ArrayBuffer;
}

function cleanFilename(filename: string) {
    return filename
        .replace(/\.(?:woff2?|ttf|otf|eot)$/i, '')
        .replace(/\[[^\]]+\]/g, ' ')
        .replace(/variablefont|wght|wdth|opsz|slnt|italic|ital|oblique|normal|regular|book|hairline|thin|extra[-_ ]?light|ultra[-_ ]?light|light|medium|semi[-_ ]?bold|demi[-_ ]?bold|extra[-_ ]?bold|ultra[-_ ]?bold|bold|black|heavy/ig, ' ')
        .replace(/\b(?:100|200|300|400|500|600|700|800|900)\b/g, ' ')
        .replace(/[-_.]+/g, ' ')
        .replace(/\s+/g, ' ')
        .trim();
}

export function guessFontVariant(file: FontFile): FontVariant {
    const name = file.name.toLowerCase();
    const weight = filenameWeightPatterns.find(({ pattern }) => pattern.test(name))?.value ?? 400;
    const style = filenameStylePatterns.find(({ pattern }) => pattern.test(name))?.value ?? 'normal';

    return {
        family: cleanFilename(file.name),
        weight,
        width: '',
        style,
    };
}

function getEnglishName(font: ParsedFont, key: string) {
    const directName = font.getEnglishName?.(key);
    if (typeof directName === 'string' && directName.trim()) {
        return directName.trim();
    }

    for (const platform of ['unicode', 'windows', 'macintosh']) {
        const translations = font.names[platform]?.[key];
        const value = translations?.en;

        if (typeof value === 'string' && value.trim()) {
            return value.trim();
        }
    }

    return '';
}

function inspectAxes(font: ParsedFont): FontVariationAxis[] {
    return (font.tables.fvar?.axes ?? [])
        .filter((axis) => [axis.minValue, axis.defaultValue, axis.maxValue].every(Number.isFinite))
        .map((axis) => ({
            tag: axis.tag,
            name: typeof axis.name === 'string' && axis.name.trim() ? axis.name.trim() : undefined,
            min: axis.minValue,
            defaultValue: axis.defaultValue,
            max: axis.maxValue,
        }));
}

async function parseFont(buffer: ArrayBuffer) {
    const { parse } = await import('opentype.js');
    return parse(buffer, { lowMemory: true });
}

let woff2DecoderPromise: Promise<(buffer: ArrayBuffer | Uint8Array) => Promise<Uint8Array>> | null = null;

async function loadWoff2Decoder() {
    if (woff2DecoderPromise) {
        return woff2DecoderPromise;
    }

    woff2DecoderPromise = (async () => {
        const decoder = await import('woff2-encoder/decompress');
        await decoder.preload();
        return decoder.default;
    })();

    try {
        return await woff2DecoderPromise;
    } catch (error) {
        woff2DecoderPromise = null;
        throw error;
    }
}

async function decompressFont(buffer: ArrayBuffer) {
    const decompressWoff2 = await loadWoff2Decoder();
    return decompressWoff2(buffer);
}

export async function preloadFontInspector() {
    await loadWoff2Decoder();
}

function variantFromFont(font: ParsedFont, fallback: FontVariant): Omit<FontVariant, 'family'> & { family: string; axes: FontVariationAxis[] } {
    const axes = inspectAxes(font);
    const weightAxis = axes.find((axis) => axis.tag === 'wght');
    const widthAxis = axes.find((axis) => axis.tag === 'wdth');
    const slantAxis = axes.find((axis) => axis.tag === 'slnt');
    const italicAxis = axes.find((axis) => axis.tag === 'ital');
    const subfamily = getEnglishName(font, 'fontSubfamily').toLowerCase();
    const fsSelection = font.tables.os2?.fsSelection ?? 0;
    const italic = /italic/.test(subfamily) || Boolean(fsSelection & 0x01);
    const oblique = /oblique/.test(subfamily);

    let style = italic ? 'italic' : oblique ? 'oblique' : fallback.style;
    if (slantAxis) {
        style = `oblique ${formatNumber(-slantAxis.max)}deg ${formatNumber(-slantAxis.min)}deg`;
    } else if (italicAxis && italicAxis.defaultValue > 0.5) {
        style = 'italic';
    }

    return {
        family: getEnglishName(font, 'fontFamily') || fallback.family,
        weight: weightAxis
            ? `${formatNumber(weightAxis.min)} ${formatNumber(weightAxis.max)}`
            : font.tables.os2?.usWeightClass || fallback.weight,
        width: widthAxis
            ? `${formatNumber(widthAxis.min)}% ${formatNumber(widthAxis.max)}%`
            : fallback.width,
        style,
        axes,
    };
}

export async function inspectFontFile(file: FontFile): Promise<InspectedFontFile> {
    const fallback = guessFontVariant(file);
    const extension = file.extension.toLowerCase();

    if (!PARSABLE_EXTENSIONS.has(extension) || Number(file.filesize ?? file.file_size ?? 0) > MAX_INSPECTION_BYTES) {
        return { ...fallback, file, axes: [], metadataDetected: false };
    }

    const controller = new AbortController();
    const timeout = setTimeout(() => controller.abort(), INSPECTION_TIMEOUT_MS);

    try {
        const response = await fetch(file.attachment_url, { credentials: 'same-origin', signal: controller.signal });
        if (!response.ok) {
            throw new Error(`Unable to read ${file.name}.`);
        }

        const contentLength = Number(response.headers.get('content-length') ?? 0);
        if (contentLength > MAX_INSPECTION_BYTES) {
            return { ...fallback, file, axes: [], metadataDetected: false };
        }

        const source = await response.arrayBuffer();

        let decoded: ArrayBuffer | Uint8Array = source;
        if (extension === 'woff2') {
            decoded = await decompressFont(source);
        }

        const font = await parseFont(toArrayBuffer(decoded));
        const variant = variantFromFont(font, fallback);

        return { ...variant, file, metadataDetected: true };
    } catch (error) {
        return { ...fallback, file, axes: [], metadataDetected: false };
    } finally {
        clearTimeout(timeout);
    }
}

function fallbackInspection(file: FontFile): InspectedFontFile {
    return { ...guessFontVariant(file), file, axes: [], metadataDetected: false };
}

function getInspectorWorkerUrl() {
    return new URL('./font-inspector.worker.ts', import.meta.url);
}

function canUseInspectorWorker() {
    if (typeof Worker === 'undefined') {
        return false;
    }

    if (typeof window === 'undefined') {
        return true;
    }

    if (import.meta.env.PROD) {
        return true;
    }

    const workerUrl = getInspectorWorkerUrl();
    if (workerUrl.origin !== window.location.origin) {
        return false;
    }

    return true;
}

function createInspectorWorker() {
    return new Worker(new URL('./font-inspector.worker.ts', import.meta.url), { type: 'module' });
}

function initializeInspectorWorker(worker: Worker, preloadWoff2: boolean) {
    return new Promise<void>((resolve, reject) => {
        const finish = (error?: Error) => {
            worker.onmessage = null;
            worker.onerror = null;
            if (error) {
                reject(error);
            } else {
                resolve();
            }
        };

        worker.onmessage = (event: MessageEvent<{ type?: string; error?: string }>) => {
            if (event.data.type === 'ready') {
                finish();
                return;
            }

            if (event.data.type === 'init-error') {
                finish(new Error(event.data.error || 'Unable to initialize the font inspector worker.'));
            }
        };
        worker.onerror = (event) => {
            finish(new Error(event.message || 'Unable to initialize the font inspector worker.'));
        };

        worker.postMessage({ type: 'init', preloadWoff2 });
    });
}

interface WorkerInspectionResult {
    result: InspectedFontFile;
    restartWorker: boolean;
}

function inspectOnWorker(worker: Worker, file: FontFile): Promise<WorkerInspectionResult> {
    return new Promise((resolve) => {
        let settled = false;
        const fallback = fallbackInspection(file);
        const finish = (result: InspectedFontFile, restartWorker: boolean) => {
            if (settled) return;
            settled = true;
            clearTimeout(timeout);
            worker.onmessage = null;
            worker.onerror = null;
            resolve({ result, restartWorker });
        };
        const timeout = setTimeout(() => {
            finish(fallback, true);
        }, INSPECTION_TIMEOUT_MS);

        worker.onmessage = (event: MessageEvent<{ id: number; result?: InspectedFontFile }>) => {
            if (event.data.result) {
                finish(event.data.result, false);
            } else {
                finish(fallback, true);
            }
        };
        worker.onerror = (event) => {
            finish(fallback, true);
        };

        try {
            worker.postMessage({ type: 'inspect', id: 1, file });
        } catch {
            finish(fallback, true);
        }
    });
}

function yieldToBrowser() {
    return new Promise<void>((resolve) => {
        if (typeof requestAnimationFrame === 'function') {
            requestAnimationFrame(() => resolve());
            return;
        }

        setTimeout(resolve, 0);
    });
}

async function inspectFontFilesOnMainThread(files: FontFile[], onProgress?: FontInspectionProgress) {
    if (files.length === 0) {
        return [];
    }

    const results = new Array<InspectedFontFile>(files.length);
    let completed = 0;
    let active = 0;

    if (files.some((file) => file.extension.toLowerCase() === 'woff2')) {
        await preloadFontInspector();
    }

    for (const [index, file] of files.entries()) {
        active = 1;
        onProgress?.(null, completed, files.length, active);
        const inspected = await inspectFontFile(file);
        results[index] = inspected;
        active = 0;
        completed += 1;
        onProgress?.(inspected, completed, files.length, active);

        // Give React a paint opportunity between files so progress and partial results are visible.
        if (completed < files.length) {
            await yieldToBrowser();
        }
    }

    return results;
}

async function inspectFontFilesWithWorkers(files: FontFile[], onProgress?: FontInspectionProgress) {
    const results = new Array<InspectedFontFile>(files.length);
    let worker = createInspectorWorker();
    let completed = 0;
    let active = 0;

    try {
        await initializeInspectorWorker(worker, files.some((file) => file.extension.toLowerCase() === 'woff2'));

        for (const [index, file] of files.entries()) {
            active = 1;
            onProgress?.(null, completed, files.length, active);

            const { result, restartWorker } = await inspectOnWorker(worker, file);
            results[index] = result;
            active = 0;
            completed += 1;
            onProgress?.(result, completed, files.length, active);

            if (restartWorker) {
                worker.terminate();
                worker = createInspectorWorker();
            }

            if (completed < files.length) {
                await yieldToBrowser();
            }
        }
    } finally {
        worker.terminate();
    }

    return results;
}

export async function inspectFontFiles(files: FontFile[], onProgress?: FontInspectionProgress) {
    if (files.length === 0) {
        return [];
    }

    if (!canUseInspectorWorker()) {
        return inspectFontFilesOnMainThread(files, onProgress);
    }

    try {
        return await inspectFontFilesWithWorkers(files, onProgress);
    } catch {
        // Keep the feature usable in older browsers or unusual CSP configurations.
        return inspectFontFilesOnMainThread(files, onProgress);
    }
}

export function fontFaceVariantKey(face: Pick<CustomFontFace, 'weight' | 'width' | 'style'>) {
    return [String(face.weight).trim(), String(face.width).trim(), String(face.style).trim().toLowerCase()].join('|');
}

export function fontFileFormatPrecedence(file: Pick<FontFile, 'extension'>) {
    return FONT_FORMAT_PRECEDENCE[file.extension.toLowerCase()] ?? Number.MAX_SAFE_INTEGER;
}
