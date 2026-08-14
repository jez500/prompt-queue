# Authentication

Registration is deliberately closed. This is built to be run for yourself or a
small trusted group, so there is no sign-up form — accounts are created from
the command line, and single sign-on logs into accounts that already exist
rather than making new ones.

## Creating users

```bash
php artisan pq:create-user
# or, in a container
docker compose exec app php artisan pq:create-user
```

It'll prompt for a name, email and password. Non-interactively:

```bash
php artisan pq:create-user --name="Jez" --email="you@example.com" --password="…"
```

In production the password rules are strict: at least 12 characters, with upper
and lower case, a number and a symbol. It is also checked against the Have I
Been Pwned breach list, which needs outbound HTTPS — on an air-gapped host,
relax `Password::defaults()` in `app/Providers/AppServiceProvider.php`.

Those rules apply in production only, so a password that works locally can be
rejected inside the production container.

## Single sign-on with Authelia

Optional. Leave it unconfigured and nothing changes: the login page shows the
email and password form exactly as it does today.

When it *is* configured, a **Continue with Authelia** button appears above the
password form. The password form stays by default — if Authelia is unreachable
you can still get into your own instance. To drop it, see
[Single sign-on only](#single-sign-on-only) below.

### 1. Create the client in Authelia

Add an OIDC client to your Authelia configuration. The redirect URI must match
your `APP_URL` exactly:

```yaml
identity_providers:
  oidc:
    clients:
      - client_id: prompt-queue
        client_name: Prompt Queue
        client_secret: '$pbkdf2-sha512$310000$…'   # the *hashed* secret
        public: false
        authorization_policy: two_factor
        redirect_uris:
          - https://queue.example.com/auth/authelia/callback
        scopes: [openid, profile, email, groups]
        grant_types: [authorization_code, refresh_token]
        response_types: [code]
```

Generate the secret and its hash with Authelia's own tooling:

```bash
docker run --rm authelia/authelia:latest \
    authelia crypto hash generate pbkdf2 --variant sha512 --random --random.length 72
```

Authelia stores only the **hash**. The plaintext goes in Prompt Queue's `.env`
and nowhere else.

> The client schema shifts between Authelia releases. Check
> [Authelia's OpenID Connect docs](https://www.authelia.com/configuration/identity-providers/openid-connect/clients/)
> against the version you run.

### 2. Configure Prompt Queue

```dotenv
AUTHELIA_BASE_URL=https://auth.example.com
AUTHELIA_CLIENT_ID=prompt-queue
AUTHELIA_CLIENT_SECRET=the-plaintext-secret
AUTHELIA_REDIRECT_URI="${APP_URL}/auth/authelia/callback"
```

Both `AUTHELIA_CLIENT_ID` and `AUTHELIA_CLIENT_SECRET` must be set. With either
one missing the feature stays off and both `/auth/authelia/*` routes return 404
— a half-configured client can't complete the token exchange, so offering the
button would only dead-end.

### 3. Create the matching local account

```bash
php artisan pq:create-user --name="Jez" --email="you@example.com"
```

Use the same email address the user has in Authelia. On their first SSO login
that account is found by email and bound to their Authelia identity; every
login after that matches on the OIDC subject instead.

## Single sign-on only

To make Authelia the only way in:

```dotenv
HIDE_LOGIN_FORM=true
```

The password form disappears from the login screen **and** `POST /login` is
refused, so this genuinely enforces single sign-on rather than just tidying the
page away.

Two things to know before you set it.

**It does nothing unless a provider is configured.** If `AUTHELIA_CLIENT_ID` or
`AUTHELIA_CLIENT_SECRET` is missing, the form comes back and password login
works as normal. That interlock is deliberate: the setting cannot lock you out
of an instance that has no other way in.

**Setting it back to `false` is the only escape hatch.** There is no in-app
override, no admin bypass, no artisan command. If Authelia goes down while this
is on, getting back in means editing `.env` and restarting:

```bash
docker compose exec app sed -i 's/^HIDE_LOGIN_FORM=.*/HIDE_LOGIN_FORM=false/' .env
docker compose restart app
```

If that would be painful on your host — no shell, no quick redeploy — leave the
password form in place. It costs nothing while nobody uses it.

Password *resets* are unaffected. The "Forgot your password?" link lives inside
the hidden form so it isn't reachable from the UI, but the routes still work if
you know the URL, and a reset only matters once you turn the form back on.

## How linking works

1. **Known subject** — an identity row matching the OIDC `sub` logs that user
   in. Checked first, so an email reassigned in Authelia can never hand someone
   a different local account.
2. **Matching email** — otherwise, an existing user with the same email address
   is linked to the identity. Rejected if Authelia explicitly reports the
   address as unverified.
3. **Neither** — the login is refused with "No Prompt Queue account is linked
   to that Authelia login." **No user is created.**

That third rule is the point. If SSO provisioned accounts on demand, this
instance would be as open as your Authelia is.

Identities live in the `user_identities` table, one row per provider login,
unique on `(provider, provider_user_id)`. Deleting a user deletes theirs.

## What is not stored

The `groups` scope is requested so the Authelia client is configured for it,
but the claim is not persisted and grants nothing. Prompt Queue has no roles —
every user sees only their own prompts. Don't treat Authelia group membership
as authorization here; it isn't wired to anything.

## Logging out

Logging out ends the Prompt Queue session only. It does not end the Authelia
session, so clicking **Continue with Authelia** again will sign straight back
in. Log out of Authelia itself to fully sign out.

## Two-factor and password resets

Password resets are enabled and use `MAIL_MAILER`; left as `log`, reset links
go to the container log rather than an inbox. Two-factor authentication is not
enabled in `config/fortify.php` — if you want a second factor, put it in front
of Authelia via its own `authorization_policy`.
