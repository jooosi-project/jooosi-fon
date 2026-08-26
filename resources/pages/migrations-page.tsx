import { __ } from '@wordpress/i18n';
import { useState } from 'react';
import { Link } from 'react-router-dom';
import { toast } from 'sonner';
import {
    ArrowRightIcon,
    BrickWallIcon,
    CheckCircle2Icon,
    CloudIcon,
    DatabaseBackupIcon,
    EraserIcon,
    FileInputIcon,
    ImportIcon,
    PanelsTopLeftIcon,
    ShieldAlertIcon,
    SparklesIcon,
    TypeIcon,
    UploadCloudIcon,
} from 'lucide-react';

import { PageHeader } from '@/components/page-header';
import { Badge } from '@/components/reui/badge';
import { Frame, FrameDescription, FrameHeader, FramePanel, FrameTitle } from '@/components/reui/frame';
import { Button, buttonVariants } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Separator } from '@/components/ui/separator';
import { Spinner } from '@/components/ui/spinner';
import { api, getErrorMessage } from '@/lib/api';

interface MigrationSource {
    id: 'custom-fonts-bricks' | 'custom-fonts-brainstorm-force' | 'custom-adobe-fonts' | 'elementor-pro-custom-fonts' | 'font-hero-dplugins' | 'fonts-plugin' | 'use-any-font';
    name: string;
    description: string;
    endpoint: 'bricks-custom-fonts' | 'brainstorm-force-custom-fonts' | 'custom-adobe-fonts' | 'elementor-pro-custom-fonts' | 'font-hero-dplugins' | 'fonts-plugin' | 'use-any-font';
    icon: typeof BrickWallIcon;
}

const sources: MigrationSource[] = [
    {
        id: 'custom-fonts-bricks', name: 'Bricks Custom Fonts', endpoint: 'bricks-custom-fonts', icon: BrickWallIcon,
        description: __('Move locally uploaded Bricks font families into the Jooosi Fon library, then retire the legacy records.', 'jooosi-fon'),
    },
    {
        id: 'custom-fonts-brainstorm-force', name: 'Custom Fonts by Brainstorm Force', endpoint: 'brainstorm-force-custom-fonts', icon: TypeIcon,
        description: __('Import local and locally hosted Google font variants from the Custom Fonts plugin, including display and fallback settings.', 'jooosi-fon'),
    },
    {
        id: 'custom-adobe-fonts', name: 'Custom Adobe Fonts', endpoint: 'custom-adobe-fonts', icon: CloudIcon,
        description: __('Move the Brainstorm Force Adobe project and its published families into Jooosi Fon’s native Adobe Fonts connection.', 'jooosi-fon'),
    },
    {
        id: 'elementor-pro-custom-fonts', name: 'Elementor Pro Custom Fonts', endpoint: 'elementor-pro-custom-fonts', icon: PanelsTopLeftIcon,
        description: __('Import Elementor Pro font families, static variants, variable weight ranges, and supported uploaded files.', 'jooosi-fon'),
    },
    {
        id: 'font-hero-dplugins', name: 'Font Hero', endpoint: 'font-hero-dplugins', icon: SparklesIcon,
        description: __('Bring Font Hero families and files into Jooosi Fon while retaining a deliberate cleanup checkpoint.', 'jooosi-fon'),
    },
    {
        id: 'use-any-font', name: 'Use Any Font', endpoint: 'use-any-font', icon: UploadCloudIcon,
        description: __('Move uploaded families, weights, styles, stretches, and selector assignments from Use Any Font into Jooosi Fon.', 'jooosi-fon'),
    },
    {
        id: 'fonts-plugin', name: 'Fonts Plugin / Google Fonts Typography', endpoint: 'fonts-plugin', icon: TypeIcon,
        description: __('Import uploaded Fonts Plugin families and variants, including preload, display, and Customizer selector assignments.', 'jooosi-fon'),
    },
];

