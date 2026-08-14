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

## Single sign-on never creates users

Registration is closed, and SSO does not reopen it. `LinkSsoIdentity` resolves
an Authelia login to a local user in this order: known OIDC subject in
`user_identities`, then an existing user with the same email, then **null** —
never a new user. The callback turns null into "ask an administrator to create
one first" and leaves the session guest.

The subject is checked before the email deliberately: an address reassigned at
the identity provider must not hand someone a different local account.
`SsoTest` pins both, and the "does not provision" test is the one that matters.

The email fallback is rejected only when the provider explicitly reports
`email_verified: false`. An absent claim is allowed through — not every
provider sends one, and refusing those would make first-time linking impossible
with no way for an admin to fix it.

If you ever add self-provisioning, it changes a self-hosted instance from
invite-only to "anyone Authelia will authenticate" — say so loudly in the
README and `docs/authentication.md`, same as enabling `Features::registration()`.

## HIDE_LOGIN_FORM has an interlock, and it is not optional

`SsoProvider::passwordLoginEnabled()` returns true whenever `enabled() === []`,
**before** it looks at `config('sso.hide_login_form')`. Do not "simplify" that
early return away: without it, setting the flag on an instance whose provider
credentials are missing or wrong removes the only remaining way in, and the fix
requires shell access to the container.

The setting is enforced in two places that must agree — the `showPasswordLogin`
prop hides the form, and `EnsurePasswordLoginIsEnabled` refuses
`POST /login`. Changing one without the other either locks people out of a form
they can still see, or leaves an endpoint open that the UI says is closed.
`SsoTest` covers all four combinations of flag and provider.
