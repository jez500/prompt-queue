# Actions

**Globs:** `app/Actions/**`

## Tags live and die with their prompts

There is no tag management screen — tags are created by typing them onto a
prompt, so nothing else can ever remove one.

`PurgeOrphanedTags` runs after every tag sync and after a prompt is deleted: a
tag left on no live prompt is deleted rather than sitting in the filter bar
forever with nothing to filter. A soft-deleted prompt does not count as holding
its tags.

The shared `tags` prop is filtered with `whereHas('prompts')` as well, which
keeps rows orphaned before the purge existed off the screen.
