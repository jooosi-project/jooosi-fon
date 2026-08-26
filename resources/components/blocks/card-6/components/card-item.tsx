import { FramePanel } from "@/components/reui/frame"

import { ICard } from "./data"

export function CardItem({ card }: { card: ICard }) {
  return (
    <FramePanel className="space-y-10 lg:space-y-20">
      <h3 className="text-muted-foreground/50 text-sm">{card.number}</h3>

      <div className="flex flex-col gap-2.5">
        <a className="hover:text-primary text-sm leading-tight font-medium">
          {card.title}
        </a>
        <p className="text-muted-foreground text-xs leading-relaxed">
          {card.description}
        </p>
      </div>
    </FramePanel>
  )
}
