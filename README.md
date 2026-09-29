# InspAYA Consult

The InspAYA Consult website: a public site (home, services, consultants,
insights, contact) and an admin CMS behind `/admin`. Built with Laravel 13
and Blade views. Assets (`public/css`, `public/js`, `public/admin-assets`)
are plain committed files — there is no frontend build step.

---

## 1. Requirements

| Tool | Version |
|---|---|
| PHP | 8.4 or newer, with the `pdo_sqlite`, `mbstring`, `openssl` and `fileinfo` extensions |
| Composer | 2.x |
| MySQL | 8.0+ (or MariaDB 10.6+) — only needed if you want to run against MySQL locally; SQLite is the default |

Check with `php -v` and `composer -V`.

---

## 2. First-time setup

Run these from the project folder.

**1. Install dependencies, create `.env`, generate the app key**

```bash
composer install
```

```bash
cp .env.example .env
```

```bash
php artisan key:generate
```

**2. Set the Super Admin account in `.env`**

There are no default credentials. The seeder refuses to run until all four of
these are filled in, and the password must be at least 12 characters:

```dotenv
SUPER_ADMIN_NAME="Your Name"
SUPER_ADMIN_USERNAME=admin
SUPER_ADMIN_EMAIL=you@example.com
SUPER_ADMIN_PASSWORD="a-strong-password-12+"
```

**3. Create the database, tables and seed data**

`.env` defaults to SQLite (`DB_CONNECTION=sqlite`):

```bash
touch database/database.sqlite
```

```bash
php artisan migrate:fresh --seed
```

Seeding creates roles and permissions, the Super Admin, site settings,
services and core values. Outside production it also adds **placeholder demo
content**: CMS pages (Home, About, Contact, Privacy, Terms), consultants and
blog posts, so every public page has something to show.

> To use MySQL instead, set `DB_CONNECTION=mysql` and the `DB_HOST` /
> `DB_PORT` / `DB_DATABASE` / `DB_USERNAME` / `DB_PASSWORD` lines in `.env`
> to a real MySQL 8+ database, then run the same `migrate:fresh --seed`.

**4. Link public storage** (so uploaded images are served at `/storage/...`)

```bash
php artisan storage:link
```

---

## 3. Running the app

The quickest way starts the web server, the queue worker and the log viewer
together:

```bash
composer run dev
```

Or run the pieces yourself, each in its own terminal:

```bash
php artisan serve
```

```bash
php artisan queue:work
```

Then open **http://localhost:8000**.

**Why the queue worker matters:** the contact form and password reset emails
are queued. Without a worker they sit in the `jobs` table and are never sent.
Locally, `MAIL_MAILER=log`, so "sent" emails are written to
`storage/logs/laravel.log` instead of a real inbox. That's where you'll find
password reset links during development.

---

## 4. Site map: the public website

All public pages are read-only and share the same header, footer and main menu.

| Page | URL | What it shows |
|---|---|---|
| Home | `/` | Hero, services, core values, up to 4 consultants, latest 3 insights |
| About | `/about` | Vision, mission, story, leadership (hidden from the menu until the page is published) |
| Services | `/services` | All active services |
| Service detail | `/services/{slug}` | Full description, capabilities, outcomes, consultants on that service |
| Consultants | `/consultants` | Active consultants with their services |
| Core Values | `/core-values` | The firm's core values |
| Insights | `/insights` | Blog articles, 9 per page (`?page=2` etc.) |
| Article | `/insights/{slug}` | One article with related articles and share links |
| Contact | `/contact` | Contact form |
| Privacy Policy | `/privacy-policy` | Only once published |
| Terms of Service | `/terms-of-service` | Only once published |
| `sitemap.xml`, `robots.txt` | — | Generated, list only published URLs (see [docs/frontend-contract.md](docs/frontend-contract.md)) |

Things worth knowing when clicking around:

- **A 404 usually means "not public", not "broken".** Unpublished pages,
  inactive services, draft or scheduled articles and badly shaped slugs
  (capitals, underscores) all return the site's 404 page.
- **Contact form:** name, email, subject, message and the privacy checkbox are
  required. On success you're sent back to `/contact` with a confirmation
  message. It is rate limited to 3 submissions a minute / 10 an hour per IP;
  going over shows the 429 page.
- **Insights vs Blog:** visitors see "Insights"; the CMS calls the same content
  "Blog".

Full details of every page and the data it receives are in
[docs/frontend-contract.md](docs/frontend-contract.md).

---

## 5. The admin CMS

### Signing in

Go to **http://localhost:8000/admin/login** and sign in with the
`SUPER_ADMIN_EMAIL` and `SUPER_ADMIN_PASSWORD` you set in `.env`. There is no
link to the admin from the public site; type the URL.

