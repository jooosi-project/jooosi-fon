import { __ } from '@wordpress/i18n';
import { nanoid } from 'nanoid';
import { useEffect, useMemo, useState, type FormEvent } from 'react';
import { useNavigate, useParams } from 'react-router-dom';
import { toast } from 'sonner';
import {
    ArrowDown01Icon,
    ChevronDownIcon,
    FilePlus2Icon,
    InfoIcon,
    PlusIcon,
    SaveIcon,
    Trash2Icon,
    TypeIcon,
    UploadCloudIcon,
    XIcon,
} from 'lucide-react';

import { FontEditorHeader, FontEditorSkeleton, PreviewControl } from '@/components/font-editor-layout';
import { Field, NativeSelect, TextField } from '@/components/form-controls';
import {
    Frame,
    FrameDescription,
    FrameFooter,
    FrameHeader,
    FramePanel,
    FrameTitle,
} from '@/components/reui/frame';
import { Badge } from '@/components/reui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Spinner } from '@/components/ui/spinner';
import { Switch } from '@/components/ui/switch';
import { Tooltip, TooltipContent, TooltipTrigger } from '@/components/ui/tooltip';
import { useUnsavedChanges } from '@/hooks/use-unsaved-changes';
import { api, getErrorMessage } from '@/lib/api';
import { createFontFaceCss } from '@/lib/font-preview';
import { cn } from '@/lib/utils';
import { fontFaceVariantKey, fontFileFormatPrecedence, inspectFontFiles, type InspectedFontFile } from '@/lib/font-inspector';
import { normalizeFontFile, openWordPressFontMedia } from '@/lib/wordpress-media';
import type { CustomFontFace, FontDetail, FontFile } from '@/types/fonts';

const displayOptions = ['auto', 'block', 'swap', 'fallback', 'optional'];

function FieldTooltipLabel({ label, description }: { label: string; description: string }) {
    return (
        <Tooltip>
            <TooltipTrigger
                render={<span className="inline-flex w-fit cursor-help items-center gap-1 border-b border-dotted border-muted-foreground/60 focus-visible:rounded-sm focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring" tabIndex={0} />}
            >
                {label}
                <InfoIcon className="size-3.5 text-muted-foreground" aria-hidden="true" />
            </TooltipTrigger>
            <TooltipContent>{description}</TooltipContent>
        </Tooltip>
    );
}

function formatFileSize(file: FontFile) {
    const bytes = Number(file.file_size ?? file.filesize ?? 0);

    if (!Number.isFinite(bytes) || bytes <= 0) {
        return '0 B';
    }

    const units = ['B', 'KB', 'MB', 'GB'];
    const unitIndex = Math.min(Math.floor(Math.log(bytes) / Math.log(1024)), units.length - 1);
    const size = Number((bytes / 1024 ** unitIndex).toFixed(unitIndex === 0 ? 0 : 2));

    return `${size} ${units[unitIndex]}`;
}

interface EditorState {
    title: string;
    family: string;
    status: boolean;
    preload: boolean;
    selector: string;
    display: string;
    fontFaces: CustomFontFace[];
}

interface InspectionProgressState {
    completed: number;
    total: number;
    active: number;
}

function blankFace(): CustomFontFace {
    return {
        id: nanoid(10),
        isEnabled: true,
        weight: 400,
        width: '',
        style: 'normal',
        display: '',
        selector: '',
        comment: '',
        unicodeRange: '',
        preload: false,
        axes: [],
        files: [],
    };
}

const blankState: EditorState = {
    title: '',
    family: '',
    status: true,
    preload: false,
    selector: '',
    display: 'swap',
    fontFaces: [],
};

