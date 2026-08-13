# Frontend conventions

**Globs:** `resources/js/pages/**`, `resources/js/components/**`, `resources/js/composables/**`

## Wayfinder generates the route layer

`resources/js/actions`, `resources/js/routes` and `resources/js/wayfinder` are
**generated and gitignored**. The Vite plugin shells out to
`php artisan wayfinder:generate` during `npm run build`, so any build
environment needs PHP, `vendor/` and a bootable app — not just Node. This is
why the Docker asset stage installs both.

Import route helpers from `@/routes/*` and controller actions from
`@/actions/*`. Never hardcode a URL string.

## Pages

- Pages live in `resources/js/pages/`, named to match `Inertia::render()`.
- Pages do not declare layouts — see [shell.md](shell.md).
- Keep pages thin: state and orchestration in the page, presentation in
  components. `pages/prompts/Index.vue` owns selection and filter state and
  hands everything else to `PromptListPane` / `PromptDetailPane`.

## Components

- Single root element per component (Inertia + Vue requirement).
- Use `defineProps` with destructuring and explicit types; use `defineEmits`
  with the typed tuple form.
- Check `components/ui/` before writing anything new — that is shadcn-vue via
  reka-ui and already covers dialogs, dropdowns, tooltips, sheets, inputs.
- Prefer extracting a shared component over duplicating markup across two
  screens. The shell primitives exist because that duplication caused drift.

## Composables

Shared reactive logic goes in `composables/`, one concern per file. Existing
ones worth knowing before you write a new one:

- `useShellBreakpoints` — layout thresholds (always use this, never a raw media query)
- `useProjectScopeNav` — the "All prompts / projects / No project" nav rows
- `usePromptFilters` — filter state synced to the query string
- `usePromptAutosave` — debounced prompt persistence
- `useCurrentUrl`, `useInitials`, `useCopyPrompt`, `useAppearance`

### Trap: nav active state

`useProjectScopeNav` gates every row's `active` flag on being on the queue
route. Without that gate an unscoped route such as `/settings/profile` has no
`?project=` param and lights up "All prompts" while the user is elsewhere. If
you add another scope row, gate it the same way.

## Formatting

Prettier and ESLint are authoritative — run `npm run format` and
`npm run lint:check`. Do not hand-format; do not argue with the formatter.
