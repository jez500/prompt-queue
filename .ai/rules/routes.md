# Routes

**Globs:** `routes/**`

## SSO routes register unconditionally, guard in the controller

`auth/{provider}/redirect` and `auth/{provider}/callback` are registered whether
or not credentials are configured. `SsoController` calls
`abort_unless($provider->isConfigured(), 404)` instead.

Do not wrap the route registration in a config check. Wayfinder generates
`resources/js/routes` from the route list at build time, so conditional routes
would emit different TypeScript per environment and break `npm run types:check`
and the Docker asset stage wherever Authelia is not configured.

`{provider}` binds to the `App\Enums\SsoProvider` enum, so an unknown segment
404s before the controller runs.

## Declare fixed segments before wildcards

`projects/reorder` sits above `projects/{project}`, or the wildcard swallows
"reorder" and the model binding fails on a string that is not an id. The same
applies to `prompts/reorder`.
