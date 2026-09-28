# Database Schema Plan — Backend Rebuild, Step 1

Source of truth: `InspAya_Consult_SRS.docx` v3.0 (18 Aug 2026) — §3 functional
requirements, §4 data requirements, §6 NFRs, §7 roles & permissions.

Scope of this step: migrations, models, factories, seeders, tests, plus
`config/inspaya.php` and `.env.example` placeholders (approved). Controllers,
views and routes are **not** touched.

---

## 0. Conventions

| Topic | Decision |
|---|---|
| Strategy | Replace existing CMS migrations; do not layer alter-migrations. |
| Primary keys | `id()` (unsigned big int, auto-increment) on every entity table; composite PKs on pure pivots. |
| Status columns | `string(20)` in the DB + PHP backed enum cast on the model. Not a native `ENUM` column: new states need no migration, and it behaves the same on MySQL and on the SQLite test DB. |
| Ordering | `sort_order` (unsigned int, default 0). The name `order` is never used. |
| Booleans | `is_*` prefix (`is_active`, `is_lead`). |
| Slugs | `string(191)`, **unique index** on every table that has a slug. |
| Images | Always a nullable FK to `media.id`. No free-text image paths anywhere. |
| JSON | `json` columns for list-like data (`capabilities`, `outcomes`, `expertise`, `qualifications`, `links`, `structured_content`, audit `old_values`/`new_values`), cast to `array` on the model. |
| Timestamps | `timestamps()` everywhere except `audit_logs` (append-only: `created_at` only). |
| Soft deletes | Not used (D6). |
| Column naming | Where the brief and the SRS differ, the **SRS §4 name wins** (see §8, "Naming reconciliations"). |

### onDelete policy (applied consistently below)

- **CASCADE**: pivot/child rows that mean nothing without the parent
  (permission_role, service_consultant, blog_post_tag, enquiry notes).
- **RESTRICT**: deleting the parent would silently strip something that must
  stay intact:
  - `users.role_id`: a role still held by users cannot be deleted, so no account is left without permissions.
  - `blog_posts.author_id`: public author attribution must not vanish. Users are
    meant to be *deactivated* (FR-ADM-11), not deleted, so this only blocks an unusual action.
- **SET NULL**: the reference is optional and the parent may legitimately go away
  without taking the child with it (media → image columns, category, enquiry
  assignee, uploader, audit-log actor, note author).

---

## 1. Tables kept unchanged (Laravel defaults)

`password_reset_tokens`, `sessions`, `cache`, `cache_locks`, `jobs`, `job_batches`,
`failed_jobs`.

`0001_01_01_000000_create_users_table.php` is **edited in place** (users gets new
columns; the password_reset_tokens and sessions definitions it also contains stay
as they are).

## 2. Tables / files removed

| Removed | Reason |
|---|---|
| migration `2025_01_01_000001_create_cms_tables.php` (settings, services, team_members, projects, blog_posts, pricing_plans) | Superseded by the SRS schema below. |
| migration `2026_08_31_202900_create_features_table.php` (features) | No SRS entity. |
| models `Feature`, `PricingPlan`, `Project`, `TeamMember`, `Setting` | No SRS entity (projects, pricing, features), or replaced (`TeamMember` → `Consultant`, `Setting` → `SiteSetting`). |
| seeder `CmsSeeder` | Hard-coded admin credential; references missing image paths. |

**Consequence:** the existing controllers, routes and views reference these
models and columns. The public site and admin will not work until the controller
rewrite (step 2). `tests/Feature/ExampleTest` gets `RefreshDatabase` and is
skipped with the reason "re-enable at controller rewrite step".

---

## 3. Tables to create

Migration order, so every FK target exists first:

1. `0001_01_00_000000_create_roles_and_permissions_tables`: roles, permissions,
   permission_role. Its timestamp sorts before the default users migration, because `users.role_id` needs `roles`.
2. `0001_01_01_000000_create_users_table` (edited), then the Laravel cache and jobs defaults.
3. Then media → categories/tags → pages → services/consultants/core_values →
   pivots → blog_posts → contact_submissions (+ notes) → site_settings → audit_logs.

### 3.1 `roles`

