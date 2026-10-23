# InspAya Consult

The InspAya Consult website: a public site (home, services, consultants,
insights, contact) and an admin CMS behind `/admin`. Laravel 13, PHP 8.4+,
Blade views, MySQL in production (SQLite locally). There is **no frontend build
step**: the CSS and JS in `public/css`, `public/js` and `public/admin-assets`
are plain committed files, and there is no Node, Vite or Tailwind.

Putting it on a server? Go straight to **[docs/deployment.md](docs/deployment.md)**.

---

## 1. Requirements

| Tool | Version |
|---|---|
| PHP | **8.4 or newer**, with `pdo_sqlite` (local/tests), `pdo_mysql` (MySQL), `mbstring`, `openssl`, `fileinfo`, `dom` |
| Composer | 2.x |
| MySQL | 8.0+ (or MariaDB 10.6+). Optional locally: SQLite is the default. Needed to run the tests the way CI does. |

Check with `php -v` and `composer -V`.

---

## 2. First-time setup

Run from the project folder.

```bash
composer install
cp .env.example .env
php artisan key:generate
```

**Set the Super Admin in `.env`.** There are no default credentials. The seeder
refuses to run until all four are filled in; the password needs 12+ characters:

```dotenv
SUPER_ADMIN_NAME="Your Name"
SUPER_ADMIN_USERNAME=admin
SUPER_ADMIN_EMAIL=you@example.test
SUPER_ADMIN_PASSWORD="a-strong-password-12+"
```

**Create the database and seed it.** `.env.example` defaults to SQLite:

```bash
touch database/database.sqlite
php artisan migrate --seed
php artisan storage:link
```

Seeding creates roles and permissions, the Super Admin, settings, the seven
services and six core values. Outside production it also adds **placeholder
demo content** (pages, consultants, blog posts, testimonials, FAQs) so every
public page has something to show. In production it adds none of that and
publishes nothing placeholder; see [docs/launch.md](docs/launch.md).

To use MySQL locally instead, set `DB_CONNECTION=mysql` and the `DB_*` lines in
`.env`, then run `php artisan migrate:fresh --seed`. `migrate:fresh` wipes
every table: never run it against a real database.

---

## 3. Running the app

```bash
composer run dev          # starts the local dev processes; see: php artisan dev --help
```

or each piece in its own terminal:

```bash
php artisan serve
php artisan queue:work
```

Open **http://localhost:8000**.

**Why the queue worker matters:** contact-form and password-reset emails are
queued; without a worker they sit in the `jobs` table and are never sent.
Locally `MAIL_MAILER=log`, so "sent" emails are written to
`storage/logs/laravel.log`: that is where reset links appear in development.

Scheduled work (scheduled posts, audit pruning, the health heartbeat) runs from
`php artisan schedule:run`; locally use `php artisan schedule:work` if you need
it.

---

## 4. The public site

| Page | URL | Notes |
|---|---|---|
| Home | `/` | Hero, services, core values, consultants, latest insights, testimonials |
| About | `/about` | 404 until the About page is published |
| Services | `/services`, `/services/{slug}` | Active services only |
| Consultants | `/consultants` | Active consultants |
| Core Values | `/core-values` | Active values |
| Insights | `/insights`, `/insights/{slug}` | Published blog posts ("Blog" in the CMS) |
| Contact | `/contact` | Form, FAQs; rate limited (3/minute, 10/hour per IP) |
| Privacy Policy, Terms | `/privacy-policy`, `/terms-of-service` | 404 until published |
| `sitemap.xml`, `robots.txt` | | Generated; published URLs only |
| Health | `/up` | `{"status":"ok"}` or 503; checks the database |

A 404 usually means "not public", not "broken": unpublished pages, inactive
services, draft or scheduled posts, and badly shaped slugs all return the
site's 404 page. Details of every page and the data it receives:
[docs/frontend-contract.md](docs/frontend-contract.md).

---

## 5. The admin CMS

Go to **/admin/login** (there is no link from the public site) and sign in with
the Super Admin you seeded. Wrong password, unknown email and a deactivated
account show the same error on purpose. Sign-in is throttled per email and IP
(5 failures, 60 s) and per IP across all emails (20 failures, 10 minutes).
Password reset: `/admin/forgot-password`; links expire after 60 minutes; new
passwords need 12+ characters with upper and lower case, a number and a symbol.

