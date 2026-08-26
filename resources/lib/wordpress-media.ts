import { nanoid } from 'nanoid';
import { __ } from '@wordpress/i18n';

import type { FontFile } from '@/types/fonts';

const FONT_UPLOAD_EXTENSIONS = 'woff2,woff,ttf,otf,eot';
const FONT_EXTENSION_ALIASES: Record<string, string> = {
    'font/woff2': 'woff2',
    'font/woff': 'woff',
    'font/ttf': 'ttf',
    'font/otf': 'otf',
    'font/eot': 'eot',
    'application/font-woff2': 'woff2',
    'application/font-woff': 'woff',
    'application/x-font-woff2': 'woff2',
    'application/x-font-woff': 'woff',
    'application/x-font-ttf': 'ttf',
    'application/x-font-truetype': 'ttf',
    'application/x-font-opentype': 'otf',
    'application/vnd.ms-opentype': 'otf',
    'application/vnd.ms-fontobject': 'eot',
    'x-font-woff2': 'woff2',
    'x-font-woff': 'woff',
    'x-font-ttf': 'ttf',
    'x-font-opentype': 'otf',
    'vnd.ms-opentype': 'otf',
    'vnd.ms-fontobject': 'eot',
    truetype: 'ttf',
    opentype: 'otf',
    'embedded-opentype': 'eot',
};

export function normalizeFontExtension(value: unknown, filename = ''): string | null {
    const candidates = [filename.split('?')[0].split('.').pop(), value];

    for (const candidate of candidates) {
        const extension = String(candidate ?? '').trim().toLowerCase();

        if (['woff2', 'woff', 'ttf', 'otf', 'eot'].includes(extension)) {
            return extension;
        }

        const alias = FONT_EXTENSION_ALIASES[extension];
        if (alias) {
            return alias;
        }
    }

    return null;
}

function extensionFromFile(attachment: WpMediaAttachment) {
    const filename = attachment.filename || attachment.url.split('/').pop() || 'font.woff2';
    return normalizeFontExtension(attachment.subtype, filename) || 'woff2';
}

function toFontFile(attachment: WpMediaAttachment): FontFile {
    const extension = extensionFromFile(attachment);
    const filename = attachment.filename || attachment.url.split('/').pop() || `font.${extension}`;
    return {
        uid: nanoid(10),
        attachment_id: Number(attachment.id),
        attachment_url: attachment.url,
        extension,
        mime: attachment.mime || `font/${extension}`,
        filesize: attachment.filesizeInBytes || 0,
        name: filename.replace(new RegExp(`\\.${extension}$`, 'i'), ''),
    };
}

export function normalizeFontFile(file: FontFile): FontFile {
    return {
        ...file,
        attachment_id: Number(file.attachment_id),
        extension: normalizeFontExtension(file.extension, file.name) || file.extension,
    };
}

function configureFontUploader(frame: WpMediaFrame) {
    const uploader = frame.uploader?.uploader;

    uploader?.param?.('jooosi_fon_font_upload', true);

    const plupload = uploader?.uploader;
    const filters = plupload?.getOption?.('filters');

    if (! plupload?.setOption || ! filters) {
        return;
    }

    plupload.setOption('filters', {
        ...filters,
        mime_types: [{
            title: __('Font files', 'jooosi-fon'),
            extensions: FONT_UPLOAD_EXTENSIONS,
        }],
    });
}

export function openWordPressFontMedia(options: {
    title: string;
    multiple?: boolean;
    onSelect: (files: FontFile[]) => void;
}) {
    const media = window.wp?.media;
    if (!media) {
        throw new Error(__('WordPress Media is unavailable on this page.', 'jooosi-fon'));
    }

    const frame = media({
        title: options.title,
        multiple: options.multiple ?? true,
        library: { type: 'font', uploadedTo: null },
    });

    frame.on('uploader:ready', () => configureFontUploader(frame));
    frame.on('open', () => configureFontUploader(frame));
    frame.on('select', () => {
        const selection = frame.state().get('selection');
        options.onSelect(selection.map((model) => toFontFile(model.toJSON())));
    });
    frame.open();
    configureFontUploader(frame);
}
