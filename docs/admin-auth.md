# Admin Authentication & Access Control

> **Status: DRAFT**, for review together with `frontend-contract.md`.

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

NFR-SEC-03 requires secure, HTTP-only, SameSite cookies. Set in the production
`.env`: `SESSION_SECURE_COOKIE=true` (HTTP-only and `SameSite=lax` are already
the defaults). NFR-SEC-09 (HTTPS redirect, security headers, CSP) belongs to a
later stage.
