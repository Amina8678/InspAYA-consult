# Admin Authentication & Access Control

Backend behaviour for signing in, passwords and role-based access (SRS FR-ADM-02,
FR-ADM-03, FR-ADM-11, NFR-SEC-01 to 08, §7). Section 1 is for whoever builds the
admin views; sections 2–4 are for developers and operations.

---

## 1. Auth pages (for the frontend)

All text is plain text: output with `{{ }}`, never `{!! !!}`. Every form needs
`@csrf`. Validation errors come back in `$errors`, and one-off messages in
`session('status')`.

| Page | URL | Route name | View | Form posts to | Fields |
|---|---|---|---|---|---|
| Sign in | `GET /admin/login` | `admin.login` | `admin.auth.login` | `admin.login.submit` | `email`, `password`, `remember` (checkbox) |
| Forgot password | `GET /admin/forgot-password` | `admin.password.request` | `admin.auth.forgot-password` | `admin.password.email` | `email` |
| Reset password | `GET /admin/reset-password/{token}` | `admin.password.reset` | `admin.auth.reset-password` | `admin.password.store` | `token` (hidden, from `$token`), `email` (prefill from `$email`), `password`, `password_confirmation` |
| Sign out | — | `admin.logout` | — | `POST` only | none (a button in a form, never a link) |
| Change own password | `GET /admin/account/password` | `admin.account.password.edit` | `admin.account.password` | `admin.account.password.update`, `PUT` (`@method('PUT')`) | `current_password`, `password`, `password_confirmation`; errors are in the `updatePassword` bag: `$errors->updatePassword` |

Messages the pages should show:

- **Sign in failure:** `$errors->first('email')`. It is deliberately the same
  for a wrong password, an unknown email and a deactivated account.
- **Too many attempts:** the server answers **429** with a `Retry-After`
  header. Admin requests (sign-in, password reset) render `admin.errors.429`,
  which receives `$retryAfter` (seconds, or null); public requests keep the
  public `errors/429` page.
- **Forgot password:** always the same `session('status')` message, whether or
  not the email has an account.
- **After a reset:** the user lands on the sign-in page with `session('status')`.
- **After changing password:** redirect to the change-password page with `session('status')` set to "Your password has been changed."

Signed-in users who open the sign-in or reset pages are sent to the dashboard.

---

## 2. Sign-in rules