export function MigrationsPage() {
    return (
        <div className="space-y-6">
            <PageHeader eyebrow={__('Data tools', 'jooosi-fon')} title={__('Migration studio', 'jooosi-fon')} description={__('Move font libraries through a guided import, validation, and cleanup sequence. Every destructive step stays separate and explicit.', 'jooosi-fon')} />
            <div className="grid gap-3 md:grid-cols-2 xl:grid-cols-3">
                {sources.map((source, index) => {
                    return (
                        <Frame key={source.id} spacing="sm" className="group">
                            <FramePanel className="flex min-h-52 flex-col justify-between gap-6">
                                <div className="flex items-start justify-between"><span className="text-xs text-muted-foreground/60">0{index + 1}</span><span className="grid size-9 place-items-center rounded-lg border bg-muted/50 shadow-xs"><ImportIcon aria-hidden="true" className="size-4" /></span></div>
                                <div><h2 className="m-0 text-base font-semibold">{source.name}</h2><p className="mb-4 mt-1.5 text-sm leading-5 text-muted-foreground">{source.description}</p><Link to={`/migrations/${source.id}`} className={buttonVariants({ variant: 'outline', size: 'sm' })}>{__('Open migration', 'jooosi-fon')}<ArrowRightIcon /></Link></div>
                            </FramePanel>
                        </Frame>
                    );
                })}
            </div>
            <Frame variant="inverse">
                <FramePanel className="flex items-start gap-4"><DatabaseBackupIcon className="mt-0.5 shrink-0 text-warning-foreground" /><div><h2 className="m-0 text-sm font-semibold">{__('Create a database backup first', 'jooosi-fon')}</h2><p className="mb-0 mt-1 text-sm leading-6 text-muted-foreground">{__('Imports add Jooosi Fon records. Cleanup removes records from the source plugin and cannot be reversed from this screen.', 'jooosi-fon')}</p></div></FramePanel>
            </Frame>
        </div>
    );
}

