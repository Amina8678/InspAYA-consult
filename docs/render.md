# Deploying to Render (Docker) with Neon

First-deploy guide for the Render setup: one Docker image, two services
(web, worker), database on Neon. The VPS guide is [deployment.md](deployment.md);
read its sections on environment variables, backups and troubleshooting too,
they apply unchanged. Files: `Dockerfile`, `docker/`, `render.yaml`,
`.env.render.example`.

## 1. How it fits together

| Piece | What runs | Notes |
|---|---|---|
| `inspaya-web` | nginx + php-fpm under supervisord | Listens on Render's `$PORT`; health check `/up`; persistent disk at `storage/app/public` |
| `inspaya-worker` | `queue:work` + `schedule:work` under supervisord | No disk, no HTTP. Replaces the VPS supervisor + cron line |
| Neon | PostgreSQL | External; sessions, cache and queue all use it (`database` drivers) |

One image serves both roles. The role is the first argument to the container
or `APP_ROLE` (`web` default, or `worker`); `render.yaml` sets `APP_ROLE`.
Any other command (`php artisan ...`, `bash`) is run as given, which is how the
pre-deploy command and the Render shell work.

At every container **start** the entrypoint builds `config`, `route`, `view`
and `event` caches (the environment only exists at runtime). It never runs
`migrate` or `db:seed`.

### PHP extensions in the image

Chosen from `composer.json` and a search of the code:

| Extension | In image | Why |
|---|---|---|
| `pdo_pgsql` | yes (built) | Neon. `pgsql` (non-PDO) is not needed. |
| `fileinfo` | yes (base image) | Declared in `composer.json`; uploads are sniffed with it |
| `mbstring`, `openssl`, `ctype`, `dom`, `xml`, `tokenizer`, `curl` | yes (base image) | Laravel |
| `pcntl` | yes (built) | `queue:work --timeout` needs it to kill a stuck job |
| `intl` | yes (built) | The admin media views call `Number::fileSize()`, which needs it (a missing `intl` shows as a 500 on `/admin/media`) |
| `opcache` | yes (built) | Production speed; `validate_timestamps=0` because the image is immutable |
| GD / imagick | **no** | Nothing resizes or re-encodes images. `FileInspector` only calls `getimagesize()`, which is PHP core |
| `zip`, `bcmath` | **no** | Nothing in `app/`, `config/` or `resources/` uses them. (`zip` is only used by Composer in the build stage) |

The build fails if a required extension is missing.

`postgresql-client-18` comes from the PostgreSQL project's apt repository, so
`pg_dump` is available in the Render shell and is never older than the Neon
server (a newer client can dump an older server, not the reverse). libpq 18
also means SNI is on (needed by Neon, libpq 14+) and `sslrootcert=system`
works.

## 2. Neon setup

1. Create **one Neon project per environment** (staging and production), both
   in **Frankfurt (`aws-eu-central-1`)** so they sit next to Render's
   `frankfurt` region. Separate projects, not branches of one project, so a
   staging mistake or restore can never touch production data.
2. In each project, create a database and a role for the app.
3. **Connection string: use the direct host**, `ep-xxxx.eu-central-1.aws.neon.tech`,
   **not** `ep-xxxx-pooler...`. The pooler is PgBouncer in transaction mode,
   which breaks `FOR UPDATE` row locks, advisory locks and migrations.
   Split the string into `DB_HOST`, `DB_DATABASE`, `DB_USERNAME`,
   `DB_PASSWORD` (`config/database.php` also accepts `DB_URL`, but the
   discrete keys are what the example file and `launch:check` assume).
4. TLS: `DB_SSLMODE=require` (the default in `.env.render.example`). `launch:check`
   **fails** if a remote Postgres connection could fall back to plaintext
   (`disable`, `allow`, `prefer`).
   For certificate checking set `DB_SSLMODE=verify-full` and
   `DB_SSLROOTCERT=/etc/ssl/certs/ca-certificates.crt` (the image's CA bundle;
   `system` also works with libpq 18). Neon's certificates chain to a public
   root, so no custom CA file is needed.
5. SNI: libpq 18 in the image sends the server name, which is how Neon routes
   a connection to the right project. (Verified: the image reports
   `PostgreSQL(libpq) Version => 18.6`.) If you ever swap the base image for
   one with libpq older than 14, add `options=endpoint%3Dep-xxxx` to the
   connection instead.