| Column | Type | Null | Notes |
|---|---|---|---|
| id | bigint PK | | |
| name | string(100) | no | display name, e.g. "Super Admin" |
| slug | string(100) | no | **unique**, e.g. `super-admin` |
| description | string(255) | yes | |
| timestamps | | | |

### 3.2 `permissions`

| Column | Type | Null | Notes |
|---|---|---|---|
| id | bigint PK | | |
| name | string(150) | no | |
| slug | string(150) | no | **unique**, e.g. `content.publish` |
| group | string(50) | no | **index**; groups permissions in the admin UI |
| description | string(255) | yes | |
| timestamps | | | |

### 3.3 `permission_role` (pivot: SRS §4 "role-permission assignments")

| Column | FK | onDelete | Why |
|---|---|---|---|
| permission_id | permissions.id | CASCADE | an assignment of a deleted permission is meaningless |
| role_id | roles.id | CASCADE | likewise for a deleted role (it can only be deleted once no user holds it) |

PK (`permission_id`, `role_id`); **index** `role_id`.

### 3.4 `users` (edited)

| Column | Type | Null | Default | Notes |
|---|---|---|---|---|
| id | bigint PK | | | |
| role_id | bigint | no | | FK roles.id **RESTRICT** (see "Role model decision" below). **index** |
| name | string(255) | no | | |
| username | string(50) | no | | **unique** |
| email | string(255) | no | | **unique** |
| email_verified_at | timestamp | yes | | |
| password | string(255) | no | | hash only (NFR-SEC-01). Laravel's name for the SRS `password_hash`. |
| status | string(20) | no | `active` | enum `UserStatus`: active, inactive. **index** (NFR-SEC-02) |
| last_login_at | timestamp | yes | | |
| two_factor_secret | text | yes | | MFA-ready (FR-ADM-02). Schema only; stored encrypted by the future feature. |
| two_factor_recovery_codes | text | yes | | same |
| two_factor_confirmed_at | timestamp | yes | | same |
| remember_token | string(100) | yes | | |
| timestamps | | | | |

**Role model decision: single `role_id` on users, not a `role_user` pivot.**
The SRS never explicitly requires more than one role per user:
- §4 lists "RBAC tables defining roles, permissions, and role-permission assignments". That covers role↔permission assignments, not user↔role.
- §2.3 and §7 describe four distinct user classes with a strictly nested capability set (Super Admin ⊇ Administrator ⊇ Editor ⊇ Author), so holding two roles would never grant anything the higher one doesn't.
- FR-ADM-11 "assign roles to … CMS users" reads as a plural across many users.

The column is NOT NULL because every CMS user must have a role for authorization to be defined.

### 3.5 `media`

| Column | Type | Null | Notes |
|---|---|---|---|
| id | bigint PK | | |
| disk | string(50) | no | default `public` |
| file_name | string(255) | no | original client file name, for display only |
| storage_path | string(255) | no | randomized path (NFR-SEC-10), e.g. `media/2026/09/<uuid>.<ext>`. **unique** |
| mime_type | string(100) | no | **index** (filter library by type) |
| size | unsigned bigint | no | bytes |
| width | unsigned int | yes | null for non-images |
| height | unsigned int | yes | |
| alt_text | string(255) | yes | NFR-ACC-01 / FR-BLOG-03 |
| caption | text | yes | |
| uploader_id | bigint | yes | FK users.id **SET NULL**: the file outlives the uploader's account |
| timestamps | | | **index** `created_at` (library sorted newest-first) |

### 3.6 `pages`

| Column | Type | Null | Notes |
|---|---|---|---|
| id | bigint PK | | |
| title | string(255) | no | |
| slug | string(191) | no | **unique** |
| status | string(20) | no | default `draft`; enum `PageStatus`: draft, published |
| structured_content | json | yes | section blocks for FR-ADM-04 |
| meta_title | string(255) | yes | NFR-SEO-01 |
| meta_description | string(500) | yes | |
| canonical_url | string(500) | yes | NFR-SEO-03 |
| published_at | timestamp | yes | |
| timestamps | | | |

**Index** (`status`, `published_at`).

