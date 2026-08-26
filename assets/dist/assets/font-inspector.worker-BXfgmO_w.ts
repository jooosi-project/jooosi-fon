import { inspectFontFile, preloadFontInspector } from './font-inspector';
import type { FontFile } from '@/types/fonts';

interface FontInspectionInitRequest {
    type: 'init';
    preloadWoff2: boolean;
}

interface FontInspectionJobRequest {
    type: 'inspect';
    id: number;
    file: FontFile;
}

type FontInspectionRequest = FontInspectionInitRequest | FontInspectionJobRequest;

interface FontInspectionResponse {
    id: number;
    result?: Awaited<ReturnType<typeof inspectFontFile>>;
    error?: string;
}

const workerScope = globalThis as unknown as {
    onmessage: ((event: MessageEvent<FontInspectionRequest>) => void) | null;
    postMessage: (message: FontInspectionResponse | { type: 'ready' | 'init-error'; error?: string }) => void;
};

workerScope.onmessage = async ({ data }) => {
    if (data.type === 'init') {
        try {
            if (data.preloadWoff2) {
                await preloadFontInspector();
            }
            workerScope.postMessage({ type: 'ready' });
        } catch (error) {
            workerScope.postMessage({
                type: 'init-error',
                error: error instanceof Error ? error.message : String(error),
            });
        }
        return;
    }

    try {
        const result = await inspectFontFile(data.file);
        workerScope.postMessage({ id: data.id, result });
    } catch (error) {
        workerScope.postMessage({
            id: data.id,
            error: error instanceof Error ? error.message : 'Unable to inspect this font.',
        });
    }
};
