import { __, _n, sprintf } from '@wordpress/i18n';
import type {
    ColumnDef,
    PaginationState,
    RowSelectionState,
    Updater,
} from '@tanstack/react-table';
import { useTable } from '@tanstack/react-table';
import {
    ArchiveRestoreIcon,
    BoxesIcon,
    DownloadIcon,
    FileUpIcon,
    RefreshCwIcon,
    SearchIcon,
    ShieldCheckIcon,
    SparklesIcon,
    TriangleAlertIcon,
    Trash2Icon,
    XIcon,
} from 'lucide-react';
import {
    type FormEvent,
    useCallback,
    useEffect,
    useMemo,
    useRef,
    useState,
} from 'react';
import { Link, useLocation, useSearchParams } from 'react-router-dom';
import { toast } from 'sonner';

import { GoogleFontsIcon } from '@/components/google-fonts-icon';
import {
    Alert,
    AlertAction,
    AlertDescription,
    AlertTitle,
} from '@/components/reui/alert';
import { Badge } from '@/components/reui/badge';
import {
    DataGrid,
    dataGridFeatures,
    type DataGridFeatures,
} from '@/components/reui/data-grid/data-grid';
import { DataGridPagination } from '@/components/reui/data-grid/data-grid-pagination';
import { DataGridScrollArea } from '@/components/reui/data-grid/data-grid-scroll-area';
import {
    DataGridTable,
    DataGridTableRowSelect,
    DataGridTableRowSelectAll,
} from '@/components/reui/data-grid/data-grid-table';
import { Frame, FrameFooter, FramePanel } from '@/components/reui/frame';
import { Button, buttonVariants } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
    InputGroup,
    InputGroupAddon,
    InputGroupButton,
    InputGroupInput,
    InputGroupText,
} from '@/components/ui/input-group';
import { Separator } from '@/components/ui/separator';
import { Spinner } from '@/components/ui/spinner';
import { Skeleton } from '@/components/ui/skeleton';
import { Switch } from '@/components/ui/switch';
import { Tooltip, TooltipContent, TooltipTrigger } from '@/components/ui/tooltip';
import { api, getErrorMessage } from '@/lib/api';
import {
    createFontFaceCss,
    fontCssVariable,
    type FontFace,
} from '@/lib/font-preview';

interface FontItem {
    id: number;
    type: 'custom' | 'google-fonts' | 'adobe-fonts';
    title: string;
    slug: string;
    family: string;
    metadata: {
        display?: string;
    };
    font_faces: FontFace[];
    status: boolean;
    created_at: number;
    updated_at: number;
    deleted_at: number | null;
}

interface FontListMeta {
    page: number;
    per_page: number;
    search: string | null;
    total_pages: number;
    from: number | null;
    to: number | null;
    total_filtered: number;
    total_deleted: number;
    total_exists: number;
    infrastructure: {
        google_fonts: {
            total: number;
            cache_updated_at: number | null;
        };
        active_fonts: {
            total: number;
        };
        adobe_fonts: {
            active: boolean;
            total: number;
        };
        integrations: {
            total: number;
        };
    } | null;
}

interface FontListResponse {
    data: FontItem[];
    meta: FontListMeta;
}

interface FontExport {
    export_time: number;
    [key: string]: unknown;
}

const defaultMeta: FontListMeta = {
    page: 1,
    per_page: 20,
    search: null,
    total_pages: 0,
    from: null,
    to: null,
    total_filtered: 0,
    total_deleted: 0,
    total_exists: 0,
    infrastructure: null,
};

function positiveInteger(value: string | null, fallback: number) {
    const number = Number(value);
    return Number.isInteger(number) && number > 0 ? number : fallback;
}

function editPath(item: FontItem) {
    return item.type === 'google-fonts'
        ? `/fonts/edit/${item.id}/google-fonts`
        : `/fonts/edit/${item.id}/custom`;
}

function formatRelativeTime(unixSeconds: number) {
    const seconds = unixSeconds - Math.floor(Date.now() / 1000);
    const formatter = new Intl.RelativeTimeFormat(undefined, { numeric: 'auto' });
    const ranges: Array<[Intl.RelativeTimeFormatUnit, number]> = [
        ['year', 31_536_000],
        ['month', 2_592_000],
        ['week', 604_800],
        ['day', 86_400],
        ['hour', 3_600],
        ['minute', 60],
    ];

    for (const [unit, duration] of ranges) {
        if (Math.abs(seconds) >= duration) {
            return formatter.format(Math.round(seconds / duration), unit);
        }
    }

    return formatter.format(seconds, 'second');
}

function formatDate(unixSeconds: number) {
    return new Intl.DateTimeFormat(undefined, { dateStyle: 'medium' }).format(
        new Date(unixSeconds * 1000),
    );
}

function sourceLabel(type: FontItem['type']) {
    switch (type) {
        case 'google-fonts':
            return __('Google Fonts', 'jooosi-fon');
        case 'adobe-fonts':
            return __('Adobe Fonts', 'jooosi-fon');
        default:
            return __('Custom', 'jooosi-fon');
    }
}

