import { __ } from '@wordpress/i18n';
import { ArrowLeftIcon } from 'lucide-react';
import type { ReactNode } from 'react';
import { Link } from 'react-router-dom';

import { buttonVariants } from '@/components/ui/button';
import { cn } from '@/lib/utils';

interface PageHeaderProps {
    title: string;
    description: string;
    eyebrow?: string;
    backTo?: string;
    actions?: ReactNode;
}

export function PageHeader({ title, description, eyebrow, backTo, actions }: PageHeaderProps) {
    return (
        <header className="relative overflow-hidden rounded-2xl border bg-card shadow-xs">
            <div className="pointer-events-none absolute -right-20 -top-32 size-72 rounded-full bg-primary/6 blur-3xl" aria-hidden="true" />
            <div className="relative flex flex-col gap-5 p-5 sm:p-6 lg:flex-row lg:items-center lg:justify-between">
                <div className="flex min-w-0 items-start gap-3.5">
                {backTo && (
                    <Link
                        to={backTo}
                        aria-label={__('Go back', 'jooosi-fon')}
                        className={cn(
                            buttonVariants({ variant: 'ghost', size: 'icon' }),
                            'mt-0.5 shrink-0 border bg-background shadow-xs',
                        )}
                    >
                        <ArrowLeftIcon aria-hidden="true" />
                    </Link>
                )}
                <div className="min-w-0">
                    {eyebrow && (
                        <p className="mb-2 flex items-center gap-2 text-xs font-semibold uppercase tracking-[0.14em] text-primary">
                            <span className="size-1.5 rounded-full bg-primary" aria-hidden="true" />
                            {eyebrow}
                        </p>
                    )}
                    <h1 className="m-0 text-2xl font-semibold tracking-[-0.025em] text-foreground sm:text-[2rem] sm:leading-tight">{title}</h1>
                    <p className="mb-0 mt-2 max-w-3xl text-sm leading-6 text-muted-foreground">{description}</p>
                </div>
            </div>
                {actions && <div className="flex shrink-0 flex-wrap gap-2 lg:justify-end">{actions}</div>}
            </div>
        </header>
    );
}
