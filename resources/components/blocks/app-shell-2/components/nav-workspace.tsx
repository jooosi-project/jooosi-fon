import { useState } from "react"

import { cn } from "@/lib/utils"
import {
  Avatar,
  AvatarFallback,
  AvatarImage,
} from "@/components/ui/avatar"
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuGroup,
  DropdownMenuItem,
  DropdownMenuSeparator,
  DropdownMenuTrigger,
} from "@/components/ui/dropdown-menu"
import {
  SidebarMenu,
  SidebarMenuButton,
  SidebarMenuItem,
} from "@/components/ui/sidebar"
import { WORKSPACES, type Workspace } from "./data"
import { ChevronsUpDownIcon, CheckIcon } from "lucide-react"

function getWorkspaceInitials(name: string): string {
  return name.charAt(0).toUpperCase()
}

function WorkspaceAvatar({
  workspace,
  className,
  size = "default",
}: {
  workspace: Workspace
  className?: string
  size?: "default" | "sm" | "lg"
}) {
  const initials = getWorkspaceInitials(workspace.name)
  return (
    <Avatar size={size} className={cn("shrink-0", className)}>
      {workspace.imageUrl ? (
        <AvatarImage src={workspace.imageUrl} alt={workspace.name} />
      ) : null}
      <AvatarFallback
        className={cn(
          "bg-background border-border text-foreground border text-sm font-medium",
          workspace.avatarClassName
        )}
      >
        {initials}
      </AvatarFallback>
    </Avatar>
  )
}

export function NavWorkspace() {
  const [activeWorkspaceId, setActiveWorkspaceId] = useState(WORKSPACES[0].id)
  const activeWorkspace =
    WORKSPACES.find((workspace) => workspace.id === activeWorkspaceId) ??
    WORKSPACES[0]

  return (
    <SidebarMenu>
      {/* Sidebar */}
      <SidebarMenuItem>
        <DropdownMenu>
          <DropdownMenuTrigger
            render={
              <SidebarMenuButton
                aria-label="Switch workspace"
                className="in-data-[state=collapsed]:justify-center"
              />
            }
          >
            <WorkspaceAvatar workspace={activeWorkspace} size="sm" />
            <span className="truncate text-sm font-medium in-data-[state=collapsed]:hidden">
              {activeWorkspace.name}
            </span>
            <ChevronsUpDownIcon className="ml-auto size-3.5 opacity-60 in-data-[state=collapsed]:hidden" aria-hidden="true" />
          </DropdownMenuTrigger>
          <DropdownMenuContent className="w-56" align="start" sideOffset={8}>
            <DropdownMenuGroup>
              <DropdownMenuGroup>
                {WORKSPACES.map((workspace) => (
                  <WorkspaceItem
                    key={workspace.id}
                    workspace={workspace}
                    isActive={activeWorkspaceId === workspace.id}
                    onSelect={setActiveWorkspaceId}
                  />
                ))}
              </DropdownMenuGroup>
              <DropdownMenuSeparator />
              <DropdownMenuGroup>
                <DropdownMenuItem>New Organization</DropdownMenuItem>
                <DropdownMenuItem render={<a href="#" />}>
                  Account Settings
                </DropdownMenuItem>
                <DropdownMenuSeparator />
                <DropdownMenuItem render={<a href="#" />}>
                  Logout
                </DropdownMenuItem>
              </DropdownMenuGroup>
            </DropdownMenuGroup>
          </DropdownMenuContent>
        </DropdownMenu>
      </SidebarMenuItem>
    </SidebarMenu>
  )
}

function WorkspaceItem({
  workspace,
  isActive,
  onSelect,
}: {
  workspace: Workspace
  isActive: boolean
  onSelect: (workspaceId: string) => void
}) {
  return (
    <DropdownMenuItem onClick={() => onSelect(workspace.id)}>
      <WorkspaceAvatar workspace={workspace} size="sm" />
      <span className="text-sm font-medium">{workspace.name}</span>
      {isActive ? (
        <CheckIcon className="ml-auto size-3.5 opacity-60" aria-hidden="true" />
      ) : null}
    </DropdownMenuItem>
  )
}