# The app shell

**Globs:** `resources/js/components/shell/**`, `resources/js/layouts/**`, `resources/js/app.ts`

## There is exactly one shell

Every authenticated screen renders inside `layouts/prompts/PromptQueueLayout.vue`.
This is not a preference — settings previously ran on a second, separately
styled shell (the shadcn starter kit) and the two drifted apart visually. That
shell has been deleted. Do not reintroduce one.

`resources/js/app.ts` is the **only** place a layout is chosen:

```ts
case name.startsWith('auth/'):     return AuthLayout;
case name.startsWith('settings/'): return [PromptQueueLayout, SettingsLayout];
default:                           return PromptQueueLayout;
```

The `default` branch is load-bearing. A new page inherits the shell without
opting in, which is what stops chrome from growing per-page.

**Pages must not declare their own layout.** `tests/Feature/ShellConsistencyTest.php`
fails the build if any non-auth page contains `layout:`. If you think you need
a page-specific layout, you almost certainly want a new pane variant instead.

## Compose screens from the shared primitives

`resources/js/components/shell/`:

- **`AppPane.vue`** — the column chrome. `variant="list"` is the fixed-width
  left column, `variant="detail"` is the flexible right column. Never hand-roll
  a `<section>` with pane styling; use this.
- **`PaneHeader.vue`** — the bordered header row. Slots: default (leading
  controls), `actions` (trailing, right-aligned). Emits `back`.
- **`NarrowTopBar.vue`** — stands in for the sidebar below the narrow
  breakpoint. Every list pane must render it when narrow, or that screen
  becomes a dead end on mobile with no way back to the queue.

Both `PromptListPane` and `SettingsNavPane` are built on these. Keeping the
prompt panes on the shared primitives is deliberate: it means the primitives
are exercised by the main screen, not just by settings.

## Breakpoints live in one composable

`composables/useShellBreakpoints.ts` owns both thresholds. Do not write a raw
`useMediaQuery('(max-width: 1099px)')` anywhere else — the sidebar, panes and
headers must agree on which mode they are in.

- **`narrow`** (`≤1099px`) — sidebar hidden, single-column master-detail. The
  list and detail panes swap; the detail pane shows a back button.
- **`compact`** (`1100–1259px`) — all three columns visible but the detail pane
  is tight. The list column drops to `344px`, `PaneHeader` hides its eyebrow,
  and long button labels shorten ("Copy prompt" → "Copy"). Without this the
  detail header wraps to two lines.

When adding controls to a detail header, check them at 1100px. The header row
is `flex-nowrap` on purpose; if something no longer fits, shorten it in the
compact band rather than letting the row wrap.

## Tooltips

`TooltipProvider` is mounted once in `PromptQueueLayout`. Icon-only buttons
need both a `Tooltip` and an `aria-label` — the tooltip is not an accessible
name.
