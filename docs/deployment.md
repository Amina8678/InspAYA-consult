# Deployment

One checklist to take InspAya Consult from an empty server to a live site, and
to deploy and roll back afterwards. Written to be followed cold. Commands
assume the app lives in `/var/www/inspaya` and runs as the `deploy` user;
change those to suit.

Related: [launch.md](launch.md) (the final gate), [admin-auth.md](admin-auth.md)
(proxies, HSTS, queue, throttling), [admin-media.md](admin-media.md) (uploads).

## 1. Server requirements

| Need | Detail |
|---|---|
| PHP | **8.4 or newer**, with `pdo_mysql`, `mbstring`, `openssl`, `fileinfo` (uploads are sniffed with it), `dom`, `xml`, `ctype`, `json`, `tokenizer`, `curl`. `gd` is not required. Run `composer check-platform-reqs --no-dev` to confirm. |
| Database | **MySQL 8.0+** (or MariaDB 10.6+), `utf8mb4`. A dedicated database and user with full rights on it only. |
| Web server | nginx + PHP-FPM or Apache + PHP-FPM. Document root is `public/`, never the project root. |
| Process manager | `supervisor` (or systemd) for the queue worker. |
| Cron | One line for the scheduler. |
| Composer | 2.x, on the server or run in CI with the result copied over. No Node, npm or build step: the CSS and JS in `public/` are plain committed files. |
| TLS | A certificate for the domain (Let's Encrypt is fine). |
| PHP limits | `upload_max_filesize = 12M`, `post_max_size = 14M` (the media library accepts up to 10 MB PDFs and 5 MB images; PHP's defaults would reject them first). `memory_limit = 256M`. |
| Writable by the PHP user | `storage/` and `bootstrap/cache/` (recursively). |

Optional but recommended: OPcache on (`opcache.enable=1`,
`opcache.validate_timestamps=0` and reload PHP-FPM on each deploy).

## 2. Web server

The app sends its own security headers (CSP, HSTS, X-Frame-Options, nosniff,
Referrer-Policy) on every response PHP produces. Do not add duplicates for
PHP-served pages. Files nginx or Apache serve directly (`/storage`, CSS/JS)
bypass PHP and so bypass those headers; the examples below cover what matters.

### nginx

```nginx
server {
    listen 80;
    server_name inspaya.example;            # your domain
    return 301 https://$host$request_uri;
}

server {
    listen 443 ssl http2;
    server_name inspaya.example;

    ssl_certificate     /etc/letsencrypt/live/inspaya.example/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/inspaya.example/privkey.pem;

    root /var/www/inspaya/public;
    index index.php;

    client_max_body_size 14M;               # matches post_max_size

    gzip on;
    gzip_vary on;
    gzip_comp_level 5;
    gzip_min_length 1024;
    gzip_types text/css application/javascript application/json application/xml image/svg+xml text/plain;

    # Versioned CSS/JS/icons (?v=... changes whenever the file does).
    location ~* \.(css|js|svg|ico|woff2)$ {
        expires 1y;
        add_header Cache-Control "public, max-age=31536000, immutable";
        add_header X-Content-Type-Options "nosniff" always;
        try_files $uri =404;
    }

    # Uploaded media. NEVER execute scripts here, whatever the extension.
    location ^~ /storage/ {
        expires 30d;
        add_header Cache-Control "public, max-age=2592000";
        add_header X-Content-Type-Options "nosniff" always;
        add_header Content-Security-Policy "default-src 'none'; style-src 'unsafe-inline'; sandbox" always;
        try_files $uri =404;               # no fastcgi_pass in this block
    }

    # The site's own images (unversioned, so a shorter cache).
    location ^~ /asset/ {
        expires 7d;
        add_header Cache-Control "public, max-age=604800";
        try_files $uri =404;
    }

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        # Only the front controller runs PHP.
        if ($uri !~ "^/index\.php$") { return 404; }
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        fastcgi_pass unix:/run/php/php8.4-fpm.sock;
    }

    location ~ /\.(?!well-known) { deny all; }
}
```

If a load balancer or CDN terminates TLS in front of nginx instead, listen on
80 behind it and read section 3 on `TRUSTED_PROXIES`.

### Apache

`public/.htaccess` already routes everything to `index.php`. Enable
`mod_rewrite`, `mod_deflate`, `mod_expires` and `mod_headers`, point
`DocumentRoot` at `public/`, and add `AllowOverride All` for it:

```apache
<VirtualHost *:443>
    ServerName inspaya.example
    DocumentRoot /var/www/inspaya/public
    SSLEngine on
    SSLCertificateFile    /etc/letsencrypt/live/inspaya.example/fullchain.pem
    SSLCertificateKeyFile /etc/letsencrypt/live/inspaya.example/privkey.pem

    <Directory /var/www/inspaya/public>
        AllowOverride All
        Require all granted
    </Directory>

    AddOutputFilterByType DEFLATE text/css application/javascript application/json application/xml image/svg+xml text/plain

    <FilesMatch "\.(css|js|svg|ico|woff2)$">
        ExpiresActive On
        ExpiresDefault "access plus 1 year"
        Header set Cache-Control "public, max-age=31536000, immutable"
    </FilesMatch>

    # Uploaded media: never execute anything here.
    <Directory /var/www/inspaya/public/storage>
        php_flag engine off
        RemoveHandler .php .phtml .php3 .php5 .phar
        ExpiresActive On
        ExpiresDefault "access plus 30 days"
        Header set X-Content-Type-Options "nosniff"
        Header set Content-Security-Policy "default-src 'none'; style-src 'unsafe-inline'; sandbox"
        Options -ExecCGI -Indexes
    </Directory>
</VirtualHost>
```

With PHP-FPM (not `mod_php`), `php_flag` is unavailable; keep `RemoveHandler`
and make sure the FPM handler is only attached via
`<FilesMatch "^index\.php$">`.

## 3. Environment variables

Start from the production template, never from `.env.example` (which is
development-oriented: debug on, `log` mailer, SQLite):

```bash
cp .env.production.example .env
chmod 640 .env
```

`.env` must be readable only by the deploy and PHP users. **Keep a copy of
`APP_KEY` somewhere safe (password manager):** losing it signs everyone out and
invalidates anything encrypted with it.

| Variable | Required? | Notes |
|---|---|---|
| `APP_NAME` | yes | The site name. Not `Laravel` (`launch:check` fails). |
| `APP_ENV` | yes | `production`. Selects the production seed and the HTTPS redirect. |
| `APP_DEBUG` | yes | `false`. Leaks stack traces and config when true. |
| `APP_KEY` | yes | `php artisan key:generate --force`, once. |
| `APP_URL` | yes | Public HTTPS URL, no trailing slash. Used for reset links, the sitemap and media URLs; the CSP only allows images from the same host. |
| `TRUSTED_PROXIES` | yes behind a proxy | IP or CIDR list of the proxy that terminates TLS (for example `10.0.0.5`). Blank means "trust nobody": correct only when this server terminates TLS itself (then run `launch:check --direct-tls`). Wrong or blank behind a proxy causes a **redirect loop**. `*` trusts whoever connects, so use it only if the app cannot be reached except through the proxy. It is read from `.env` through `config/trustedproxy.php`, so it works with `config:cache`. |
| `DB_CONNECTION`, `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD` | yes | MySQL. |
| `MAIL_MAILER` (`smtp`), `MAIL_HOST`, `MAIL_PORT`, `MAIL_USERNAME`, `MAIL_PASSWORD`, `MAIL_SCHEME`, `MAIL_FROM_ADDRESS`, `MAIL_FROM_NAME` | yes | The client's SMTP provider. `log` and `array` never deliver. |
| `QUEUE_CONNECTION` | yes | `database` (default in the template). Not `sync`. |
| `SESSION_DRIVER`, `CACHE_STORE` | yes | `database` in the template. `SESSION_SECURE_COOKIE=true`. |
| `SUPER_ADMIN_NAME`, `SUPER_ADMIN_USERNAME`, `SUPER_ADMIN_EMAIL`, `SUPER_ADMIN_PASSWORD` | first seed only | 12+ character password. Remove `SUPER_ADMIN_PASSWORD` afterwards (section 5). |
| `LOG_CHANNEL=daily`, `LOG_LEVEL=warning`, `LOG_DAILY_DAYS` | recommended | Rotated logs, 14 days by default. |
| `HSTS_MAX_AGE` | optional | Seconds, default 604800 (1 week). Raise once HTTPS is stable. |
| `AUDIT_LOGIN_RETENTION_DAYS` | optional | Default 90. |
| `DB_QUEUE_RETRY_AFTER` | optional | Default 90 seconds; must stay larger than the worker's `--timeout`. |

After any change to `.env` on a server that caches config:
`php artisan config:cache` (and `supervisorctl restart inspaya-worker:*`).

## 4. First deploy

As the deploy user, in the project directory, in this order:

```bash
git clone <repo> /var/www/inspaya && cd /var/www/inspaya
git checkout <release tag or main>
cp .env.production.example .env           # then fill it in (section 3)

composer install --no-dev --optimize-autoloader --no-interaction
php artisan key:generate --force          # only if APP_KEY is still blank

php artisan migrate --force
php artisan db:seed --force               # ONCE. See the warning below.
php artisan storage:link

php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache

sudo chown -R www-data:www-data storage bootstrap/cache
sudo chmod -R ug+rwX storage bootstrap/cache
```

Then set up the queue worker (section 6) and the cron line (section 7), and
reload PHP-FPM.

> **Run `db:seed` once only.** `RolesAndPermissionsSeeder` resets every role's
> permissions to the matrix in code, so re-running it silently undoes role
> changes a Super Admin made in the CMS. Settings, services, core values, pages
> and the Super Admin password are never overwritten by a reseed; roles are the
> exception. Later permission changes ship as a migration or a reviewed
> one-off seeder.

What the production seed creates (and does not):

- 4 roles with their permissions, and the Super Admin from `SUPER_ADMIN_*`
- site settings, with the placeholder ones **blank** (contact details, meta
  description, sender and enquiry recipient)
- the 7 services and 6 core values, **inactive**, with placeholder copy
- 4 empty **draft** pages: Home, About, Privacy Policy, Terms of Service
- no demo content, consultants, posts, testimonials or FAQs

After the first sign-in: change the Super Admin password in the CMS, delete
`SUPER_ADMIN_PASSWORD` from `.env`, run `php artisan config:cache` again.

## 5. Before going public

1. The client supplies content (section 11); enter it in the CMS: Settings
   (contact details, meta description, **"Send new enquiries to"**, social
   links, logo), services (edit, then tick Active), core values, consultants,
   Home/About, and publish **Privacy Policy** and **Terms of Service**.
2. Send a real contact-form enquiry and confirm both emails arrive.
3. `php artisan config:cache`, then:

   ```bash
   php artisan launch:check
   ```

   It exits non-zero and lists every problem. Fix them all. Details in
   [launch.md](launch.md).
4. Confirm the section 8 backup has run once and a restore has been tested.
5. Point DNS at the server. Check `https://<domain>/up` returns `{"status":"ok"}`.

## 6. Queue worker (supervisor)

Contact-form emails and password-reset emails are queued. **Without a running
worker nobody receives them and nothing reports an error.** (The dashboard
warns Admins when a job has waited over 15 minutes; do not rely on that alone.)

`/etc/supervisor/conf.d/inspaya-worker.conf`:

```ini
[program:inspaya-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /var/www/inspaya/artisan queue:work database --sleep=3 --tries=3 --backoff=60 --timeout=60 --max-time=3600
directory=/var/www/inspaya
user=www-data
numprocs=2
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
stopwaitsecs=70
redirect_stderr=true
stdout_logfile=/var/www/inspaya/storage/logs/worker.log
```

```bash
sudo supervisorctl reread && sudo supervisorctl update
sudo supervisorctl status          # inspaya-worker:* RUNNING
```

Notes:

- The two contact-form emails set their own retry policy: 5 attempts, waiting
  1, 5, 15 and 60 minutes. `--tries=3 --backoff=60` applies to jobs that do
  not (password reset).
- `--max-time=3600` makes each worker restart hourly, which picks up new code;
  `queue:restart` (section 9) does it immediately.
- `--timeout` (60) must stay below `DB_QUEUE_RETRY_AFTER` (90).
- A job that exhausts its attempts goes to `failed_jobs` and shows on the
  dashboard. List and retry them with `php artisan queue:failed` and
  `php artisan queue:retry all` (see [admin-auth.md](admin-auth.md) "Failed and
  stuck jobs").

## 7. Scheduler (cron)

```cron
* * * * * www-data cd /var/www/inspaya && php artisan schedule:run >> /dev/null 2>&1
```

It runs, every minute: the scheduled-post announcements (and sitemap
refresh), the heartbeat shown on `/admin/health`, and daily at 03:30 the login
audit pruning. Without it scheduled posts still appear on time, but the audit
trail, sitemap freshness and pruning stop silently. Check
`/admin/health`: `scheduler_last_run_seconds` should be under about 60.

## 8. Backups and restore

Nothing in the repository takes backups; set this up on the host before
launch. What must be backed up:

| What | How | Frequency |
|---|---|---|
| Database | `mysqldump --single-transaction --routines --default-character-set=utf8mb4 inspaya \| gzip > inspaya-$(date +%F).sql.gz` | Daily, keep 14+ days |
| Uploads | `tar czf uploads-$(date +%F).tar.gz -C /var/www/inspaya/storage/app public` | Daily, or `rsync` to the backup host |
| `.env` | Copy to a password manager or encrypted store. Holds `APP_KEY` and SMTP/database credentials. | When it changes |

Ship them off the server (object storage, another host). A backup that sits
on the machine it protects is not a backup.

### Restore drill

Do this once before launch, then every quarter, on a **separate** database
(never over production):

```bash
# 1. Database
mysql -e "CREATE DATABASE inspaya_restore CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"
gunzip < inspaya-YYYY-MM-DD.sql.gz | mysql inspaya_restore

# 2. Check the data came back
mysql inspaya_restore -e "SELECT COUNT(*) FROM users; SELECT COUNT(*) FROM pages; SELECT COUNT(*) FROM contact_submissions; SELECT MAX(created_at) FROM audit_logs;"

# 3. Uploads: unpack to a scratch directory and compare the file count
mkdir /tmp/uploads-check && tar xzf uploads-YYYY-MM-DD.tar.gz -C /tmp/uploads-check
find /tmp/uploads-check/public -type f | wc -l
mysql inspaya_restore -e "SELECT COUNT(*) FROM media"     # roughly equal

# 4. Optional: boot a copy of the app against it
DB_DATABASE=inspaya_restore php artisan about
```

Write down how long it took and what you had to look up. For a real
disaster: install the app (section 4 without `db:seed`), restore the database
and `storage/app/public`, restore `.env`, `php artisan storage:link`, the
caches, restart the worker, then `launch:check`.

## 9. Routine deploys

```bash
cd /var/www/inspaya
git fetch --tags && git checkout <new tag>        # note the old tag first
composer install --no-dev --optimize-autoloader --no-interaction
php artisan migrate --force
php artisan config:cache && php artisan route:cache && php artisan view:cache && php artisan event:cache
php artisan queue:restart
sudo systemctl reload php8.4-fpm                  # resets OPcache
```

Then smoke-test: `/up`, the home page, the contact form page, the admin login.

**Migrations during a deploy.** Every migration so far is additive (new tables
or columns), so old code keeps working against the new schema for the few
seconds between `migrate` and the cache commands, and `migrate` can run with
the site live. Keep it that way: ship destructive changes (drops, renames) in
two releases, and for one that cannot be avoided use
`php artisan down --retry=60` first and `php artisan up` after. Never run
`migrate:fresh`, `migrate:reset` or `db:wipe` on production.

**Do not run `db:seed` again** (section 4 warning).

### Rollback

1. Note the previous tag before deploying.
2. Code: `git checkout <previous tag>`, then the same composer, cache and
   `queue:restart` steps as a deploy, and reload PHP-FPM.
3. Database: because migrations are additive, the old code runs fine against
   the newer schema, so **usually do not roll the schema back**. If a
   migration itself was wrong, `php artisan migrate:rollback --step=1 --force`
   runs its `down()`, which drops what it created (data in that table or
   column is lost); otherwise restore the pre-deploy database backup. Take a
   `mysqldump` immediately before any deploy that contains a migration.
4. Verify with the same smoke tests, and check `/admin/health`.

## 10. Monitoring

| What | How |
|---|---|
| Is it up? | An external monitor (UptimeRobot, Better Stack, Pingdom or similar) on `https://<domain>/up` every minute. It returns `{"status":"ok"}` (200) or `{"status":"unavailable"}` (503) and runs a real database query. Probe the HTTPS URL; plain HTTP gets a 301. |
| Is the background work healthy? | Sign in as an Admin and open `/admin/health` (JSON): `queue_pending`, `queue_oldest_pending_seconds`, `failed_jobs`, `scheduler_last_run_seconds`. Alert on `failed_jobs > 0`, an oldest pending job over 900 s, or a scheduler run over 180 s old. A monitor can poll it with a session cookie, or run `php artisan tinker` / SQL on the server for the same numbers. |
| Errors | Logs rotate daily in `storage/logs/` at `warning` and above. Ship them to a log service, or add an error tracker such as Sentry or Flare (`composer require`, then set its DSN in `.env`). Not installed by default. |
| Disk, certificate, backups | Host-level alerts: disk over 80%, certificate expiry under 14 days, backup job failed or older than 26 hours. |
| Dashboard | The CMS dashboard's "Needs attention" box shows failed contact-form emails, a stuck queue, unpublished legal pages and new enquiries. |

### What an on-call person needs to diagnose a failure

1. `https://<domain>/up`. 503 means the database; check MySQL is running.
2. `tail -n 100 storage/logs/laravel-$(date +%F).log` for the exception.
3. `sudo supervisorctl status` and `storage/logs/worker.log` for the worker.
4. `/admin/health` or `php artisan queue:failed` for jobs; `php artisan schedule:list` for the schedule.
5. Disk space and permissions: `df -h`, and `storage/` writable by the PHP user.
6. Redirect loop on every page: `TRUSTED_PROXIES` is wrong or blank behind a proxy.
7. Config looks stale after an `.env` edit: `php artisan config:cache`.
8. Site down for maintenance: `php artisan up`. Branded 503 page is shown meanwhile.

## 11. Client content checklist

The site cannot launch until the client supplies these (the CMS holds them;
nothing needs a developer):

- [ ] **SMTP** account details and a verified sender address, plus the address
      that should receive new enquiries
- [ ] **Branding:** logo (and footer logo/image if different), site name as it
      should appear, a favicon (replace `public/favicon.svg` and
      `public/favicon.ico`; the shipped one is a neutral placeholder)
- [ ] **Real contact details:** public email, phone, office address, social
      links
- [ ] **Home page** copy and hero text; **About** page copy
- [ ] **Copy for each of the 7 services** (summary, description, capabilities,
      outcomes) and the **6 core values**
- [ ] **Consultant bios** and photos (with alt text)
- [ ] **Privacy Policy** and **Terms of Service** text (legal review; both
      pages must be published)
- [ ] A **default meta description** and social-sharing image
- [ ] Testimonials and FAQs, if wanted (optional; the sections hide when empty)
- [ ] Analytics ID, if wanted (it stays blocked until the consent banner is
      built; see [admin-auth.md](admin-auth.md) "Analytics consent")
- [ ] The domain, and who controls its DNS