| Section | URL | Purpose |
|---|---|---|
| Dashboard | `/admin` | Counts, recent activity, and a "Needs attention" box (new enquiries, approvals, failed emails, stuck queue) |
| Pages | `/admin/pages` | Home, About, Privacy, Terms and their sections |
| Services, Consultants, Core Values | `/admin/services` etc. | List, add, edit, delete, reorder, activate |
| Testimonials, FAQs | `/admin/testimonials`, `/admin/faqs` | Same pattern |
| Blog | `/admin/posts` | Posts, plus categories and tags |
| Media | `/admin/media` | Uploads (images, PDFs) with required alt text |
| Enquiries | `/admin/enquiries` | Contact submissions, status, internal notes, CSV export |
| Content approvals | `/admin/content-approvals` | Edits to live pages and posts awaiting review ([docs/content-approval.md](docs/content-approval.md)) |
| Users | `/admin/users` | CMS accounts |
| Settings | `/admin/settings` | Site name, logos, contact details, social links, meta defaults, email addresses, analytics ID |
| Audit log | `/admin/audit-logs` | Read-only history of who changed what |
| Health detail | `/admin/health` | JSON: queue backlog, failed jobs, scheduler heartbeat |

Four roles are seeded; each account has exactly one.

| Role | Can do |
|---|---|
| Super Admin | Everything, including roles and the audit log |
| Administrator | All content and settings, publishing and approvals, users (not roles or Super Admins) |
| Editor | Edits services, values, consultants, pages and posts; handles enquiries; cannot publish, and edits to live pages and posts wait for approval |
| Author | Writes and edits their own draft posts; uploads media |

Rules and edge cases: [docs/admin-auth.md](docs/admin-auth.md).

---

## 6. Tests

The suite needs no setup on SQLite (in memory):

```bash
composer test                         # same as: php artisan test
vendor/bin/pint --test                # code style, as CI checks it
```

CI also runs the whole suite against **MySQL 8**, where the two row-lock tests
(skipped on SQLite) really run. To do the same locally, create an empty
database whose name **ends in `_testing`** (`tests/TestCase.php` refuses
anything else, because `RefreshDatabase` wipes the target) and pass the
connection as environment variables:

```bash
mysql -uroot -e "CREATE DATABASE inspaya_testing CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"

DB_CONNECTION=mysql DB_HOST=127.0.0.1 DB_PORT=3306 \
DB_DATABASE=inspaya_testing DB_USERNAME=root DB_PASSWORD= \
vendor/bin/phpunit --fail-on-skipped
```

Exports like these override `.env`, so your development database is never
touched. Use `PAO_DISABLE=1` before the command for plain PHPUnit output.
`.github/workflows/ci.yml` shows the full setup, including a migrate and seed
run against MySQL before the tests.

Other everyday commands:

| Task | Command |
|---|---|
| Tail logs | `php artisan pail` |
| List routes | `php artisan route:list` |
| Pre-launch gate | `php artisan launch:check` |
| Poke at data | `php artisan tinker` |

---

## 7. Troubleshooting

| Symptom | Fix |
|---|---|
| `Cannot seed the Super Admin: set SUPER_ADMIN_…` | Fill in all four `SUPER_ADMIN_*` values in `.env` (password 12+ characters), then `php artisan db:seed`. |
| `Refusing to run tests against [...]` | You are pointed at a real database. Use SQLite or a database whose name ends in `_testing`. |
| Uploaded images don't show | `php artisan storage:link`, and make `APP_URL` match the address in your browser. |
| No reset or contact emails | Start `php artisan queue:work`; locally read `storage/logs/laravel.log`. In production see the failed-jobs section of [docs/admin-auth.md](docs/admin-auth.md). |
| `No application encryption key` | `php artisan key:generate` |
| A public page 404s | It is probably unpublished, inactive or scheduled. |
| Changed `.env`, nothing happened | `php artisan config:clear` (production: `config:cache`). |
| Redirect loop in production | `TRUSTED_PROXIES` is wrong or blank behind a proxy; see [docs/deployment.md](docs/deployment.md). |

---

## 8. Documentation

- [docs/deployment.md](docs/deployment.md): server setup, nginx/Apache, environment, first deploy, queue worker, cron, backups and restore, deploys and rollback, monitoring, client content checklist
- [docs/launch.md](docs/launch.md): `php artisan launch:check` and what the production seed publishes
- [docs/admin-auth.md](docs/admin-auth.md): sign-in rules, throttling, password policy, email and queue setup, failed jobs, HSTS and trusted proxies
- [docs/content-approval.md](docs/content-approval.md): how edits to live content are reviewed
- [docs/admin-media.md](docs/admin-media.md): how uploads are validated, stored and served
- [docs/frontend-contract.md](docs/frontend-contract.md): every public route, view and the data it receives
- [docs/database/schema-plan.md](docs/database/schema-plan.md): the table-by-table schema and the decisions behind it
- `.env.example` (local development) and `.env.production.example` (servers)
