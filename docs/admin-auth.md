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
- **The CSP has no `'unsafe-inline'`** for scripts or styles. If a future page
  adds a script or an inline `style="..."` attribute, it will be silently
  blocked by browsers unless it either becomes a same-origin file, or (for a
  script) carries the per-request nonce shared as `$cspNonce` — see the three
  JSON-LD blocks (Organization/Article/Service) for the pattern.
