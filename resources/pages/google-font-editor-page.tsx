import { __ } from '@wordpress/i18n';
import Fuse from 'fuse.js';
import { nanoid } from 'nanoid';
import { useCallback, useEffect, useMemo, useRef, useState, type FormEvent } from 'react';
import { useNavigate, useParams } from 'react-router-dom';
import { toast } from 'sonner';
import {
    CheckIcon,
    ChevronDownIcon,
    ChevronsUpDownIcon,
    RefreshCwIcon,
    SaveIcon,
    SearchIcon,
    Trash2Icon,
} from 'lucide-react';

import { FontEditorHeader, FontEditorSkeleton, PreviewControl } from '@/components/font-editor-layout';
import { Field, NativeSelect, TextField } from '@/components/form-controls';
import { GoogleFontsIcon } from '@/components/google-fonts-icon';
import {
    Frame,
    FrameDescription,
    FrameFooter,
    FrameHeader,
    FramePanel,
    FrameTitle,
} from '@/components/reui/frame';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Popover, PopoverContent, PopoverTrigger } from '@/components/ui/popover';
import { Separator } from '@/components/ui/separator';
import { Spinner } from '@/components/ui/spinner';
import { Switch } from '@/components/ui/switch';
import { useUnsavedChanges } from '@/hooks/use-unsaved-changes';
import { api, getErrorMessage } from '@/lib/api';
import { cn } from '@/lib/utils';
import type { FontDetail, GoogleFontCatalogItem, GoogleFontFace, GoogleFontFile } from '@/types/fonts';

interface GoogleEditorState {
    title: string;
    status: boolean;
    preload: boolean;
    selector: string;
    display: string;
    variable: boolean;
    formats: string[];
    fontData: GoogleFontCatalogItem | null;
    subsets: string[];
    fontFiles: GoogleFontFile[];
    fontFaces: GoogleFontFace[];
}

const CATALOG_PAGE_SIZE = 50;

const blankState: GoogleEditorState = {
    title: '', status: true, preload: false, selector: '', display: 'swap',
    variable: false, formats: ['woff2'], fontData: null, subsets: [], fontFiles: [], fontFaces: [],
};

function normalizeCatalogItem(font: GoogleFontCatalogItem): GoogleFontCatalogItem {
    return {
        ...font,
        subsets: (font.subsets || []).filter((subset) => subset !== 'menu'),
        variants: font.variants || [],
        designers: font.designers || [],
    };
}

function formatCatalogDate(value?: string) {
    if (!value) return '—';

    const date = new Date(/^\d{4}-\d{2}-\d{2}$/.test(value) ? `${value}T00:00:00` : value);
    if (Number.isNaN(date.getTime())) return value;

    return new Intl.DateTimeFormat(undefined, {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
    }).format(date);
}

function uniqueFontFiles(files: GoogleFontFile[]) {
    return files.filter((file, index, all) => all.findIndex((candidate) => (
        candidate.weight === file.weight
        && candidate.style === file.style
        && candidate.format === file.format
        && JSON.stringify([...candidate.subsets].sort()) === JSON.stringify([...file.subsets].sort())
    )) === index);
}

function buildFaces(font: GoogleFontCatalogItem, files: GoogleFontFile[], variable: boolean): GoogleFontFace[] {
    if (variable) {
        const widthAxis = font.axes?.find((axis) => axis.tag === 'wdth');
        return ['normal', 'italic'].filter((style) => files.some((file) => file.weight === 0 && file.style === style)).map((style) => ({
            id: nanoid(10), key: style === 'normal' ? '0' : '0i', weight: 0,
            width: widthAxis ? `${widthAxis.min}% ${widthAxis.max}%` : '', style,
            isEnabled: true, display: '', selector: '', comment: '', preload: false,
        }));
    }
    return font.variants.map((variant) => {
        const numericWeight = Number.parseInt(variant, 10);
        const suffix = variant.replace('regular', '').replace(/[0-9]/g, '').trim();
        const weight = Number.isNaN(numericWeight) ? 400 : numericWeight;
        return {
            id: nanoid(10), key: variant, weight, width: '',
            style: suffix === 'i' ? 'italic' : suffix === 'o' ? 'oblique' : 'normal',
            isEnabled: weight === 400, display: '', selector: '', comment: '', preload: false,
        };
    });
}

