export interface FontFile {
    uid: string;
    attachment_id: number;
    attachment_url: string;
    extension: string;
    mime: string;
    file_size?: number;
    filesize?: number;
    name: string;
}

export interface FontVariationAxis {
    tag: string;
    name?: string;
    min: number;
    defaultValue: number;
    max: number;
}

export interface CustomFontFace {
    id: string;
    isEnabled?: boolean;
    weight: number | string;
    width: number | string;
    style: string;
    display: string;
    selector: string;
    comment: string;
    unicodeRange: string;
    preload: boolean;
    axes?: FontVariationAxis[];
    files: FontFile[];
}

export interface GoogleFontCatalogItem {
    family: string;
    slug: string;
    category?: string;
    version?: string;
    designers?: string[];
    modifiedAt?: string;
    lastModified?: string;
    popularity?: number;
    subsets: string[];
    variants: string[];
    axes?: Array<{ tag: string; min: number; max: number; defaultValue?: number }>;
    isSupportVariable?: boolean;
    files?: GoogleFontFile[];
}

export interface GoogleFontFile {
    uid: string;
    format: string;
    weight: number;
    style: string;
    subsets: string[];
    url: string;
    unicodeRange?: string;
    file?: FontFile;
}

export interface GoogleFontFace {
    id: string;
    key: string;
    weight: number;
    width: string;
    style: string;
    isEnabled: boolean;
    display: string;
    selector: string;
    comment: string;
    preload: boolean;
    attached_font_files?: string[];
}

export interface FontDetail {
    id: number;
    type: 'custom' | 'google-fonts' | 'adobe-fonts';
    title: string;
    slug: string;
    family: string;
    status: boolean;
    metadata: {
        preload?: boolean;
        selector?: string;
        display?: string;
        google_fonts?: {
            variable: boolean;
            formats: string[];
            font_data: GoogleFontCatalogItem;
            subsets: string[];
            font_files: GoogleFontFile[];
            font_faces: GoogleFontFace[];
        };
    };
    font_faces: CustomFontFace[];
}
