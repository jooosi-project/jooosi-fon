"use client"

import type { ReactNode } from "react"
import { type BadgeProps } from "@/components/reui/badge"
import { BotIcon, SparklesIcon, GlobeIcon, LinkIcon, FileTextIcon, UsersIcon, MailIcon, BellIcon } from "lucide-react"

// ── Types ──

export interface PreferenceBadge {
  label: string
  variant: BadgeProps["variant"]
}

export interface PreferenceTeamMember {
  src?: string
  initials: string
  name: string
}

export interface PreferenceTeam {
  members: PreferenceTeamMember[]
  extraCount?: number
}

export interface PreferenceItem {
  id: string
  icon: ReactNode
  title: string
  description?: string
  control: "button" | "switch"
  badge?: PreferenceBadge
  buttonLabel?: string
  defaultChecked?: boolean
  team?: PreferenceTeam
}

export interface PreferenceSection {
  id: string
  title: string
  items: PreferenceItem[]
}

// ── Data ──

export const PREFERENCE_SECTIONS: PreferenceSection[] = [
  {
    id: "ai-features",
    title: "AI features",
    items: [
      {
        id: "ai-semantic-search",
        icon: (
          <BotIcon aria-hidden="true" />
        ),
        title: "Enable AI semantic search",
        description: "Find content by meaning, not exact wording.",
        badge: {
          label: "Smart",
          variant: "primary-light",
        },
        control: "switch",
        defaultChecked: true,
      },
      {
        id: "ai-insight",
        icon: (
          <SparklesIcon aria-hidden="true" />
        ),
        title: "Enable AI insight",
        description: "Surface patterns and highlights from your workspace.",
        badge: {
          label: "Beta",
          variant: "info-light",
        },
        control: "switch",
        defaultChecked: true,
      },
    ],
  },
  {
    id: "publishing",
    title: "Publishing",
    items: [
      {
        id: "subdomain",
        icon: (
          <GlobeIcon aria-hidden="true" />
        ),
        title: "Subdomain",
        description: "Short hostname visitors use before your custom domain.",
        badge: {
          label: "Fallback",
          variant: "focus-light",
        },
        control: "button",
        buttonLabel: "Edit subdomain",
      },
      {
        id: "custom-domain",
        icon: (
          <LinkIcon aria-hidden="true" />
        ),
        title: "Custom domain",
        description: "Bring your brand URL and serve it over HTTPS.",
        badge: {
          label: "HTTPS",
          variant: "success-light",
        },
        control: "button",
        buttonLabel: "Connect domain",
      },
      {
        id: "default-content",
        icon: (
          <FileTextIcon aria-hidden="true" />
        ),
        title: "Default content",
        description: "What new visitors see when they land on your site.",
        badge: {
          label: "Homepage",
          variant: "warning-light",
        },
        control: "button",
        buttonLabel: "Change default",
      },
    ],
  },
  {
    id: "collaboration",
    title: "Collaboration",
    items: [
      {
        id: "workspace-team",
        icon: (
          <UsersIcon aria-hidden="true" />
        ),
        title: "Workspace team",
        badge: {
          label: "12 members",
          variant: "focus-light",
        },
        control: "button",
        buttonLabel: "Manage members",
        team: {
          members: [
            {
              src: "https://images.unsplash.com/photo-1485206412256-701ccc5b93ca?w=96&h=96&dpr=2&q=80",
              initials: "NJ",
              name: "Nick Johnson",
            },
            {
              src: "https://images.unsplash.com/photo-1535713875002-d1d0cf377fde?w=96&h=96&dpr=2&q=80",
              initials: "AJ",
              name: "Alex Johnson",
            },
            {
              src: "https://images.unsplash.com/photo-1519699047748-de8e457a634e?w=96&h=96&dpr=2&q=80",
              initials: "SC",
              name: "Sarah Chen",
            },
          ],
          extraCount: 8,
        },
      },
    ],
  },
  {
    id: "notifications",
    title: "Notifications",
    items: [
      {
        id: "email-digest",
        icon: (
          <MailIcon aria-hidden="true" />
        ),
        title: "Email digest",
        description:
          "Weekly summary of activity and mentions in this workspace.",
        badge: {
          label: "Weekly",
          variant: "outline",
        },
        control: "switch",
        defaultChecked: true,
      },
      {
        id: "in-app-alerts",
        icon: (
          <BellIcon aria-hidden="true" />
        ),
        title: "In-app alerts",
        description: "Show real-time notifications while you are working here.",
        badge: {
          label: "Instant",
          variant: "info-light",
        },
        control: "switch",
        defaultChecked: true,
      },
    ],
  },
]