export function MigrationRunnerPage({ sourceId }: { sourceId: MigrationSource['id'] }) {
    const source = sources.find((item) => item.id === sourceId) ?? sources[0];
    const [backupConfirmed, setBackupConfirmed] = useState(false);
    const [imported, setImported] = useState(false);
    const [cleaned, setCleaned] = useState(false);
    const [preserveSource, setPreserveSource] = useState(true);
    const [busy, setBusy] = useState<'import' | 'cleanup' | null>(null);
    const [result, setResult] = useState<string | null>(null);
    const Icon = source.icon;

    const run = async (action: 'import' | 'cleanup') => {
        if (!backupConfirmed) { toast.error(__('Confirm that a current database backup exists.', 'jooosi-fon')); return; }
        if (action === 'cleanup' && !window.confirm(__('Delete the migrated source records now? This cleanup cannot be undone here.', 'jooosi-fon'))) return;
        setBusy(action); setResult(null);
        try {
            const { data } = await api.post<{ message?: string } | null>(
                `/migrations/${source.endpoint}/${action === 'import' ? 'import-fonts' : 'clean-up'}`,
                action === 'cleanup' ? { confirm: true } : undefined,
            );
            if (action === 'import') setImported(true); else setCleaned(true);
            const message = data?.message || (action === 'import' ? __('Fonts imported. Review the Jooosi Fon library before cleanup.', 'jooosi-fon') : __('Source records cleaned up.', 'jooosi-fon'));
            setResult(message); toast.success(message);
        } catch (requestError) {
            const message = getErrorMessage(requestError); setResult(message); toast.error(message);
        } finally { setBusy(null); }
    };

    return (
        <div className="space-y-6">
            <PageHeader eyebrow={__('Guided migration', 'jooosi-fon')} title={source.name} description={source.description} backTo="/migrations/index" actions={<Badge variant={cleaned || (imported && preserveSource) ? 'success-light' : imported ? 'warning-light' : 'secondary'}>{cleaned ? __('Complete', 'jooosi-fon') : imported && preserveSource ? __('Source preserved', 'jooosi-fon') : imported ? __('Awaiting cleanup', 'jooosi-fon') : __('Not started', 'jooosi-fon')}</Badge>} />
            <div className="grid gap-5 lg:grid-cols-[minmax(0,1fr)_340px]">
                <Frame>
                    <FrameHeader><FrameTitle>{__('Migration runbook', 'jooosi-fon')}</FrameTitle><FrameDescription>{__('Finish each checkpoint in order. Import is non-destructive; cleanup is intentionally separate.', 'jooosi-fon')}</FrameDescription></FrameHeader>
                    <FramePanel className="p-0">
                        <section className="flex gap-4 p-5"><span className="grid size-9 shrink-0 place-items-center rounded-full bg-primary text-sm font-semibold text-primary-foreground">1</span><div className="flex-1"><h2 className="m-0 text-sm font-semibold">{__('Confirm recovery', 'jooosi-fon')}</h2><p className="mb-3 mt-1 text-sm text-muted-foreground">{__('Take a current database backup and verify that it can be restored.', 'jooosi-fon')}</p><label className="inline-flex items-center gap-2 rounded-lg border bg-muted/30 px-3 py-2 text-sm"><Checkbox checked={backupConfirmed} onCheckedChange={setBackupConfirmed} />{__('I have a current database backup', 'jooosi-fon')}</label></div></section><Separator />
                        <section className="flex gap-4 p-5"><span className="grid size-9 shrink-0 place-items-center rounded-full bg-primary text-sm font-semibold text-primary-foreground">2</span><div className="flex-1"><h2 className="m-0 text-sm font-semibold">{__('Import font records', 'jooosi-fon')}</h2><p className="mb-3 mt-1 text-sm text-muted-foreground">{__('Copy supported families into Jooosi Fon without deleting the source data.', 'jooosi-fon')}</p><Button onClick={() => run('import')} disabled={!backupConfirmed || busy !== null || imported}>{busy === 'import' ? <Spinner /> : imported ? <CheckCircle2Icon /> : <FileInputIcon />}{imported ? __('Imported', 'jooosi-fon') : __('Run import', 'jooosi-fon')}</Button></div></section><Separator />
                        <section className="flex gap-4 p-5"><span className="grid size-9 shrink-0 place-items-center rounded-full bg-destructive text-sm font-semibold text-white">3</span><div className="flex-1"><h2 className="m-0 text-sm font-semibold">{__('Review and choose source retention', 'jooosi-fon')}</h2><p className="mb-3 mt-1 text-sm text-muted-foreground">{__('Source records are preserved by default. Disable preservation only after verifying the imported families.', 'jooosi-fon')}</p><label className="mb-3 inline-flex items-center gap-2 rounded-lg border bg-muted/30 px-3 py-2 text-sm"><Checkbox checked={preserveSource} onCheckedChange={setPreserveSource} />{__('Keep source records (recommended)', 'jooosi-fon')}</label><div className="flex flex-wrap gap-2"><Link to="/fonts/index" className={buttonVariants({ variant: 'outline' })}>{__('Review font library', 'jooosi-fon')}<ArrowRightIcon /></Link><Button variant="destructive" onClick={() => run('cleanup')} disabled={!imported || preserveSource || busy !== null || cleaned}>{busy === 'cleanup' ? <Spinner /> : cleaned ? <CheckCircle2Icon /> : <EraserIcon />}{cleaned ? __('Cleanup complete', 'jooosi-fon') : __('Delete source records', 'jooosi-fon')}</Button></div></div></section>
                    </FramePanel>
                </Frame>
                <div className="space-y-5">
                    <Frame variant="inverse"><FramePanel className="space-y-4"><div className="flex items-center gap-3"><span className="grid size-10 place-items-center rounded-lg border bg-background"><Icon /></span><div><p className="m-0 text-sm font-semibold">{source.name}</p><p className="mb-0 mt-1 text-xs text-muted-foreground">{__('Source adapter detected at runtime', 'jooosi-fon')}</p></div></div><Separator /><div className="grid grid-cols-[auto_1fr] gap-x-3 gap-y-3 text-sm"><CheckCircle2Icon className={backupConfirmed ? 'text-success' : 'text-muted-foreground'} /><span>{__('Backup confirmed', 'jooosi-fon')}</span><CheckCircle2Icon className={imported ? 'text-success' : 'text-muted-foreground'} /><span>{__('Fonts imported', 'jooosi-fon')}</span><CheckCircle2Icon className={cleaned ? 'text-success' : 'text-muted-foreground'} /><span>{__('Source cleaned', 'jooosi-fon')}</span></div></FramePanel></Frame>
                    <Frame><FramePanel className="flex gap-3"><ShieldAlertIcon className="mt-0.5 shrink-0 text-warning-foreground" /><p className="m-0 text-sm leading-6 text-muted-foreground">{__('If the source plugin is missing, the API will stop safely and report that no records are available.', 'jooosi-fon')}</p></FramePanel></Frame>
                    {result && <Frame><FramePanel><p className="m-0 text-sm leading-6" role="status">{result}</p></FramePanel></Frame>}
                </div>
            </div>
        </div>
    );
}
