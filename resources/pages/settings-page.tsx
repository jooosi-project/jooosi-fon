import { __ } from '@wordpress/i18n';
import { useEffect, useMemo, useState, type ReactNode } from 'react';
import { toast } from 'sonner';
import {
    BlocksIcon,
    CloudCogIcon,
    ExternalLinkIcon,
    EyeIcon,
    EyeOffIcon,
    FileCode2Icon,
    KeyRoundIcon,
    LibraryIcon,
    RefreshCwIcon,
    SaveIcon,
    ShieldCheckIcon,
    SlidersHorizontalIcon,
    Trash2Icon,
} from 'lucide-react';

import { Field, TextField } from '@/components/form-controls';
import { PageHeader } from '@/components/page-header';
import { Badge } from '@/components/reui/badge';
import { Frame, FrameDescription, FrameHeader, FramePanel, FrameTitle } from '@/components/reui/frame';
import { Button, buttonVariants } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Item, ItemActions, ItemContent, ItemDescription, ItemMedia, ItemTitle } from '@/components/ui/item';
import { Separator } from '@/components/ui/separator';
import { Skeleton } from '@/components/ui/skeleton';
import { Spinner } from '@/components/ui/spinner';
import { Switch } from '@/components/ui/switch';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { useIsMobile } from '@/hooks/use-mobile';
import { useUnsavedChanges } from '@/hooks/use-unsaved-changes';
import { api, getErrorMessage } from '@/lib/api';
import { cn } from '@/lib/utils';

interface LicenseState { key: string | null; is_activated: boolean; opt_in_pre_release: boolean }
interface CacheState { last_generated: number | string; pending_task: boolean; file_url: string }
interface SettingsOptions {
    cache?: { inline_print?: boolean };
    misc?: {
        hide_media_library?: boolean;
        export_bundle_binary?: boolean;
        disable_user_google_fonts?: boolean;
        delete_legacy_data?: boolean;
    };
    builder_integrations?: { disable_google_fonts?: Record<string, boolean> };
    adobe_fonts?: { project_id?: string | null; kit?: unknown };
    [key: string]: unknown;
}

const builders = [
    ['bricks', 'Bricks'], ['elementor', 'Elementor'], ['oxygen', 'Oxygen'],
    ['breakdance', 'Breakdance'], ['slider_revolution', 'Slider Revolution'], ['funnelkit', 'FunnelKit'],
] as const;

type SettingsTabId = 'delivery' | 'privacy' | 'sources' | 'license';

interface SettingsTabItem {
    value: SettingsTabId;
    label: string;
    icon: ReactNode;
}

function SettingRow({ icon, title, description, children }: { icon: ReactNode; title: string; description: string; children: ReactNode }) {
    return (
        <Item className="items-start gap-3 px-4 py-4 sm:flex-nowrap sm:items-center">
            <ItemMedia variant="icon">
                <span className="grid size-10 place-items-center rounded-lg border bg-muted/60 text-muted-foreground shadow-xs">{icon}</span>
            </ItemMedia>
            <ItemContent className="min-w-0">
                <ItemTitle>{title}</ItemTitle>
                <ItemDescription className="max-w-2xl">{description}</ItemDescription>
            </ItemContent>
            <ItemActions className="ml-auto shrink-0 self-center">{children}</ItemActions>
        </Item>
    );
}

