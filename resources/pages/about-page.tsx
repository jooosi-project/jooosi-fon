import { __, sprintf } from '@wordpress/i18n';
import type { ComponentType } from 'react';
import {
    BadgeCheckIcon,
    CodeXmlIcon,
    ExternalLinkIcon,
    FileCode2Icon,
    HeartHandshakeIcon,
    LibraryBigIcon,
    PackageIcon,
    ShieldCheckIcon,
    SparklesIcon,
    UserStarIcon,
    UsersIcon,
    ZapIcon,
} from 'lucide-react';

import { Badge } from '@/components/reui/badge';
import { Frame, FrameDescription, FrameFooter, FrameHeader, FramePanel, FrameTitle } from '@/components/reui/frame';
import { buttonVariants } from '@/components/ui/button';
import { Separator } from '@/components/ui/separator';
import { cn } from '@/lib/utils';

import JooosiFonLogo from '../../jooosi-fon.svg?react';
import JooosiSponsorIcon from '@/icons/jooosi.svg?react';
import LiveCanvasSponsorIcon from '@/icons/livecanvas.svg?react';

type Capability = {
    title: string;
    description: string;
    icon: ComponentType<{ className?: string }>;
};

type Sponsor = {
    name: string;
    description: string;
    href: string;
    icon: ComponentType<{ className?: string }>;
};

const capabilities: Capability[] = [
    {
        title: __('Self-hosted fonts', 'jooosi-fon'),
        description: __('Keep custom and Google font files under your WordPress control.', 'jooosi-fon'),
        icon: LibraryBigIcon,
    },
    {
        title: __('Privacy-first delivery', 'jooosi-fon'),
        description: __('Serve fonts locally to support privacy-conscious sites and visitors.', 'jooosi-fon'),
        icon: ShieldCheckIcon,
    },
    {
        title: __('Flexible typography', 'jooosi-fon'),
        description: __('Manage custom, Google, Adobe, and variable fonts from one library.', 'jooosi-fon'),
        icon: SparklesIcon,
    },
    {
        title: __('Builder integrations', 'jooosi-fon'),
        description: __('Make your font library available across WordPress themes and builders.', 'jooosi-fon'),
        icon: CodeXmlIcon,
    },
];

const sponsorshipBenefits: Capability[] = [
    {
        title: __('Release visibility', 'jooosi-fon'),
        description: __('Your brand can appear with supported plugin releases.', 'jooosi-fon'),
        icon: PackageIcon,
    },
    {
        title: __('Project documentation', 'jooosi-fon'),
        description: __('Sponsors are recognized across project documentation.', 'jooosi-fon'),
        icon: FileCode2Icon,
    },
    {
        title: __('Admin recognition', 'jooosi-fon'),
        description: __('Featured support helps sustain ongoing development.', 'jooosi-fon'),
        icon: BadgeCheckIcon,
    },
    {
        title: __('Developer reach', 'jooosi-fon'),
        description: __('Connect with the wider WordPress developer community.', 'jooosi-fon'),
        icon: UsersIcon,
    },
];

const sponsors: Sponsor[] = [
    {
        name: 'Jooosi',
        description: __('Open-source tools and products for WordPress.', 'jooosi-fon'),
        href: 'https://jooo.si',
        icon: JooosiSponsorIcon,
    },
    {
        name: 'LiveCanvas',
        description: __('A visual site builder for WordPress.', 'jooosi-fon'),
        href: 'https://livecanvas.com',
        icon: LiveCanvasSponsorIcon,
    },
    {
        name: __('You, yes you!', 'jooosi-fon'),
        description: __('Your Jooosi Fon Pro purchase helps keep this open-source project moving. Thank you!', 'jooosi-fon'),
        href: 'https://fon.jooo.si',
        icon: UserStarIcon,
    },
];

