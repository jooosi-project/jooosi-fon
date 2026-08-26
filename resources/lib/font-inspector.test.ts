import { describe, expect, it } from 'vitest';

import { fontFaceVariantKey, fontFileFormatPrecedence, guessFontVariant, inspectFontFiles } from './font-inspector';

const file = (name: string) => ({
    uid: 'test',
    attachment_id: 1,
    attachment_url: '',
    extension: 'woff2',
    mime: 'font/woff2',
    filesize: 100,
    name,
});

describe('font filename fallback inspection', () => {
    it('recognizes variable-font filename conventions', () => {
        expect(guessFontVariant(file('Inter-VariableFont_wght'))).toMatchObject({
            family: 'Inter',
            weight: '100 900',
            style: 'normal',
        });
    });

    it('recognizes concatenated weight and italic names', () => {
        expect(guessFontVariant(file('Acme-SemiBoldItalic'))).toMatchObject({
            family: 'Acme',
            weight: 600,
            style: 'italic',
        });
    });

    it('creates stable keys for grouping equivalent formats', () => {
        expect(fontFaceVariantKey({ weight: '100 900', width: '', style: 'normal' }))
            .toBe('100 900||normal');
    });

    it('keeps WOFF2 before fallback formats', () => {
        expect(fontFileFormatPrecedence({ extension: 'woff2' })).toBeLessThan(fontFileFormatPrecedence({ extension: 'ttf' }));
    });

    it('reports each completed file for progressive bulk inspection', async () => {
        const progress: number[] = [];
        const activeWorkers: number[] = [];
        const files = [file('Acme-Regular.eot'), file('Acme-Bold.eot')];

        const inspected = await inspectFontFiles(files, (result, completed, _total, active) => {
            activeWorkers.push(active);
            if (result) {
                progress.push(completed);
            }
        });

        expect(inspected).toHaveLength(2);
        expect(progress).toHaveLength(2);
        expect(progress.at(-1)).toBe(2);
        expect(Math.max(...activeWorkers)).toBe(1);
    });
});