`structured_content` shape (validated in the app layer, not the DB): an ordered
array of section blocks, each `{ "type": "<section>", "data": { … } }`. Images
inside blocks are stored as **media ids** (`"background_media_id": 12`), never
paths. The home page's `hero` block carries `background_media_id` (was the
`hero_bg_image` setting) and its `feature` block carries `background_media_id`
(was `feature_bg_image`).

JSON media ids are not DB-enforced foreign keys, so deleting a media row cannot
null them automatically. The media-delete flow (controller step) must clear or
refuse ids referenced from `structured_content`. Rendering must treat an unknown
id as "no image".

### 3.7 `services`

| Column | Type | Null | Notes |
|---|---|---|---|
| id | bigint PK | | |
| title | string(255) | no | |
| slug | string(191) | no | **unique** (NFR-SEO-02) |
| short_description | string(500) | yes | |
| description | longText | yes | |
| capabilities | json | yes | array of strings |
| outcomes | json | yes | array of strings |
| is_active | boolean | no | default true |
| sort_order | unsigned int | no | default 0 |
| meta_title | string(255) | yes | |
| meta_description | string(500) | yes | |
| timestamps | | | |

**Index** (`is_active`, `sort_order`). No image column (not in §4 / FR-SVC-02).

### 3.8 `consultants`

| Column | Type | Null | Notes |
|---|---|---|---|
| id | bigint PK | | |
| name | string(255) | no | |
| title | string(255) | yes | job title |
| bio | text | yes | |
| photo_id | bigint | yes | FK media.id **SET NULL**: deleting a photo must not delete the profile |
| expertise | json | yes | array of strings |
| qualifications | json | yes | array of strings |
| email | string(255) | yes | only where approved (§4) |
| links | json | yes | `{linkedin: url, …}` approved links (FR-TEAM-01) |
| is_active | boolean | no | default true |
| sort_order | unsigned int | no | default 0 |
| timestamps | | | |

**Index** (`is_active`, `sort_order`). No slug, because the SRS has no consultant detail page.

### 3.9 `service_consultant` (pivot)

| Column | Type | Notes |
|---|---|---|
| service_id | bigint | FK services.id **CASCADE**: the assignment dies with the service |
| consultant_id | bigint | FK consultants.id **CASCADE**: the assignment dies with the consultant |
| is_lead | boolean | default false (SRS `lead_flag`, FR-TEAM-02) |
| sort_order | unsigned int | default 0 |
| timestamps | | |

PK (`service_id`, `consultant_id`); **index** `consultant_id`.

### 3.10 `core_values`

| Column | Type | Null | Notes |
|---|---|---|---|
| id | bigint PK | | |
| title | string(255) | no | |
| slug | string(191) | no | **unique** |
| description | text | yes | |
| icon_id | bigint | yes | FK media.id **SET NULL**: optional image (FR-VAL-02) |
| is_active | boolean | no | default true |
| sort_order | unsigned int | no | default 0 |
| timestamps | | | |

**Index** (`is_active`, `sort_order`).

### 3.11 `categories`

| Column | Type | Null | Notes |
|---|---|---|---|
| id | bigint PK | | |
| name | string(100) | no | |
| slug | string(191) | no | **unique** |
| description | string(500) | yes | |
| timestamps | | | |

### 3.12 `tags`

| Column | Type | Null | Notes |
|---|---|---|---|
| id | bigint PK | | |
| name | string(100) | no | |
| slug | string(191) | no | **unique** |
| timestamps | | | |

### 3.13 `blog_posts`

