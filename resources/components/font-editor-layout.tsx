import { __ } from '@wordpress/i18n';
import { ArrowLeftIcon, SlidersHorizontalIcon, TypeIcon } from 'lucide-react';
import { useEffect, useId, useState, type ReactNode } from 'react';
import { Link } from 'react-router-dom';

import { Badge } from '@/components/reui/badge';
import { Button, buttonVariants } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Skeleton } from '@/components/ui/skeleton';
import { cn } from '@/lib/utils';

interface FontEditorHeaderProps {
    source: string;
    title: string;
    description: string;
    icon?: ReactNode;
    actions?: ReactNode;
}

export function FontEditorHeader({ source, title, description, icon, actions }: FontEditorHeaderProps) {
    return (
        <header className="flex flex-col gap-5 border-b pb-5 lg:flex-row lg:items-center lg:justify-between">
            <div className="flex min-w-0 items-start gap-3.5">
                <Link
                    to="/fonts/index"
                    className={cn(buttonVariants({ variant: 'outline', size: 'icon' }), 'mt-0.5 shrink-0')}
                    aria-label={__('Back to font library', 'jooosi-fon')}
                >
                    <ArrowLeftIcon aria-hidden="true" />
                </Link>
                <span className="mt-0.5 hidden size-9 shrink-0 place-items-center rounded-xl border bg-card shadow-xs sm:grid">
                    {icon || <TypeIcon aria-hidden="true" className="size-4 text-muted-foreground" />}
                </span>
                <div className="min-w-0">
                    <Badge variant="primary-light" size="sm" radius="full">{source}</Badge>
                    <h1 className="mb-0 mt-2 text-2xl font-semibold tracking-[-0.025em] sm:text-[1.75rem] sm:leading-tight">{title}</h1>
                    <p className="mb-0 mt-1.5 max-w-2xl text-sm leading-6 text-muted-foreground">{description}</p>
                </div>
            </div>
            {actions && <div className="flex shrink-0 flex-wrap gap-2 pl-[3.25rem] sm:pl-[6.5rem] lg:pl-0">{actions}</div>}
        </header>
    );
}

interface FontSpecimenProps {
    css: string;
    family: string;
    text: string;
    onTextChange: (value: string) => void;
    details: Array<{ label: string; value: ReactNode }>;
}

export function FontSpecimen({ css, family, text, onTextChange, details }: FontSpecimenProps) {
    return (
        <section className="overflow-hidden rounded-2xl border bg-card shadow-xs" aria-labelledby="font-specimen-title">
            <style>{css}</style>
            <div className="flex flex-col gap-3 border-b px-4 py-3 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <p id="font-specimen-title" className="m-0 text-sm font-semibold">{__('Live specimen', 'jooosi-fon')}</p>
                    <p className="mb-0 mt-0.5 text-xs text-muted-foreground">{__('Rendered from the files and descriptors currently configured below.', 'jooosi-fon')}</p>
                </div>
                <Input value={text} onChange={(event) => onTextChange(event.target.value)} aria-label={__('Preview text', 'jooosi-fon')} className="w-full sm:max-w-md" />
            </div>
            <div className="relative min-h-64 overflow-hidden bg-invert p-6 text-invert-foreground sm:min-h-72 sm:p-9">
                <div className="pointer-events-none absolute -right-24 -top-24 size-80 rounded-full bg-primary/20 blur-3xl" aria-hidden="true" />
                <p className="relative m-0 max-w-5xl break-words text-4xl leading-[1.08] tracking-[-0.04em] sm:text-5xl lg:text-6xl" style={{ fontFamily: family ? `'${family}', sans-serif` : 'inherit' }}>
                    {text || __('The quick brown fox jumps over the lazy dog.', 'jooosi-fon')}
                </p>
                <p className="relative mb-0 mt-8 text-base tracking-[0.22em] text-invert-foreground/60" style={{ fontFamily: family ? `'${family}', sans-serif` : 'inherit' }}>
                    ABCDEFGHIJKLMNOPQRSTUVWXYZ · 0123456789
                </p>
            </div>
            <dl className="grid bg-muted/20 sm:grid-cols-2 lg:grid-cols-4">
                {details.map(({ label, value }) => (
                    <div key={label} className="border-b px-4 py-3 last:border-b-0 sm:border-r sm:[&:nth-child(even)]:border-r-0 lg:border-b-0 lg:[&:nth-child(even)]:border-r lg:last:border-r-0">
                        <dt className="text-[10px] font-semibold uppercase tracking-[0.12em] text-muted-foreground">{label}</dt>
                        <dd className="mb-0 mt-1 truncate text-sm font-medium">{value}</dd>
                    </div>
                ))}
            </dl>
        </section>
    );
}

interface PreviewControlProps {
    label: string;
    value: number;
    suffix?: string;
    min: number;
    max: number;
    step?: number;
    onChange: (value: number) => void;
    compact?: boolean;
    className?: string;
}

