# Project rules

Settled decisions, non-obvious traps and standing constraints for this
codebase. Read every file whose globs cover the paths you are about to touch,
**before** writing code. Also `grep -rin '<keyword>' .ai/rules` — a path match
alone misses rules that are relevant by topic.

Record new durable rules with the Boost `record-rule` MCP tool rather than
editing these files by hand, so they land in the right file with the right
glob.

| Globs | Rules |
| --- | --- |
| `resources/js/components/shell/**`, `resources/js/layouts/**`, `resources/js/app.ts` | [shell.md](shell.md) — one app shell, layout resolution, breakpoints |
| `resources/css/app.css`, `resources/js/**/*.vue` | [design-tokens.md](design-tokens.md) — theme tokens, no raw hex in chrome |
| `resources/js/pages/**`, `resources/js/components/**`, `resources/js/composables/**` | [frontend.md](frontend.md) — Inertia pages, Vue conventions, Wayfinder |
| `tests/**`, `app/**`, `database/**` | [testing.md](testing.md) — Pest, what must be covered |
| `Dockerfile`, `docker/**`, `docker-compose.yml`, `.github/workflows/**` | [deployment.md](deployment.md) — image layout, entrypoint, publishing |
| `.lando.yml`, `vite.config.ts` | [local-dev.md](local-dev.md) — Lando environment, Vite dev server |

## The short version

- Every authenticated screen renders in **one shell**. Do not build a second.
- Style chrome from **theme tokens**, never raw hex.
- Registration is closed; users are created with `php artisan pq:create-user`.
- The app is **dark-only** by deliberate decision — see design-tokens.md.
