# Self-hosting

Prompt Queue ships as a single container: FrankenPHP, one process, SQLite by
default. You need Docker with Compose v2.

## Install

**1. Get the compose file**

```bash
mkdir prompt-queue && cd prompt-queue
curl -O https://raw.githubusercontent.com/jez500/prompt-queue/main/docker-compose.yml
curl -o .env https://raw.githubusercontent.com/jez500/prompt-queue/main/.env.docker.example
```

**2. Generate an application key**

```bash
docker run --rm jez500/prompt-queue php artisan key:generate --show
```

Paste the `base64:…` output into `APP_KEY` in `.env`.

> Use plain `docker run` here, not `docker compose run` — compose checks
> `APP_KEY` on every subcommand, so it can't generate the key it's asking for.

**3. Start it**

```bash
docker compose up -d
```

**4. Create your login**

```bash
docker compose exec app php artisan pq:create-user
```

See [authentication.md](authentication.md) for the password rules and for
wiring up single sign-on.

Then open <http://localhost:8080>.

## Configuration

Everything below is optional and set in `.env`.

| Variable | Default | Notes |
| --- | --- | --- |
| `APP_KEY` | — | **Required.** See step 2. |
| `APP_PORT` | `8080` | Host port to publish on. |
| `APP_URL` | `http://localhost:8080` | Set this to your real URL behind a proxy. |
| `APP_NAME` | `Prompt Queue` | Shown in the browser title. |
| `MAIL_MAILER` | `log` | Only used for password resets. Left as `log`, reset links go to the container log instead of being emailed. |
| `TRUSTED_PROXIES` | `*` | Which proxies may set `X-Forwarded-*`. See [Behind a reverse proxy](#behind-a-reverse-proxy). |

Single sign-on adds `AUTHELIA_*` and `HIDE_LOGIN_FORM`, all covered in
[authentication.md](authentication.md).

## Backup and restore

Data lives in the `prompt-queue-data` volume, mounted at `/app/database`.

```bash
# Back up — VACUUM INTO takes a consistent snapshot while the app is running,
# which copying the live file does not.
docker compose exec app php artisan pq:backup /app/database/backup.sqlite
docker compose cp app:/app/database/backup.sqlite ./backup.sqlite
docker compose exec app rm /app/database/backup.sqlite

# Restore — stop the app first, then put the file back.
docker compose down
docker compose cp ./backup.sqlite app:/app/database/database.sqlite
docker compose up -d

# Update
docker compose pull && docker compose up -d    # migrations run on boot
```

## Behind a reverse proxy

The container serves plain HTTP on `8080`; terminate TLS at your proxy. Set
`APP_URL` to the public HTTPS URL so links generated outside a request — such
as password reset emails — are correct.

Your proxy must forward the original scheme in `X-Forwarded-Proto`. The app
trusts any proxy by default, because the image is only ever meant to be reached
through one. If the container is also reachable directly, narrow it:

```dotenv
TRUSTED_PROXIES=10.0.0.0/8,172.16.0.0/12
```

A comma separated list of addresses or CIDR ranges. Getting this wrong in
either direction is visible: too narrow and every stylesheet, script, font and
the manifest are blocked as mixed content, because the app builds `http://`
URLs for a page the browser loaded over `https`. Traefik, Caddy and nginx-proxy
send the header by default; a hand-rolled nginx `proxy_pass` usually needs

```nginx
proxy_set_header X-Forwarded-Proto $scheme;
proxy_set_header X-Forwarded-Host  $host;
```

## Using MySQL instead

SQLite suits this workload and is the default. `docker-compose.yml` ships a
commented MySQL service — uncomment it, the `prompt-queue-db` volume, the
`depends_on` block and the `DB_*` variables, then set `DB_PASSWORD` and
`DB_ROOT_PASSWORD` in `.env`.
