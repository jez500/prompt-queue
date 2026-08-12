# Prompt Queue — Design

Date: 2026-08-12
Status: Approved

## Purpose

A private, low-friction holding pen for prompts you think of before you are ready to run
them. A todo list for prompts: capture in one keystroke, group by project, and find
things again later.

The app never talks to an AI tool. Prompts leave via the clipboard.

## Success criteria

1. Capturing a new prompt takes one interaction: type the body, press `Cmd/Ctrl+Enter`.
2. Every prompt is reachable within two interactions by project, status, priority, tag or
   free-text search.
3. Getting a prompt into an editor is one click, and that click also advances its status.
4. No user can read or modify another user's prompts or projects.

## Stack

Existing starter kit, unchanged except for one approved addition:

- Laravel 13, PHP 8.3, SQLite
- Inertia v3 + Vue 3, Tailwind v4, reka-ui (shadcn-vue components already present)
- Fortify for auth, Wayfinder for typed route calls, Pest 4 for tests
- **New dependency (approved):** `sortablejs` + `vuedraggable` (Vue 3 build, v4) for drag
  reordering

## Data model

### `projects`

| Column       | Type                | Notes                             |
| ------------ | ------------------- | --------------------------------- |
| `id`         | id                  |                                   |
| `user_id`    | foreignId, cascade  | Owner                             |
| `name`       | string              | Unique per user                   |
| `color`      | string              | One of a fixed palette            |
| timestamps   |                     |                                   |

Unique index on `(user_id, name)` so type-to-create cannot produce duplicates.

A project is a name and a colour. No description, no archiving.

### `prompts`

| Column       | Type                       | Notes                                        |
| ------------ | -------------------------- | -------------------------------------------- |
| `id`         | id                         |                                              |
| `user_id`    | foreignId, cascade         | Owner                                        |
| `project_id` | foreignId, nullable, null-on-delete | `null` means Inbox                  |
| `title`      | string(255), nullable      | Null falls back to first line of body        |
| `body`       | text                       | Required                                     |
| `status`     | string, enum-cast          | `todo` \| `implementing` \| `done`           |
| `priority`   | string, enum-cast          | `low` \| `normal` \| `high`, default `normal`|
| `position`   | integer                    | Order within its bucket                      |
| timestamps   |                            |                                              |

Index on `(user_id, project_id, position)` to serve the default list query.

`status` and `priority` are backed PHP enums (`PromptStatus`, `PromptPriority`) cast on
the model, with `TitleCase` enum keys per project convention.

### `tags` and `prompt_tag`

`tags`: `user_id`, `name`, timestamps, unique on `(user_id, name)`. Free-form and
per-user. `prompt_tag` is a plain pivot.

Tags have no controller of their own. `PromptController@update` accepts an array of tag
names and first-or-creates them for the owner, so a tag exists only as a consequence of
being attached to a prompt.

### Key model decisions

**Title is nullable.** When null, lists render the first line of the body, trimmed and
truncated. Setting a title explicitly stops it tracking the body. This is what makes
body-only capture work without leaving stale auto-titles behind after a later edit.
Exposed as a `displayTitle` accessor so the fallback lives in one place.

**`position` is scoped to `(user_id, project_id)`.** Inbox is its own bucket. Position is
independent of status, so a prompt keeps its place when it moves to `implementing` and
back. New prompts are inserted at position 0 and existing rows in that bucket shift down,
because the thing just typed is the thing being thought about.

**Ownership.** Everything is scoped to `user_id`. Deleting a project nulls its prompts
back to Inbox rather than destroying them.

## Behaviour

### Capture

A textarea pinned above the list, always visible, focused on page load.

- `Cmd/Ctrl+Enter` submits. Plain `Enter` inserts a newline — bodies are multi-line.
- On success the textarea clears and the new prompt appears at the top of the list.
- The project is inherited from the current view: on a project view it goes to that
  project; on Inbox or All it goes to Inbox (`project_id = null`).
- Status defaults to `todo`, priority to `normal`, title to `null`.

Nothing else is asked for at capture time. Retitling, tags, priority and project
assignment happen later in the edit slide-over.

### Consumption

Each row has a copy button.

1. Write `body` to the clipboard.
2. **Only if the copy succeeded**, and only if status is currently `todo`, PATCH the
   status to `implementing`.

Copying a prompt that is already `implementing` or `done` does not change its status.
Re-copying something mid-flight is not a state change, and re-copying a finished prompt
must not resurrect it.

If the Clipboard API is unavailable (it requires a secure context, so plain HTTP on a LAN
address will fail), the row selects its body text instead and a toast explains to copy
manually. The status is left untouched, because nothing left the app.

### Browse, search, filter

**Sidebar:** All · Inbox · then each project with its colour dot and a count of its
non-done prompts.

**Filter bar:** search box, status filter, priority filter, tag filter. Status defaults to
`todo` + `implementing`; `done` is hidden until asked for.

