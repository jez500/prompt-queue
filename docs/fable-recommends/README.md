# Fable recommends — project audit

Audit of Prompt Queue (2026-08-13), every non-generated source file read in full.
Settled decisions in `.ai/rules/` (single shell, dark-only, closed registration,
no JS test harness, sanctioned semantic hexes) were treated as constraints, not
findings.

| Section | File |
| --- | --- |
| Backend & database | [01-backend-and-database.md](01-backend-and-database.md) |
| Frontend | [02-frontend.md](02-frontend.md) |
| Test suite | [03-tests.md](03-tests.md) |
| Deployment, CI & docs | [04-deployment-ci-docs.md](04-deployment-ci-docs.md) |
| Cross-cutting & product | [05-cross-cutting.md](05-cross-cutting.md) |
| **Remediation plan** | [06-remediation-plan.md](06-remediation-plan.md) |
| **Status — what was fixed** | [07-status.md](07-status.md) |

> **All High and Medium findings are fixed** — see
> [07-status.md](07-status.md). The sections below are the original audit,
> kept as the record of what was found and why.

## Overall

This is a well-built codebase. Authorization is airtight and tested on every
endpoint, the position/bucket model is coherent across all layers, the deployment
story is unusually careful, and the project's own guard-rail tests
(`ShellConsistencyTest`) are a genuinely good idea. The significant findings
cluster in two places: the **autosave error paths** (the happy path is excellent;
failures lie to the user) and **drift the guard rails don't yet cover** (raw hex in
the prompt components, hand-mirrored enums, README claims vs. implemented
shortcuts).

## Do first (High)

1. **Autosave failures show "Saved" and can silently discard edits** — no
   `onError`, `savedAt` set in `onFinish`. → [02](02-frontend.md)
2. **A title typed on a new prompt is never persisted** — the follow-up
   `PATCH {title}` fails `body`-required validation, invisibly. → [02](02-frontend.md)
3. **Copy-to-clipboard is broken on plain-HTTP deploys** — the fallback selects a
   `[data-prompt-body]` element that doesn't exist; LAN/Docker HTTP is this app's
   home turf. → [02](02-frontend.md)

## Do soon (Medium, grouped)

- **Correctness:** SQLite `LIKE` escaping breaks search terms with `%`/`_`;
  profile-email casing can lock users out of login; autosave PATCHes echo stale
  status/tags/body (send only changed fields). → [01](01-backend-and-database.md), [02](02-frontend.md)
- **Design-system drift:** raw hex (incl. off-token values) across
  `PromptQueueCard`, `FilterBar`, `PromptQueueSidebar`; the status/priority pill
  built twice; extend the `ShellConsistencyTest` hex rule once tokenized. → [02](02-frontend.md), [03](03-tests.md)
- **Contracts:** pin PHP enums ↔ TS unions ↔ class maps with a ten-line test;
  pin the partial-update (status/priority preserved) contract. → [05](05-cross-cutting.md), [03](03-tests.md)
- **Docs vs reality:** README's password rules are wrong, its SQLite backup
  command can produce a torn copy, and it advertises ⌘K/⌘C which don't exist
  (implement them — "keyboard-first" is the stated principle). → [04](04-deployment-ci-docs.md)
- **Ops:** Dependabot ignores composer/npm/docker; the Docker image is never
  build-tested on PRs; `QUEUE_CONNECTION: database` with no worker; fonts load
  from Google at runtime while the build self-hosts an unused font. → [04](04-deployment-ci-docs.md)
- **Scale headroom:** list payload ships every prompt's full body to render one
  line; send excerpts + fetch the selected body on demand. → [05](05-cross-cutting.md)
- **UX:** project deletion has no confirmation. → [02](02-frontend.md)

## Cleanups (Low)

Dead code (`TagInput`, `ProjectSidebarNav`, `AlertError`, light-mode class maps,
`sidebarOpen` prop, 2FA request class, email-verification ceremony, test
scaffolding cruft), authorization living in three patterns, OPcache/php.ini
tuning, CI caching, tag-name case sensitivity, `window.confirm` vs dialogs,
non-ticking timestamps — all itemised in the section files.

## Opportunities (product, not defects)

Programmatic prompt access for agents (API token or MCP — the strongest gap
relative to the product's own pitch), `pq:export`/`pq:backup`, duplicate prompt,
"Clear done", deletion undo, first-run hint on the login page. → [05](05-cross-cutting.md)