function AdobeFontsIcon() {
    return (
        <svg viewBox="0 0 24 24" className="size-5 text-foreground" aria-hidden="true">
            <path
                fill="currentColor"
                d="M19.764.375H4.236A4.236 4.236 0 0 0 0 4.611V19.39a4.236 4.236 0 0 0 4.236 4.236h15.528A4.236 4.236 0 0 0 24 19.389V4.61A4.236 4.236 0 0 0 19.764.375zm-3.25 6.536c-.242 0-.364-.181-.44-.439-.257-.97-.59-1.257-.787-1.257s-.5.364-.833 1.12c-.417.97-.754 1.97-1.007 2.994l1.732-.002c.11.28.01.6-.238.772H13.23c-.56 1.878-1.031 3.688-1.592 5.46a9.676 9.676 0 0 1-1.105 2.56 3.144 3.144 0 0 1-2.484 1.332c-.773 0-1.53-.363-1.53-1.166.036-.503.424-.91.924-.97a.46.46 0 0 1 .424.243c.379.682.742 1.075.909 1.075.166 0 .303-.227.575-1.211l1.988-7.322-1.43-.002a.685.685 0 0 1 .227-.774h1.423c.257-.895.609-1.76 1.048-2.58a3.786 3.786 0 0 1 3.272-2.195c1.136 0 1.605.545 1.605 1.242a1.144 1.144 0 0 1-.97 1.12z"
            />
        </svg>
    );
}

function FontSourceIcon({ type }: { type: FontItem['type'] }) {
    return (
        <span className="grid size-9 shrink-0 place-items-center rounded-lg bg-muted font-serif text-lg font-semibold text-foreground">
            {type === 'google-fonts' ? (
                <GoogleFontsIcon />
            ) : type === 'adobe-fonts' ? (
                <AdobeFontsIcon />
            ) : (
                'Aa'
            )}
        </span>
    );
}

