export interface ICard {
  title: string
  description: string
  number: string
}

export const CARDS: ICard[] = [
  {
    title: "Plan Setup",
    description: "Define goals and gather the essentials before starting.",
    number: "0.1",
  },
  {
    title: "Configuration",
    description: "Adjust key settings to match your project needs.",
    number: "0.2",
  },
  {
    title: "Integration",
    description: "Connect services and verify everything is running smoothly.",
    number: "0.3",
  },
  {
    title: "Launch & Monitor",
    description: "Go live and keep track of performance metrics.",
    number: "0.4",
  },
]