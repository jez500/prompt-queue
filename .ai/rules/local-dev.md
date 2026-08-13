# Local development

**Globs:** `.lando.yml`, `vite.config.ts`

Lando is optional. `composer setup && composer dev` on the host works and is
lighter; Lando exists for a matching, throwaway environment.

## Why there is no recipe

`recipe: laravel` insists on building a database service. Both `database: sqlite`
and `database: none` fail — the recipe resolves the value to a `laravel-<value>`
service type that does not exist in the builder registry:

```
Could not find a service builder called laravel-sqlite in the builder registry!
```

This app is SQLite-only, so the appserver is defined directly. That also avoids
running a MySQL container nothing would connect to.

## Apache, not nginx

`via: apache` on purpose. `via: nginx` spawns a separate `<service>_nginx`
sidecar that reaches PHP over the `fpm` network alias. On Docker 29 that
sidecar is attached only to `lando_bridge_network`, never to the project
network where `fpm` lives, so it dies on boot:

```
[emerg] host not found in upstream "fpm" in /opt/bitnami/nginx/conf/vhosts/lando.conf:11
```

Apache runs inside the PHP container, so there is no cross-container hop.

Related: Lando 3.26 supports Docker Engine `>=18 <28` and warns on newer. If
services behave strangely, check the Docker version before anything else.

## Node lives in the appserver

Vite cannot run in a separate node service: the Wayfinder plugin shells out to
`php artisan wayfinder:generate` during the build, so it needs PHP on the same
filesystem. Node is installed into the appserver via `build_as_root`.

## The Vite dev server URL

Vite must bind `0.0.0.0` to be reachable through the published port, but the
URL it advertises has to be one the *browser* can resolve. Left alone, the
plugin writes `http://0.0.0.0:5273` into `public/hot` and every asset 404s.

`.lando.yml` sets `VITE_DEV_SERVER_ORIGIN`, and `vite.config.ts` builds its
`server` block from it — `origin` (which laravel-vite-plugin prefers over the
resolved address) plus `hmr.host`. The variable is unset outside Lando, so the
whole block is inert for `npm run dev` on the host. Do not hardcode Lando
assumptions into `vite.config.ts` any other way.

Port **5273**, not Vite's default 5173, which is commonly already taken by
another Lando project. Host and container use the same number so the advertised
URL is valid on both sides.

## Traps

- **`lando restart` does not pick up env or config changes.** It stops and
  starts existing containers. Anything touching `overrides.environment`,
  `build`, or ports needs `lando rebuild -y`. This wastes a lot of time if you
  do not know it.
- **Hostnames need an `/etc/hosts` entry.** Lando adds one at start, which
  needs root. Without it `*.lndo.site` may fall through to wildcard DNS and hit
  something else entirely — the proxy only ever binds `127.0.0.1`. Verify with
  `curl -H "Host: prompt-queue.lndo.site" http://127.0.0.1/` before believing a
  404 in the browser.
- **`ssl: true` is required, not optional.** `*.lndo.site` is on Chrome's HSTS
  preload list, so browsers always upgrade to HTTPS. Without certs the proxy
  has no `:443` route and every browser request 404s — while `curl http://…`
  returns 200, which makes it look like a browser or DNS fault. If the app
  works under curl but 404s in the browser, check `hasCerts` in `lando info`.
- **Check for port clashes across projects** with `docker ps --format '{{.Names}}\t{{.Ports}}'`
  before assigning a new one.