export function PreviewControl({ label, value, suffix, min, max, step = 1, onChange, compact = false, className }: PreviewControlProps) {
    const labelId = useId();
    const [draftValue, setDraftValue] = useState(String(value));

    useEffect(() => setDraftValue(String(value)), [value]);

    const commitDraftValue = () => {
        const nextValue = Number(draftValue);
        if (!Number.isFinite(nextValue)) {
            setDraftValue(String(value));
            return;
        }

        const clampedValue = Math.min(max, Math.max(min, nextValue));
        setDraftValue(String(clampedValue));
        onChange(clampedValue);
    };

    return (
        <div className={cn(
            compact
                ? 'grid min-w-32 grid-cols-[1fr_auto] items-center gap-x-2 gap-y-1 text-xs font-medium'
                : 'grid grid-cols-[5rem_minmax(5rem,1fr)_4.5rem] items-center gap-2 text-xs font-medium',
            className,
        )}>
            <span id={labelId} className="text-muted-foreground">{label}</span>
            <input
                type="range"
                min={min}
                max={max}
                step={step}
                value={value}
                onChange={(event) => onChange(Number(event.target.value))}
                aria-labelledby={labelId}
                className={cn(
                    'h-5 w-full cursor-pointer overflow-visible accent-primary focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring',
                    compact && 'col-span-2 row-start-2',
                )}
            />
            <span className={cn(
                'flex min-w-0 items-center rounded-md border bg-background focus-within:border-ring focus-within:ring-2 focus-within:ring-ring/50',
                compact && 'col-start-2 row-start-1 min-w-16',
            )}>
                <input
                    type="number"
                    min={min}
                    max={max}
                    step={step}
                    value={draftValue}
                    onChange={(event) => {
                        const nextDraft = event.target.value;
                        setDraftValue(nextDraft);
                        const nextValue = Number(nextDraft);
                        if (nextDraft !== '' && Number.isFinite(nextValue) && nextValue >= min && nextValue <= max) onChange(nextValue);
                    }}
                    onBlur={commitDraftValue}
                    onKeyDown={(event) => {
                        if (event.key === 'Enter') event.currentTarget.blur();
                        if (event.key === 'Escape') {
                            setDraftValue(String(value));
                            event.currentTarget.blur();
                        }
                    }}
                    aria-labelledby={labelId}
                    className="min-w-0 flex-1 appearance-none bg-transparent py-1 pl-1.5 text-right tabular-nums outline-none [appearance:textfield] [&::-webkit-inner-spin-button]:appearance-none [&::-webkit-outer-spin-button]:appearance-none"
                />
                {suffix && <span aria-hidden="true" className="pr-1.5 text-muted-foreground">{suffix}</span>}
            </span>
        </div>
    );
}

interface FontPreviewToolbarProps {
    text: string;
    onTextChange: (value: string) => void;
    size: number;
    onSizeChange: (value: number) => void;
    weight: number;
    onWeightChange: (value: number) => void;
    width: number;
    onWidthChange: (value: number) => void;
    className?: string;
}

export function FontPreviewToolbar({
    text,
    onTextChange,
    size,
    onSizeChange,
    weight,
    onWeightChange,
    width,
    onWidthChange,
    className,
}: FontPreviewToolbarProps) {
    return (
        <section className={cn('rounded-xl border bg-card shadow-xs', className)} aria-labelledby="preview-controls-title">
            <div className="flex flex-col gap-3 border-b px-4 py-3 lg:flex-row lg:items-center">
                <div className="flex shrink-0 items-center gap-2 lg:w-44">
                    <SlidersHorizontalIcon aria-hidden="true" className="size-4 text-primary" />
                    <div>
                        <h2 id="preview-controls-title" className="m-0 text-sm font-semibold">{__('Variant preview', 'jooosi-fon')}</h2>
                        <p className="m-0 text-[11px] text-muted-foreground">{__('Shared by every row', 'jooosi-fon')}</p>
                    </div>
                </div>
                <Input
                    value={text}
                    onChange={(event) => onTextChange(event.target.value)}
                    aria-label={__('Preview text', 'jooosi-fon')}
                    placeholder={__('Type preview text…', 'jooosi-fon')}
                    className="h-9 flex-1 text-base"
                />
            </div>
            <div className="grid gap-3 px-4 py-3 md:grid-cols-3">
                <PreviewControl label={__('Size', 'jooosi-fon')} value={size} suffix="px" min={12} max={96} onChange={onSizeChange} />
                <PreviewControl label={__('Weight', 'jooosi-fon')} value={weight} min={100} max={900} step={1} onChange={onWeightChange} />
                <PreviewControl label={__('Width', 'jooosi-fon')} value={width} suffix="%" min={50} max={200} onChange={onWidthChange} />
            </div>
        </section>
    );
}

export function FontEditorSkeleton() {
    return (
        <div className="space-y-5" aria-busy="true" aria-label={__('Loading font editor', 'jooosi-fon')}>
            <div className="flex items-start gap-3.5 border-b pb-5">
                <Skeleton className="size-9 shrink-0 rounded-lg" />
                <div className="min-w-0 flex-1 space-y-2.5">
                    <Skeleton className="h-5 w-28 rounded-full" />
                    <Skeleton className="h-8 w-64 max-w-full" />
                    <Skeleton className="h-4 w-[34rem] max-w-full" />
                </div>
            </div>
            <div className="overflow-hidden rounded-2xl border">
                <div className="flex items-center justify-between gap-4 border-b p-4">
                    <div className="space-y-2"><Skeleton className="h-4 w-24" /><Skeleton className="h-3 w-64 max-w-full" /></div>
                    <Skeleton className="hidden h-9 w-72 sm:block" />
                </div>
                <Skeleton className="h-64 rounded-none sm:h-72" />
                <div className="grid grid-cols-2 gap-px bg-border lg:grid-cols-4">
                    {[0, 1, 2, 3].map((item) => <Skeleton key={item} className="h-16 rounded-none bg-card" />)}
                </div>
            </div>
            <div className="grid gap-5 xl:grid-cols-[minmax(0,1fr)_340px]">
                <Skeleton className="h-80 rounded-xl" />
                <Skeleton className="h-80 rounded-xl" />
            </div>
            <span className="sr-only">{__('Loading font editor', 'jooosi-fon')}</span>
        </div>
    );
}
