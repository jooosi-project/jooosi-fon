import { type ReactNode } from "react"
import { LayoutDashboardIcon, UsersIcon, CreditCardIcon, BarChart3Icon, ZapIcon, LifeBuoyIcon, SettingsIcon, BookOpenIcon } from "lucide-react"

// ── Types ──

export type NavChild = {
  id: string
  label: string
  isActive?: boolean
}

export type NavItem = {
  id: string
  label: string
  icon: ReactNode
  badge?: string | number
  isActive?: boolean
  children?: NavChild[]
}

export type Workspace = {
  id: string
  name: string
  tier?: string
  imageUrl?: string
  avatarClassName?: string
}

export type SecondaryItem = {
  id: string
  label: string
  icon: ReactNode
}

// ── Workspaces ──

export const WORKSPACES: Workspace[] = [
  {
    id: "vercel",
    name: "Vercel",
    tier: "Pro",
    imageUrl: "https://github.com/vercel.png",
    avatarClassName: "",
  },
  {
    id: "openai",
    name: "OpenAI",
    tier: "Team",
    imageUrl: "https://github.com/openai.png",
    avatarClassName: "",
  },
  {
    id: "claude",
    name: "Claude",
    tier: "Enterprise",
    imageUrl: "https://github.com/claude.png",
    avatarClassName: "",
  },
]

// ── Nav Main ──

export const NAV_MAIN: NavItem[] = [
  {
    id: "overview",
    label: "Overview",
    icon: (
      <LayoutDashboardIcon aria-hidden="true" />
    ),
    isActive: true,
  },
  {
    id: "customers",
    label: "Customers",
    icon: (
      <UsersIcon aria-hidden="true" />
    ),
    children: [
      { id: "segments", label: "Segments" },
      { id: "accounts", label: "Accounts", isActive: true },
      { id: "health", label: "Health Scores" },
    ],
  },
  {
    id: "subscriptions",
    label: "Subscriptions",
    icon: (
      <CreditCardIcon aria-hidden="true" />
    ),
    children: [
      { id: "plans", label: "Plans & Pricing" },
      { id: "invoices", label: "Invoices" },
      { id: "dunning", label: "Dunning Rules" },
    ],
  },
  {
    id: "revenue",
    label: "Revenue",
    icon: (
      <BarChart3Icon aria-hidden="true" />
    ),
    badge: "14",
  },
  {
    id: "automation",
    label: "Automation",
    icon: (
      <ZapIcon aria-hidden="true" />
    ),
  },
  {
    id: "support",
    label: "Support",
    icon: (
      <LifeBuoyIcon aria-hidden="true" />
    ),
  },
]

// ── Nav Secondary ──

export const NAV_SECONDARY: SecondaryItem[] = [
  {
    id: "settings",
    label: "Settings",
    icon: (
      <SettingsIcon aria-hidden="true" />
    ),
  },
  {
    id: "invite",
    label: "Invite Team",
    icon: (
      <UsersIcon aria-hidden="true" />
    ),
  },
  {
    id: "docs",
    label: "Documentation",
    icon: (
      <BookOpenIcon aria-hidden="true" />
    ),
  },
]