export function AboutPage() {
    return (
        <div className="grid min-w-0 gap-6">
            <Frame stacked spacing="lg">
                <FrameHeader className="flex-col gap-5 sm:flex-row sm:items-center sm:justify-between">
                    <div className="flex min-w-0 items-center gap-4">
                        <span className="grid size-14 shrink-0 place-items-center rounded-xl border bg-background shadow-xs">
                            <JooosiFonLogo aria-hidden="true" focusable="false" className="size-10 text-primary" />
                        </span>
                        <div className="min-w-0">
                            <div className="flex flex-wrap items-center gap-2">
                                <FrameTitle className="text-xl">Jooosi Fon</FrameTitle>
                                <Badge variant="primary-light" radius="full">
                                    {sprintf(__('Version %s', 'jooosi-fon'), window.jooosiFon._version)}
                                </Badge>
                            </div>
                            <FrameDescription className="mt-1.5 max-w-3xl leading-6">
                                {__('A modern WordPress font manager for self-hosting, organizing, and delivering the typography your site needs.', 'jooosi-fon')}
                            </FrameDescription>
                        </div>
                    </div>
                    <a
                        className={cn(buttonVariants({ variant: 'outline', size: 'sm' }), 'shrink-0')}
                        href="https://fon.jooo.si"
                        target="_blank"
                        rel="noopener noreferrer"
                    >
                        <ExternalLinkIcon aria-hidden="true" />
                        {__('Visit website', 'jooosi-fon')}
                    </a>
                </FrameHeader>

                <FramePanel className="grid grid-cols-1 p-0! sm:grid-cols-2 xl:grid-cols-4">
                    {capabilities.map((capability, index) => {
                        const Icon = capability.icon;

                        return (
                            <div
                                key={capability.title}
                                className={cn(
                                    'relative flex min-w-0 flex-col gap-3 p-5',
                                    index < capabilities.length - 1 && 'border-b xl:border-r xl:border-b-0',
                                    index === 0 && 'sm:border-r',
                                    index === 1 && 'sm:border-r-0',
                                    index === 2 && 'sm:border-b-0 sm:border-r',
                                )}
                            >
                                <span className="grid size-9 place-items-center rounded-lg border bg-muted/50" aria-hidden="true">
                                    <Icon className="size-4" />
                                </span>
                                <div>
                                    <h2 className="text-sm font-semibold">{capability.title}</h2>
                                    <p className="mt-1 text-sm leading-5 text-muted-foreground">{capability.description}</p>
                                </div>
                            </div>
                        );
                    })}
                </FramePanel>

                <FrameFooter className="flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <div className="flex items-center gap-2 text-sm text-muted-foreground">
                        <Badge variant="success-light">GPL-3.0-or-later</Badge>
                        <span>{__('Open source', 'jooosi-fon')}</span>
                    </div>
                    <div className="flex flex-wrap gap-2">
                        <a
                            className={buttonVariants({ variant: 'outline', size: 'sm' })}
                            href="https://github.com/jooosi-project/jooosi-fon"
                            target="_blank"
                            rel="noopener noreferrer"
                        >
                            <CodeXmlIcon aria-hidden="true" />
                            {__('GitHub repository', 'jooosi-fon')}
                        </a>
                        <a
                            className={buttonVariants({ variant: 'outline', size: 'sm' })}
                            href="https://www.facebook.com/groups/1142662969627943"
                            target="_blank"
                            rel="noopener noreferrer"
                        >
                            <UsersIcon aria-hidden="true" />
                            {__('Community', 'jooosi-fon')}
                        </a>
                    </div>
                </FrameFooter>
            </Frame>

            <div className="grid min-w-0 gap-5 lg:grid-cols-[minmax(0,1.1fr)_minmax(20rem,0.9fr)]">
                <Frame stacked spacing="sm">
                    <FrameHeader>
                        <FrameTitle>{__('Open-source sponsorship', 'jooosi-fon')}</FrameTitle>
                        <FrameDescription>
                            {__('Every contribution supports maintenance across the complete WordPress plugin portfolio.', 'jooosi-fon')}
                        </FrameDescription>
                    </FrameHeader>
                    <FramePanel className="p-0!">
                        <div className="grid sm:grid-cols-2">
                            {sponsorshipBenefits.map((benefit, index) => {
                                const Icon = benefit.icon;

                                return (
                                    <div
                                        key={benefit.title}
                                        className={cn(
                                            'flex min-w-0 items-start gap-3 border-b p-4',
                                            index % 2 === 0 && 'sm:border-r',
                                            index >= 2 && 'sm:border-b-0',
                                            index === sponsorshipBenefits.length - 1 && 'border-b-0',
                                        )}
                                    >
                                        <span className="grid size-9 shrink-0 place-items-center rounded-lg border bg-muted/50" aria-hidden="true">
                                            <Icon className="size-4" />
                                        </span>
                                        <div className="min-w-0">
                                            <h3 className="text-sm font-medium">{benefit.title}</h3>
                                            <p className="mt-1 text-xs leading-5 text-muted-foreground">{benefit.description}</p>
                                        </div>
                                    </div>
                                );
                            })}
                        </div>
                    </FramePanel>
                    <FrameFooter className="gap-2 sm:flex-row">
                        <a
                            className={cn(buttonVariants({ size: 'sm' }), 'flex-1')}
                            href="https://github.com/sponsors/suasgn"
                            target="_blank"
                            rel="noopener noreferrer"
                        >
                            <CodeXmlIcon aria-hidden="true" />
                            {__('GitHub Sponsors', 'jooosi-fon')}
                        </a>
                        <a
                            className={cn(buttonVariants({ variant: 'outline', size: 'sm' }), 'flex-1')}
                            href="https://ko-fi.com/Q5Q75XSF7"
                            target="_blank"
                            rel="noopener noreferrer"
                        >
                            <HeartHandshakeIcon aria-hidden="true" />
                            {__('Ko-fi', 'jooosi-fon')}
                        </a>
                    </FrameFooter>
                </Frame>

                <Frame stacked spacing="sm">
                    <FrameHeader className="flex-row items-center gap-3">
                        <span className="grid size-9 shrink-0 place-items-center rounded-lg border bg-muted/50" aria-hidden="true">
                            <ZapIcon className="size-4" />
                        </span>
                        <div className="min-w-0">
                            <FrameTitle>{__('Proudly sponsored by', 'jooosi-fon')}</FrameTitle>
                            <FrameDescription>{__('Partners helping keep Jooosi Fon sustainable.', 'jooosi-fon')}</FrameDescription>
                        </div>
                    </FrameHeader>
                    <FramePanel className="p-0!">
                        {sponsors.map((sponsor, index) => {
                            const SponsorIcon = sponsor.icon;

                            return (
                                <a
                                    key={sponsor.name}
                                    className="group relative flex items-center gap-4 p-4 no-underline transition-colors hover:bg-muted/50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-inset"
                                    href={sponsor.href}
                                    target="_blank"
                                    rel="noopener noreferrer"
                                >
                                    <span className="relative grid size-12 shrink-0 place-items-center overflow-hidden rounded-xl border bg-muted/50 text-foreground">
                                        <SponsorIcon
                                            aria-hidden="true"
                                            className="relative size-7 transition-transform group-hover:-translate-y-0.5"
                                        />
                                    </span>
                                    <span className="min-w-0 flex-1">
                                        <span className="block text-sm font-medium text-foreground">{sponsor.name}</span>
                                        <span className="mt-0.5 block text-xs leading-5 text-muted-foreground">{sponsor.description}</span>
                                    </span>
                                    <ExternalLinkIcon aria-hidden="true" className="size-4 shrink-0 text-muted-foreground" />
                                    {index < sponsors.length - 1 && <Separator className="absolute inset-x-0 bottom-0" />}
                                </a>
                            );
                        })}
                    </FramePanel>
                </Frame>
            </div>
        </div>
    );
}
