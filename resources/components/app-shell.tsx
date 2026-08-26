import { __, sprintf } from '@wordpress/i18n';
import {
    BookOpenIcon,
    ChevronDownIcon,
    CircleHelpIcon,
    ExternalLinkIcon,
    FileInputIcon,
    FolderPlusIcon,
    HeadphonesIcon,
    LibraryBigIcon,
    InfoIcon,
    MenuIcon,
    PlusIcon,
    SearchIcon,
    Settings2Icon,
    SparklesIcon,
    UploadIcon,
    UsersIcon,
} from 'lucide-react';
import { type ChangeEvent, useEffect, useRef, useState } from 'react';
import { Link, NavLink, Outlet, useNavigate } from 'react-router-dom';
import { toast } from 'sonner';

import { Badge } from '@/components/reui/badge';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuGroup,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Input } from '@/components/ui/input';
import { Kbd } from '@/components/ui/kbd';
import { api, getErrorMessage } from '@/lib/api';
import { cn } from '@/lib/utils';

import JooosiFonLogo from '../../jooosi-fon.svg?react';

const mainNavigation = [
    { label: __('Library', 'jooosi-fon'), to: '/fonts/index', icon: LibraryBigIcon },
    { label: __('Settings', 'jooosi-fon'), to: '/settings', icon: Settings2Icon },
    { label: __('Migrations', 'jooosi-fon'), to: '/migrations/index', icon: FileInputIcon },
    { label: __('About', 'jooosi-fon'), to: '/about', icon: InfoIcon },
];

const createActions = [
    {
        label: __('Upload custom font', 'jooosi-fon'),
        description: __('Use files from WordPress Media', 'jooosi-fon'),
        to: '/fonts/create/custom',
        icon: FolderPlusIcon,
    },
    {
        label: __('Import Google Font', 'jooosi-fon'),
        description: __('Download and host it locally', 'jooosi-fon'),
        to: '/fonts/create/google-fonts',
        icon: SparklesIcon,
    },
];

const commands = [
    ...mainNavigation,
    ...createActions.map(({ label, to, icon }) => ({ label, to, icon })),
];

interface FontImportFile {
    module_id: string;
    site_url: string;
    is_bundled: boolean;
    version: string;
    items: Array<{
        title: string;
        type: 'custom' | 'google-fonts' | 'adobe-fonts';
        [key: string]: unknown;
    }>;
}

function CommandPalette({ open, onOpenChange }: { open: boolean; onOpenChange: (open: boolean) => void }) {
    const navigate = useNavigate();
    const [query, setQuery] = useState('');
    const filtered = commands.filter((command) => command.label.toLowerCase().includes(query.trim().toLowerCase()));

    const choose = (to: string) => {
        navigate(to);
        onOpenChange(false);
        setQuery('');
    };

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="top-[30%] max-w-xl gap-0 overflow-hidden p-0">
                <DialogHeader className="sr-only">
                    <DialogTitle>{__('Quick navigation', 'jooosi-fon')}</DialogTitle>
                    <DialogDescription>{__('Search Jooosi Fon pages and actions.', 'jooosi-fon')}</DialogDescription>
                </DialogHeader>
                <div className="relative border-b">
                    <SearchIcon aria-hidden="true" className="absolute left-4 top-1/2 size-4 -translate-y-1/2 text-muted-foreground" />
                    <Input
                        autoFocus
                        className="h-12 rounded-none border-0 bg-transparent pl-11 pr-12 shadow-none focus-visible:ring-0"
                        value={query}
                        onChange={(event) => setQuery(event.target.value)}
                        placeholder={__('Search pages and actions…', 'jooosi-fon')}
                    />
                </div>
                <div className="grid max-h-80 gap-1 overflow-y-auto p-2">
                    {filtered.map(({ label, to, icon: Icon }) => (
                        <button
                            key={to}
                            type="button"
                            className="flex items-center gap-3 rounded-lg px-3 py-2.5 text-left text-sm transition-colors hover:bg-muted focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
                            onClick={() => choose(to)}
                        >
                            <span className="grid size-8 place-items-center rounded-md border bg-background shadow-xs">
                                <Icon aria-hidden="true" className="size-4 text-muted-foreground" />
                            </span>
                            {label}
                        </button>
                    ))}
                    {filtered.length === 0 && <p className="p-8 text-center text-sm text-muted-foreground">{__('No matching destination.', 'jooosi-fon')}</p>}
                </div>
            </DialogContent>
        </Dialog>
    );
}