function SettingsNavigation({ tabs, isMobile, activeValue }: { tabs: SettingsTabItem[]; isMobile: boolean; activeValue: SettingsTabId }) {
    return (
        <nav className={cn('min-w-0', isMobile ? 'w-full' : 'w-48 shrink-0')} aria-label={__('Settings sections', 'jooosi-fon')}>
            <div className={cn(isMobile && '-mx-1 overflow-x-auto px-1 pb-1')}>
                <TabsList
                    variant="line"
                    className={cn(
                        'h-auto gap-1 bg-transparent p-0',
                        isMobile ? 'w-max min-w-max justify-start' : 'w-full flex-col items-stretch',
                    )}
                >
                    {tabs.map((tab) => (
                        <TabsTrigger
                            key={tab.value}
                            value={tab.value}
                            className={cn(
                                'h-9 justify-start gap-2.5 px-3 py-1.5 shadow-none',
                                activeValue === tab.value ? 'bg-muted!' : 'bg-transparent',
                            )}
                        >
                            {tab.icon}
                            <span className="truncate">{tab.label}</span>
                        </TabsTrigger>
                    ))}
                </TabsList>
            </div>
        </nav>
    );
}

function SettingsPageSkeleton() {
    return (
        <div className="grid min-w-0 gap-6" role="status" aria-live="polite">
            <span className="sr-only">{__('Loading settings…', 'jooosi-fon')}</span>
            <Skeleton className="h-40 rounded-2xl" />
            <div className="mx-auto grid w-full max-w-6xl gap-5 md:grid-cols-[12rem_minmax(0,1fr)] lg:gap-8">
                <div className="grid content-start gap-2">
                    <Skeleton className="h-9" />
                    <Skeleton className="h-9" />
                    <Skeleton className="h-9" />
                    <Skeleton className="h-9" />
                </div>
                <div className="grid gap-3 rounded-xl border bg-card p-4">
                    <Skeleton className="h-5 w-44" />
                    <Skeleton className="h-4 w-2/3" />
                    <Skeleton className="mt-2 h-20" />
                    <Skeleton className="h-20" />
                    <Skeleton className="h-20" />
                </div>
            </div>
        </div>
    );
}

function updateNested<T extends SettingsOptions>(options: T, section: string, key: string, value: unknown): T {
    const currentSection = (options[section] && typeof options[section] === 'object') ? options[section] as Record<string, unknown> : {};
    return { ...options, [section]: { ...currentSection, [key]: value } };
}

