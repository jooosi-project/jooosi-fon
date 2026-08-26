# Project instructions

- The existing WordPress admin UI is built with Vue 3 and Vite.
- ReUI is configured for a planned UI rebuild. Its registry components are React/TSX, so do not add them to the current Vue single-file components unless the user explicitly asks to begin the React migration.
- When the rebuild begins, use the repository-scoped `reui` skill and ReUI MCP workflow: search the registry, inspect the real component API, install through the shadcn CLI, adapt by reuse, validate usage, and run the audit checklist.
- Before installing ReUI components, establish the React/shadcn application structure and `components.json` for the chosen base and style. Preserve the plugin's WordPress mounting, routing, localization, and API integration contracts during the migration.