function CreateMenu() {
    const navigate = useNavigate();
    const fileInputRef = useRef<HTMLInputElement>(null);
    const [importing, setImporting] = useState(false);

    const importFile = async (event: ChangeEvent<HTMLInputElement>) => {
        const file = event.target.files?.[0];
        event.target.value = '';
        if (!file) {
            return;
        }

        setImporting(true);
        try {
            const data = JSON.parse(await file.text()) as FontImportFile;
            // TODO: Remove the legacy export identifier completely in Jooosi Fon 3.0.0.
            const supportedModuleIds = [window.jooosiFon.option_namespace, 'yabe_webfont'];
            if (!supportedModuleIds.includes(data.module_id) || !Array.isArray(data.items)) {
                throw new Error(__('This is not a valid Jooosi Fon export file.', 'jooosi-fon'));
            }

            for (const item of data.items) {
                await api.post('/fonts/import', {
                    site_url: data.site_url,
                    is_bundled: data.is_bundled,
                    version: data.version,
                    item,
                });
                toast.success(sprintf(__('Imported “%s”.', 'jooosi-fon'), item.title));
            }

            navigate('/fonts/index', { state: { importedAt: Date.now() } });
        } catch (requestError) {
            toast.error(getErrorMessage(requestError));
        } finally {
            setImporting(false);
        }
    };

    return (
        <>
            <DropdownMenu>
                <DropdownMenuTrigger render={<Button type="button" disabled={importing} />}>
                    <PlusIcon aria-hidden="true" />
                    <span className="hidden sm:inline">{importing ? __('Importing…', 'jooosi-fon') : __('Add font', 'jooosi-fon')}</span>
                    <ChevronDownIcon aria-hidden="true" className="hidden size-3.5 opacity-70 sm:block" />
                </DropdownMenuTrigger>
                <DropdownMenuContent align="end" className="w-72">
                    <DropdownMenuGroup>
                        <DropdownMenuLabel>{__('Add to your library', 'jooosi-fon')}</DropdownMenuLabel>
                        {createActions.map(({ label, description, to, icon: Icon }) => (
                            <DropdownMenuItem key={to} render={<Link to={to} />} className="items-start py-2.5">
                                <span className="mt-0.5 grid size-8 shrink-0 place-items-center rounded-md border bg-background">
                                    <Icon aria-hidden="true" className="size-4" />
                                </span>
                                <span className="grid gap-0.5">
                                    <span className="font-medium text-foreground">{label}</span>
                                    <span className="text-xs text-muted-foreground">{description}</span>
                                </span>
                            </DropdownMenuItem>
                        ))}
                    </DropdownMenuGroup>
                    <DropdownMenuSeparator />
                    <DropdownMenuItem className="items-start py-2.5" disabled={importing} onClick={() => fileInputRef.current?.click()}>
                        <span className="mt-0.5 grid size-8 shrink-0 place-items-center rounded-md border bg-background">
                            <UploadIcon aria-hidden="true" className="size-4" />
                        </span>
                        <span className="grid gap-0.5">
                            <span className="font-medium text-foreground">{__('Import bundle', 'jooosi-fon')}</span>
                            <span className="text-xs text-muted-foreground">{__('Restore a Jooosi Fon export file', 'jooosi-fon')}</span>
                        </span>
                    </DropdownMenuItem>
                </DropdownMenuContent>
            </DropdownMenu>
            <input
                ref={fileInputRef}
                type="file"
                accept="application/json,.json"
                className="sr-only"
                aria-label={__('Choose a Jooosi Fon export file', 'jooosi-fon')}
                onChange={(event) => void importFile(event)}
            />
        </>
    );
}

function UtilityMenu({ mobile = false }: { mobile?: boolean }) {
    const resources = [
        {
            label: __('Documentation', 'jooosi-fon'),
            href: 'https://fon.jooo.si/docs?utm_source=wordpress-plugins&utm_medium=plugin-menu&utm_campaign=jooosi-fon&utm_id=pro-version',
            icon: BookOpenIcon,
        },
        { label: __('Support', 'jooosi-fon'), href: 'https://jooo.si/account/?view=support-tickets', icon: HeadphonesIcon },
        { label: __('Community', 'jooosi-fon'), href: 'https://www.facebook.com/groups/1142662969627943', icon: UsersIcon },
    ];

    return (
        <DropdownMenu>
            <DropdownMenuTrigger
                render={<Button type="button" variant="outline" size="icon" aria-label={mobile ? __('Open navigation', 'jooosi-fon') : __('Open help menu', 'jooosi-fon')} />}
            >
                {mobile ? <MenuIcon aria-hidden="true" /> : <CircleHelpIcon aria-hidden="true" />}
            </DropdownMenuTrigger>
            <DropdownMenuContent align="end" className="w-60">
                {mobile && (
                    <>
                        <DropdownMenuGroup>
                            <DropdownMenuLabel>{__('Workspace', 'jooosi-fon')}</DropdownMenuLabel>
                            {mainNavigation.map(({ label, to, icon: Icon }) => (
                                <DropdownMenuItem key={to} render={<Link to={to} />}>
                                    <Icon aria-hidden="true" />
                                    {label}
                                </DropdownMenuItem>
                            ))}
                        </DropdownMenuGroup>
                        <DropdownMenuSeparator />
                    </>
                )}
                <DropdownMenuGroup>
                    <DropdownMenuLabel>{__('Resources', 'jooosi-fon')}</DropdownMenuLabel>
                    {resources.map(({ label, href, icon: Icon }) => (
                        <DropdownMenuItem key={href} render={<a href={href} target="_blank" rel="noopener noreferrer" />}>
                            <Icon aria-hidden="true" />
                            {label}
                            <ExternalLinkIcon aria-hidden="true" className="ml-auto size-3.5 opacity-60" />
                        </DropdownMenuItem>
                    ))}
                </DropdownMenuGroup>
            </DropdownMenuContent>
        </DropdownMenu>
    );
}