export function SettingsPage() {
    const isMobile = useIsMobile();
    const [activeTab, setActiveTab] = useState<SettingsTabId>('delivery');
    const [license, setLicense] = useState<LicenseState>({ key: null, is_activated: false, opt_in_pre_release: false });
    const [licenseKey, setLicenseKey] = useState('');
    const [showLicense, setShowLicense] = useState(false);
    const [cache, setCache] = useState<CacheState>({ last_generated: '', pending_task: false, file_url: '' });
    const [options, setOptions] = useState<SettingsOptions>({});
    const [optionsSnapshot, setOptionsSnapshot] = useState('{}');
    const [loading, setLoading] = useState(true);
    const [loadError, setLoadError] = useState<string | null>(null);
    const [reloadToken, setReloadToken] = useState(0);
    const [busy, setBusy] = useState<string | null>(null);
    const [adobeProjectId, setAdobeProjectId] = useState('');
    const [adobeKit, setAdobeKit] = useState<unknown>(null);

    const optionsDirty = !loading && JSON.stringify(options) !== optionsSnapshot;
    useUnsavedChanges(optionsDirty);

    useEffect(() => {
        let cancelled = false;
        setLoading(true);
        setLoadError(null);
        Promise.all([
            api.get<{ license: LicenseState }>('/setting/license/index'),
            api.get<{ cache: CacheState }>('/setting/cache/index'),
            api.get<{ options: SettingsOptions }>('/setting/option/index'),
        ]).then(([licenseResponse, cacheResponse, optionsResponse]) => {
            if (cancelled) return;
            setLicense(licenseResponse.data.license);
            setLicenseKey(licenseResponse.data.license.key || '');
            setCache(cacheResponse.data.cache);
            setOptions(optionsResponse.data.options);
            setOptionsSnapshot(JSON.stringify(optionsResponse.data.options));
            setAdobeProjectId(optionsResponse.data.options.adobe_fonts?.project_id || '');
            setAdobeKit(optionsResponse.data.options.adobe_fonts?.kit || null);
        }).catch((requestError) => {
            if (cancelled) return;
            const message = getErrorMessage(requestError);
            setLoadError(message);
            toast.error(message);
        }).finally(() => { if (!cancelled) setLoading(false); });
        return () => { cancelled = true; };
    }, [reloadToken]);

    const googleDisabled = useMemo(() => options.builder_integrations?.disable_google_fonts || {}, [options]);
    const settingsTabs = useMemo<SettingsTabItem[]>(() => {
        const tabs: SettingsTabItem[] = [
            { value: 'delivery', label: __('Delivery', 'jooosi-fon'), icon: <SlidersHorizontalIcon aria-hidden="true" /> },
            { value: 'privacy', label: __('Privacy', 'jooosi-fon'), icon: <ShieldCheckIcon aria-hidden="true" /> },
            { value: 'sources', label: __('Font sources', 'jooosi-fon'), icon: <CloudCogIcon aria-hidden="true" /> },
        ];
        if (!window.jooosiFon.lite_edition) tabs.push({ value: 'license', label: __('License', 'jooosi-fon'), icon: <KeyRoundIcon aria-hidden="true" /> });
        return tabs;
    }, []);

    const saveLicense = async () => {
        setBusy('license');
        try {
            const { data } = await api.post<{ license: LicenseState; notice?: { success?: string; error?: string } }>('/setting/license/store', { license: licenseKey.trim() });
            setLicense(data.license);
            setLicenseKey(data.license.key || '');
            if (data.notice?.error) toast.error(data.notice.error); else toast.success(data.notice?.success || __('License setting saved.', 'jooosi-fon'));
        } catch (requestError) { toast.error(getErrorMessage(requestError)); } finally { setBusy(null); }
    };

    const saveOptions = async (nextOptions = options) => {
        setBusy('options');
        try {
            const { data } = await api.post<{ options: SettingsOptions }>('/setting/option/store', { options: nextOptions });
            setOptions(data.options);
            setOptionsSnapshot(JSON.stringify(data.options));
            toast.success(__('Settings saved.', 'jooosi-fon'));
        } catch (requestError) { toast.error(getErrorMessage(requestError)); } finally { setBusy(null); }
    };

    const generateCache = async () => {
        setBusy('cache');
        try {
            const { data } = await api.post<{ cache: CacheState }>('/setting/cache/generate');
            setCache(data.cache);
            toast.success(__('Font CSS cache generation started.', 'jooosi-fon'));
        } catch (requestError) { toast.error(getErrorMessage(requestError)); } finally { setBusy(null); }
    };

    const connectAdobe = async () => {
        if (!adobeProjectId.trim()) { toast.error(__('Enter an Adobe Fonts project ID.', 'jooosi-fon')); return; }
        setBusy('adobe');
        try {
            const kits = await api.post<{ data: { kit: unknown } }>('/setting/adobe-fonts/get-kits', { project_id: adobeProjectId.trim() });
            const kit = kits.data.data.kit;
            const next = { ...options, adobe_fonts: { project_id: adobeProjectId.trim(), kit } };
            const stored = await api.post<{ options: SettingsOptions }>('/setting/option/store', { options: next });
            await api.post('/setting/adobe-fonts/sync', { project_id: adobeProjectId.trim(), kit });
            setAdobeKit(kit);
            setOptions(stored.data.options);
            setOptionsSnapshot(JSON.stringify(stored.data.options));
            toast.success(__('Adobe Fonts connected and synced.', 'jooosi-fon'));
        } catch (requestError) { toast.error(getErrorMessage(requestError)); } finally { setBusy(null); }
    };

    const clearAdobe = async () => {
        if (!window.confirm(__('Remove the Adobe Fonts project and its imported families?', 'jooosi-fon'))) return;
        setBusy('adobe');
        try {
            await api.post('/setting/adobe-fonts/destroy');
            const next = { ...options, adobe_fonts: { project_id: null, kit: null } };
            const { data } = await api.post<{ options: SettingsOptions }>('/setting/option/store', { options: next });
            setAdobeProjectId(''); setAdobeKit(null); setOptions(data.options); setOptionsSnapshot(JSON.stringify(data.options));
            toast.success(__('Adobe Fonts connection cleared.', 'jooosi-fon'));
        } catch (requestError) { toast.error(getErrorMessage(requestError)); } finally { setBusy(null); }
    };

    if (loading) return <SettingsPageSkeleton />;

    if (loadError) {
        return (
            <div className="grid min-w-0 gap-6">
                <PageHeader
                    eyebrow={__('Configuration', 'jooosi-fon')}
                    title={__('Settings', 'jooosi-fon')}
                    description={__('Control licensing, delivery, integrations, and font sources from one operational workspace.', 'jooosi-fon')}
                />
                <div className="mx-auto w-full max-w-6xl">
                    <Frame variant="inverse" role="status" aria-live="polite">
                        <FramePanel className="flex flex-col items-start gap-4 sm:flex-row sm:items-center">
                            <RefreshCwIcon aria-hidden="true" className="shrink-0 text-destructive" />
                            <div className="min-w-0 flex-1">
                                <h2 className="m-0 text-sm font-semibold">{__('Settings could not be loaded', 'jooosi-fon')}</h2>
                                <p className="mb-0 mt-1 text-sm text-muted-foreground">{loadError}</p>
                            </div>
                            <Button type="button" variant="outline" onClick={() => setReloadToken((value) => value + 1)}>
                                <RefreshCwIcon aria-hidden="true" />
                                {__('Try again', 'jooosi-fon')}
                            </Button>
                        </FramePanel>
                    </Frame>
                </div>
            </div>
        );
    }

    return (
        <div className="grid min-w-0 gap-6">
            <PageHeader
                eyebrow={__('Configuration', 'jooosi-fon')}
                title={__('Settings', 'jooosi-fon')}
                description={__('Control licensing, delivery, integrations, and font sources from one operational workspace.', 'jooosi-fon')}
                actions={(
                    <>
                        {optionsDirty && <Badge variant="warning-light">{__('Unsaved changes', 'jooosi-fon')}</Badge>}
                        <Button type="button" onClick={() => saveOptions()} disabled={!optionsDirty || busy !== null}>
                            {busy === 'options' ? <Spinner /> : <SaveIcon aria-hidden="true" />}
                            {__('Save settings', 'jooosi-fon')}
                        </Button>
                    </>
                )}
            />

            <Tabs
                value={activeTab}
                onValueChange={(value) => setActiveTab(value as SettingsTabId)}
                orientation={isMobile ? 'horizontal' : 'vertical'}
                className="mx-auto min-w-0 w-full max-w-6xl items-start gap-5 lg:gap-8"
            >
                <SettingsNavigation tabs={settingsTabs} isMobile={isMobile} activeValue={activeTab} />

                <div className="min-w-0 flex-1">
                    <TabsContent value="delivery" className="mt-0 grid gap-5">
                        <Frame>
                            <FrameHeader>
                                <FrameTitle>{__('Font delivery', 'jooosi-fon')}</FrameTitle>
                                <FrameDescription>{__('Tune how generated CSS and WordPress media records are handled.', 'jooosi-fon')}</FrameDescription>
                            </FrameHeader>
                            <FramePanel className="p-0">
                                <SettingRow
                                    icon={<FileCode2Icon aria-hidden="true" />}
                                    title={__('Inline generated CSS', 'jooosi-fon')}
                                    description={__('Print font CSS in the document instead of linking the cache file.', 'jooosi-fon')}
                                >
                                    <Switch
                                        checked={Boolean(options.cache?.inline_print)}
                                        onCheckedChange={(checked) => setOptions(updateNested(options, 'cache', 'inline_print', checked))}
                                        aria-label={__('Inline generated CSS', 'jooosi-fon')}
                                    />
                                </SettingRow>
                                <Separator />
                                <SettingRow
                                    icon={<LibraryIcon aria-hidden="true" />}
                                    title={__('Keep font files out of Media Library', 'jooosi-fon')}
                                    description={__('Hide Jooosi Fon-managed font attachments from general media browsing.', 'jooosi-fon')}
                                >
                                    <Switch
                                        checked={Boolean(options.misc?.hide_media_library)}
                                        onCheckedChange={(checked) => setOptions(updateNested(options, 'misc', 'hide_media_library', checked))}
                                        aria-label={__('Keep font files out of Media Library', 'jooosi-fon')}
                                    />
                                </SettingRow>
                                <Separator />
                                <SettingRow
                                    icon={<CloudCogIcon aria-hidden="true" />}
                                    title={__('Binary export bundles', 'jooosi-fon')}
                                    description={__('Include locally hosted file data when exporting font bundles.', 'jooosi-fon')}
                                >
                                    <Switch
                                        checked={Boolean(options.misc?.export_bundle_binary)}
                                        onCheckedChange={(checked) => setOptions(updateNested(options, 'misc', 'export_bundle_binary', checked))}
                                        aria-label={__('Binary export bundles', 'jooosi-fon')}
                                    />
                                </SettingRow>
                            </FramePanel>
                        </Frame>

                        <Frame>
                            <FrameHeader>
                                <FrameTitle>{__('Legacy data', 'jooosi-fon')}</FrameTitle>
                                <FrameDescription>{__('The rebrand migration keeps the original Yabe Webfont files until you choose to remove them.', 'jooosi-fon')}</FrameDescription>
                            </FrameHeader>
                            <FramePanel className="p-0">
                                <SettingRow
                                    icon={<Trash2Icon aria-hidden="true" />}
                                    title={__('Delete old Yabe Webfont data', 'jooosi-fon')}
                                    description={__('Delete the preserved uploads/yabe-webfont files after the migration. This cannot be undone.', 'jooosi-fon')}
                                >
                                    <Switch
                                        checked={Boolean(options.misc?.delete_legacy_data)}
                                        onCheckedChange={(checked) => {
                                            if (!checked || window.confirm(__('Delete the preserved Yabe Webfont data? This cannot be undone.', 'jooosi-fon'))) {
                                                setOptions(updateNested(options, 'misc', 'delete_legacy_data', checked));
                                            }
                                        }}
                                        aria-label={__('Delete old Yabe Webfont data', 'jooosi-fon')}
                                    />
                                </SettingRow>
                            </FramePanel>
                        </Frame>

                        <Frame>
                            <FrameHeader>
                                <FrameTitle>{__('CSS cache', 'jooosi-fon')}</FrameTitle>
                                <FrameDescription>{__('Regenerate the frontend font stylesheet after infrastructure or file changes.', 'jooosi-fon')}</FrameDescription>
                            </FrameHeader>
                            <FramePanel className="p-0">
                                <SettingRow
                                    icon={<RefreshCwIcon aria-hidden="true" />}
                                    title={__('Generated stylesheet', 'jooosi-fon')}
                                    description={cache.last_generated
                                        ? `${__('Last generated', 'jooosi-fon')}: ${new Date(Number(cache.last_generated) * 1000).toLocaleString()}`
                                        : __('No cache file has been generated yet.', 'jooosi-fon')}
                                >
                                    <Badge variant={cache.pending_task ? 'warning-light' : cache.last_generated ? 'success-light' : 'secondary'}>
                                        {cache.pending_task ? __('Queued', 'jooosi-fon') : cache.last_generated ? __('Ready', 'jooosi-fon') : __('Not generated', 'jooosi-fon')}
                                    </Badge>
                                </SettingRow>
                                <Separator />
                                <div className="flex flex-wrap justify-end gap-2 p-4">
                                    {cache.file_url && (
                                        <a
                                            href={cache.file_url}
                                            target="_blank"
                                            rel="noopener noreferrer"
                                            className={buttonVariants({ variant: 'outline' })}
                                        >
                                            <ExternalLinkIcon aria-hidden="true" />
                                            {__('Open CSS file', 'jooosi-fon')}
                                        </a>
                                    )}
                                    <Button type="button" onClick={generateCache} disabled={busy !== null || cache.pending_task}>
                                        {busy === 'cache' ? <Spinner /> : <RefreshCwIcon aria-hidden="true" />}
                                        {__('Regenerate cache', 'jooosi-fon')}
                                    </Button>
                                </div>
                            </FramePanel>
                        </Frame>
                    </TabsContent>

                    <TabsContent value="privacy" className="mt-0 grid gap-5">
                        <Frame>
                            <FrameHeader>
                                <FrameTitle>{__('Google Fonts privacy', 'jooosi-fon')}</FrameTitle>
                                <FrameDescription>{__('Stop manual and builder-generated requests to Google-hosted font files.', 'jooosi-fon')}</FrameDescription>
                            </FrameHeader>
                            <FramePanel className="p-0">
                                <SettingRow
                                    icon={<BlocksIcon aria-hidden="true" />}
                                    title={__('Block manual Google Fonts calls', 'jooosi-fon')}
                                    description={__('Disable user-added calls to the Google Fonts API; locally hosted fonts remain available.', 'jooosi-fon')}
                                >
                                    <Switch
                                        checked={Boolean(options.misc?.disable_user_google_fonts)}
                                        onCheckedChange={(checked) => setOptions(updateNested(options, 'misc', 'disable_user_google_fonts', checked))}
                                        aria-label={__('Block manual Google Fonts calls', 'jooosi-fon')}
                                    />
                                </SettingRow>
                                <Separator />
                                <div className="grid gap-3 p-4 sm:grid-cols-2 xl:grid-cols-3">
                                    {builders.map(([key, label]) => (
                                        <label key={key} className="flex items-center justify-between gap-3 rounded-lg border bg-muted/30 px-3.5 py-3 text-sm font-medium">
                                            <span className="min-w-0 truncate">{label}</span>
                                            <Switch
                                                checked={googleDisabled[key] ?? true}
                                                onCheckedChange={(checked) => setOptions({
                                                    ...options,
                                                    builder_integrations: {
                                                        ...options.builder_integrations,
                                                        disable_google_fonts: { ...googleDisabled, [key]: checked },
                                                    },
                                                })}
                                            />
                                        </label>
                                    ))}
                                </div>
                            </FramePanel>
                        </Frame>
                    </TabsContent>

                    <TabsContent value="sources" className="mt-0 grid gap-5">
                        <Frame>
                            <FrameHeader>
                                <FrameTitle>{__('Adobe Fonts project', 'jooosi-fon')}</FrameTitle>
                                <FrameDescription>{__('Connect one Adobe Fonts web project, import its families, and keep them synchronized.', 'jooosi-fon')}</FrameDescription>
                            </FrameHeader>
                            <FramePanel className="p-0">
                                <div className="p-4">
                                    <Field label={__('Project ID', 'jooosi-fon')} hint={__('Found in the Adobe Fonts web project embed code.', 'jooosi-fon')}>
                                        <TextField value={adobeProjectId} onChange={(event) => setAdobeProjectId(event.target.value)} placeholder="abc1def" />
                                    </Field>
                                </div>
                                <Separator />
                                <SettingRow
                                    icon={<CloudCogIcon aria-hidden="true" />}
                                    title={__('Connection status', 'jooosi-fon')}
                                    description={adobeKit
                                        ? __('The project is available to sync with the local font library.', 'jooosi-fon')
                                        : __('Connect a project to import Adobe Fonts families.', 'jooosi-fon')}
                                >
                                    <Badge variant={adobeKit ? 'success-light' : 'secondary'}>
                                        {adobeKit ? __('Connected', 'jooosi-fon') : __('Not connected', 'jooosi-fon')}
                                    </Badge>
                                </SettingRow>
                                <Separator />
                                <div className="flex flex-wrap justify-end gap-2 p-4">
                                    {Boolean(adobeKit) && (
                                        <Button type="button" variant="outline" onClick={clearAdobe} disabled={busy !== null}>
                                            <Trash2Icon aria-hidden="true" />
                                            {__('Clear connection', 'jooosi-fon')}
                                        </Button>
                                    )}
                                    <Button type="button" onClick={connectAdobe} disabled={busy !== null}>
                                        {busy === 'adobe' ? <Spinner /> : <RefreshCwIcon aria-hidden="true" />}
                                        {adobeKit ? __('Verify & sync', 'jooosi-fon') : __('Connect & sync', 'jooosi-fon')}
                                    </Button>
                                </div>
                            </FramePanel>
                        </Frame>
                    </TabsContent>

                    {!window.jooosiFon.lite_edition && (
                        <TabsContent value="license" className="mt-0 grid gap-5">
                            <Frame>
                                <FrameHeader>
                                    <FrameTitle>{__('License & updates', 'jooosi-fon')}</FrameTitle>
                                    <FrameDescription>{__('Manage the Jooosi Fon product license used by this WordPress installation.', 'jooosi-fon')}</FrameDescription>
                                </FrameHeader>
                                <FramePanel className="p-0">
                                    <div className="p-4">
                                        <Field
                                            label={__('License key', 'jooosi-fon')}
                                            hint={license.is_activated ? __('Activated for this website.', 'jooosi-fon') : __('Enter your product license to enable updates.', 'jooosi-fon')}
                                        >
                                            <div className="flex gap-2">
                                                <Input
                                                    type={showLicense ? 'text' : 'password'}
                                                    autoComplete="off"
                                                    value={licenseKey}
                                                    onChange={(event) => setLicenseKey(event.target.value)}
                                                />
                                                <Button
                                                    type="button"
                                                    variant="outline"
                                                    size="icon"
                                                    aria-label={showLicense ? __('Hide key', 'jooosi-fon') : __('Show key', 'jooosi-fon')}
                                                    onClick={() => setShowLicense((value) => !value)}
                                                >
                                                    {showLicense ? <EyeOffIcon aria-hidden="true" /> : <EyeIcon aria-hidden="true" />}
                                                </Button>
                                            </div>
                                        </Field>
                                    </div>
                                    <Separator />
                                    <SettingRow
                                        icon={<ShieldCheckIcon aria-hidden="true" />}
                                        title={__('Activation status', 'jooosi-fon')}
                                        description={license.is_activated
                                            ? __('Updates and licensed features are enabled for this website.', 'jooosi-fon')
                                            : __('Save a valid license key to enable product updates.', 'jooosi-fon')}
                                    >
                                        <Badge variant={license.is_activated ? 'success-light' : 'secondary'}>
                                            {license.is_activated ? __('Activated', 'jooosi-fon') : __('Inactive', 'jooosi-fon')}
                                        </Badge>
                                    </SettingRow>
                                    <Separator />
                                    <div className="flex justify-end p-4">
                                        <Button type="button" onClick={saveLicense} disabled={busy !== null}>
                                            {busy === 'license' ? <Spinner /> : <KeyRoundIcon aria-hidden="true" />}
                                            {__('Save license', 'jooosi-fon')}
                                        </Button>
                                    </div>
                                </FramePanel>
                            </Frame>
                        </TabsContent>
                    )}
                </div>
            </Tabs>
        </div>
    );
}
