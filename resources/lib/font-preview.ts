export interface FontFile {
    attachment_url: string;
    extension: string;
}

export interface FontFace {
    comment?: string;
    isEnabled?: boolean;
    style: string;
    weight: number | string;
    width?: number | string;
    display?: string;
    unicodeRange?: string;
    files: FontFile[];
}

function fontFormat(extension: string) {
    switch (extension) {
        case 'woff':
        case 'font/woff':
            return 'woff';
        case 'ttf':
        case 'font/ttf':
            return 'truetype';
        case 'otf':
        case 'font/otf':
            return 'opentype';
        case 'eot':
        case 'font/eot':
            return 'embedded-opentype';
        default:
            return 'woff2';
    }
}

function escapeCssString(value: string) {
    return value
        .replaceAll('\\', '\\\\')
        .replaceAll("'", "\\'")
        .replace(/[\n\r\f]/g, (character) => `\\${character.codePointAt(0)?.toString(16)} `);
}

function safeFontStyle(value: string) {
    const normalized = value.trim().toLowerCase();
    return /^(normal|italic|oblique(?:\s+-?\d+(?:\.\d+)?deg(?:\s+-?\d+(?:\.\d+)?deg)?)?)$/.test(normalized)
        ? normalized
        : 'normal';
}

function safeFontWeight(value: number | string) {
    const normalized = String(value).trim().toLowerCase();
    if (normalized === 'normal' || normalized === 'bold') {
        return normalized;
    }

    const weights = normalized.split(/\s+/).map(Number);
    return weights.length > 0
        && weights.length <= 2
        && weights.every((weight) => Number.isFinite(weight) && weight >= 1 && weight <= 1000)
        ? normalized
        : null;
}

function safeFontStretch(value: number | string | undefined) {
    const normalized = String(value ?? '').trim().toLowerCase();
    if (!normalized) return null;
    if (/^(normal|ultra-condensed|extra-condensed|condensed|semi-condensed|semi-expanded|expanded|extra-expanded|ultra-expanded)$/.test(normalized)) return normalized;
    const tokens = normalized.split(/\s+/);
    const widths = tokens.map((width) => Number(width.replace('%', '')));
    return widths.length > 0 && widths.length <= 2 && tokens.every((width) => /^\d+(?:\.\d+)?%$/.test(width)) && widths.every((width) => Number.isFinite(width) && width > 0 && width <= 1000)
        ? normalized
        : null;
}

function safeFontDisplay(value: string | undefined) {
    const normalized = value?.trim().toLowerCase();
    return normalized && ['auto', 'block', 'swap', 'fallback', 'optional'].includes(normalized)
        ? normalized
        : 'auto';
}

function safeUnicodeRange(value: string | undefined) {
    const token = 'U\\+[0-9A-F?]{1,6}(?:-[0-9A-F]{1,6})?';
    const pattern = new RegExp(`^${token}(?:\\s*,\\s*${token})*$`, 'i');
    return value && pattern.test(value.trim()) ? value.trim() : null;
}

export function createFontFaceCss(
    family: string,
    fontFaces: FontFace[],
    fallbackDisplay = 'auto',
) {
    if (!family.trim()) {
        return '';
    }

    return fontFaces.filter((fontFace) => fontFace.isEnabled !== false).map((fontFace) => {
        const declarations = [
            `font-family: '${escapeCssString(family)}';`,
            `font-style: ${safeFontStyle(fontFace.style)};`,
            `font-display: ${safeFontDisplay(fontFace.display || fallbackDisplay)};`,
        ];

        const weight = safeFontWeight(fontFace.weight);
        if (weight) {
            declarations.push(`font-weight: ${weight};`);
        }

        const stretch = safeFontStretch(fontFace.width);
        if (stretch) {
            declarations.push(`font-stretch: ${stretch};`);
        }

        if (fontFace.files.length > 0) {
            const sources = fontFace.files.map((file) => (
                `url('${escapeCssString(file.attachment_url)}') format('${fontFormat(file.extension)}')`
            ));
            declarations.push(`src: ${sources.join(', ')};`);
        }

        const unicodeRange = safeUnicodeRange(fontFace.unicodeRange);
        if (unicodeRange) {
            declarations.push(`unicode-range: ${unicodeRange};`);
        }

        return `@font-face {\n  ${declarations.join('\n  ')}\n}`;
    }).join('\n\n');
}

export function fontCssVariable(family: string) {
    return `--jf--family-${family.replace(/[^a-zA-Z0-9\-_]+/g, '-').toLowerCase()}`;
}