function payloadFromState(state: GoogleEditorState) {
    return {
        title: state.title.trim(),
        status: state.status,
        metadata: {
            preload: state.preload,
            selector: state.selector.trim(),
            display: state.display,
            google_fonts: {
                variable: state.variable,
                formats: state.formats,
                font_data: state.fontData,
                subsets: state.subsets,
                font_files: uniqueFontFiles(state.fontFiles),
                font_faces: state.fontFaces,
            },
        },
    };
}

function remotePreviewCss(state: GoogleEditorState) {
    if (!state.fontData) return '';
    return state.fontFaces.map((face) => {
        const file = state.fontFiles.find((candidate) => candidate.weight === face.weight
            && candidate.style === face.style);
        const url = file?.file?.attachment_url || file?.url;
        if (!url) return '';
        const weightAxis = state.fontData?.axes?.find((axis) => axis.tag === 'wght');
        const weight = face.weight || (weightAxis ? `${weightAxis.min} ${weightAxis.max}` : 400);
        const stretch = face.width ? `font-stretch:${face.width};` : '';
        return `@font-face{font-family:'JooosiFonGooglePreview';font-style:${face.style};font-weight:${weight};${stretch}font-display:${face.display || state.display};src:url('${url.replaceAll("'", "\\'")}') format('${file.format}')}`;
    }).join('\n');
}