export function AppShell() {
    const [searchOpen, setSearchOpen] = useState(false);

    useEffect(() => {
        const handler = (event: KeyboardEvent) => {
            if ((event.metaKey || event.ctrlKey) && event.key.toLowerCase() === 'k') {
                event.preventDefault();
                setSearchOpen(true);
            }
        };
        window.addEventListener('keydown', handler);
        return () => window.removeEventListener('keydown', handler);
    }, []);

    return (
        <div className="min-h-[inherit] bg-background text-foreground">
            <header className="sticky top-[var(--wp-admin--admin-bar--height,32px)] z-40 border-b bg-card/95 backdrop-blur supports-[backdrop-filter]:bg-card/85">
                <div className="mx-auto flex h-16 w-full max-w-[1600px] items-center gap-3 px-4 sm:px-6 lg:px-8">
                    <Link to="/fonts/index" className="flex min-w-0 shrink-0 items-center gap-2.5 text-foreground no-underline">
                        <span className="grid size-9 place-items-center rounded-xl border bg-background shadow-xs">
                            <JooosiFonLogo aria-hidden="true" focusable="false" className="size-7 text-primary" />
                        </span>
                        <span className="hidden min-w-0 sm:block">
                            <span className="flex items-center gap-2 text-sm font-semibold leading-none">
                                Jooosi Fon
                                {/* <Badge variant="secondary" size="sm">{__('Local', 'jooosi-fon')}</Badge> */}
                                <Badge variant="secondary" size="sm">{window.jooosiFon._version}</Badge>
                            </span>
                            {/* <span className="mt-1 block text-[11px] leading-none text-muted-foreground">{__('Font operations for WordPress', 'jooosi-fon')}</span> */}
                        </span>
                    </Link>

                    <nav className="ml-5 hidden h-full items-center gap-1 md:flex" aria-label={__('Primary navigation', 'jooosi-fon')}>
                        {mainNavigation.map(({ label, to }) => (
                            <NavLink
                                key={to}
                                to={to}
                                className={({ isActive }) => cn(
                                    'relative inline-flex h-full items-center px-3 text-sm font-medium text-muted-foreground no-underline transition-colors hover:text-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-inset',
                                    isActive && 'text-foreground after:absolute after:inset-x-3 after:bottom-0 after:h-0.5 after:rounded-full after:bg-primary',
                                )}
                            >
                                {label}
                            </NavLink>
                        ))}
                    </nav>

                    <div className="ml-auto flex items-center gap-2">
                        <Button type="button" variant="outline" className="hidden min-w-44 justify-between text-muted-foreground lg:flex" onClick={() => setSearchOpen(true)}>
                            <span className="inline-flex items-center gap-2"><SearchIcon aria-hidden="true" />{__('Quick search', 'jooosi-fon')}</span>
                            <Kbd>⌘ K</Kbd>
                        </Button>
                        <Button type="button" variant="outline" size="icon" className="lg:hidden" aria-label={__('Open quick search', 'jooosi-fon')} onClick={() => setSearchOpen(true)}>
                            <SearchIcon aria-hidden="true" />
                        </Button>
                        <CreateMenu />
                        <span className="hidden md:inline-flex"><UtilityMenu /></span>
                        <span className="md:hidden"><UtilityMenu mobile /></span>
                    </div>
                </div>
            </header>

            <main className="mx-auto w-full max-w-[1600px] px-4 py-6 sm:px-6 sm:py-8 lg:px-8">
                <Outlet />
            </main>

            <CommandPalette open={searchOpen} onOpenChange={setSearchOpen} />
        </div>
    );
}