| Rule | Detail |
|---|---|
| Throttling | 5 failed attempts per **email + IP** → 429 with `Retry-After` for 60 s. A success clears the count. The correct password is also refused while throttled. |
| Forgot/reset forms | 5 requests per minute per email + IP (plus Laravel's one-email-per-minute per account). |
| Account status | Only `active` users can sign in. A user deactivated while signed in is signed out on their next request. |
| Session | The session id is regenerated at sign-in; sign-out invalidates the session and CSRF token. Changing or resetting a password signs out the user's other sessions. |
| No account probing | Same error for unknown email / wrong password / inactive account; unknown emails pay the same hashing cost; reset requests always get the same answer. |
| Audit log | `login`, `login_failed` (with reason: `unknown_email`, `invalid_password`, `inactive`), `login_throttled`, `logout`, `password_changed`, `password_reset_requested`, `password_reset`, with IP and user agent. Passwords and tokens are never logged. |

### Audit log volume and pruning

Failed sign-ins are the one audit event an anonymous visitor can trigger, so
they are capped: at most **one `login_failed` row per email + IP per minute**,
and **one `login_throttled` row per throttle window** (the 429 is still
returned on every request; only the audit row is suppressed). The throttle
itself still counts every attempt.

`php artisan audit:prune-login-noise` (scheduled daily at 03:30, so it needs the
`schedule:run` cron below) deletes `login_failed` and `login_throttled` rows
older than `AUDIT_LOGIN_RETENTION_DAYS` days (default 90; or `--days=N`, whole
number ≥ 1). `AuditLog` refuses updates and deletes through Eloquent, via
model events that only fire for model instances. The command deletes with the
query builder (`DB::table('audit_logs')`), which never boots the model, so
those events do not run. It can only ever remove those two actions: the list
is a constant in `PruneLoginAuditNoise::ACTIONS`, applied in both the select
and the delete, and no option can change it. Every other action is kept
indefinitely; there is no retention policy for them yet.

### Password policy

The SRS requires hashing (NFR-SEC-01) but **defines no password rules**. Until
the client sets one, every password set through the app (change or reset) must:

- be at least 12 characters;
- contain upper- and lower-case letters, a number and a symbol.

It is defined once in `AppServiceProvider` (`Password::defaults()`). Checking
passwords against known breaches (`->uncompromised()`) is available but not
enabled: it calls an external service (Have I Been Pwned, k-anonymity).

---

## 3. Password reset email: assumed configuration

Reset emails are **queued** (`AdminResetPassword`, `ShouldQueue`). They need:

| Setting | Needed value |
|---|---|
| `QUEUE_CONNECTION` | `database` (current) or `redis`; **not** `sync` in production |
| Queue worker | `php artisan queue:work` running (supervisor/systemd) |
| `MAIL_MAILER` | `smtp` in production (currently `log`, so local emails go to `storage/logs/laravel.log`) |
| `MAIL_HOST`, `MAIL_PORT`, `MAIL_USERNAME`, `MAIL_PASSWORD`, `MAIL_SCHEME` | From the client's SMTP provider (SRS Appendix B: not chosen yet) |
| `MAIL_FROM_ADDRESS`, `MAIL_FROM_NAME` | A verified sender on that provider |
| `APP_URL` | The public HTTPS URL; reset links are built from it |

Links expire after 60 minutes (`config/auth.php`, `passwords.users.expire`).

**The queue-worker requirement above is not limited to password resets.**
Contact-form notification and acknowledgement emails (`EnquiryReceived`,
`EnquiryAcknowledgement`) are also `ShouldQueue`, through the same
`QUEUE_CONNECTION`. **`php artisan queue:work` must run as a persistent
process (supervisor or systemd) in production**, or every queued email —
password resets and contact-form notifications alike — is written to the
`jobs` table and silently never sent. There is no error anywhere: the HTTP
response, the audit log and the application log all look completely normal,
because from the app's point of view the job was queued successfully; it is
simply never picked up. This is a launch-day failure mode with zero
diagnostic signal of its own — confirm a queue worker is running as part of
the deployment checklist, not after someone reports a missing email.

### Scheduled publishing

FR-BLOG-02's "scheduled publication" needs no queue worker and no new
infrastructure to work at all: `BlogPost::scopePublished()` is a plain query
filter (`status = published AND published_at <= now()`), evaluated fresh on
every request, so a post picking a future publish date already goes live on
its own the moment that time passes — nothing has to run for visibility.

What genuinely needs the scheduler is `App\Console\Commands\AnnounceScheduledPosts`
(`posts:announce-scheduled`, registered in `routes/console.php`): it records
an audit-log entry for the actual moment a scheduled post becomes public
(distinct from the moment an admin scheduled it, which may have been logged
days earlier — NFR-SEC-07 wants that publication event on record even when
nobody was at a keyboard for it) and clears the sitemap cache so a scheduled
post's URL appears immediately instead of waiting out the normal 5-minute
TTL (`SeoController::CACHE_SECONDS`). Idempotent: it checks each post's own
audit trail before recording, so a missed run or a restart never double-fires.

**`php artisan schedule:run` must run every minute in production** — Laravel's
scheduler is not a daemon; a single cron entry drives it:

```
* * * * * cd /path-to-app && php artisan schedule:run >> /dev/null 2>&1
```

Left unset, `posts:announce-scheduled` (and any future scheduled command)
simply never runs — which, per the paragraph above, does **not** stop
scheduled posts from actually publishing on time, only from getting an
audit entry and an immediately-fresh sitemap. This is a real but low-severity
gap if missed, unlike the queue worker above.

---

## 4. Authorization

Permissions come from the database (roles → permissions, seeded from plan §6).
Nothing is cached between requests: a change to a role's permissions applies on
that role's users' next request.

- **Permission checks.** Every permission slug is a Gate ability:
  `Gate::allows('media.upload')`, `@can('settings.manage')` in Blade, or the route
  middleware `permission:settings.manage` (comma-separate to require several:
  `permission:users.view,users.update`). Unknown slugs are denied.
- **Record checks.** Policies (`app/Policies`) add the per-record rules on top:

| Rule | Where |
|---|---|
| Authors edit only their own posts, and only before publication | `BlogPostPolicy::update` |
| Editors edit any post but cannot publish (plan D12) | `BlogPostPolicy::publish` → `content.publish` |
| Editors edit services / values / consultants / pages; only Admin+ create, delete, reorder or (de)activate them | `EditOrManage` (`update` vs `create`, `delete`, `reorder`, `changeStatus`) |
| Authors modify only media they uploaded | `MediaPolicy` |
| Administrators cannot edit, deactivate or reset a Super Admin, or grant the Super Admin role | `UserPolicy` |
| Nobody changes their own role or deactivates themselves; users are never deleted | `UserPolicy` |
| Only Super Admin edits roles; the Super Admin role itself is never edited | `RolePolicy` |
| Settings are edited, never created/deleted; audit logs are never edited/deleted | `SiteSettingPolicy`, `AuditLogPolicy` |

- **Inactive accounts** are denied every ability, even if their role grants it.
- Controllers built in later stages must call these (`$this->authorize(...)` /
  `can:` middleware). Hiding a button is never the only check (NFR-SEC-06).

### Production settings not enforceable from app code

NFR-SEC-03 requires secure, HTTP-only, SameSite cookies. `config/session.php`
reads `'secure' => env('SESSION_SECURE_COOKIE')`, never a hardcoded `false`,
so nothing in the app itself needs to change — but the production `.env` must
still set it explicitly:

- **`SESSION_SECURE_COOKIE=true`** in the production `.env` (HTTP-only and
  `SameSite=lax` are already the defaults). Left unset, Laravel defaults to
  `null` ("secure if the current request is HTTPS"), which is the right
  behaviour locally but should not be relied on in production — set it
  explicitly.

NFR-SEC-09 (HTTPS redirect, security headers, CSP) is implemented as of this
stage (`ForceHttps`, `SecurityHeaders` middleware, applied to every request in
`bootstrap/app.php`). Two things this code cannot do for you:

- **`ForceHttps` only redirects in the `production` environment**, and only
  acts on `$request->secure()`. If production sits behind a reverse proxy or
  load balancer that terminates TLS, `$request->secure()` reflects the real
  scheme only once that proxy is trusted — see "Trusted proxies" below.
- **The CSP has no `'unsafe-inline'`** for scripts or styles. If a future page
  adds a script or an inline `style="..."` attribute, it will be silently
  blocked by browsers unless it either becomes a same-origin file, or (for a
  script) carries the per-request nonce shared as `$cspNonce` — see the three
  JSON-LD blocks (Organization/Article/Service) for the pattern.

### HSTS

`SecurityHeaders` sends `Strict-Transport-Security: max-age=604800` (one week)
on HTTPS responses only; plain-http responses never carry it. It deliberately
has **no `includeSubDomains`** (nothing in this repo knows whether other
subdomains of the domain serve HTTPS, and a wrong guess locks browsers out of
them for the whole max-age) and **no `preload`** (effectively irreversible).

The short max-age is a safety margin for the first weeks. **Once HTTPS is
confirmed stable in production, raise it** via `HSTS_MAX_AGE` in `.env`
(e.g. `2592000` for 30 days, then `31536000` for a year), then re-cache config
if `config:cache` is in use. Add `includeSubDomains` only after checking every
subdomain; that needs a code change in `SecurityHeaders`.

### Trusted proxies

Laravel's `TrustProxies` middleware is already in the app's global middleware
stack (`bootstrap/app.php`), but it trusts nothing by default: an
`X-Forwarded-Proto: https` header from an untrusted source is ignored, so
`$request->secure()` reflects the raw connection the app server sees. Behind
a reverse proxy or load balancer that terminates TLS, that raw connection is
plain HTTP even though the visitor is on HTTPS — `ForceHttps` then sees "not
secure" and redirects, which the proxy immediately terminates back to HTTP
again, producing a redirect loop for the whole site.

**Set `TRUSTED_PROXIES` in the production `.env`** to fix this — the exact
value is server-specific and must come from whoever configures that proxy:

- A specific IP or comma-separated list of IPs/CIDR ranges (e.g.
  `TRUSTED_PROXIES=10.0.0.5` or `10.0.0.0/24,10.0.1.0/24`) — the proxy's own
  address(es), not the visitor's. This is the expected value for a
  self-managed nginx/HAProxy/ALB in front of the app.
- `TRUSTED_PROXIES=*` trusts whatever host is directly connecting to the app
  as a proxy. Only correct when that connection is itself already secured
  (e.g. the app is unreachable except through the proxy, such as inside a
  private network or a PaaS that guarantees this) — never set this if the
  app is also reachable directly from the internet, since it would let any
  direct caller spoof `X-Forwarded-Proto` and other forwarded headers.
- Left blank (the `.env.example` default), no proxy is trusted and
  `$request->secure()` reflects the raw connection — correct only when the
  app itself terminates TLS with no proxy in front of it.

This is deliberately not guessed or defaulted to "trust everything": an
unconfigured trust setting is a redirect loop (visible immediately), not a
silent security hole, and the correct value can only come from whoever
controls the production network.

### Analytics consent

FR-LEGAL-02 requires a cookie/analytics notice and preference controls before
any non-essential (i.e. analytics) cookie is set. That consent mechanism does
not exist in this codebase yet — no banner, no preference UI, nothing reading
or writing a consent cookie.

Until it does, `SiteSettingsRequest` refuses to save a non-empty
`analytics.tracking_id` (`'tracking' => ['string', 'prohibited']`): an admin
can leave the field empty or clear an existing value, but cannot set one.
This is a stopgap for the specific risk of a non-technical admin turning on
tracking with no consent gate in place — it is **not** the fix for
FR-LEGAL-02, which still needs the actual banner and preference controls on
the public site. Remove the `prohibited` rule (restoring the previous format
check, `max:40` + `regex:/^[A-Za-z0-9-]+$/D`) only once that consent
mechanism ships and can gate the analytics script itself; see the comment
above the `'tracking'` case in `SiteSettingsRequest::rules()`.