All filter state lives in the URL query string and drives Inertia partial reloads. Search
is debounced ~250ms. Views are bookmarkable and browser back/forward behaves.

Search is `LIKE` across `title` and `body`. No FTS5 and no relevance ranking — a deliberate
choice, adequate for thousands of rows on local SQLite. If it becomes slow, adding FTS is
a contained later change.

### Reordering

Drag is enabled **only** when the list shows a single unfiltered bucket: one project or
Inbox, no search text, and the default status filter. Outside that case the drag handles
are absent and the list stays sorted by `position` ascending.

Dragging row 3 above row 1 inside a filtered list is genuinely ambiguous — what happens to
the hidden rows between them? Rather than guess, the gesture is unavailable.

Persistence is a PATCH carrying the full ordered list of prompt IDs for that bucket. The
server rewrites all positions in a transaction. No fractional indices. The client applies
the new order locally on drop and reverts it if the request fails.

**Drag order wins.** Priority never reorders anything; it renders as a badge and is
filterable. This was an explicit decision — the drag gesture means exactly one thing.

## Code structure

### Backend

- `PromptController` — `index` (the workbench page), `store`, `update`, `destroy`
- `PromptStatusController` (invokable) — the copy-button status flip
- `PromptOrderController` (invokable) — the reorder PATCH
- `ProjectController` — `store`, `update`, `destroy` (no index page)

Four small controllers rather than one, so none becomes a grab-bag.

**Filtering** lives in query scopes on `Prompt`: `search`, `withStatus`, `withPriority`,
`withTags`. A `PromptIndexRequest` validates the query string, so `?status=banana` is a
422 rather than a silently empty list.

**Authorisation** is `PromptPolicy` + `ProjectPolicy`, plus owner scoping on every query.
Route model binding is scoped to the owner so another user's record is a **404, not a
403** — a 403 confirms the record exists.

**Payload shape** is defined once in `PromptResource` and `ProjectResource`, mirrored by
the hand-written TypeScript types.

**Shared props.** Projects and tags are shared Inertia props for authenticated requests
only, so the sidebar and tag autocomplete stay current without every page re-fetching.

### Frontend

`pages/Prompts/Index.vue` is thin and composes:

| File                                    | Responsibility                                          |
| --------------------------------------- | ------------------------------------------------------- |
| `components/prompts/QuickCapture.vue`   | The pinned textarea and its submit shortcut             |
| `components/prompts/FilterBar.vue`      | Search + status/priority/tag controls                   |
| `components/prompts/PromptList.vue`     | The draggable list; the only place that knows whether drag is enabled |
| `components/prompts/PromptRow.vue`      | Title, project dot, priority badge, tags, copy button   |
| `components/prompts/PromptEditSheet.vue`| Slide-over for everything not captured up front         |
| `components/prompts/TagInput.vue`       | Tag entry with autocomplete over shared tags            |
| `components/projects/ProjectSidebarNav.vue` | Project list in the sidebar                         |
| `composables/useCopyPrompt.ts`          | Clipboard write, status flip, fallback path             |
| `composables/usePromptFilters.ts`       | URL ↔ filter-state binding                              |

All route calls go through Wayfinder imports. No hardcoded URLs.

## Error handling

- `body` required, max 65535 characters; `title` optional, max 255.
- The reorder endpoint rejects any ID not owned by the user or not in the bucket being
  reordered, rather than silently skipping it. A silent skip would leave the UI showing an
  order the database does not have.
- Reorder is last-write-wins. Acceptable for a single-user-per-account app.
- Clipboard failure path as described under Consumption.

## Testing

Pest feature tests, one behaviour each:

- Capture with body only → `title` null, `project_id` null, `position` 0, status `todo`,
  owned by the authed user
- Capture while viewing a project → lands in that project; existing rows shift down
- Copy endpoint: `todo` → `implementing`; `implementing` and `done` unchanged
- Reorder rewrites positions; rejects foreign IDs and out-of-bucket IDs
- Filters: search hits both title and body; status, priority and tag each narrow
  correctly; `done` hidden by default
- Isolation: user A gets 404 on user B's prompt for update, delete, status and reorder
- Deleting a project nulls its prompts to Inbox
- Duplicate project name for the same user is rejected

Unit tests cover the `PromptStatus` and `PromptPriority` enums and the `displayTitle`
accessor fallback.

No browser tests in v1. `pest-plugin-browser` and Playwright are not installed and adding
them was declined, so the Vue layer is covered by Inertia prop assertions on the index
response plus manual use. Drag is verified at the endpoint level, where it is the
`PromptOrderController` contract that matters.

Models get factories. `PromptFactory` gets states for each status and for
unassigned-to-project.

## Out of scope for v1

Sharing and teams. Markdown rendering of bodies. Prompt versioning or history. Variables
and templating. Any AI integration. Import/export. Project archiving. Bulk multi-select
copy. Browser/E2E tests.