export function GoogleFontEditorPage() {
    const { id } = useParams();
    const navigate = useNavigate();
    const editing = Boolean(id);
    const [state, setState] = useState<GoogleEditorState>(blankState);
    const [snapshot, setSnapshot] = useState(() => JSON.stringify(payloadFromState(blankState)));
    const [catalog, setCatalog] = useState<GoogleFontCatalogItem[]>([]);
    const [catalogSearch, setCatalogSearch] = useState('');
    const [catalogPage, setCatalogPage] = useState(0);
    const [loading, setLoading] = useState(true);
    const [catalogBusy, setCatalogBusy] = useState(false);
    const [filesBusy, setFilesBusy] = useState(false);
    const [saving, setSaving] = useState(false);
    const [deleting, setDeleting] = useState(false);
    const [error, setError] = useState<string | null>(null);
    const [previewText, setPreviewText] = useState(__('Pack my box with five dozen liquor jugs.', 'jooosi-fon'));
    const [previewSize, setPreviewSize] = useState(18);
    const [previewWeight, setPreviewWeight] = useState(400);
    const [previewWidth, setPreviewWidth] = useState(100);
    const [catalogOpen, setCatalogOpen] = useState(false);
    const [expandedFaces, setExpandedFaces] = useState<string[]>([]);
    const fontFilesRequest = useRef(0);

    const currentPayload = useMemo(() => payloadFromState(state), [state]);
    const allowNavigation = useUnsavedChanges(!loading && JSON.stringify(currentPayload) !== snapshot);

    const fetchCatalog = useCallback(async (force = false) => {
        setCatalogBusy(true);
        try {
            const { data } = await api.get<{ fonts: GoogleFontCatalogItem[] }>('/fonts/google-fonts/metadata', { params: force ? { force: 1 } : {} });
            const nextCatalog = data.fonts.map(normalizeCatalogItem);
            setCatalog(nextCatalog);
            setCatalogPage(0);
            setState((current) => {
                if (!current.fontData) return current;
                const refreshedFont = nextCatalog.find((font) => font.slug === current.fontData?.slug);
                return refreshedFont ? { ...current, fontData: { ...current.fontData, ...refreshedFont } } : current;
            });
            if (force) toast.success(__('Google Fonts catalog refreshed.', 'jooosi-fon'));
        } catch (requestError) {
            setError(getErrorMessage(requestError));
        } finally {
            setCatalogBusy(false);
        }
    }, []);

    useEffect(() => {
        let cancelled = false;
        Promise.all([
            api.get<{ fonts: GoogleFontCatalogItem[] }>('/fonts/google-fonts/metadata'),
            editing && id ? api.get<FontDetail>(`/fonts/detail/${id}`) : Promise.resolve(null),
        ]).then(([catalogResponse, detailResponse]) => {
            if (cancelled) return;
            const nextCatalog = catalogResponse.data.fonts.map(normalizeCatalogItem);
            setCatalog(nextCatalog);
            if (detailResponse) {
                const detail = detailResponse.data;
                const google = detail.metadata.google_fonts;
                if (!google) throw new Error(__('Google Fonts metadata is missing.', 'jooosi-fon'));
                const catalogFont = nextCatalog.find((font) => font.slug === google.font_data.slug);
                const fontData = normalizeCatalogItem({ ...google.font_data, ...catalogFont });
                const subsets = google.subsets.length > 0
                    ? google.subsets
                    : fontData.subsets.includes('latin') ? ['latin'] : fontData.subsets.slice(0, 1);
                const next: GoogleEditorState = {
                    title: detail.title, status: detail.status,
                    preload: Boolean(detail.metadata.preload), selector: detail.metadata.selector || '', display: detail.metadata.display || 'swap',
                    variable: google.variable, formats: google.formats.length > 0 ? google.formats : ['woff2'], fontData,
                    subsets, fontFiles: google.font_files, fontFaces: google.font_faces,
                };
                setState(next);
                setSnapshot(JSON.stringify(payloadFromState(next)));
            }
        }).catch((requestError) => {
            if (!cancelled) setError(getErrorMessage(requestError));
        }).finally(() => {
            if (!cancelled) setLoading(false);
        });
        return () => {
            cancelled = true;
            fontFilesRequest.current += 1;
        };
    }, [editing, id]);

    const catalogFuse = useMemo(() => new Fuse(catalog, {
        threshold: 0.3,
        ignoreLocation: true,
        keys: [
            { name: 'family', weight: 0.8 },
            { name: 'category', weight: 0.15 },
            { name: 'designers', weight: 0.05 },
        ],
    }), [catalog]);

    const filteredCatalog = useMemo(() => {
        const query = catalogSearch.trim();
        return query ? catalogFuse.search(query).map(({ item }) => item) : catalog;
    }, [catalog, catalogFuse, catalogSearch]);

    const catalogPageCount = Math.max(1, Math.ceil(filteredCatalog.length / CATALOG_PAGE_SIZE));
    const currentCatalogPage = Math.min(catalogPage, catalogPageCount - 1);
    const visibleCatalog = filteredCatalog.slice(
        currentCatalogPage * CATALOG_PAGE_SIZE,
        (currentCatalogPage + 1) * CATALOG_PAGE_SIZE,
    );

    const loadFontFiles = useCallback(async (font: GoogleFontCatalogItem, subsets: string[]) => {
        const requestId = ++fontFilesRequest.current;

        if (subsets.length === 0) {
            setFilesBusy(false);
            setState((current) => current.fontData?.slug === font.slug
                ? { ...current, fontFiles: [], fontFaces: [] }
                : current);
            return;
        }

        setFilesBusy(true);
        try {
            const { data } = await api.get<{ font: GoogleFontCatalogItem; files: Omit<GoogleFontFile, 'uid'>[] }>(`/fonts/google-fonts/webfonts/${font.slug}`, { params: { subsets: subsets.join(',') } });
            if (requestId !== fontFilesRequest.current) return;

            const responseFont = normalizeCatalogItem(data.font);
            const files = Object.values(data.files).map((file) => ({ ...file, uid: nanoid(10) }));
            setState((current) => {
                if (current.fontData?.slug !== font.slug) return current;
                const nextFont = { ...font, ...responseFont };
                return {
                    ...current,
                    fontData: nextFont,
                    fontFiles: files,
                    fontFaces: buildFaces(nextFont, files, current.variable),
                };
            });
        } catch (requestError) {
            if (requestId === fontFilesRequest.current) toast.error(getErrorMessage(requestError));
        } finally {
            if (requestId === fontFilesRequest.current) setFilesBusy(false);
        }
    }, []);

    const selectFamily = (slug: string) => {
        const font = catalog.find((candidate) => candidate.slug === slug) || null;
        if (!font) return;
        const subsets = font.subsets.includes('latin') ? ['latin'] : font.subsets.slice(0, 1);
        setState((current) => ({
            ...current,
            title: current.title.trim() === '' || current.title === current.fontData?.family ? font.family : current.title,
            fontData: font,
            subsets,
            fontFiles: [],
            fontFaces: [],
            variable: false,
        }));
        setExpandedFaces([]);
        setCatalogOpen(false);
        setCatalogSearch('');
        setCatalogPage(0);
        void loadFontFiles(font, subsets);
    };

    const toggleFace = (faceId: string) => {
        setExpandedFaces((current) => current.includes(faceId)
            ? current.filter((item) => item !== faceId)
            : [...current, faceId]);
    };

    const updateFace = (faceId: string, changes: Partial<GoogleFontFace>) => setState((current) => ({ ...current, fontFaces: current.fontFaces.map((face) => face.id === faceId ? { ...face, ...changes } : face) }));
    const toggleVariable = (checked: boolean) => {
        if (checked) {
            const weightAxis = state.fontData?.axes?.find((axis) => axis.tag === 'wght');
            const widthAxis = state.fontData?.axes?.find((axis) => axis.tag === 'wdth');
            if (weightAxis) setPreviewWeight(weightAxis.defaultValue ?? Math.min(Math.max(400, weightAxis.min), weightAxis.max));
            if (widthAxis) setPreviewWidth(widthAxis.defaultValue ?? Math.min(Math.max(100, widthAxis.min), widthAxis.max));
        }
        setState((current) => ({
            ...current,
            variable: checked,
            fontFaces: current.fontData ? buildFaces(current.fontData, current.fontFiles, checked) : [],
        }));
    };
    const toggleSubset = (subset: string, checked: boolean) => {
        if (!checked && state.subsets.length === 1 && state.subsets.includes(subset)) return;
        const subsets = checked
            ? [...new Set([...state.subsets, subset])]
            : state.subsets.filter((item) => item !== subset);
        setState((current) => ({ ...current, subsets, fontFiles: [], fontFaces: [] }));
        if (state.fontData) void loadFontFiles(state.fontData, subsets);
    };
    const toggleFormat = (format: string, checked: boolean) => setState((current) => {
        if (!checked && current.formats.length === 1 && current.formats.includes(format)) return current;
        return { ...current, formats: checked ? [...new Set([...current.formats, format])] : current.formats.filter((item) => item !== format) };
    });

    const submit = async (event: FormEvent) => {
        event.preventDefault();
        if (!state.fontData) { toast.error(__('Choose a Google Fonts family.', 'jooosi-fon')); return; }
        if (filesBusy) { toast.error(__('Wait for the font files to finish loading.', 'jooosi-fon')); return; }
        if (state.subsets.length === 0 || state.formats.length === 0 || state.fontFaces.every((face) => !face.isEnabled)) { toast.error(__('Select at least one subset, format, and variant.', 'jooosi-fon')); return; }
        if (saving) return;
        setSaving(true);
        try {
            const endpoint = editing ? `/fonts/google-fonts/update/${id}` : '/fonts/google-fonts/store';
            const { data } = await api.post<{ id: number }>(endpoint, currentPayload);
            setSnapshot(JSON.stringify(currentPayload));
            toast.success(editing ? __('Google Font updated.', 'jooosi-fon') : __('Google Font imported and hosted locally.', 'jooosi-fon'));
            if (!editing) { allowNavigation(); navigate(`/fonts/edit/${data.id}/google-fonts`, { replace: true }); }
        } catch (requestError) {
            toast.error(getErrorMessage(requestError));
        } finally {
            setSaving(false);
        }
    };

    const removeFont = async () => {
        if (!id || deleting || !window.confirm(__('Move this font to the trash?', 'jooosi-fon'))) return;
        setDeleting(true);
        try {
            await api.post(`/fonts/delete/${id}`);
            setSnapshot(JSON.stringify(currentPayload));
            allowNavigation();
            navigate('/fonts/index', { replace: true });
            toast.success(__('Font moved to trash.', 'jooosi-fon'));
        } catch (requestError) { toast.error(getErrorMessage(requestError)); } finally { setDeleting(false); }
    };

    if (loading) return <FontEditorSkeleton />;
    if (error && catalog.length === 0) return <Frame><FramePanel role="status" aria-live="polite"><p className="text-sm text-destructive">{error}</p></FramePanel></Frame>;

    const previewCss = remotePreviewCss(state);
    const weightAxis = state.variable ? state.fontData?.axes?.find((axis) => axis.tag === 'wght') : undefined;
    const widthAxis = state.variable ? state.fontData?.axes?.find((axis) => axis.tag === 'wdth') : undefined;

    return (
        <form className="grid gap-5" onSubmit={submit}>
            <FontEditorHeader
                source={__('Google Fonts · hosted locally', 'jooosi-fon')}
                title={editing ? state.title || __('Manage Google Font', 'jooosi-fon') : __('Import a Google Font', 'jooosi-fon')}
                description={__('Select only what the site needs; Jooosi Fon downloads and serves the files from WordPress.', 'jooosi-fon')}
                icon={<GoogleFontsIcon />}
            />

            {error && <div role="status" aria-live="polite" className="rounded-lg border border-destructive/20 bg-destructive/10 px-4 py-3 text-sm text-destructive">{error}</div>}

            <Frame spacing="sm">
                <FrameHeader className="flex-row flex-wrap items-center justify-between gap-3"><div><FrameTitle>{__('Font details', 'jooosi-fon')}</FrameTitle><FrameDescription>{__('Choose a catalog family and define its default CSS behavior.', 'jooosi-fon')}</FrameDescription></div><Button type="button" size="sm" variant="ghost" onClick={() => fetchCatalog(true)} disabled={catalogBusy}>{catalogBusy ? <Spinner /> : <RefreshCwIcon />}{__('Refresh catalog', 'jooosi-fon')}</Button></FrameHeader>
                <FramePanel className="grid gap-4 md:grid-cols-2 xl:grid-cols-[1.1fr_1.1fr_.9fr_1.25fr_.8fr]">
                    <Field label={__('Name', 'jooosi-fon')} hint={__('Shown in the font library.', 'jooosi-fon')}><TextField required value={state.title} onChange={(event) => setState({ ...state, title: event.target.value })} /></Field>
                    <Field label={__('Font Family', 'jooosi-fon')} hint={state.fontData ? `${state.fontData.category || __('Font', 'jooosi-fon')} · ${state.fontData.variants.length} ${__('variants', 'jooosi-fon')}` : __('Search the local catalog.', 'jooosi-fon')}>
                        <Popover open={catalogOpen} onOpenChange={setCatalogOpen}>
                            <PopoverTrigger render={<Button type="button" variant="outline" className="w-full justify-between font-normal" />}>
                                <span className="truncate">{state.fontData?.family || __('Choose a family…', 'jooosi-fon')}</span><ChevronsUpDownIcon aria-hidden="true" className="text-muted-foreground" />
                            </PopoverTrigger>
                            <PopoverContent align="start" className="flex h-[min(28rem,calc(100vh-2rem))] min-h-0 w-[min(32rem,calc(100vw-2rem))] flex-col gap-0 p-0">
                                <div className="relative border-b p-2"><SearchIcon aria-hidden="true" className="absolute left-5 top-1/2 size-4 -translate-y-1/2 text-muted-foreground" /><Input autoFocus aria-label={__('Search Google Fonts families', 'jooosi-fon')} className="pl-9" value={catalogSearch} onChange={(event) => { setCatalogSearch(event.target.value); setCatalogPage(0); }} placeholder={__('Search family, category, or designer…', 'jooosi-fon')} /></div>
                                <div className="min-h-0 flex-1 overflow-y-auto p-1" role="listbox" aria-label={__('Google Fonts families', 'jooosi-fon')}>
                                    {visibleCatalog.map((font) => {
                                        const selected = state.fontData?.slug === font.slug;
                                        return <button key={font.slug} type="button" role="option" aria-selected={selected} onClick={() => selectFamily(font.slug)} className={cn('flex w-full items-center gap-3 rounded-md px-3 py-2.5 text-left hover:bg-muted focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring', selected && 'bg-primary/[0.07]')}><span className={cn('grid size-8 shrink-0 place-items-center rounded-md border font-medium', selected ? 'border-primary bg-primary text-primary-foreground' : 'bg-background')}>{font.family.charAt(0)}</span><span className="min-w-0 flex-1"><strong className="block truncate text-sm font-medium">{font.family}</strong><span className="block text-xs text-muted-foreground">{font.category || __('Google Fonts', 'jooosi-fon')} · {font.variants.length} {__('variants', 'jooosi-fon')}</span></span>{selected && <CheckIcon aria-hidden="true" className="size-4 text-primary" />}</button>;
                                    })}
                                    {filteredCatalog.length === 0 && <p className="m-0 p-8 text-center text-sm text-muted-foreground">{__('No matching families.', 'jooosi-fon')}</p>}
                                </div>
                                {filteredCatalog.length > 0 && <div className="flex items-center justify-between gap-2 border-t p-2"><p className="m-0 text-xs tabular-nums text-muted-foreground">{currentCatalogPage * CATALOG_PAGE_SIZE + 1}–{Math.min((currentCatalogPage + 1) * CATALOG_PAGE_SIZE, filteredCatalog.length)} {__('of', 'jooosi-fon')} {filteredCatalog.length}</p><div className="flex gap-1"><Button type="button" size="sm" variant="ghost" disabled={currentCatalogPage === 0} onClick={() => setCatalogPage(currentCatalogPage - 1)}>{__('Previous', 'jooosi-fon')}</Button><Button type="button" size="sm" variant="ghost" disabled={currentCatalogPage >= catalogPageCount - 1} onClick={() => setCatalogPage(currentCatalogPage + 1)}>{__('Next', 'jooosi-fon')}</Button></div></div>}
                            </PopoverContent>
                        </Popover>
                    </Field>
                    <Field label={__('Font display', 'jooosi-fon')} hint={__('Text appearance while loading.', 'jooosi-fon')}><NativeSelect value={state.display} onChange={(event) => setState({ ...state, display: event.target.value })}>{['auto', 'block', 'swap', 'fallback', 'optional'].map((display) => <option key={display}>{display}</option>)}</NativeSelect></Field>
                    <Field label={__('CSS selector / fallback', 'jooosi-fon')} hint={__('Optional automatic assignment.', 'jooosi-fon')}><TextField value={state.selector} onChange={(event) => setState({ ...state, selector: event.target.value })} placeholder="body | Arial, sans-serif" /></Field>
                    <div className="grid gap-1.5 text-sm font-medium"><span id="preload-family-label">{__('Preload family', 'jooosi-fon')}</span><div className="flex h-8 items-center justify-between gap-3 rounded-lg border bg-background px-2.5"><span className="text-xs font-normal text-muted-foreground">{state.preload ? __('Enabled', 'jooosi-fon') : __('Disabled', 'jooosi-fon')}</span><Switch size="sm" checked={state.preload} onCheckedChange={(checked) => setState({ ...state, preload: checked })} aria-labelledby="preload-family-label" aria-describedby="preload-family-help" /></div><span id="preload-family-help" className="text-xs font-normal text-muted-foreground">{__('Request selected files early.', 'jooosi-fon')}</span></div>
                </FramePanel>
            </Frame>

            <div className="grid gap-5 xl:grid-cols-[minmax(0,1fr)_320px] xl:items-start">
                <div className="grid min-w-0 gap-5">
                    <style>{previewCss}</style>

                    <Frame className="min-w-0" spacing="sm" dense>
                        <FrameHeader className="gap-3 px-4 py-3">
                            <div><FrameTitle>{__('Variants', 'jooosi-fon')}</FrameTitle><FrameDescription>{__('Preview and configure each variant.', 'jooosi-fon')}</FrameDescription></div>
                        </FrameHeader>
                        <FramePanel className="p-0">
                            {state.fontFaces.length === 0 && <div className="grid min-h-40 place-items-center px-4 text-center" role="status" aria-live="polite"><div>{filesBusy ? <Spinner className="mx-auto mb-3 size-7 text-primary" /> : <SearchIcon aria-hidden="true" className="mx-auto mb-3 size-7 text-primary" />}<p className="m-0 text-sm font-medium">{filesBusy ? __('Loading font variants…', 'jooosi-fon') : state.fontData ? __('No variants match the selected subsets.', 'jooosi-fon') : __('Select a font family to preview its variants.', 'jooosi-fon')}</p><p className="m-0 text-xs text-muted-foreground">{filesBusy ? __('The editor will update automatically.', 'jooosi-fon') : state.fontData ? __('Try another subset or refresh the catalog.', 'jooosi-fon') : __('Use the Font Family field above to get started.', 'jooosi-fon')}</p></div></div>}
                            {state.fontFaces.map((face) => {
                                const expanded = expandedFaces.includes(face.id);
                                return (
                                    <section key={face.id} className={cn('border-b last:border-b-0', !face.isEnabled && 'bg-muted/20')}>
                                        <div className="grid gap-3 px-3 py-3 md:grid-cols-[44px_112px_minmax(12rem,1fr)_auto] md:items-center">
                                            <Switch checked={face.isEnabled} onCheckedChange={(checked) => updateFace(face.id, { isEnabled: checked })} aria-label={`${__('Include', 'jooosi-fon')} ${face.key}`} />
                                            <div><p className="m-0 text-sm font-semibold">{face.weight === 0 ? __('Variable', 'jooosi-fon') : face.weight}</p><p className="m-0 text-xs capitalize text-muted-foreground">{face.style}</p></div>
                                            <div className={cn('min-w-0 rounded-lg border bg-card px-3 py-2 transition-[background-color,color,opacity]', !face.isEnabled && 'bg-muted/50 text-muted-foreground opacity-50')}><Input value={previewText} onChange={(event) => setPreviewText(event.target.value)} aria-label={`${__('Preview text for variant', 'jooosi-fon')} ${face.key}`} placeholder={__('Type preview text…', 'jooosi-fon')} className={cn('h-auto min-w-0 border-0 bg-transparent p-0 shadow-none focus-visible:border-transparent focus-visible:ring-0', !face.isEnabled && 'text-muted-foreground')} style={{ fontFamily: state.fontData ? "'JooosiFonGooglePreview', sans-serif" : 'inherit', fontSize: `${previewSize}px`, fontWeight: face.weight === 0 ? previewWeight : face.weight, fontStyle: face.style, fontStretch: face.width ? `${previewWidth}%` : '100%' }} /></div>
                                            <div className="flex justify-end"><Button type="button" variant="ghost" size="icon" aria-expanded={expanded} aria-label={expanded ? __('Close variant settings', 'jooosi-fon') : __('Open variant settings', 'jooosi-fon')} onClick={() => toggleFace(face.id)}><ChevronDownIcon className={expanded ? 'rotate-180 transition-transform' : 'transition-transform'} /></Button></div>
                                        </div>
                                        {expanded && <div className="grid gap-4 border-t bg-muted/20 p-4 sm:grid-cols-2 lg:grid-cols-4"><Field label={__('Display override', 'jooosi-fon')}><NativeSelect value={face.display} onChange={(event) => updateFace(face.id, { display: event.target.value })}><option value="">{__('Use family default', 'jooosi-fon')}</option>{['auto', 'block', 'swap', 'fallback', 'optional'].map((display) => <option key={display}>{display}</option>)}</NativeSelect></Field><Field label={__('Variant selector', 'jooosi-fon')}><TextField value={face.selector} onChange={(event) => updateFace(face.id, { selector: event.target.value })} placeholder=".font-bold" /></Field><Field label={__('Variant note', 'jooosi-fon')}><TextField value={face.comment} onChange={(event) => updateFace(face.id, { comment: event.target.value })} placeholder="latin" /></Field><div className="flex items-center justify-between gap-3 rounded-lg border bg-card px-3 py-2"><div><p id={`preload-${face.id}`} className="m-0 text-sm font-medium">{__('Preload variant', 'jooosi-fon')}</p><p id={`preload-help-${face.id}`} className="m-0 text-xs text-muted-foreground">{__('Request this variant early.', 'jooosi-fon')}</p></div><Switch size="sm" checked={face.preload} onCheckedChange={(checked) => updateFace(face.id, { preload: checked })} aria-labelledby={`preload-${face.id}`} aria-describedby={`preload-help-${face.id}`} /></div></div>}
                                    </section>
                                );
                            })}
                        </FramePanel>
                        {state.fontFaces.length > 0 && <FrameFooter className="flex-row flex-wrap items-center justify-end gap-3 px-4 py-3"><PreviewControl compact label={__('Size', 'jooosi-fon')} value={previewSize} suffix="px" min={12} max={96} onChange={setPreviewSize} />{widthAxis && <PreviewControl compact label={__('Width', 'jooosi-fon')} value={previewWidth} suffix="%" min={widthAxis.min} max={widthAxis.max} onChange={setPreviewWidth} />}{weightAxis && <PreviewControl compact label={__('Weight', 'jooosi-fon')} value={previewWeight} min={weightAxis.min} max={weightAxis.max} onChange={setPreviewWeight} />}</FrameFooter>}
                    </Frame>
                </div>

                <aside className="grid gap-5 xl:sticky xl:top-[calc(var(--wp-admin--admin-bar--height,32px)+5rem)]">
                    <Frame stacked spacing="sm">
                        <FrameHeader className="flex-row items-center justify-between"><div><FrameTitle>{__('Publish', 'jooosi-fon')}</FrameTitle><FrameDescription>{__('Make this font available on your website.', 'jooosi-fon')}</FrameDescription></div>{filesBusy && <span role="status" aria-live="polite" className="flex items-center gap-2 text-xs text-muted-foreground"><Spinner />{__('Loading…', 'jooosi-fon')}</span>}</FrameHeader>
                        <FramePanel className="grid gap-4">
                            {state.fontData ? <div><p className="mb-2 text-sm font-medium">{__('Subsets', 'jooosi-fon')}</p><div className="flex flex-wrap gap-2">{state.fontData.subsets.map((subset) => { const selected = state.subsets.includes(subset); return <label key={subset} className="flex items-center gap-2 rounded-lg border px-2.5 py-2 text-xs"><Checkbox checked={selected} disabled={selected && state.subsets.length === 1} onCheckedChange={(checked) => toggleSubset(subset, checked)} />{subset}</label>; })}</div></div> : <p className="m-0 py-5 text-center text-sm text-muted-foreground">{__('Select a family from the catalog.', 'jooosi-fon')}</p>}
                        </FramePanel>
                        <FramePanel className="grid gap-4">
                            <div><p className="mb-2 text-sm font-medium">{__('File formats', 'jooosi-fon')}</p><div className="flex flex-wrap gap-2">{['woff2', 'woff', 'ttf'].map((format) => { const selected = state.formats.includes(format); return <label key={format} className="flex items-center gap-2 rounded-lg border px-2.5 py-2 text-xs"><Checkbox checked={selected} disabled={selected && state.formats.length === 1} onCheckedChange={(checked) => toggleFormat(format, checked)} />{format.toUpperCase()}</label>; })}</div></div>
                            <Separator />
                            <div className="flex items-center justify-between gap-4"><div><p className="m-0 text-sm font-medium">{__('Variable font', 'jooosi-fon')}</p><p className="m-0 text-xs text-muted-foreground">{state.fontData?.axes?.length ? __('Use available axes', 'jooosi-fon') : __('Unavailable', 'jooosi-fon')}</p></div><Switch checked={state.variable} disabled={!state.fontData?.axes?.length} onCheckedChange={toggleVariable} aria-label={__('Use variable font', 'jooosi-fon')} /></div>
                        </FramePanel>
                        <FramePanel className="grid gap-4 text-sm">
                            <div className="flex items-center justify-between gap-4"><span>{state.status ? __('Publish', 'jooosi-fon') : __('Draft', 'jooosi-fon')}</span><Switch checked={state.status} onCheckedChange={(checked) => setState({ ...state, status: checked })} aria-label={__('Publish status', 'jooosi-fon')} /></div>
                        </FramePanel>
                        <FrameFooter><Button className="w-full" type="submit" disabled={saving || filesBusy}>{saving || filesBusy ? <Spinner /> : <SaveIcon data-icon="inline-start" />}{__('Save', 'jooosi-fon')}</Button>{editing && <Button type="button" variant="destructive" className="w-full" onClick={removeFont} disabled={deleting}>{deleting ? <Spinner /> : <Trash2Icon />}{__('Move to trash', 'jooosi-fon')}</Button>}</FrameFooter>
                    </Frame>

                    {state.fontData && <Frame spacing="sm"><FrameHeader><FrameTitle>{__('Font information', 'jooosi-fon')}</FrameTitle><FrameDescription>{__('Catalog metadata for the selected family.', 'jooosi-fon')}</FrameDescription></FrameHeader><FramePanel className="grid gap-3 text-sm"><div className="flex justify-between gap-4"><span className="text-muted-foreground">{__('Rank', 'jooosi-fon')}</span><strong className="font-medium">{typeof state.fontData.popularity === 'number' ? `#${state.fontData.popularity}` : '—'}</strong></div><div className="flex justify-between gap-4"><span className="text-muted-foreground">{__('Category', 'jooosi-fon')}</span><strong className="font-medium">{state.fontData.category || '—'}</strong></div><div className="flex justify-between gap-4"><span className="text-muted-foreground">{__('Designer', 'jooosi-fon')}</span><strong className="text-right font-medium">{state.fontData.designers?.join(', ') || '—'}</strong></div><div className="flex justify-between gap-4"><span className="text-muted-foreground">{__('Last modified', 'jooosi-fon')}</span><strong className="font-medium"><time dateTime={state.fontData.modifiedAt || state.fontData.lastModified}>{formatCatalogDate(state.fontData.modifiedAt || state.fontData.lastModified)}</time></strong></div><div className="flex justify-between gap-4"><span className="text-muted-foreground">{__('Version', 'jooosi-fon')}</span><strong className="font-medium">{state.fontData.version || '—'}</strong></div><div className="flex justify-between gap-4"><span className="text-muted-foreground">{__('Subsets', 'jooosi-fon')}</span><strong className="font-medium">{state.fontData.subsets.length}</strong></div><div className="flex justify-between gap-4"><span className="text-muted-foreground">{__('Variants', 'jooosi-fon')}</span><strong className="font-medium">{state.fontData.variants.length}</strong></div>{Boolean(state.fontData.axes?.length) && <><Separator /><div><p className="mb-2 text-xs font-semibold uppercase tracking-wider text-muted-foreground">{__('Variable axes', 'jooosi-fon')}</p><div className="grid gap-2">{state.fontData.axes?.map((axis) => <div key={axis.tag} className="grid grid-cols-[1fr_auto_auto] gap-3 rounded-md bg-muted/50 px-2.5 py-2 text-xs"><strong>{axis.tag}</strong><span>{axis.min}</span><span>{axis.max}</span></div>)}</div></div></>}</FramePanel></Frame>}
                </aside>
            </div>
        </form>
    );
}