| Column | Type | Null | Notes |
|---|---|---|---|
| id | bigint PK | | |
| title | string(255) | no | |
| slug | string(191) | no | **unique** |
| excerpt | text | yes | |
| content | longText | no | |
| author_id | bigint | no | FK users.id **RESTRICT**: public author attribution (FR-BLOG-03) must not vanish |
| category_id | bigint | yes | FK categories.id **SET NULL**: the post becomes uncategorised, not deleted |
| featured_image_id | bigint | yes | FK media.id **SET NULL**: the post survives losing its image |
| status | string(20) | no | default `draft`; enum `PostStatus`: draft, review, published (FR-BLOG-02) |
| published_at | timestamp | yes | publication date (FR-BLOG-03) |
| meta_title | string(255) | yes | |
| meta_description | string(500) | yes | |
| canonical_url | string(500) | yes | FR-BLOG-04 (missing from §4's table, see A5) |
| timestamps | | | |

**Indexes:** composite (`status`, `published_at`) for the public listing,
"latest published" and sitemap queries. `author_id` ("my posts" for Authors).
`category_id` (category archives).

### 3.14 `blog_post_tag` (pivot)

| Column | FK | onDelete | Why |
|---|---|---|---|
| blog_post_id | blog_posts.id | CASCADE | tagging dies with the post |
| tag_id | tags.id | CASCADE | tagging dies with the tag |

PK (`blog_post_id`, `tag_id`); **index** `tag_id`.

### 3.15 `contact_submissions`

| Column | Type | Null | Notes |
|---|---|---|---|
| id | bigint PK | | |
| name | string(255) | no | |
| email | string(255) | no | **index** (enquiry history by sender) |
| phone | string(50) | yes | |
| organization | string(255) | yes | |
| subject | string(255) | no | FR-CONT-01 |
| message | text | no | |
| status | string(20) | no | default `new`; enum `EnquiryStatus`: new, in_progress, responded, closed (FR-CONT-06) |
| assigned_to | bigint | yes | FK users.id **SET NULL**: the enquiry stays in the inbox unassigned |
| consent_at | timestamp | no | when privacy consent was given (FR-CONT-02). NOT NULL enforces "no consent, no record". |
| responded_at | timestamp | yes | |
| ip_address | string(45) | yes | abuse/rate-limit forensics (D7) |
| timestamps | | | |

**Indexes:** (`status`, `created_at`) for the inbox. `assigned_to`.

### 3.16 `contact_submission_notes` (required by §3, missing from §4; see A4)

| Column | Type | Null | Notes |
|---|---|---|---|
| id | bigint PK | | |
| contact_submission_id | bigint | no | FK **CASCADE**: notes belong to the enquiry |
| user_id | bigint | yes | FK users.id **SET NULL**: the note is kept if its author's account goes |
| body | text | no | |
| timestamps | | | |

**Index** `contact_submission_id`.

### 3.17 `site_settings`

| Column | Type | Null | Notes |
|---|---|---|---|
| id | bigint PK | | |
| key | string(100) | no | **unique**, e.g. `contact.email` |
| group | string(50) | no | **index**: branding, contact, social, seo, analytics, email |
| type | string(20) | no | default `string`; enum `SettingType`: string, text, boolean, integer, json, media |
| value | text | yes | scalar or JSON-encoded |
| media_id | bigint | yes | FK media.id **SET NULL**, used when `type = media` (logo, favicon, default OG image). Keeps images as real FKs. |
| timestamps | | | |

SMTP credentials stay in `.env`, never in this table (D8).

**Image settings (`type = media`, value in `media_id`)**, replacing the
`storage/images/…` path strings the current `SettingsController` writes:

| Key | Group | Replaces current key |
|---|---|---|
| `branding.logo` | branding | `logo_image` |
| `branding.footer_logo` | branding | `footer_logo_image` |
| `branding.footer_image` | branding | `footer_right_image` |

`hero_bg_image` and `feature_bg_image` are **not** site settings. They are
home-page content, so they move to the `home` page's `structured_content` as
media ids in the hero and feature section blocks (see §3.6).

Seeded values are placeholders only. Nothing is copied from the current
`PageController` footer defaults (see §11).

### 3.18 `audit_logs`

| Column | Type | Null | Notes |
|---|---|---|---|
| id | bigint PK | | |
| user_id | bigint | yes | FK users.id **SET NULL**: the log must survive the actor (NFR-SEC-07). Null also covers system/anonymous events such as failed logins. |
| action | string(50) | no | e.g. `login`, `login_failed`, `created`, `updated`, `deleted`, `published`, `permissions_changed`. **index** |
| entity_type | string(255) | yes | morph class |
| entity_id | unsigned bigint | yes | |
| old_values | json | yes | |
| new_values | json | yes | |
| ip_address | string(45) | yes | |
| user_agent | text | yes | |
| created_at | timestamp | no | `useCurrent()`. **index**. No `updated_at`. |

**Indexes:** (`entity_type`, `entity_id`) and (`user_id`, `created_at`).
Immutability is enforced at app level (the model throws on update/delete; D9).

---

## 4. Models, enums, relationships (stage c)

Enums (`app/Enums`): `UserStatus`, `PageStatus`, `PostStatus`, `EnquiryStatus`, `SettingType`.

| Model | Relationships (both directions) |
|---|---|
| Role | users (HM), permissions (BTM) |
| Permission | roles (BTM) |
| User | role (BT), blogPosts (HM author_id), uploadedMedia (HM), assignedEnquiries (HM), enquiryNotes (HM), auditLogs (HM) |
| Media | uploader (BT User), blogPosts (HM), consultants (HM), coreValues (HM), siteSettings (HM) |
| Page | — |
| Service | consultants (BTM with is_lead, sort_order), leadConsultants (filtered BTM) |
| Consultant | services (BTM), photo (BT Media) |
| CoreValue | icon (BT Media) |
| Category | blogPosts (HM) |
| Tag | blogPosts (BTM) |
| BlogPost | author (BT User), category (BT), featuredImage (BT Media), tags (BTM) |
| ContactSubmission | assignee (BT User), notes (HM) |
| ContactSubmissionNote | submission (BT), user (BT) |
| SiteSetting | media (BT) |
| AuditLog | user (BT), auditable (morphTo on entity_type/entity_id) |

A factory for every model.

---

## 5. Seeders (stage d)

`DatabaseSeeder` order:

1. `RolesAndPermissionsSeeder`: upsert roles and permissions by slug, then `sync()` the matrix, so reruns are idempotent.
2. `SuperAdminSeeder`: reads `config('inspaya.super_admin.*')`, which comes from
   `SUPER_ADMIN_NAME`, `SUPER_ADMIN_USERNAME`, `SUPER_ADMIN_EMAIL`, `SUPER_ADMIN_PASSWORD`.
   **Throws a `RuntimeException` naming the missing variable(s)** if any is unset.
   The user is created on first run only; reruns never overwrite the password.
   `.env.example` gets blank placeholders only.
3. `SiteSettingsSeeder`: placeholder keys, `firstOrCreate`, so admin edits are never overwritten.
4. `ServiceSeeder`: the seven Appendix A services, `firstOrCreate` by slug, placeholder copy.
5. `CoreValueSeeder`: the six FR-VAL-01 values with placeholder descriptions, in every environment.
6. **Production only**: `PageShellSeeder` creates home, about, privacy-policy and
   terms-of-service as empty draft shells (no content, no SEO metadata, unpublished).
7. **Non-production only** (D10): `Demo\PageSeeder` (the same pages plus contact, with
   placeholder content), `Demo\ConsultantSeeder`, `Demo\BlogSeeder` (categories, tags,
   posts). All image FKs are null, so nothing references a missing file.

---

## 6. Permission → §7 matrix mapping

§7 rows and their wording are reproduced in the "§7 capability" column. The
**Scope** column records where §7 says Limited / Own / Approved / View-Respond and
how that is (or isn't) represented.

| # | Permission slug | Super Admin | Administrator | Editor | Author | §7 capability (SA / Admin / Editor / Author) | Scope notes |
|---|---|:-:|:-:|:-:|:-:|---|---|
| 1 | `users.view` | ✓ | ✓ | | | Manage users and roles (Full / Limited / No / No) | |
| 2 | `users.create` | ✓ | ✓ | | | ″ | Admin "Limited": policy (step 2) stops Admins creating Super Admins |
| 3 | `users.update` | ✓ | ✓ | | | ″ | Admin cannot edit a Super Admin (policy) |
| 4 | `users.deactivate` | ✓ | ✓ | | | ″ | Admin cannot deactivate a Super Admin (policy) |
| 5 | `users.assign-role` | ✓ | ✓ | | | ″ | Admin cannot grant/revoke Super Admin (policy) |
| 6 | `users.reset-password` | ✓ | ✓ | | | ″ + FR-ADM-11 | Admin cannot reset a Super Admin (policy) |
| 7 | `roles.manage` | ✓ | | | | ″ | Admin "Limited" = cannot change role→permission mapping |
| 8 | `settings.manage` | ✓ | ✓ | | | Manage site settings (Full / Full / No / No) | |
| 9 | `services.edit` | ✓ | ✓ | ✓ | | Manage services / values / team (Full / Full / Edit / No) | Editor "Edit" = update existing records |
| 10 | `services.manage` | ✓ | ✓ | | | ″ | create, delete, reorder, activate/deactivate |
| 11 | `values.edit` | ✓ | ✓ | ✓ | | ″ | |
| 12 | `values.manage` | ✓ | ✓ | | | ″ | |
| 13 | `consultants.edit` | ✓ | ✓ | ✓ | | ″ | |
| 14 | `consultants.manage` | ✓ | ✓ | | | ″ | includes service assignment (FR-ADM-07) |
| 15 | `pages.edit` | ✓ | ✓ | ✓ | | *(no §7 row; A8)* | mapped by analogy with services |
| 16 | `pages.manage` | ✓ | ✓ | | | *(no §7 row; A8)* | ″ |
| 17 | `posts.create` | ✓ | ✓ | ✓ | ✓ | Create / edit blog posts (Full / Full / Full / Own posts only) | |
| 18 | `posts.edit-own` | ✓ | ✓ | ✓ | ✓ | ″ | ownership check by `author_id` in policy |
| 19 | `posts.edit-any` | ✓ | ✓ | ✓ | | ″ | |
| 20 | `posts.delete` | ✓ | ✓ | ✓ | | *(no §7 row; A8)* | Author cannot delete, even own posts (least privilege) |
| 21 | `taxonomy.manage` | ✓ | ✓ | ✓ | | *(no §7 row; A8)* | categories & tags |
| 22 | `content.publish` | ✓ | ✓ | | | Publish content (Full / Full / Approved items only / No) | **Withheld from Editors** until "approved" is defined (D12, §7). Editors can move posts to `review`; SA/Admin publish. |
| 23 | `media.view` | ✓ | ✓ | ✓ | ✓ | Manage media library (Full / Full / Full / Limited) | |
| 24 | `media.upload` | ✓ | ✓ | ✓ | ✓ | ″ | |
| 25 | `media.manage-own` | ✓ | ✓ | ✓ | ✓ | ″ | Author "Limited" = edit/replace/delete own uploads only (`uploader_id`) |
| 26 | `media.manage` | ✓ | ✓ | ✓ | | ″ | edit/replace/delete any file |
| 27 | `enquiries.view` | ✓ | ✓ | ✓ | | Manage enquiries (Full / Full / View-Respond / No) | |
| 28 | `enquiries.respond` | ✓ | ✓ | ✓ | | ″ | change status, add internal notes |
| 29 | `enquiries.assign` | ✓ | ✓ | | | ″ | |
| 30 | `enquiries.delete` | ✓ | ✓ | | | ″ | |
| 31 | `enquiries.export` | ✓ | ✓ | | | ″ + FR-ADM-10 | |
| 32 | `audit-logs.view` | ✓ | ✓ | | | View audit logs (Full / Full / No / No) | |

Super Admin is seeded with **all** permissions explicitly.

---

## 7. Deferred items and where they would slot in

| Deferred | Why | Where it slots in later |
|---|---|---|
| Testimonials / FAQs | In §1.4 scope, no §4 entity, unconfirmed in App. B | New `testimonials` (quote, author_name, author_title, organization, photo_id → media SET NULL, is_active, sort_order) and `faqs` (question, answer, is_active, sort_order) tables. Additive migrations, `services.edit`-style permissions. |
| Scheduled publishing | FR-BLOG-02 mentions it but never defines it | No schema change needed: the `(status, published_at)` index already supports "published and `published_at` ≤ now". Implementation is a query scope plus the publish flow. If a distinct state is wanted, add `scheduled` to `PostStatus` (no migration, string column). |
| Editor "Approved items only" | §7 doesn't define approval | Additive `approved_by` (FK users SET NULL) + `approved_at` on `blog_posts`/`pages`, and an Editor check in the post policy, then grant `content.publish` to Editor. Until then Editors cannot publish (row 22 in §6). |
| Redirect management (NFR-SEO-05) | Not in §4 | `redirects` (from_path unique, to_path, status_code, hits). |
| Backup status (FR-ADM-14) | Not in §4 | Read from backup tooling, or `site_settings` key `system.last_backup_at`. |

---

## 8. SRS ambiguities and contradictions

| # | Issue | SRS location | Handling |
|---|---|---|---|
| A1 | FR-ADM-12 points to "Section 4.6 (Non-Functional Requirements – Security)". Security NFRs are §6.3. | §3.2.8 | Treated as §6.3 / NFR-SEC-07. |
| A2 | FR-SVC-01 says the seven services are "defined in Appendix B"; they are in **Appendix A**. App. B says their descriptions are not yet approved. | §3.1.3, App. A/B | Seed App. A titles with marked placeholder copy. |
| A3 | §1.4 lists testimonials and FAQs; §4 has no entities for them. | §1.4 vs §4 | Deferred (§7). |
| A4 | FR-CONT-04 / FR-ADM-10 require internal notes; §4 `contact_submissions` has none. | §3 vs §4 | `contact_submission_notes` table. |
| A5 | FR-BLOG-04 requires `canonical_url` on posts; §4 omits it. | §3 vs §4 | Added. |
| A6 | "Scheduled publication" undefined. | FR-BLOG-02 | Deferred (§7). |
| A7 | Editor "Approved items only" undefined. | §7 | Deferred (§7). |
| A8 | §7 has no rows for pages, categories/tags or deleting posts. | §7 | Mapped by analogy (§6 rows 15, 16, 20, 21). |
| A9 | Admin "Limited" user/role management undefined. | §7, §2.3, FR-ADM-11 | Admin has users.*, not roles.manage. SA-protection by policy. |
| A10 | "MFA-ready" has no §4 columns. | FR-ADM-02 | Nullable two-factor columns on users. |
| A11 | FR-ADM-04 "page sections" vs §4 `structured_content`. | §3 vs §4 | JSON column per §4 (D3). |
| A12 | FR-VAL-02 "icon/image": image or icon-font class? | §3.1.4 | Media FK (D5). |
| A13 | One vs many roles per user isn't stated. | §4, §7, FR-ADM-11 | Single `role_id` (see §3.4). |

### Naming reconciliations (brief vs SRS; SRS wins unless noted)

| Brief says | SRS §4 says | Using |
|---|---|---|
| contact `organisation` | `organization` | `organization` |
| contact `consent` | `consent_at` | `consent_at` |
| contact `ip` | — | `ip_address` (kept per brief) |
| media `path` | `storage_path` | `storage_path` |
| media `uploaded_by` | `uploader_id` | `uploader_id` |
| media `mime` | `mime_type` | `mime_type` |
| media `dimensions` | `width`, `height` | `width`, `height` |
| — | users `password_hash` | `password` (Laravel auth convention) |
| — | pivot `lead_flag` | `is_lead` |
| — | consultant `photo` | `photo_id` FK |
| — | core value `icon` | `icon_id` FK |
| consultants `sort_order` | — | added, per brief |

---

## 9. Decisions (all approved)

Also decided: failing test skipped (not fixed via controller); `config/inspaya.php`
approved; two-factor columns added; single role per user; testimonials/FAQs,
scheduling and Editor approval deferred.

- **D1.** Enquiry notes live in a separate `contact_submission_notes` table (multi-author, timestamped), not one `internal_notes` column.
- **D2.** Page statuses are draft/published only (no review step for pages).
- **D3.** Page sections are a JSON `structured_content` column, not a `page_sections` table.
- **D4.** Delete behaviour on media is SET NULL everywhere. Deleting an in-use image blanks it rather than being blocked. An "in use" warning is a UI concern.
- **D5.** Images: services have none. Core values get `icon_id` (media). Consultants have no slug/detail page.
- **D6.** No soft deletes. Deletions are recorded in the audit log instead.
- **D7.** Enquiry `ip_address` is stored. A retention/purge policy (NFR-PRIV-01) is a later task.
- **D8.** SMTP secrets stay in `.env`, never in `site_settings`.
- **D9.** Audit-log immutability is app-level only (model guard); DB triggers / a restricted DB user are an ops decision.
- **D10.** Demo content (placeholder pages, consultants, categories, tags, posts) is seeded only when `APP_ENV` ≠ `production`. Roles, Super Admin, settings, the seven services and the six core values are seeded everywhere. Production additionally gets empty draft page shells (§5).
- **D11.** Admin-panel permission gaps (pages, taxonomy, post deletion) are mapped as in §6 rows 15, 16, 20, 21.
- **D12.** Editors do **not** get `content.publish` until the "approved items only" rule is defined (§7). SA and Admin publish.

---

## 10. Assumptions

1. MySQL 8 / MariaDB 10.6+ in production. SQLite in-memory for tests (per `phpunit.xml`; Laravel enables FK enforcement there by default).
2. `utf8mb4` everywhere. 191-char slugs keep unique indexes within key-length limits.
3. Users are deactivated (`status = inactive`), not deleted, in normal operation. This is why RESTRICT on `author_id` and `role_id` is acceptable.
4. No existing production data needs preserving. `migrate:fresh` is acceptable.
5. Placeholder content is clearly marked as placeholder and doesn't pretend to be approved client copy (App. B).

---

## 11. Findings from merging `origin/main` (2c9ef22) — for the controller step

Commit `2c9ef22` changed controllers and views only; no schema files. These
issues sit outside this step's scope (no controller changes), but the controller
rewrite must address them:

| # | Finding | Where | Required in controller step |
|---|---|---|---|
| F1 | Uploaded images are stored as path strings in settings (`storage/images/…`), validated only with `image` + `max`. This does not meet **NFR-SEC-10**: no MIME/content inspection beyond the `image` rule, no approved-type allow-list, no randomized-name policy, no media-library record. | `Admin/SettingsController::update` | Route every upload through the media library (§3.5): allow-list MIME types, inspect content, store under randomized names, create a `media` row, and save the `media_id`. |
| F2 | New items get `order = max(order) + 1`. Two concurrent creates can read the same max and get the **same position** (race). | `Admin/{Service,BlogPost,TeamMember,Project,PricingPlan}Controller::store` | Compute and insert inside a transaction with a lock (`lockForUpdate` on the max query), or allow ties and break them by `id`. Keep "new items append at the end" behaviour on `sort_order`. |
| F3 | `PageController::home` hard-codes a **personal Gmail address and phone number** as footer fallbacks. | `PageController::home` | Remove the fallbacks and read from `site_settings`. **Seeders must use placeholders only** (e.g. `hello@example.com`, `+000 000 0000`), never these values. |
| F4 | Controllers and views still use `order`, `image` path columns and the removed models. | all admin controllers, `welcome.blade.php` | Already covered by §2: they break when the stage (b) schema lands, and are rewritten in step 2. |

---

## 12. Deployment notes

- **Never run `db:seed` on production after roles have been edited.**
  `RolesAndPermissionsSeeder` syncs every role's permissions to the matrix in
  code (§6), so a reseed silently undoes any role changes a Super Admin made in
  the CMS (`roles.manage`). Seed production once, at first deploy. Later
  permission changes ship as a dedicated migration or a reviewed one-off
  seeder, not a full reseed. (Settings, services, core values, pages and the
  Super Admin password are never overwritten by a reseed; roles are the
  exception.)
- **Super Admin credentials come only from environment variables.**
  `SUPER_ADMIN_NAME`, `SUPER_ADMIN_USERNAME`, `SUPER_ADMIN_EMAIL` and
  `SUPER_ADMIN_PASSWORD` are read via `config/inspaya.php`. They are never
  committed; `.env.example` holds blank placeholders only. The seeder refuses to
  run if any is unset or the password is under 12 characters. After the first
  login, rotate the password and remove `SUPER_ADMIN_PASSWORD` from the
  production environment. If config is cached (`config:cache`), the value is
  also written to `bootstrap/cache/config.php`, so re-cache after removing it.
- Production requires `php artisan db:seed --force`. In production it seeds
  roles, the Super Admin, settings, the seven services, the six core values and
  empty draft page shells. No demo content.