## 3. First deploy

1. Push the repo to GitHub/GitLab.
2. Render dashboard: **New > Blueprint**, pick the repo and branch. It creates
   `inspaya-web`, `inspaya-worker` and the `inspaya-shared` environment group.
   No database or cron service is created.
3. Fill the **environment group** `inspaya-shared` in the dashboard. The
   blueprint pre-fills the non-secret keys. Render does not allow secrets
   (`sync: false`) in a group, so add these yourself, once, in the group:

   | Key | Value |
   |---|---|
   | `APP_KEY` | `php artisan key:generate --show` on your machine. Keep a copy in the password manager |
   | `APP_URL` | `https://your-domain` (or the `onrender.com` URL first), no trailing slash |
   | `DB_HOST`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD` | From Neon (direct host) |
   | `MAIL_USERNAME`, `MAIL_PASSWORD`, `MAIL_FROM_ADDRESS` | From Brevo (section 8) |
   | `SUPER_ADMIN_NAME`, `SUPER_ADMIN_USERNAME`, `SUPER_ADMIN_EMAIL`, `SUPER_ADMIN_PASSWORD` | First admin; 12+ character password |

   Because both services read the same group, they cannot drift apart. The
   keys `BASIC_AUTH_*`, `STAGING_NOINDEX` and `PHP_FPM_MAX_CHILDREN` live on
   the web service, `QUEUE_SLEEP` on the worker.
4. Trigger a deploy. The pre-deploy command `php artisan migrate --force` runs
   in a one-off container first; the web service only goes live if it
   succeeds and `/up` answers.
5. **Seed once**, from the web service's **Shell** tab (never in a start or
   pre-deploy command, so a redeploy can never re-seed):

   ```bash
   php artisan db:seed --force
   ```

   Then sign in at `/admin`, change the Super Admin password, and **delete
   `SUPER_ADMIN_PASSWORD` from the environment group** so the password does not
   linger in the environment.
6. In the same shell:

   ```bash
   php artisan launch:check
   ```

   It must pass before the domain is public: production env, debug off, mail
   configured, trusted proxies, **TLS to the database**, legal pages published,
   no placeholder content, no failed jobs.

## 4. Custom domain and DNS

Web service > **Settings > Custom Domains > Add**. Render shows the DNS
records: a `CNAME` to the `onrender.com` host for a subdomain, or the `A`
records it lists for an apex domain. Add them at your DNS provider; Render
issues the TLS certificate once the records resolve. Then set `APP_URL` to the
final `https://` address in the environment group and redeploy (reset links,
sitemap and the CSP image host come from it). Raise `HSTS_MAX_AGE` only after
the domain has been stable on HTTPS for a while.

## 5. Staging protection

On a **staging** web service only (never production), set in the dashboard:

| Key | Effect |
|---|---|
| `BASIC_AUTH_USER` + `BASIC_AUTH_PASSWORD` | nginx basic auth on everything except `/up` (Render's health check keeps working). Both must be set |
| `STAGING_NOINDEX=1` | adds `X-Robots-Tag: noindex, nofollow` to every response, including static files and 404s |

No credentials are in git; the password is hashed into an htpasswd file when
the container starts. Staging has its own Neon project, its own `APP_KEY` and
its own `inspaya-shared` values. Create it with a second blueprint (change the
service and group names) or as a separate Render environment.

## 6. Neon: idle suspend, connection recovery, compute hours

**Suspend.** On the free plan Neon suspends compute after 5 minutes without
activity (not configurable; Launch can disable it, Scale can tune it). The
first connection after that waits for the compute to wake (about 0.5-2 s); open
connections held by php-fpm or the worker were dropped by the server.

**Recovery.** The app does not hold persistent connections: each request opens
its own, so a request after a suspend simply connects (slowly). For a
connection that is already open when the server drops it, Laravel recognises
the error (`server closed the connection unexpectedly`, `SSL connection has
been closed unexpectedly`, `SSL SYSCALL error: EOF detected`), reconnects and
re-runs the query. This is tested in `tests/Feature/Database/ConnectionRecoveryTest.php`:
the second test kills the live server backend from another connection and
asserts the next query succeeds on a fresh one (runs on the PostgreSQL and
MySQL CI jobs). What this cannot cover: a query inside an open transaction
(Laravel never replays those; the request fails and the visitor retries) and
a cold start long enough to hit a client timeout.

**The worker keeps the database awake.** `queue:work` polls the `jobs` table
and `schedule:work` runs every minute and writes the scheduler heartbeat to the
`cache` table. As long as the worker service runs, Neon never reaches 5 idle
minutes, so the compute runs 24/7: about 730 h a month on 0.25 CU. The free
plan includes 100 CU-hours per project per month (about 400 h at 0.25 CU), so
**a running worker alone uses the free allowance up in roughly two weeks.**
Plan for a paid Neon plan on production, or:

- **Staging tip:** suspend the Render **worker** service (Settings > Suspend)
  when you are not testing email or scheduled posts. With it off, only web
  traffic wakes Neon, and the compute sleeps between visits. Queued emails wait
  in the `jobs` table until you resume it.

**`QUEUE_SLEEP`** (seconds the worker waits when the queue is empty, default
3) is the trade-off between latency and query volume: a contact-form email
leaves up to `QUEUE_SLEEP` seconds after it is queued, and each idle poll is
one query. Raising it (say 10-30) cuts query volume but does **not** let Neon
sleep, because `schedule:work` still writes every minute. It is not a
compute-saving setting; suspending the worker is. The jobs keep their own
retry policy (contact emails: 5 attempts, 1/5/15/60 min); `--tries=3
--backoff=60` only applies to jobs that set none (password reset).

## 7. Connection budget

Direct Neon connections are capped by compute size: **0.25 CU = 104
connections, 0.5 CU = 209, 1 CU = 419**, of which 7 are reserved for Neon's own
superuser account (Neon docs; check the current table). Smallest compute, 97
usable.

| Consumer | Connections (worst case) | Basis |
|---|---|---|
| Web: php-fpm `pm.max_children` | **8** | One connection per in-flight request, none persistent. `PHP_FPM_MAX_CHILDREN` |
| Worker: `queue:work` | 1 | Single process, one job at a time |
| Worker: `schedule:work` | 1 | Runs `schedule:run` as a child every minute; one at a time |
| Pre-deploy `migrate` | 1 | Only during a deploy |
| Render shell / `pg_dump` / `launch:check` | 3 | You, occasionally |
| Neon SQL editor / psql | 2 | You, occasionally |
| **Total** | **16** | |

16 of 97 usable. Assumptions: one web instance and one worker instance (a
service with a disk cannot scale out); a disk-backed web service is stopped
before the new one starts, so old and new web instances do not overlap (the
worker briefly does, +2); the worker runs one `queue:work` process. 8 php-fpm
children at roughly 40-60 MB each also fit Render's smallest paid web plan
(512 MB). If you add worker processes or raise `PHP_FPM_MAX_CHILDREN`, redo this
sum; stay under about half the usable connections.

## 8. Brevo (mail)

SMTP relay settings (Brevo: SMTP & API > SMTP):

```
MAIL_MAILER=smtp
MAIL_HOST=smtp-relay.brevo.com
MAIL_PORT=587          # STARTTLS
MAIL_SCHEME=null
MAIL_USERNAME=<the SMTP login Brevo shows, not your account email>
MAIL_PASSWORD=<an SMTP key you generate in Brevo>
MAIL_FROM_ADDRESS=<a sender or domain verified in Brevo>
```

Authenticate the sending domain in Brevo (SPF/DKIM/DMARC records) or mail will
be filtered. The worker sends the mail; check it with a contact-form test and
`php artisan queue:failed`.

## 9. Why `TRUSTED_PROXIES=*` is acceptable here

The app redirects HTTP to HTTPS in production, and decides "is this request
secure?" from `X-Forwarded-Proto`, which it only believes from a trusted proxy.
On Render the router terminates TLS and connects to the container from changing
internal addresses, so no fixed IP list exists. `*` is acceptable **because the
container is reachable only through Render's router**: there is no public
port, and a client cannot connect to nginx directly to forge the header. (The
VPS guide warns against `*` precisely when the app is also reachable directly;
that is not the case here.) Do not copy this value to a host that exposes
the container's port to the internet.

`tests/Feature/Security/TrustedProxiesEnvFileTest.php`
(`test_a_wildcard_behind_a_platform_proxy_causes_no_redirect_loop`) boots the
app in production with `TRUSTED_PROXIES=*` and requests from several
internal addresses with `X-Forwarded-Proto: https`: status 200, no `Location`,
HSTS present. nginx passes `X-Forwarded-Proto` through unchanged and never
builds a redirect from its own scheme or port.

**Health check.** Render probes `/up` over plain HTTP. `ForceHttps` exempts
exactly `GET`/`HEAD /up` (it only returns `ok` or `503`); every other path
keeps the 301 and HSTS.

## 10. Rollback, snapshots, backups

**Rollback.** Web service > Events/Deploys > pick an earlier successful deploy
> **Rollback**. This redeploys the old image; it does **not** undo database
migrations or environment changes. Migrations here are additive, but before a
deploy that contains a destructive migration, take a manual dump
(below) or a Neon restore point. A bad deploy that fails its pre-deploy command
or health check never replaces the running version.

**Uploads (disk).** The disk at `storage/app/public` is a Render snapshot
target: Render snapshots it every 24 hours and keeps each for 7 days; a restore
returns the **whole disk** to that snapshot (no partial restore). The disk is
visible to the web service only, not to the worker, pre-deploy or one-off jobs.
Uploads are not in the database, so also copy them off-site occasionally
(Render shell: `tar czf - -C storage/app public | ...`, or `rclone` to object
storage).

**Database.**

| Neon plan | Point-in-time restore window |
|---|---|
| Free | 6 hours (capped at 1 GB-month of changes) |
| Launch | up to 7 days (default 1 day) |
| Scale | up to 30 days (default 1 day) |

That is a restore tool, not an off-site backup (it lives in the same Neon
project). **Recommended: a scheduled `pg_dump` to storage you control**, for
example a GitHub Actions cron or any small scheduled job outside Render:

```bash
pg_dump "postgresql://USER:PASSWORD@ep-xxxx.eu-central-1.aws.neon.tech/DB?sslmode=require" \
  --format=custom --no-owner --file="inspaya-$(date +%F).dump"
# then upload to S3 / Backblaze / Drive, keep 14+ days, and test a restore
# into a scratch Neon branch now and then
```

`pg_dump` is also in the Render shell (build the connection string from the
`DB_*` variables) for a one-off dump before risky changes. Restore with
`pg_restore --no-owner -d <target>`. Back up `APP_KEY` separately.

## 11. What differs from the VPS guide

| | VPS ([deployment.md](deployment.md)) | Render |
|---|---|---|
| Instances | As many as you run | **One** web instance (a disk blocks scaling out) |
| Deploys | `git pull`, reload php-fpm, zero downtime | New image per deploy; **a few seconds of downtime** per web deploy (the old instance stops before the new one starts, because of the disk) |
| Worker | supervisor + cron | Separate worker service; `schedule:work` replaces cron |
| Worker and uploads | Same filesystem | **Worker cannot see the disk.** Anything a queued job needs from `storage/app/public` would be missing; today's jobs (emails) do not use it |
| Config | `.env` file, `config:cache` by hand | Env vars in Render; caches rebuilt at every container start |
| Logs | Files under `storage/logs` | `LOG_CHANNEL=stderr` to Render's log stream |
| Migrations | Run by hand | Pre-deploy command |
| Seeding | Manual | Manual, once, from the Render shell |
| TLS / proxy | Your nginx and certs | Render terminates TLS; `TRUSTED_PROXIES=*` |
| Cron-free scheduling | n/a | The worker must stay running or scheduled posts, pruning and the scheduler heartbeat stop |

## 12. Troubleshooting

- **Deploy fails at pre-deploy:** read the migration error in the deploy log;
  common cause is a wrong `DB_HOST` (pooler host) or `DB_SSLMODE`.
- **Redirect loop:** `TRUSTED_PROXIES` is not `*` in the group, or the service
  was not redeployed after changing it.
- **`/up` returns 503:** the database is unreachable (credentials, Neon
  project suspended or over quota). `launch:check` prints the connection error.
- **Emails not arriving:** worker suspended or crashed (`/admin/health`:
  `queue_pending`, `scheduler_last_run_seconds`), or Brevo sender not verified.
- **Uploads vanish after a deploy:** the disk is not mounted at
  `/var/www/html/storage/app/public`.
