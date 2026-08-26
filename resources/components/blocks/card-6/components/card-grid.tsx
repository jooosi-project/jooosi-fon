import { Frame } from "@/components/reui/frame"

import { CardItem } from "./card-item"
import { CARDS } from "./data"

export function CardGrid() {
  return (
    <Frame className="@container w-full">
      {/* Grid */}
      <div className="grid gap-1 @2xl:grid-cols-2 @4xl:grid-cols-4">
        {CARDS.map((card) => (
          <CardItem key={card.title} card={card} />
        ))}
      </div>
    </Frame>
  )
}
