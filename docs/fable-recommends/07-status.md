# Status — what was fixed

All **High** and **Medium** findings from this audit are done, on
`feat/deployment-dev-env-and-ui-fixes`, as 18 atomic commits following
[06-remediation-plan.md](06-remediation-plan.md).

Gate at completion: **161 Pest tests, 0 failures** (4 skipped — Fortify features
that are off), pint, phpstan, eslint, prettier and vue-tsc all clean. Frontend
work was verified in a real browser at wide, compact (1150px) and narrow (900px),
and the production Docker image was built and booted locally.

## Fixed

| # | Finding | Commit |
| --- | --- | --- |
| High | Failed autosaves reported "Saved" and could discard edits | `63e3f12` |
| High | Title typed on a new prompt was silently dropped | `63e3f12` |
| High | Clipboard fallback selected an element that did not exist | `e476db1` |
| Med | `LIKE` wildcards made search terms unmatchable | `87aa255` |
| Med | Profile email casing could lock a user out of login | `37a960c` |
| Med | Partial updates wiped tags / moved prompts to the Inbox | `c203b51` |
| Med | Enums could drift from their TypeScript counterparts | `97477a9` |
| Med | Autosave echoed stale fields, reverting concurrent changes | `63e3f12` |
| Med | Raw hex chrome across the prompt components | `c092457` |
| Med | Status/priority pill built twice, styled two ways | `c092457` |
| Med | ⌘K and ⌘C advertised but not implemented | `07f883b` |
| Med | Project deletion had no confirmation | `7021957` |
| Med | `QUEUE_CONNECTION: database` with no worker | `96a9166` |
| Med | Fonts fetched from Google; wrong family self-hosted | `3ef422e` |
| Med | Dependabot blind to composer/npm/docker; image never build-tested | `90459ca` |
| Med | Documented backup could produce a torn copy | `b3962de` |
| Med | README password rules, backup and shortcuts all wrong | `4d91ac7` |
| Med | Whole-body payload for a one-line preview | `9a5a0a0` |

## Found while fixing

Four defects that the audit did not catch, surfaced by verifying rather than
reasoning:

1. **No toaster was mounted** (`9c0de1a`) — the `Toaster` component was written
   and exported but never rendered, so *every* `toast()` call in the app was a
   no-op. Deleting a prompt, deleting a project, updating a profile and changing
   a password all reported nothing. Found because the new autosave error toast
   did not appear.
2. **A body-only update moved the prompt to the Inbox** (`c203b51`) — an absent
   `project` key read as an explicit "no project". Caught by a new
   partial-update test, and it would have been triggered by the autosave fix.
3. **Any filter toggle disabled dragging** (`9a5a0a0`) — query strings were
   rebuilt from the `filters` prop, which carries server-resolved defaults, so
   `status=todo,implementing` was written into the URL as though chosen. Since
   reordering is only offered in an unfiltered bucket, drag switched off and
   stayed off. Found in the browser while verifying selection.
4. **`whereLike()` does not escape wildcards** — the remedy this audit
   originally recommended for the search bug would not have worked; with
   `caseSensitive: false` it compiles to a plain `LIKE` with the raw binding.
   Corrected in [01](01-backend-and-database.md) and fixed with an explicit
   `ESCAPE` clause instead.

## Deliberately not done

Everything in the **Low** and **Opportunity** tiers, per the agreed scope —
listed in the parked backlog at the foot of
[06-remediation-plan.md](06-remediation-plan.md). The largest are the dead-code
sweep, consolidating authorization onto one pattern, OPcache tuning, and the
product opportunities (agent API/MCP access, export, undo, "clear done").

Two things worth knowing before picking those up:

- **Deleting the light-mode class maps is now guarded.** `EnumContractTest`
  asserts the *queue* maps stay complete but does not touch
  `PROMPT_STATUS_BADGE_CLASSES` and friends, so removing them stays safe.
- **`sidebarOpen` is still shared on every request** and still unused; removing
  it also means removing the `sidebar_state` cookie read and its encryption
  exemption in `bootstrap/app.php`.
