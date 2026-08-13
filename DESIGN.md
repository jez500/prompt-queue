# Prompt Queue — design system

The visual and structural rules behind the interface. This describes what the
app *is*, not what it might become; if you change the design, change this file
in the same commit.

For the enforcement rules an agent should follow while coding, see
[`.ai/rules/design-tokens.md`](.ai/rules/design-tokens.md) and
[`.ai/rules/shell.md`](.ai/rules/shell.md).

---

## Principles

**One shell, always.** Every authenticated screen renders in the same chrome.
Settings once ran on a separate starter-kit layout and the two drifted; that
shell has been deleted and layout resolution is centralised in
`resources/js/app.ts`. A new page inherits the shell without opting in.

**Tokens over values.** Structural colour is named. Reaching for a raw hex is
how two screens end up almost-but-not-quite matching.

**Dense, quiet, keyboard-first.** This is a tool for queueing work, not a
dashboard to admire. Small type, tight spacing, low-chroma surfaces, and colour
reserved for meaning — status, priority, project.

**Mono for machine text.** Counts, timestamps, keyboard hints, status pills and
the prompt body itself are monospace. Prose and headings are not. The split is
information design, not decoration.

---

## Layout

A three-column shell:

```
┌──────────┬────────────────┬──────────────────────────┐
│ sidebar  │ list pane      │ detail pane              │
│ 212px    │ 430px          │ flex-1                   │
│ (68px    │ (344px in      │                          │
│ collapsed)│ compact band) │                          │
└──────────┴────────────────┴──────────────────────────┘
  bg-sidebar   bg-pane          bg-background
```

Both content columns are `AppPane` — `variant="list"` and `variant="detail"`.
The prompt queue and settings use the *same* two components, which is what
keeps them identical.

### Breakpoints

Defined once in `composables/useShellBreakpoints.ts`.

| Mode | Range | Behaviour |
| --- | --- | --- |
| **wide** | `≥1260px` | All three columns, full labels. |
| **compact** | `1100–1259px` | Three columns, list drops to `344px`, pane-header eyebrow hidden, long labels shorten ("Copy prompt" → "Copy"). Keeps the detail header on one line. |
| **narrow** | `≤1099px` | Sidebar hidden, single column. List and detail swap; detail gets a back button; `NarrowTopBar` carries project nav, capture and the account menu. |

The detail header is `flex-nowrap` deliberately — it must never wrap. New
controls have to earn their width, or shorten in the compact band.

---

## Colour

Dark only. `:root` and `.dark` share one palette, so the app renders dark
regardless of the Appearance preference; that toggle is retained as a no-op so
it keeps working if a light theme is ever added.

### Surfaces

| Token | Value | Role |
| --- | --- | --- |
| `background` | `#08080a` | App background, detail pane |
| `pane` | `#0a0a0e` | List pane |
| `sidebar` | `#0c0c10` | Sidebar |
| `card` | `#0d0d12` | Cards, prompt body editor |
| `muted` / `secondary` | `#141419` | Chips, inset controls |
| `popover` | `#15151c` | Dropdowns, menus |
| `accent` | `#1b1b22` | Active nav row, selected pill |
| `surface-hover` | `#22222c` | Avatar chips, menu hover |

The surface ramp is intentionally shallow — four near-black steps within
`#08`–`#1b`. Separation comes from a 1px border, not from contrast.

### Borders

Three distinct steps. Collapsing them flattens hover feedback.

| Token | Value | Role |
| --- | --- | --- |
| `border` / `input` | `#1c1c24` | Default |
| `border-strong` | `#26262f` | Control borders |
| `ring` | `#2e2e3a` | Focus rings, dropdown edges |
| `border-hover` | `#3a3a46` | Hover |
| `sidebar-border` | `#17171c` | Column dividers |

### Foreground

| Token | Value | Role |
| --- | --- | --- |
| `foreground` | `#f2f2f4` | Primary text |
| `secondary-foreground` | `#b4b4bf` | Secondary |
| `muted-foreground` | `#8a8a96` | Muted |
| `subtle-foreground` | `#6c6c78` | Labels, counts |
| `faint-foreground` | `#5a5a66` | Eyebrows, inactive dots |
| `ghost-foreground` | `#4a4a55` | Placeholders, empty states |

Six steps sounds excessive until you see the header: eyebrow, label, count and
save-state all sit in one row and need to read as a hierarchy without colour.

### Brand and semantics

`primary` is `#6e56f8` — the only saturated colour in the chrome, reserved for
the primary action (New prompt, Copy prompt) and the logo mark.
`destructive` is `#e0405f`.

Semantic colour lives in `resources/js/lib/`, not in the theme:

| Meaning | Source | Values |
| --- | --- | --- |
| Status | `promptStatus.ts` | Todo neutral `#9A9AA6`, Implementing blue `#3B82F6`/`#7FB2FF`, Done green `#2E7D5B`/`#6FCFA1` |
| Priority | `promptPriority.ts` | High / Normal / Low pills |
| Project | `projectColors.ts` | Tailwind 500s: slate, rose, amber, emerald, sky, violet |

Projects get a colour so they can be identified by a 6px dot, in the sidebar,
in list rows and in the detail header, without spending horizontal space on a
label.

---

## Type

- **Sans** — Space Grotesk, falling back to IBM Plex Sans. UI and headings.
- **Mono** — IBM Plex Mono. Metadata, pills, keyboard hints, prompt body.

Sizes are set in fractional pixels (`12.5px`, `14.5px`, `19px`) rather than a
scale, tuned per context for density. Headings are `font-bold tracking-tight`.

Radii step from `--radius: 0.6rem`; pills are fully rounded, panels use
`rounded-[14px]`.

---

## Components

`resources/js/components/shell/`

| Component | Role |
| --- | --- |
| `AppPane` | Column chrome — `list` (fixed width) or `detail` (flex) |
| `PaneHeader` | Bordered header row: eyebrow, leading slot, trailing `actions` slot, back button when narrow |
| `NarrowTopBar` | Sidebar replacement below `1100px` |

Everything under `components/ui/` is shadcn-vue on reka-ui. Check there before
building anything new.

### Buttons

- **Primary** — `bg-primary`, white text, `rounded-[9px]`, `h-8`.
- **Secondary** — `border-border-strong`, transparent, brightening on hover.
- **Icon-only** — `size-8`, bordered, hover tints toward meaning: trash → red
  `#FF8B9C`, mark-done → green `#6FCFA1`. Icon-only buttons must carry **both**
  a `Tooltip` and an `aria-label`; the tooltip is not an accessible name.
  `TooltipProvider` is mounted once, in `PromptQueueLayout`.

### Detail header order

Metadata pills read **Status → Priority → Project**. Status first because it is
what changes most; project last because it is context, not an action.

---

## Interaction

- **Autosave.** Prompts save on a debounce; the header shows
  `Auto-saves → Saving… → Saved` with a matching dot. No save button.
- **Keyboard.** `N` new prompt, `⌘K` search, `⌘C` copy the open prompt. Hints
  are rendered inline in mono at reduced opacity.
- **Drag to reorder** within a single unfiltered bucket only — ordering is
  meaningless across a filtered set, so the handle is withheld.
- **Empty states** state what the space is for rather than apologising:
  "Nothing here yet — capture the next thing you want an agent to pick up."