function mergeInspectedFontFile(current: EditorState, inspected: InspectedFontFile): EditorState {
    const fontFaces = current.fontFaces.map((face) => ({
        ...face,
        files: [...face.files],
        axes: face.axes ? [...face.axes] : [],
    }));
    const inferredFamily = current.family.trim() || inspected.family;
    const variant = {
        weight: inspected.weight,
        width: inspected.width,
        style: inspected.style,
    };
    const key = fontFaceVariantKey(variant);
    let face = fontFaces.find((candidate) => fontFaceVariantKey(candidate) === key);

    if (!face) {
        face = {
            ...blankFace(),
            ...variant,
            axes: inspected.axes,
        };
        fontFaces.push(face);
    } else if (face.axes?.length === 0 && inspected.axes.length > 0) {
        face.axes = inspected.axes;
    }

    if (!face.files.some((existing) => existing.attachment_id === inspected.file.attachment_id)) {
        face.files.push(inspected.file);
        face.files.sort((a, b) => fontFileFormatPrecedence(a) - fontFileFormatPrecedence(b));
    }

    return {
        ...current,
        title: current.title.trim() || inferredFamily,
        family: inferredFamily,
        fontFaces,
    };
}

function payloadFromState(state: EditorState) {
    return {
        title: state.title.trim(),
        family: state.family.trim(),
        status: state.status,
        font_faces: state.fontFaces.map((face) => ({
            ...face,
            id: String(face.id),
            weight: typeof face.weight === 'string' ? face.weight.trim() : face.weight,
            style: String(face.style || 'normal'),
            isEnabled: face.isEnabled !== false,
            files: face.files.map(normalizeFontFile),
        })),
        metadata: {
            preload: state.preload,
            selector: state.selector.trim(),
            display: state.display,
        },
    };
}