- Wrong password, unknown email and deactivated account all show the same
  error, on purpose.
- After 5 failed attempts for the same email and IP you're locked out for
  60 seconds (429 page).
- If you're already signed in, `/admin/login` sends you straight to the
  dashboard.

### Forgot / reset password

| Step | URL |
|---|---|
| Request a reset link | `/admin/forgot-password` |
| Open the link from the email | `/admin/reset-password/{token}` |

Locally, find the link in `storage/logs/laravel.log` (the queue worker must be
running). Links expire after 60 minutes. New passwords must be at least 12
characters with upper- and lower-case letters, a number and a symbol.

### Admin sections

Everything below is under `/admin` and requires signing in.

| Section | URL | Purpose |
|---|---|---|
| Dashboard | `/admin` | Counts for each content type, links into each section |
| Theme & Content | `/admin/settings` | Site name, logos, colours, homepage headings, footer contact details |
| Services | `/admin/services` | List, add, edit, delete, reorder |
| Consultants | `/admin/consultants` | Same pattern |
| Core Values | `/admin/core-values` | Same pattern |
| Pages | `/admin/pages` | About, Privacy Policy, Terms of Service |
| Blog / Insights | `/admin/posts` | Posts, plus categories (`/admin/categories`) and tags (`/admin/tags`) |
| Media | `/admin/media` | Uploaded images used across the site |
| Enquiries | `/admin/enquiries` | Contact form submissions, with internal notes |
| Users | `/admin/users` | CMS accounts (Super Admin only for user management) |
| Audit Log | `/admin/audit-logs` | Read-only history of who changed what |

Logout is a button (POST), not a link.

### Roles

Four roles are seeded. Every account gets exactly one.

| Role | Can do |
|---|---|
| Super Admin | Everything, including users, roles and audit logs |
| Administrator | All content and settings; manages users but can't edit roles or touch a Super Admin |
| Editor | Edits services, values, consultants, pages and any blog post; handles enquiries; cannot publish |
| Author | Writes and edits their own blog posts before publication; uploads media |

Rules and edge cases are in [docs/admin-auth.md](docs/admin-auth.md).

---

## 6. Everyday commands

| Task | Command |
|---|---|
| Run the test suite (SQLite, the default) | `composer test` |
| Run the test suite against MySQL | set `DB_CONNECTION=mysql` etc. to a database whose name **ends in `_testing`**, then `composer test` (see below) |
| Format PHP code | `./vendor/bin/pint` |
| Reset the database and reseed demo content | `php artisan migrate:fresh --seed` |
| Tail the logs (including local emails) | `php artisan pail` |
| List every route | `php artisan route:list` |
| Poke at data | `php artisan tinker` |

> `migrate:fresh` wipes every table. Never run it against a real database.

**Running tests against MySQL:** `tests/TestCase.php` refuses to boot against
anything but SQLite or a database whose name ends in `_testing`, precisely so
a mis-set `DB_DATABASE` can never wipe a real one (`RefreshDatabase` wipes the
target between tests). Point a separate `.env.testing`, or exported
`DB_*` environment variables, at a scratch database such as
`inspaya_testing` before running `composer test`. `.github/workflows/ci.yml`
does exactly this against a throwaway MySQL 8 service container, on every
push — see it there for a working example.

---

## 7. Troubleshooting

| Symptom | Fix |
|---|---|
| `Cannot seed the Super Admin: set SUPER_ADMIN_…` | Fill in all four `SUPER_ADMIN_*` values in `.env` (password 12+ characters), then `php artisan db:seed`. |
| `Refusing to run tests against [...] database [...]` | You're pointed at a real database. Use SQLite or a database whose name ends in `_testing`. |
| Uploaded images don't show | Run `php artisan storage:link` and check `APP_URL` matches the address in your browser. |
| No reset email / contact emails | Start `php artisan queue:work`; locally, look in `storage/logs/laravel.log`. |
| `No application encryption key` | `php artisan key:generate` |
| A public page 404s | It's probably unpublished, inactive or scheduled. Check the record, or reseed demo content. |
| Changed `.env` but nothing happened | `php artisan config:clear` |

---

## 8. Further documentation

- [docs/frontend-contract.md](docs/frontend-contract.md): every public route, view and the data it receives
- [docs/admin-auth.md](docs/admin-auth.md): sign-in rules, password policy, email setup, roles and permissions — including the [production-only settings](docs/admin-auth.md#production-settings-not-enforceable-from-app-code) (`SESSION_SECURE_COOKIE`, trusted proxies for `ForceHttps`, the CSP) that only get set correctly by the deployment `.env`, not by app code
- [docs/admin-media.md](docs/admin-media.md): how uploaded media is stored, validated and served
- [docs/database/schema-plan.md](docs/database/schema-plan.md): the full table-by-table schema and the decisions behind it