export function FontsPage() {
    const location = useLocation();
    const [searchParams, setSearchParams] = useSearchParams();
    const page = positiveInteger(searchParams.get('page'), 1);
    const pageSize = positiveInteger(searchParams.get('per_page'), 20);
    const softDeleted = searchParams.get('soft_deleted') === '1';
    const search = searchParams.get('search')?.trim() ?? '';

    const [items, setItems] = useState<FontItem[]>([]);
    const [meta, setMeta] = useState<FontListMeta>(defaultMeta);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState<string | null>(null);
    const [refreshToken, setRefreshToken] = useState(0);
    const [searchValue, setSearchValue] = useState(search);
    const [rowSelection, setRowSelection] = useState<RowSelectionState>({});
    const [pending, setPending] = useState<Record<string, boolean>>({});
    const previewTextRef = useRef(__('The quick brown fox jumps over a lazy dog', 'jooosi-fon'));
    const [previewSize, setPreviewSize] = useState(18);
    const [previewWeight, setPreviewWeight] = useState(400);
    const importedAt = (location.state as { importedAt?: number } | null)?.importedAt;

    const [pagination, setPagination] = useState<PaginationState>({
        pageIndex: page - 1,
        pageSize,
    });

    const infrastructureCards = useMemo(() => {
        if (!meta.infrastructure) {
            return [
                { label: __('Google Fonts', 'jooosi-fon'), value: undefined, detail: undefined, icon: RefreshCwIcon },
                { label: __('Active families', 'jooosi-fon'), value: undefined, detail: undefined, icon: ShieldCheckIcon },
                { label: __('Adobe Fonts', 'jooosi-fon'), value: undefined, detail: undefined, icon: SparklesIcon },
                { label: __('Integrations', 'jooosi-fon'), value: undefined, detail: undefined, icon: BoxesIcon },
            ];
        }

        const { active_fonts: activeFonts, adobe_fonts: adobeFonts, google_fonts: googleFonts, integrations } = meta.infrastructure;
        const numberFormatter = new Intl.NumberFormat();

        return [
            {
                label: __('Google Fonts', 'jooosi-fon'),
                value: numberFormatter.format(googleFonts.total),
                detail: googleFonts.cache_updated_at
                    ? sprintf(__('Catalog cached %s', 'jooosi-fon'), formatDate(googleFonts.cache_updated_at))
                    : __('Catalog cache not created yet', 'jooosi-fon'),
                icon: RefreshCwIcon,
            },
            {
                label: __('Active families', 'jooosi-fon'),
                value: numberFormatter.format(activeFonts.total),
                detail: sprintf(__('of %s in your library', 'jooosi-fon'), numberFormatter.format(meta.total_exists)),
                icon: ShieldCheckIcon,
            },
            {
                label: __('Adobe Fonts', 'jooosi-fon'),
                value: adobeFonts.active ? __('Active', 'jooosi-fon') : __('Inactive', 'jooosi-fon'),
                detail: adobeFonts.active
                    ? sprintf(_n('%s synced family', '%s synced families', adobeFonts.total, 'jooosi-fon'), numberFormatter.format(adobeFonts.total))
                    : __('Connect a web project in Settings', 'jooosi-fon'),
                icon: SparklesIcon,
            },
            {
                label: __('Integrations', 'jooosi-fon'),
                value: numberFormatter.format(integrations.total),
                detail: __('builders and editors supported', 'jooosi-fon'),
                icon: BoxesIcon,
            },
        ];
    }, [meta.infrastructure, meta.total_exists]);

    useEffect(() => {
        setSearchValue(search);
    }, [search]);

    useEffect(() => {
        setPagination({ pageIndex: page - 1, pageSize });
    }, [page, pageSize]);

    const updateRouteQuery = useCallback((updates: Record<string, string | number | null>) => {
        const next = new URLSearchParams(searchParams);
        Object.entries(updates).forEach(([key, value]) => {
            if (value === null || value === '' || (key === 'soft_deleted' && Number(value) === 0)) {
                next.delete(key);
            } else {
                next.set(key, String(value));
            }
        });
        setSearchParams(next);
    }, [searchParams, setSearchParams]);

    const refresh = useCallback(() => setRefreshToken((value) => value + 1), []);

    useEffect(() => {
        const controller = new AbortController();
        setLoading(true);
        setError(null);

        api.get<FontListResponse>('/fonts/index', {
            params: {
                page,
                per_page: pageSize,
                search,
                soft_deleted: softDeleted ? 1 : 0,
            },
            signal: controller.signal,
        }).then((response) => {
            setItems(response.data.data);
            setMeta(response.data.meta);
            setRowSelection({});
        }).catch((requestError: unknown) => {
            if (!controller.signal.aborted) {
                setError(getErrorMessage(requestError));
            }
        }).finally(() => {
            if (!controller.signal.aborted) {
                setLoading(false);
            }
        });

        return () => controller.abort();
    }, [importedAt, page, pageSize, refreshToken, search, softDeleted]);

    useEffect(() => {
        const controller = new AbortController();
        api.get<{ options?: { adobe_fonts?: { project_id?: string } } }>('/setting/option/index', {
            signal: controller.signal,
        }).then((response) => {
            const projectId = response.data.options?.adobe_fonts?.project_id;
            if (!projectId) {
                return;
            }

            let link = document.querySelector<HTMLLinkElement>('#typekit-css');
            if (!link) {
                link = document.createElement('link');
                link.id = 'typekit-css';
                link.rel = 'stylesheet';
                document.head.appendChild(link);
            }
            link.href = `https://use.typekit.net/${encodeURIComponent(projectId)}.css`;
        }).catch(() => undefined);

        return () => controller.abort();
    }, []);

    useEffect(() => {
        const style = document.createElement('style');
        style.id = 'jooosi-fon-react-preview';
        style.textContent = items.map((item) => createFontFaceCss(
            item.family,
            item.font_faces,
            item.metadata.display,
        )).join('\n\n');
        document.head.appendChild(style);

        return () => style.remove();
    }, [items]);

    const setPendingAction = useCallback((key: string, value: boolean) => {
        setPending((current) => ({ ...current, [key]: value }));
    }, []);

    const handlePreviewInput = useCallback((event: FormEvent<HTMLDivElement>) => {
        const editor = event.currentTarget;
        const nextText = editor.textContent ?? '';
        previewTextRef.current = nextText;

        editor.closest('[data-font-preview-scope]')
            ?.querySelectorAll<HTMLElement>('[data-font-preview]')
            .forEach((preview) => {
                if (preview !== editor && preview.textContent !== nextText) {
                    preview.textContent = nextText;
                }
            });
    }, []);

    const updateStatus = useCallback(async (item: FontItem, status: boolean) => {
        if (item.status === status) {
            return;
        }

        const key = `status:${item.id}`;
        setPendingAction(key, true);
        try {
            await api.patch(`/fonts/update-status/${item.id}`, { status });
            setItems((current) => current.map((candidate) => (
                candidate.id === item.id ? { ...candidate, status } : candidate
            )));
            setMeta((current) => {
                if (!current.infrastructure) {
                    return current;
                }

                return {
                    ...current,
                    infrastructure: {
                        ...current.infrastructure,
                        active_fonts: {
                            total: Math.max(0, current.infrastructure.active_fonts.total + (status ? 1 : -1)),
                        },
                    },
                };
            });
            toast.success(status
                ? __('Font activated.', 'jooosi-fon')
                : __('Font deactivated.', 'jooosi-fon'));
        } catch (requestError) {
            toast.error(getErrorMessage(requestError));
        } finally {
            setPendingAction(key, false);
        }
    }, [setPendingAction]);

    const deleteFont = useCallback(async (
        item: FontItem,
        options: { confirmAction?: boolean; refreshAfter?: boolean } = {},
    ) => {
        const { confirmAction = true, refreshAfter = true } = options;
        if (confirmAction && !window.confirm(__('Are you sure you want to delete this font?', 'jooosi-fon'))) {
            return;
        }

        const key = `delete:${item.id}`;
        setPendingAction(key, true);
        try {
            await api.post(`/fonts/delete/${item.id}`);
            toast.success(item.deleted_at === null
                ? __('Font moved to the Trash.', 'jooosi-fon')
                : __('Font permanently deleted.', 'jooosi-fon'));
            if (refreshAfter) {
                refresh();
            }
        } catch (requestError) {
            toast.error(getErrorMessage(requestError));
        } finally {
            setPendingAction(key, false);
        }
    }, [refresh, setPendingAction]);

    const restoreFont = useCallback(async (
        item: FontItem,
        options: { refreshAfter?: boolean } = {},
    ) => {
        const { refreshAfter = true } = options;
        const key = `restore:${item.id}`;
        setPendingAction(key, true);
        try {
            await api.post(`/fonts/restore/${item.id}`);
            toast.success(__('Font restored.', 'jooosi-fon'));
            if (refreshAfter) {
                refresh();
            }
        } catch (requestError) {
            toast.error(getErrorMessage(requestError));
        } finally {
            setPendingAction(key, false);
        }
    }, [refresh, setPendingAction]);

    const columns = useMemo<ColumnDef<DataGridFeatures, FontItem>[]>(() => [
        {
            id: 'select',
            header: () => <DataGridTableRowSelectAll />,
            cell: ({ row }) => <DataGridTableRowSelect row={row} />,
            enableSorting: false,
            size: 44,
        },
        {
            id: 'status',
            header: __('Status', 'jooosi-fon'),
            cell: ({ row }) => {
                const item = row.original;
                if (item.deleted_at !== null) {
                    return <Badge variant="warning-light">{__('Trashed', 'jooosi-fon')}</Badge>;
                }

                return (
                    <Tooltip>
                        <TooltipTrigger
                            render={(
                                <Switch
                                    checked={item.status}
                                    disabled={pending[`status:${item.id}`]}
                                    onCheckedChange={(checked) => void updateStatus(item, checked)}
                                    aria-label={sprintf(
                                        item.status
                                            ? __('Deactivate %s', 'jooosi-fon')
                                            : __('Activate %s', 'jooosi-fon'),
                                        item.title,
                                    )}
                                />
                            )}
                        />
                        <TooltipContent>
                            {item.status ? __('Active', 'jooosi-fon') : __('Inactive', 'jooosi-fon')}
                        </TooltipContent>
                    </Tooltip>
                );
            },
            size: 80,
        },
        {
            accessorKey: 'title',
            header: __('Font', 'jooosi-fon'),
            cell: ({ row }) => {
                const item = row.original;
                const sourceIcon = <FontSourceIcon type={item.type} />;
                const iconLinkLabel = item.type === 'adobe-fonts'
                    ? sprintf(__('View %1$s on %2$s', 'jooosi-fon'), item.title, sourceLabel(item.type))
                    : sprintf(__('Edit %1$s (%2$s)', 'jooosi-fon'), item.title, sourceLabel(item.type));
                const titleClassName = 'block truncate font-medium text-foreground underline-offset-4 hover:underline focus-visible:rounded-sm focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring';
                const titleElement = item.type === 'adobe-fonts' ? (
                    <a
                        href={`https://fonts.adobe.com/fonts/${encodeURIComponent(item.slug)}`}
                        target="_blank"
                        rel="noopener noreferrer"
                        className={titleClassName}
                    >
                        {item.title}
                    </a>
                ) : item.deleted_at === null ? (
                    <Link className={titleClassName} to={editPath(item)}>
                        {item.title}
                    </Link>
                ) : (
                    <span className={titleClassName} tabIndex={0}>{item.title}</span>
                );

                return (
                    <div className="flex min-w-0 items-center gap-3">
                        {item.deleted_at !== null ? (
                            <span title={sourceLabel(item.type)}>{sourceIcon}</span>
                        ) : item.type === 'adobe-fonts' ? (
                            <a
                                href={`https://fonts.adobe.com/fonts/${encodeURIComponent(item.slug)}`}
                                target="_blank"
                                rel="noopener noreferrer"
                                aria-label={iconLinkLabel}
                                className="rounded-lg outline-none focus-visible:ring-2 focus-visible:ring-ring"
                            >
                                {sourceIcon}
                            </a>
                        ) : (
                            <Link
                                to={editPath(item)}
                                aria-label={iconLinkLabel}
                                className="rounded-lg outline-none focus-visible:ring-2 focus-visible:ring-ring"
                            >
                                {sourceIcon}
                            </Link>
                        )}
                        <div className="min-w-0">
                            <Tooltip>
                                <TooltipTrigger render={titleElement} />
                                <TooltipContent>
                                    {sprintf(__('Font ID: %d', 'jooosi-fon'), item.id)}
                                </TooltipContent>
                            </Tooltip>
                        </div>
                    </div>
                );
            },
            size: 220,
        },
        {
            accessorKey: 'family',
            header: __('Font family', 'jooosi-fon'),
            cell: ({ row }) => (
                <Tooltip>
                    <TooltipTrigger
                        render={<span className="inline-block max-w-full truncate font-medium align-bottom focus-visible:rounded-sm focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring" tabIndex={0} />}
                    >
                        {row.original.family}
                    </TooltipTrigger>
                    <TooltipContent>
                        {sprintf(__('CSS variable: %s', 'jooosi-fon'), `var(${fontCssVariable(row.original.family)})`)}
                    </TooltipContent>
                </Tooltip>
            ),
            size: 190,
        },
        {
            accessorKey: 'updated_at',
            header: __('Modified', 'jooosi-fon'),
            cell: ({ row }) => (
                <time
                    dateTime={new Date(row.original.updated_at * 1000).toISOString()}
                    title={new Date(row.original.updated_at * 1000).toLocaleString()}
                    className="text-sm text-muted-foreground"
                >
                    {formatRelativeTime(row.original.updated_at)}
                </time>
            ),
            size: 120,
        },
        {
            id: 'preview',
            header: __('Preview', 'jooosi-fon'),
            cell: ({ row }) => (
                <div
                    role="textbox"
                    aria-label={sprintf(__('Preview %s', 'jooosi-fon'), row.original.title)}
                    aria-multiline="false"
                    contentEditable
                    suppressContentEditableWarning
                    data-font-preview
                    onInput={handlePreviewInput}
                    className="w-full min-w-0 truncate rounded-md px-2 py-1 outline-none focus-visible:ring-2 focus-visible:ring-ring"
                    style={{
                        fontFamily: row.original.family,
                        fontSize: `${previewSize}px`,
                        fontWeight: previewWeight,
                    }}
                >
                    {previewTextRef.current}
                </div>
            ),
            size: 260,
        },
        {
            id: 'actions',
            header: '',
            cell: ({ row }) => {
                const item = row.original;
                if (item.type === 'adobe-fonts') {
                    return null;
                }

                const deleteLabel = item.deleted_at === null
                    ? __('Move to Trash', 'jooosi-fon')
                    : __('Delete permanently', 'jooosi-fon');

                return (
                    <div className="flex items-center justify-end gap-1">
                        {item.deleted_at !== null && (
                            <Tooltip>
                                <TooltipTrigger
                                    render={(
                                        <Button
                                            type="button"
                                            variant="ghost"
                                            size="icon-sm"
                                            disabled={pending[`restore:${item.id}`]}
                                            onClick={() => void restoreFont(item)}
                                            aria-label={sprintf(__('Restore %s', 'jooosi-fon'), item.title)}
                                        />
                                    )}
                                >
                                    <ArchiveRestoreIcon aria-hidden="true" />
                                </TooltipTrigger>
                                <TooltipContent>{__('Restore', 'jooosi-fon')}</TooltipContent>
                            </Tooltip>
                        )}
                        <Tooltip>
                            <TooltipTrigger
                                render={(
                                    <Button
                                        type="button"
                                        variant="ghost"
                                        size="icon-sm"
                                        disabled={pending[`delete:${item.id}`]}
                                        onClick={() => void deleteFont(item)}
                                        aria-label={sprintf(__('%1$s: %2$s', 'jooosi-fon'), deleteLabel, item.title)}
                                    />
                                )}
                            >
                                <Trash2Icon aria-hidden="true" className="text-destructive" />
                            </TooltipTrigger>
                            <TooltipContent>{deleteLabel}</TooltipContent>
                        </Tooltip>
                    </div>
                );
            },
            size: softDeleted ? 88 : 56,
        },
    ], [deleteFont, handlePreviewInput, pending, previewSize, previewWeight, restoreFont, softDeleted, updateStatus]);

    const handlePaginationChange = useCallback((updater: Updater<PaginationState>) => {
        setPagination((current) => {
            const next = typeof updater === 'function' ? updater(current) : updater;
            updateRouteQuery({
                page: next.pageIndex + 1,
                per_page: next.pageSize,
            });
            return next;
        });
    }, [updateRouteQuery]);

    const table = useTable({
        features: dataGridFeatures,
        columns,
        data: items,
        getRowId: (row: FontItem) => String(row.id),
        pageCount: meta.total_pages,
        rowCount: meta.total_filtered,
        manualPagination: true,
        state: {
            pagination,
            rowSelection,
        },
        enableRowSelection: true,
        onRowSelectionChange: setRowSelection,
        onPaginationChange: handlePaginationChange,
    });

    const selectedItems = useMemo(() => (
        items.filter((item) => rowSelection[String(item.id)])
    ), [items, rowSelection]);

    const exportFonts = useCallback(async (fonts: FontItem[]) => {
        if (fonts.length === 0) {
            toast.error(__('Select at least one font to export.', 'jooosi-fon'));
            return;
        }

        try {
            const response = await api.post<{ data: FontExport }>('/fonts/export', {
                items: fonts.map((item) => item.id),
            });
            const blob = new Blob([JSON.stringify(response.data.data)], { type: 'application/json' });
            const href = URL.createObjectURL(blob);
            const anchor = document.createElement('a');
            anchor.href = href;
            anchor.download = `jooosi-fon-exported-${response.data.data.export_time}.json`;
            anchor.click();
            URL.revokeObjectURL(href);
            toast.success(__('Font export created.', 'jooosi-fon'));
        } catch (requestError) {
            toast.error(getErrorMessage(requestError));
        }
    }, []);

    const runBulkAction = useCallback(async (action: 'activate' | 'deactivate' | 'restore' | 'delete' | 'export') => {
        if (selectedItems.length === 0) {
            toast.error(__('Select at least one font first.', 'jooosi-fon'));
            return;
        }

        if (action === 'export') {
            await exportFonts(selectedItems);
            setRowSelection({});
            return;
        }

        const actionLabels = {
            activate: __('Activate', 'jooosi-fon'),
            deactivate: __('Deactivate', 'jooosi-fon'),
            restore: __('Restore', 'jooosi-fon'),
            delete: softDeleted
                ? __('Delete permanently', 'jooosi-fon')
                : __('Move to Trash', 'jooosi-fon'),
        };
        const confirmation = sprintf(
            __('Apply “%1$s” to %2$d selected font(s)?', 'jooosi-fon'),
            actionLabels[action],
            selectedItems.length,
        );
        if (!window.confirm(confirmation)) {
            return;
        }

        for (const item of selectedItems) {
            if (action === 'activate') {
                await updateStatus(item, true);
            } else if (action === 'deactivate') {
                await updateStatus(item, false);
            } else if (action === 'restore') {
                await restoreFont(item, { refreshAfter: false });
            } else if (item.type !== 'adobe-fonts') {
                await deleteFont(item, { confirmAction: false, refreshAfter: false });
            }
        }
        if (action === 'delete' || action === 'restore') {
            refresh();
        }
        setRowSelection({});
    }, [deleteFont, exportFonts, refresh, restoreFont, selectedItems, softDeleted, updateStatus]);

    const submitSearch = (event: FormEvent) => {
        event.preventDefault();
        updateRouteQuery({ page: 1, search: searchValue });
    };

    return (
        <section className="min-w-0 space-y-6">
            <header className="relative overflow-hidden rounded-2xl border bg-card shadow-xs">
                <div className="pointer-events-none absolute -right-16 -top-24 size-80 rounded-full bg-primary/[0.07] blur-3xl" aria-hidden="true" />
                <div className="relative p-5 sm:p-7">
                    <div className="max-w-3xl">
                        <div className="mb-3 flex flex-wrap items-center gap-2">
                            <Badge variant="primary-light" radius="full">
                                <ShieldCheckIcon aria-hidden="true" />
                                {__('Privacy-first hosting', 'jooosi-fon')}
                            </Badge>
                            <span className="text-xs text-muted-foreground">{__('Custom · Google · Adobe', 'jooosi-fon')}</span>
                        </div>
                        <h1 className="m-0 text-3xl font-semibold tracking-[-0.035em] sm:text-4xl">{__('Your font infrastructure, in one place.', 'jooosi-fon')}</h1>
                        <p className="mb-0 mt-3 max-w-2xl text-sm leading-6 text-muted-foreground sm:text-base">
                            {__('Add fonts to your website, choose which ones are active, and use them consistently across WordPress.', 'jooosi-fon')}
                        </p>
                    </div>
                </div>

                <div className="relative grid border-t bg-muted/20 sm:grid-cols-2 xl:grid-cols-4" aria-busy={!meta.infrastructure} aria-live="polite">
                    {infrastructureCards.map(({ label, value, detail, icon: Icon }) => (
                        <div key={label} className="flex items-center gap-3 border-b p-4 last:border-b-0 sm:border-r sm:[&:nth-child(2)]:border-r-0 xl:border-b-0 xl:[&:nth-child(2)]:border-r xl:last:border-r-0">
                            <span className="grid size-10 shrink-0 place-items-center rounded-xl border bg-background shadow-xs"><Icon aria-hidden="true" className="size-4 text-muted-foreground" /></span>
                            <div className="min-w-0">
                                {value === undefined || detail === undefined ? (
                                    <>
                                        <Skeleton className="h-5 w-28" />
                                        <Skeleton className="mt-1.5 h-3 w-36 max-w-full" />
                                    </>
                                ) : (
                                    <>
                                        <p className="m-0 flex items-baseline gap-2"><strong className="text-xl font-semibold tabular-nums">{value}</strong><span className="text-xs font-medium text-foreground">{label}</span></p>
                                        <p className="mb-0 mt-0.5 truncate text-xs text-muted-foreground" title={detail}>{detail}</p>
                                    </>
                                )}
                            </div>
                        </div>
                    ))}
                </div>
            </header>

            <DataGrid
                table={table}
                recordCount={meta.total_filtered}
                isLoading={loading}
                emptyMessage={(
                    <div className="flex flex-col items-center gap-3 py-12 text-center">
                        <FileUpIcon aria-hidden="true" className="size-8 text-muted-foreground" />
                        <div>
                            <p className="m-0 font-medium">{__('No fonts found', 'jooosi-fon')}</p>
                            <p className="mb-0 mt-1 text-sm text-muted-foreground">
                                {search
                                    ? __('Try a different search or clear the current filter.', 'jooosi-fon')
                                    : softDeleted
                                        ? __('The Trash is empty.', 'jooosi-fon')
                                        : __('Add a custom font or import one from Google Fonts.', 'jooosi-fon')}
                            </p>
                        </div>
                        {search ? (
                            <Button
                                type="button"
                                size="sm"
                                variant="outline"
                                onClick={() => {
                                    setSearchValue('');
                                    updateRouteQuery({ page: 1, search: null });
                                }}
                            >
                                {__('Clear search', 'jooosi-fon')}
                            </Button>
                        ) : softDeleted ? (
                            <Button type="button" size="sm" variant="outline" onClick={() => updateRouteQuery({ page: 1, soft_deleted: 0 })}>
                                {__('Return to library', 'jooosi-fon')}
                            </Button>
                        ) : (
                            <div className="flex flex-wrap justify-center gap-2">
                                <Link to="/fonts/create/custom" className={buttonVariants({ size: 'sm' })}>
                                    {__('Add custom font', 'jooosi-fon')}
                                </Link>
                                <Link to="/fonts/create/google-fonts" className={buttonVariants({ variant: 'outline', size: 'sm' })}>
                                    {__('Import Google Font', 'jooosi-fon')}
                                </Link>
                            </div>
                        )}
                    </div>
                )}
                tableLayout={{
                    dense: true,
                    headerBackground: true,
                    headerSticky: false,
                    width: 'fixed',
                    columnsResizable: false,
                }}
            >
                <Frame dense className="w-full" data-font-preview-scope>
                    <FramePanel className="p-0 shadow-none!">
                        <div className="flex flex-col gap-4 px-(--frame-panel-header-px) py-4 lg:flex-row lg:items-center lg:justify-between">
                            <div className="min-w-0">
                                <div className="flex items-center gap-2">
                                    <h2 className="m-0 text-lg font-semibold tracking-tight">{softDeleted ? __('Trash', 'jooosi-fon') : __('Font Library', 'jooosi-fon')}</h2>
                                    <Badge variant="secondary" size="sm" radius="full">{softDeleted ? meta.total_deleted : meta.total_filtered}</Badge>
                                </div>
                                <p className="mb-0 mt-1 text-xs text-muted-foreground">
                                    {softDeleted ? __('Restore a family or remove it permanently.', 'jooosi-fon') : __('Preview your fonts, update their details, and choose which ones are active on your website.', 'jooosi-fon')}
                                </p>
                            </div>

                            <form className="flex w-full min-w-0 flex-col gap-2 sm:flex-row lg:max-w-xl" onSubmit={submitSearch} role="search">
                                <InputGroup className="min-w-0 flex-1">
                                    <InputGroupAddon align="inline-start">
                                        <SearchIcon aria-hidden="true" className="text-muted-foreground" />
                                    </InputGroupAddon>
                                    <InputGroupInput
                                        type="search"
                                        value={searchValue}
                                        onChange={(event) => setSearchValue(event.target.value)}
                                        placeholder={__('Search title or family…', 'jooosi-fon')}
                                        aria-label={__('Search fonts', 'jooosi-fon')}
                                    />
                                    {searchValue && (
                                        <InputGroupAddon align="inline-end">
                                            <InputGroupButton
                                                type="button"
                                                size="icon-xs"
                                                aria-label={__('Clear search', 'jooosi-fon')}
                                                onClick={() => {
                                                    setSearchValue('');
                                                    updateRouteQuery({ page: 1, search: null });
                                                }}
                                            >
                                                <XIcon aria-hidden="true" />
                                            </InputGroupButton>
                                        </InputGroupAddon>
                                    )}
                                </InputGroup>
                                <Button type="submit" variant="outline">{__('Search', 'jooosi-fon')}</Button>
                                <Button type="button" variant="ghost" size="icon" onClick={refresh} aria-label={__('Refresh fonts', 'jooosi-fon')}>
                                    <RefreshCwIcon aria-hidden="true" className={loading ? 'animate-spin' : undefined} />
                                </Button>
                            </form>
                        </div>

                        <Separator />

                        <div className="flex flex-col gap-3 bg-muted/25 px-(--frame-panel-px) py-3 sm:flex-row sm:items-center sm:justify-between">
                            <div className="flex flex-wrap items-center gap-1">
                                <Button
                                    type="button"
                                    size="sm"
                                    variant={softDeleted ? 'ghost' : 'secondary'}
                                    onClick={() => updateRouteQuery({ page: 1, soft_deleted: 0 })}
                                    aria-pressed={!softDeleted}
                                >
                                    {__('All', 'jooosi-fon')} <span className="text-muted-foreground">{meta.total_exists}</span>
                                </Button>
                                <Button
                                    type="button"
                                    size="sm"
                                    variant={softDeleted ? 'secondary' : 'ghost'}
                                    onClick={() => updateRouteQuery({ page: 1, soft_deleted: 1 })}
                                    aria-pressed={softDeleted}
                                >
                                    {__('Trash', 'jooosi-fon')} <span className="text-muted-foreground">{meta.total_deleted}</span>
                                </Button>
                            </div>

                            <div className="flex flex-wrap items-center gap-2 text-sm">
                                <label htmlFor="preview-size" className="text-muted-foreground">{__('Size', 'jooosi-fon')}</label>
                                <InputGroup className="w-20">
                                    <InputGroupInput
                                        id="preview-size"
                                        type="number"
                                        min={8}
                                        max={96}
                                        value={previewSize}
                                        onChange={(event) => setPreviewSize(Number(event.target.value) || 18)}
                                        className="[appearance:textfield] [&::-webkit-inner-spin-button]:appearance-none [&::-webkit-outer-spin-button]:appearance-none"
                                    />
                                    <InputGroupAddon align="inline-end">
                                        <InputGroupText>px</InputGroupText>
                                    </InputGroupAddon>
                                </InputGroup>
                                <label htmlFor="preview-weight" className="text-muted-foreground">{__('Weight', 'jooosi-fon')}</label>
                                <Input
                                    id="preview-weight"
                                    type="number"
                                    min={1}
                                    max={1000}
                                    value={previewWeight}
                                    onChange={(event) => setPreviewWeight(Number(event.target.value) || 400)}
                                    className="h-8 w-20 [appearance:textfield] [&::-webkit-inner-spin-button]:appearance-none [&::-webkit-outer-spin-button]:appearance-none"
                                />
                            </div>
                        </div>

                        {selectedItems.length > 0 && (
                            <>
                                <Separator />
                                <div className="flex flex-col gap-3 bg-muted/25 px-(--frame-panel-px) py-3 lg:flex-row lg:items-center lg:justify-between">
                                    <div className="flex min-w-0 flex-col gap-0.5">
                                        <span className="text-sm font-medium">
                                            {sprintf(_n('%d font selected', '%d fonts selected', selectedItems.length, 'jooosi-fon'), selectedItems.length)}
                                        </span>
                                        <span className="text-xs text-muted-foreground">
                                            {softDeleted
                                                ? __('Restore the selection or remove it permanently.', 'jooosi-fon')
                                                : __('Update availability, export, or move the selection to Trash.', 'jooosi-fon')}
                                        </span>
                                    </div>
                                    <div className="flex flex-wrap items-center gap-2">
                                        {!softDeleted ? (
                                            <>
                                                <Button type="button" size="sm" onClick={() => void runBulkAction('activate')}>
                                                    <ShieldCheckIcon data-icon="inline-start" aria-hidden="true" />
                                                    {__('Activate', 'jooosi-fon')}
                                                </Button>
                                                <Button type="button" size="sm" variant="outline" onClick={() => void runBulkAction('deactivate')}>
                                                    {__('Deactivate', 'jooosi-fon')}
                                                </Button>
                                                <Button type="button" size="sm" variant="outline" onClick={() => void runBulkAction('export')}>
                                                    <DownloadIcon data-icon="inline-start" aria-hidden="true" />
                                                    {__('Export', 'jooosi-fon')}
                                                </Button>
                                                <Button type="button" size="sm" variant="destructive" onClick={() => void runBulkAction('delete')}>
                                                    <Trash2Icon data-icon="inline-start" aria-hidden="true" />
                                                    {__('Move to Trash', 'jooosi-fon')}
                                                </Button>
                                            </>
                                        ) : (
                                            <>
                                                <Button type="button" size="sm" onClick={() => void runBulkAction('restore')}>
                                                    <ArchiveRestoreIcon data-icon="inline-start" aria-hidden="true" />
                                                    {__('Restore', 'jooosi-fon')}
                                                </Button>
                                                <Button type="button" size="sm" variant="destructive" onClick={() => void runBulkAction('delete')}>
                                                    <Trash2Icon data-icon="inline-start" aria-hidden="true" />
                                                    {__('Delete permanently', 'jooosi-fon')}
                                                </Button>
                                            </>
                                        )}
                                        <Button type="button" size="sm" variant="ghost" onClick={() => setRowSelection({})}>
                                            {__('Clear', 'jooosi-fon')}
                                        </Button>
                                    </div>
                                </div>
                            </>
                        )}

                        {error && (
                            <div className="p-3">
                                <Alert variant="destructive" aria-live="polite">
                                    <TriangleAlertIcon aria-hidden="true" />
                                    <AlertTitle>{__('Fonts could not be loaded.', 'jooosi-fon')}</AlertTitle>
                                    <AlertDescription>{error}</AlertDescription>
                                    <AlertAction>
                                        <Button type="button" variant="outline" size="sm" onClick={refresh}>{__('Retry', 'jooosi-fon')}</Button>
                                    </AlertAction>
                                </Alert>
                            </div>
                        )}

                        <Separator />

                        <DataGridScrollArea>
                            <DataGridTable />
                        </DataGridScrollArea>

                        <Separator />

                        <FrameFooter>
                            <DataGridPagination
                                sizes={[10, 20, 50, 100]}
                                rowsPerPageLabel={__('Rows per page', 'jooosi-fon')}
                                previousPageLabel={__('Go to previous page', 'jooosi-fon')}
                                nextPageLabel={__('Go to next page', 'jooosi-fon')}
                                pageLabel={__('Go to page {page}', 'jooosi-fon')}
                                previousPagesLabel={__('Go to previous pages', 'jooosi-fon')}
                                nextPagesLabel={__('Go to next pages', 'jooosi-fon')}
                                info={sprintf(__('%1$s – %2$s of %3$s', 'jooosi-fon'), '{from}', '{to}', '{count}')}
                            />
                        </FrameFooter>

                        {Object.values(pending).some(Boolean) && (
                            <>
                                <Separator />
                                <div className="flex items-center gap-2 px-(--frame-panel-px) py-2.5 text-xs text-muted-foreground" role="status" aria-live="polite">
                                    <Spinner />
                                    {__('Saving font changes…', 'jooosi-fon')}
                                </div>
                            </>
                        )}
                    </FramePanel>
                </Frame>
            </DataGrid>
        </section>
    );
}