export function CustomFontEditorPage() {
    const { id } = useParams();
    const navigate = useNavigate();
    const editing = Boolean(id);
    const [state, setState] = useState<EditorState>(blankState);
    const [snapshot, setSnapshot] = useState(() => JSON.stringify(payloadFromState(blankState)));
    const [loading, setLoading] = useState(editing);
    const [saving, setSaving] = useState(false);
    const [deleting, setDeleting] = useState(false);
    const [inspectingFiles, setInspectingFiles] = useState(false);
    const [inspectionProgress, setInspectionProgress] = useState<InspectionProgressState | null>(null);
    const [error, setError] = useState<string | null>(null);
    const [previewText, setPreviewText] = useState(__('Sphinx of black quartz, judge my vow.', 'jooosi-fon'));
    const [previewSize, setPreviewSize] = useState(18);
    const [previewWeight, setPreviewWeight] = useState(400);
    const [previewWidth, setPreviewWidth] = useState(100);
    const [expandedFaces, setExpandedFaces] = useState<string[]>([]);

    const currentPayload = useMemo(() => payloadFromState(state), [state]);
    const dirty = !loading && JSON.stringify(currentPayload) !== snapshot;
    const allowNavigation = useUnsavedChanges(dirty);

    useEffect(() => {
        if (!editing || !id) return;
        const controller = new AbortController();
        setLoading(true);
        api.get<FontDetail>(`/fonts/detail/${id}`, { signal: controller.signal })
            .then(({ data }) => {
                const next: EditorState = {
                    title: data.title,
                    family: data.family,
                    status: data.status,
                    preload: Boolean(data.metadata.preload),
                    selector: data.metadata.selector || '',
                    display: data.metadata.display || 'swap',
                    fontFaces: (data.font_faces || []).map((face) => ({ ...face, isEnabled: face.isEnabled !== false })),
                };
                setState(next);
                setSnapshot(JSON.stringify(payloadFromState(next)));
            })
            .catch((requestError) => {
                if (!controller.signal.aborted) setError(getErrorMessage(requestError));
            })
            .finally(() => {
                if (!controller.signal.aborted) setLoading(false);
            });
        return () => controller.abort();
    }, [editing, id]);

    const updateFace = (faceId: string, changes: Partial<CustomFontFace>) => {
        setState((current) => ({
            ...current,
            fontFaces: current.fontFaces.map((face) => face.id === faceId ? { ...face, ...changes } : face),
        }));
    };

    const addEmptyFace = () => {
        const face = blankFace();
        setState((current) => ({ ...current, fontFaces: [...current.fontFaces, face] }));
        setExpandedFaces((current) => [...current, face.id]);
    };

    const toggleFace = (faceId: string) => {
        setExpandedFaces((current) => current.includes(faceId)
            ? current.filter((item) => item !== faceId)
            : [...current, faceId]);
    };

    const sortFaces = () => {
        setState((current) => ({
            ...current,
            fontFaces: [...current.fontFaces].sort((a, b) => {
                const weightDifference = Number.parseInt(String(a.weight), 10) - Number.parseInt(String(b.weight), 10);
                return weightDifference || a.style.localeCompare(b.style);
            }),
        }));
    };

    const addBulkFiles = () => {
        try {
            openWordPressFontMedia({
                title: __('Upload font files', 'jooosi-fon'),
                onSelect: (files) => {
                    if (files.length === 0) return;
                    setInspectingFiles(true);
                    setInspectionProgress({ completed: 0, total: files.length, active: 0 });
                    void inspectFontFiles(files, (inspected, completed, total, active) => {
                        setInspectionProgress({ completed, total, active });
                        if (inspected) {
                            setState((current) => mergeInspectedFontFile(current, inspected));
                        }
                    })
                        .catch((error) => toast.error(getErrorMessage(error)))
                        .finally(() => {
                            setInspectingFiles(false);
                            setInspectionProgress(null);
                        });
                },
            });
        } catch (mediaError) {
            toast.error(getErrorMessage(mediaError));
        }
    };

    const addFilesToFace = (faceId: string) => {
        try {
            openWordPressFontMedia({
                title: __('Select files for this variant', 'jooosi-fon'),
                onSelect: (files) => setState((current) => ({
                    ...current,
                    fontFaces: current.fontFaces.map((face) => face.id === faceId
                        ? { ...face, files: [...face.files, ...files.filter((file) => !face.files.some((existing) => existing.attachment_id === file.attachment_id))] }
                        : face),
                })),
            });
        } catch (mediaError) {
            toast.error(getErrorMessage(mediaError));
        }
    };

    const validate = () => {
        if (!state.title.trim() || !state.family.trim()) {
            toast.error(__('Add a font name and CSS family.', 'jooosi-fon'));
            return false;
        }
        if (state.fontFaces.length === 0 || state.fontFaces.every((face) => face.isEnabled === false || face.files.length === 0)) {
            toast.error(__('Enable a variant with a font file before saving.', 'jooosi-fon'));
            return false;
        }
        return true;
    };

    const submit = async (event: FormEvent) => {
        event.preventDefault();
        if (!validate() || saving) return;
        setSaving(true);
        try {
            const endpoint = editing ? `/fonts/custom/update/${id}` : '/fonts/custom/store';
            const { data } = await api.post<{ id: number }>(endpoint, currentPayload);
            setSnapshot(JSON.stringify(currentPayload));
            toast.success(editing ? __('Custom font updated.', 'jooosi-fon') : __('Custom font created.', 'jooosi-fon'));
            if (!editing) { allowNavigation(); navigate(`/fonts/edit/${data.id}/custom`, { replace: true }); }
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
            toast.success(__('Font moved to trash.', 'jooosi-fon'));
            allowNavigation();
            navigate('/fonts/index', { replace: true });
        } catch (requestError) {
            toast.error(getErrorMessage(requestError));
        } finally {
            setDeleting(false);
        }
    };

    if (loading) {
        return <FontEditorSkeleton />;
    }

    if (error) {
        return <Frame><FramePanel role="status" aria-live="polite"><p className="text-sm text-destructive">{error}</p><Button type="button" className="mt-4" onClick={() => navigate('/fonts/index')}>{__('Back to fonts', 'jooosi-fon')}</Button></FramePanel></Frame>;
    }

    const previewCss = createFontFaceCss(state.family, state.fontFaces, state.display);
    const hasVariableWeight = state.fontFaces.some((face) => String(face.weight).trim().includes(' '));
    const hasVariableWidth = state.fontFaces.some((face) => String(face.width).trim().includes(' '));

    return (
        <form className="grid gap-5" onSubmit={submit}>
            <FontEditorHeader
                source={__('Custom upload', 'jooosi-fon')}
                title={editing ? state.title || __('Edit custom font', 'jooosi-fon') : __('Create custom font', 'jooosi-fon')}
                description={__('Assemble a locally hosted family from WordPress Media files and control its exact CSS output.', 'jooosi-fon')}
            />

            <Frame spacing="sm">
                <FrameHeader><FrameTitle>{__('Font details', 'jooosi-fon')}</FrameTitle><FrameDescription>{__('Name the family and define its default CSS behavior.', 'jooosi-fon')}</FrameDescription></FrameHeader>
                <FramePanel className="grid gap-4 md:grid-cols-2 xl:grid-cols-[1.1fr_1.1fr_.9fr_1.25fr_.8fr]">
                    <Field label={__('Library name', 'jooosi-fon')} hint={__('Shown in the font library.', 'jooosi-fon')}><TextField required value={state.title} onChange={(event) => setState({ ...state, title: event.target.value })} placeholder={__('Acme Sans — Brand', 'jooosi-fon')} /></Field>
                    <Field label={__('CSS font-family', 'jooosi-fon')} hint={__('Used in generated @font-face rules.', 'jooosi-fon')}><TextField required value={state.family} onChange={(event) => setState({ ...state, family: event.target.value })} placeholder="Acme Sans" /></Field>
                    <Field label={__('Font display', 'jooosi-fon')} hint={__('Text appearance while loading.', 'jooosi-fon')}><NativeSelect value={state.display} onChange={(event) => setState({ ...state, display: event.target.value })}>{displayOptions.map((display) => <option key={display}>{display}</option>)}</NativeSelect></Field>
                    <Field label={__('CSS selector / fallback', 'jooosi-fon')} hint={__('Optional automatic assignment.', 'jooosi-fon')}><TextField placeholder="h1, .site-title | Arial, sans-serif" value={state.selector} onChange={(event) => setState({ ...state, selector: event.target.value })} /></Field>
                    <div className="grid gap-1.5 text-sm font-medium"><span id="preload-family-label">{__('Preload family', 'jooosi-fon')}</span><div className="flex h-8 items-center justify-between gap-3 rounded-lg border bg-background px-2.5"><span className="text-xs font-normal text-muted-foreground">{state.preload ? __('Enabled', 'jooosi-fon') : __('Disabled', 'jooosi-fon')}</span><Switch size="sm" checked={state.preload} onCheckedChange={(checked) => setState({ ...state, preload: checked })} aria-labelledby="preload-family-label" aria-describedby="preload-family-help" /></div><span id="preload-family-help" className="text-xs font-normal text-muted-foreground">{__('Request selected files early.', 'jooosi-fon')}</span></div>
                </FramePanel>
            </Frame>

            <div className="grid gap-5 xl:grid-cols-[minmax(0,1fr)_300px] xl:items-start">
                <div className="grid min-w-0 gap-5">
                    <style>{previewCss}</style>

                    <Frame className="min-w-0" spacing="sm" dense>
                        <FrameHeader className="gap-3 px-4 py-3">
                            <div className="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                                <div><FrameTitle>{__('Font variants', 'jooosi-fon')}</FrameTitle><FrameDescription>{__('Edit essentials in the row; open a row only for advanced settings and files.', 'jooosi-fon')}</FrameDescription></div>
                                <div className="flex flex-wrap gap-2">
                                    <Button type="button" size="sm" variant="ghost" onClick={sortFaces} disabled={state.fontFaces.length < 2}><ArrowDown01Icon />{__('Sort', 'jooosi-fon')}</Button>
                                    <Button type="button" size="sm" variant="outline" onClick={addEmptyFace}><PlusIcon />{__('Empty variant', 'jooosi-fon')}</Button>
                                    <Button type="button" size="sm" onClick={addBulkFiles} disabled={inspectingFiles}><UploadCloudIcon />{inspectingFiles ? <>{__('Inspecting files…', 'jooosi-fon')} {inspectionProgress && `${inspectionProgress.completed}/${inspectionProgress.total}`}</> : __('Upload files', 'jooosi-fon')}</Button>
                                </div>
                            </div>
                        </FrameHeader>
                        {inspectionProgress && (
                            <div className="mx-4 mb-3 rounded-lg border bg-muted/20 p-3" role="status" aria-live="polite">
                                <div className="mb-2 flex items-center justify-between gap-3 text-xs">
                                    <span className="text-muted-foreground">{__('Inspecting font metadata…', 'jooosi-fon')}</span>
                                    <span className="font-medium tabular-nums">{inspectionProgress.completed}/{inspectionProgress.total}{inspectionProgress.active > 0 && ` · ${inspectionProgress.active} ${__('running', 'jooosi-fon')}`}</span>
                                </div>
                                <div
                                    className="h-1.5 overflow-hidden rounded-full bg-muted"
                                    role="progressbar"
                                    aria-label={__('Font inspection progress', 'jooosi-fon')}
                                    aria-valuemin={0}
                                    aria-valuemax={inspectionProgress.total}
                                    aria-valuenow={inspectionProgress.completed}
                                >
                                    <div
                                        className={cn('h-full rounded-full bg-primary transition-[width] duration-200', inspectionProgress.completed === 0 && inspectionProgress.active > 0 && 'w-1/3 animate-pulse')}
                                        style={inspectionProgress.completed > 0 ? { width: `${Math.round((inspectionProgress.completed / inspectionProgress.total) * 100)}%` } : undefined}
                                    />
                                </div>
                            </div>
                        )}
                        <FramePanel className="p-0">
                            {state.fontFaces.length === 0 && (
                                <button type="button" onClick={addEmptyFace} className="grid min-h-44 w-full place-items-center border border-dashed bg-muted/20 p-8 text-center transition-colors hover:bg-muted/40 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-ring">
                                    <span><TypeIcon aria-hidden="true" className="mx-auto mb-3 size-7 text-primary" /><strong className="block text-sm">{__('Add your first font variant', 'jooosi-fon')}</strong><span className="mt-1 block text-xs text-muted-foreground">{__('Define its weight and style, then attach font files.', 'jooosi-fon')}</span></span>
                                </button>
                            )}
                            {state.fontFaces.map((face, index) => {
                                const expanded = expandedFaces.includes(face.id);
                                const enabled = face.isEnabled !== false;
                                const weight = String(face.weight).trim().includes(' ') ? previewWeight : face.weight;
                                const stretch = String(face.width).trim().includes(' ') ? `${previewWidth}%` : String(face.width || '100%');
                                return (
                                    <section key={face.id} className={cn('border-b last:border-b-0', !enabled && 'bg-muted/20')}>
                                        <h3 className="sr-only">{__('Variant', 'jooosi-fon')} {index + 1}</h3>
                                        <div className="grid gap-3 px-3 py-3 md:grid-cols-[44px_90px_110px_minmax(12rem,1fr)_auto] md:items-center">
                                            <Switch checked={enabled} onCheckedChange={(checked) => updateFace(face.id, { isEnabled: checked })} aria-label={`${__('Include variant', 'jooosi-fon')} ${index + 1}`} />
                                            <Tooltip><TooltipTrigger render={<TextField className="h-8" value={String(face.weight)} onChange={(event) => updateFace(face.id, { weight: event.target.value })} aria-label={`${__('Weight for variant', 'jooosi-fon')} ${index + 1}`} />} /><TooltipContent>{__('Weight', 'jooosi-fon')}</TooltipContent></Tooltip>
                                            <Tooltip><TooltipTrigger render={<NativeSelect className="h-8" value={face.style} onChange={(event) => updateFace(face.id, { style: event.target.value })} aria-label={`${__('Style for variant', 'jooosi-fon')} ${index + 1}`}><option value="normal">normal</option><option value="italic">italic</option><option value="oblique">oblique</option>{!['normal', 'italic', 'oblique'].includes(face.style) && <option value={face.style}>{face.style}</option>}</NativeSelect>} /><TooltipContent>{__('Style', 'jooosi-fon')}</TooltipContent></Tooltip>
                                            <div className={cn('min-w-0 rounded-lg border bg-card px-3 py-2 transition-[background-color,color,opacity]', !enabled && 'bg-muted/50 text-muted-foreground opacity-50')}>
                                                <Input value={previewText} onChange={(event) => setPreviewText(event.target.value)} aria-label={`${__('Preview text for variant', 'jooosi-fon')} ${index + 1}`} placeholder={__('Type preview text…', 'jooosi-fon')} className={cn('h-auto min-w-0 border-0 bg-transparent p-0 shadow-none focus-visible:border-transparent focus-visible:ring-0', !enabled && 'text-muted-foreground')} style={{ fontFamily: state.family ? `'${state.family}', sans-serif` : 'inherit', fontSize: `${previewSize}px`, fontWeight: weight, fontStyle: face.style, fontStretch: stretch }} />
                                            </div>
                                            <div className="flex items-center justify-end gap-1">
                                                <Button type="button" variant="ghost" size="icon" aria-label={expanded ? __('Close variant settings', 'jooosi-fon') : __('Open variant settings', 'jooosi-fon')} aria-expanded={expanded} onClick={() => toggleFace(face.id)}><ChevronDownIcon className={expanded ? 'rotate-180 transition-transform' : 'transition-transform'} /></Button>
                                                <Button type="button" variant="ghost" size="icon" aria-label={__('Remove variant', 'jooosi-fon')} onClick={() => setState((current) => ({ ...current, fontFaces: current.fontFaces.filter((item) => item.id !== face.id) }))}><Trash2Icon className="text-destructive" /></Button>
                                            </div>
                                        </div>
                                        {expanded && (
                                            <div className="grid gap-4 border-t bg-muted/20 p-4 sm:grid-cols-2 lg:grid-cols-6">
                                                <Field label={<FieldTooltipLabel label={__('Width', 'jooosi-fon')} description={__('Value or variable range.', 'jooosi-fon')} />}><TextField value={String(face.width)} onChange={(event) => updateFace(face.id, { width: event.target.value })} placeholder="75% 125%" /></Field>
                                                <Field label={<FieldTooltipLabel label={__('Display override', 'jooosi-fon')} description={__('Use a different font-display value for this variant.', 'jooosi-fon')} />}><NativeSelect value={face.display} onChange={(event) => updateFace(face.id, { display: event.target.value })}><option value="">{__('Use family default', 'jooosi-fon')}</option>{displayOptions.map((display) => <option key={display}>{display}</option>)}</NativeSelect></Field>
                                                <Field label={<FieldTooltipLabel label={__('Unicode range', 'jooosi-fon')} description="U+0000-00FF" />}><TextField value={face.unicodeRange} onChange={(event) => updateFace(face.id, { unicodeRange: event.target.value })} /></Field>
                                                <Field label={<FieldTooltipLabel label={__('Variant selector', 'jooosi-fon')} description={__('Apply this variant to matching elements.', 'jooosi-fon')} />}><TextField value={face.selector} onChange={(event) => updateFace(face.id, { selector: event.target.value })} placeholder=".font-bold" /></Field>
                                                <Field label={<FieldTooltipLabel label={__('Comment', 'jooosi-fon')} description={__('Optional comment for this variant.', 'jooosi-fon')} />}><TextField value={face.comment} onChange={(event) => updateFace(face.id, { comment: event.target.value })} /></Field>
                                                <Field label={<FieldTooltipLabel label={__('Preload variant', 'jooosi-fon')} description={__('Request this variant early.', 'jooosi-fon')} />}><Switch checked={face.preload} onCheckedChange={(checked) => updateFace(face.id, { preload: checked })} aria-label={__('Preload variant', 'jooosi-fon')} /></Field>
                                                <div className="flex flex-wrap items-center gap-2 border-t pt-4 sm:col-span-2 lg:col-span-6">
                                                    {face.files.map((file) => <Badge key={file.uid} variant="outline"><span className="text-muted-foreground">[{file.extension}]</span>{' '}{file.name}<span className="text-muted-foreground">{' · '}{formatFileSize(file)}</span><button type="button" className="ml-1 rounded-sm text-muted-foreground hover:text-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring" aria-label={__('Remove file', 'jooosi-fon')} onClick={() => updateFace(face.id, { files: face.files.filter((item) => item.uid !== file.uid) })}><XIcon aria-hidden="true" className="size-3" /></button></Badge>)}
                                                    <Button type="button" size="sm" variant="outline" onClick={() => addFilesToFace(face.id)}><FilePlus2Icon />{__('Add files', 'jooosi-fon')}</Button>
                                                </div>
                                            </div>
                                        )}
                                    </section>
                                );
                            })}
                        </FramePanel>
                        {state.fontFaces.length > 0 && <FrameFooter className="flex-row flex-wrap items-center justify-end gap-3 px-4 py-3"><PreviewControl compact label={__('Size', 'jooosi-fon')} value={previewSize} suffix="px" min={12} max={96} onChange={setPreviewSize} />{hasVariableWidth && <PreviewControl compact label={__('Width', 'jooosi-fon')} value={previewWidth} suffix="%" min={50} max={200} onChange={setPreviewWidth} />}{hasVariableWeight && <PreviewControl compact label={__('Weight', 'jooosi-fon')} value={previewWeight} min={100} max={900} step={1} onChange={setPreviewWeight} />}</FrameFooter>}
                    </Frame>
                </div>

                <aside className="grid gap-5 xl:sticky xl:top-[calc(var(--wp-admin--admin-bar--height,32px)+5rem)]">
                    <Frame stacked spacing="sm">
                        <FrameHeader><FrameTitle>{__('Publish', 'jooosi-fon')}</FrameTitle><FrameDescription>{__('Make this font available on your website.', 'jooosi-fon')}</FrameDescription></FrameHeader>
                        <FramePanel className="grid gap-4 text-sm">
                            <div className="flex items-center justify-between gap-4"><span>{state.status ? __('Publish', 'jooosi-fon') : __('Draft', 'jooosi-fon')}</span><Switch checked={state.status} onCheckedChange={(checked) => setState({ ...state, status: checked })} aria-label={__('Publish status', 'jooosi-fon')} /></div>
                        </FramePanel>
                        <FrameFooter className="gap-2"><Button type="submit" className="w-full" disabled={saving || inspectingFiles}>{saving ? <Spinner /> : <SaveIcon data-icon="inline-start" />}{__('Save', 'jooosi-fon')}</Button>{editing && <Button type="button" variant="destructive" className="w-full" onClick={removeFont} disabled={deleting || inspectingFiles}>{deleting ? <Spinner /> : <Trash2Icon />}{__('Move to trash', 'jooosi-fon')}</Button>}</FrameFooter>
                    </Frame>
                </aside>
            </div>
        </form>
    );
}
