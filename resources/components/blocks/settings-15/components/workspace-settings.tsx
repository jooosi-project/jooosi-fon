import { Badge } from "@/components/reui/badge"
import {
  Frame,
  FrameHeader,
  FramePanel,
  FrameTitle,
} from "@/components/reui/frame"

import {
  Avatar,
  AvatarFallback,
  AvatarGroup,
  AvatarGroupCount,
  AvatarImage,
} from "@/components/ui/avatar"
import { Button } from "@/components/ui/button"
import {
  Item,
  ItemActions,
  ItemContent,
  ItemDescription,
  ItemMedia,
  ItemTitle,
} from "@/components/ui/item"
import { Separator } from "@/components/ui/separator"
import { Switch } from "@/components/ui/switch"

import { PREFERENCE_SECTIONS, type PreferenceItem } from "./data"

// ── Team Members Preview ──

function TeamMembersPreview({ item }: { item: PreferenceItem }) {
  if (!item.team) {
    return null
  }

  return (
    <AvatarGroup aria-label="Workspace members" className="-space-x-1.5">
      {item.team.members.map((member) => (
        <Avatar key={member.name} size="sm" className="size-6 border">
          <AvatarImage src={member.src} alt={member.name} />
          <AvatarFallback>{member.initials}</AvatarFallback>
        </Avatar>
      ))}
      {item.team.extraCount ? (
        <AvatarGroupCount className="size-6 border text-[10px] leading-none">
          +{item.team.extraCount}
        </AvatarGroupCount>
      ) : null}
    </AvatarGroup>
  )
}

// ── Preference Row ──

function PreferenceRow({ item }: { item: PreferenceItem }) {
  return (
    <Item className="px-3.5">
      {/* Media */}
      <ItemMedia variant="icon">
        <Item className="bg-muted/60 border-background flex size-10 shrink-0 items-center justify-center border-2 p-0 shadow-[0_1px_3px_0_rgba(0,0,0,0.14)] dark:border [&_svg]:size-4.5 [&_svg]:opacity-50">
          {item.icon}
        </Item>
      </ItemMedia>

      {/* Content */}
      <ItemContent className="min-w-0">
        <ItemTitle className="gap-2">
          {item.title}
          {item.badge ? (
            <Badge variant={item.badge.variant} size="sm">
              {item.badge.label}
            </Badge>
          ) : null}
        </ItemTitle>
        {item.team ? (
          <div className="mt-1">
            <TeamMembersPreview item={item} />
          </div>
        ) : item.description ? (
          <ItemDescription>{item.description}</ItemDescription>
        ) : null}
      </ItemContent>

      {/* Actions */}
      <ItemActions className="gap-2">
        {item.control === "button" && item.buttonLabel && (
          <Button variant="outline">{item.buttonLabel}</Button>
        )}
        {item.control === "switch" && (
          <Switch
            defaultChecked={item.defaultChecked}
            aria-label={item.title}
          />
        )}
      </ItemActions>
    </Item>
  )
}

// ── Main component ──

export function WorkspaceSettings() {
  return (
    <div className="w-full max-w-2xl space-y-5">
      {PREFERENCE_SECTIONS.map((section) => (
        <Frame key={section.id}>
          <FrameHeader>
            <FrameTitle>{section.title}</FrameTitle>
          </FrameHeader>

          <FramePanel className="p-0!">
            {section.items.map((item, index) => (
              <div key={item.id}>
                {index > 0 && <Separator />}
                <PreferenceRow item={item} />
              </div>
            ))}
          </FramePanel>
        </Frame>
      ))}
    </div>
  )
}
