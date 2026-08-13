# Design tokens

**Globs:** `resources/css/app.css`, `resources/js/**/*.vue`

## Style chrome from tokens, not hex

Structural colour — backgrounds, borders, neutral text — comes from the theme
tokens in `resources/css/app.css`. Writing `bg-[#0A0A0E]` when `bg-pane` exists
is how the design drifts: the next component picks a slightly different grey and
nobody notices until two screens sit side by side.

`tests/Feature/ShellConsistencyTest.php` asserts the shell files contain no raw
six-digit hex. Other components are not yet enforced, but the same rule applies.

### The neutral ramp

Surfaces, darkest to lightest:

| Token | Value | Used for |
| --- | --- | --- |
| `background` | `#08080a` | app background, detail pane |
| `pane` | `#0a0a0e` | list pane |
| `sidebar` | `#0c0c10` | sidebar |
| `card` | `#0d0d12` | cards, the prompt body textarea |
| `muted` / `secondary` | `#141419` | chips, inset controls |
| `popover` | `#15151c` | dropdowns, menus |
| `accent` | `#1b1b22` | active nav row, selected pill |
| `surface-hover` | `#22222c` | avatar chips, menu item hover |

Borders, weakest to strongest — these are three distinct steps and collapsing
them flattens hover states:

| Token | Value | Used for |
| --- | --- | --- |
| `border` / `input` | `#1c1c24` | default borders |
| `border-strong` | `#26262f` | control borders (buttons) |
| `ring` | `#2e2e3a` | focus rings, dropdown borders |
| `border-hover` | `#3a3a46` | hover borders |

Foreground, brightest to dimmest:

| Token | Value | Used for |
| --- | --- | --- |
| `foreground` | `#f2f2f4` | primary text |
| `secondary-foreground` | `#b4b4bf` | secondary text |
| `muted-foreground` | `#8a8a96` | muted text |
| `subtle-foreground` | `#6c6c78` | labels, counts |
| `faint-foreground` | `#5a5a66` | eyebrows, inactive dots |
| `ghost-foreground` | `#4a4a55` | placeholders, empty states |

Brand: `primary` `#6e56f8`, `destructive` `#e0405f`.

## Semantic colour stays in lib/

Status, priority and project colours are **not** theme tokens. They live in
`resources/js/lib/promptStatus.ts`, `promptPriority.ts` and `projectColors.ts`
and are already centralised. Import the class maps; do not inline the values.

A handful of one-off semantic hexes remain inline (the success green `#6FCFA1`,
the delete-hover red `#FF8B9C`/`#5A2733`). That is accepted — they are meaning,
not chrome. Do not "fix" them into neutral tokens.

## Dark only, on purpose

`:root` and `.dark` share one palette, so the app renders dark regardless of
the user's Appearance preference. The light/dark/system toggle still works as a
no-op so the setting keeps functioning if a light theme is ever added. Do not
"fix" this by deleting the toggle or by adding half a light theme.

## Type

`--font-sans` is Space Grotesk (falling back to IBM Plex Sans); `--font-mono`
is IBM Plex Mono. Mono is used deliberately for metadata — counts, timestamps,
keyboard hints, status pills and the prompt body — to separate machine-ish text
from prose. Keep that split.
