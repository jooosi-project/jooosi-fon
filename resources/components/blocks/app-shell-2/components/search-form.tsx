"use client"

import { useEffect, useId, useState, type ComponentProps } from "react"

import { Button } from "@/components/ui/button"
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogHeader,
  DialogTitle,
} from "@/components/ui/dialog"
import { Input } from "@/components/ui/input"
import { Kbd } from "@/components/ui/kbd"
import {
  SidebarGroup,
  SidebarGroupContent,
} from "@/components/ui/sidebar"
import { SearchIcon } from "lucide-react"

export function SearchForm({ ...props }: ComponentProps<"form">) {
  const [open, setOpen] = useState(false)
  const searchInputId = useId()

  useEffect(() => {
    function onKeyDown(event: KeyboardEvent) {
      if (event.key.toLowerCase() === "k" && (event.metaKey || event.ctrlKey)) {
        event.preventDefault()
        setOpen(true)
      }
    }

    window.addEventListener("keydown", onKeyDown)
    return () => window.removeEventListener("keydown", onKeyDown)
  }, [])

  return (
    <form {...props}>
      {/* Sidebar */}
      <SidebarGroup className="py-0">
        <SidebarGroupContent className="relative">
          <Button
            id="search"
            type="button"
            variant="outline"
            className="hover:bg-background h-8 w-full justify-start pl-7 font-normal transition-[width] duration-200 ease-linear in-data-[state=collapsed]:w-8! in-data-[state=collapsed]:pl-4! in-data-[state=collapsed]:text-transparent"
            onClick={() => setOpen(true)}
          >
            Search...
          </Button>
          <SearchIcon aria-hidden="true" className="pointer-events-none absolute top-1/2 left-2 size-3.5 -translate-y-1/2 opacity-50 select-none" />
          <Kbd className="absolute top-1/2 right-2 -translate-y-1/2 in-data-[state=collapsed]:hidden">
            ⌘K
          </Kbd>
        </SidebarGroupContent>
      </SidebarGroup>

      <Dialog open={open} onOpenChange={setOpen}>
        <DialogHeader className="sr-only">
          <DialogTitle>Search</DialogTitle>
          <DialogDescription>Search your workspace content.</DialogDescription>
        </DialogHeader>
        <DialogContent className="max-w-md px-4 py-2 **:data-[slot=dialog-close]:top-3 **:data-[slot=dialog-close]:right-3 **:data-[slot=dialog-close]:opacity-60">
          <div className="relative flex items-center gap-3">
            <SearchIcon aria-hidden="true" className="pointer-events-none size-4 opacity-60 select-none" />
            <Input
              id={searchInputId}
              className="h-10 border-none p-0 shadow-none outline-none focus-visible:ring-0"
              autoFocus
              placeholder="Type to search..."
              aria-label="Search"
            />
          </div>
        </DialogContent>
      </Dialog>
    </form>
  )
}