# Frontend Contract — Public Website

This document is the agreement between the backend (controllers, routes, data)
and the frontend (Blade views, CSS, JS). It lists every public page, the Blade
view the backend asks for, and exactly what data that view receives.

---

## 1. Ground rules

1. **Where views live.** A view name like `public.services.show` means the file
   `resources/views/public/services/show.blade.php`.
2. **Shared data.** Every view whose name starts with `public.` automatically
   receives `$settings`, `$navigation` and `$footer` (section 4). The base
   layout is `layouts.public` (`resources/views/layouts/public.blade.php`): it
   receives them, and `$seo`, from the page view that `@extends` it. The header
   and footer are `public.partials.header` and `public.partials.footer`; being
   `public.*` views, they receive the shared data wherever they're included, so
   error pages (`errors.*`) still get the full header and footer even though
   the error view itself receives nothing (the layout falls back to defaults
   for `$settings` and `$seo`).
3. **Everything is plain data.** Variables are arrays (and one paginator), not
   database objects. Read fields with array syntax: `$service['title']`.
   Anything not listed here is not available; ask for it rather than guessing.
4. **All text is plain text. Always output it escaped with `{{ }}`. Never use
   `{!! !!}`.** This applies to every text field, including blog `content`,
   service `description` and page section text. Line breaks in long text are
   preserved as newline characters; show them with CSS
   (`white-space: pre-line;`) rather than converting them to HTML.
   (Rich-text/HTML content will be introduced later with a sanitiser, and this
   contract will say so explicitly when it happens.)
5. **Anything may be empty.** Fields marked *nullable* can be `null`; lists can
   be empty (`[]`). Views must render sensibly in both cases, e.g. hide an image
   block when its image is `null`.
6. **Links.** Every item that links somewhere already carries a ready-made
   absolute `url`. Use it as-is. If you need a route in a template, the route
   names in section 2 are also available through `route('…')`. URLs typed into
   the CMS (consultant links, section call-to-actions, social links) only ever
   arrive as `http(s)`, `mailto:`, `tel:` or site-relative URLs; anything else
   (e.g. `javascript:`) is removed, so the value may be `null`.

---

## 2. Routes

| URL | Route name | View | Returns 404 when |
|---|---|---|---|
| `/` | `home` | `public.home` | never |
| `/about` | `about` | `public.about` | the About page is not published |
| `/services` | `services.index` | `public.services.index` | never |
| `/services/{slug}` | `services.show` | `public.services.show` | the service is unknown or inactive |
| `/consultants` | `consultants.index` | `public.consultants.index` | never |
| `/core-values` | `core-values.index` | `public.core-values.index` | never |
| `/insights` | `insights.index` | `public.insights.index` | never (an out-of-range `?page=` shows an empty list) |
| `/insights/{slug}` | `insights.show` | `public.insights.show` | the article is unknown, a draft, in review, or not yet due |
| `/contact` | `contact` | `public.contact` | never |
| `/privacy-policy` | `privacy-policy` | `public.privacy-policy` | the page is not published |
| `/terms-of-service` | `terms-of-service` | `public.terms-of-service` | the page is not published |

**Naming.** Each section uses one term everywhere (URL, route name, view name),
taken from the SRS site map (Appendix A):

- **Consultants**, not "Team": the SRS entity, profile requirements (FR-TEAM-01/02)
  and data model all use "consultant"; the site-map entry is "Team / Consultants".
- **Insights**, not "Blog": the site map lists "Insights" and "Article Detail",
  and the home page requirement says "featured insights/articles". The CMS
  calls them blog posts internally; visitors see "Insights".

**Slugs** are lowercase words joined by hyphens (`energy-policy-evaluations-and-formulation`).
Any other shape (capitals, underscores) is a 404.

**Not included (by design):**
- **Category/tag archive pages and blog search.** FR-BLOG-01 requires posts to
  *support* categories and tags (each article shows them), but the SRS does not
  require filtering or search pages. Categories and tags are therefore plain
  labels for now, not links.
- Testimonials, FAQs and client logos (not yet confirmed by the client).

### What a 404 means

A 404 is the backend deliberately saying "this page is not public", not an
error: the content is unpublished, inactive, scheduled for later, or does not
exist. Laravel renders `resources/views/errors/404.blade.php` (and `429` for
rate limits). The error view itself receives **none** of the page variables;
it gets the site header and footer through the layout's partials (rule 2) and
is marked `noindex`.
Anything else going wrong is a 500, which is always a backend bug to report.

---

## 3. Common shapes

These shapes are reused by several pages.

### Image (`image`)

Every image comes from the media library and reaches the view in this shape
(or as `null` when there is none):

| Field | Type | Notes |
|---|---|---|
| `url` | string | Absolute, ready to use in `src`. Do not build or modify it. |
| `alt` | string | Alt text; may be an empty string. Always output it (`alt="{{ $image['alt'] }}"`). |
| `width` | int, nullable | Pixels; use for `width`/`height` attributes to avoid layout shift. |
| `height` | int, nullable | |
| `mime_type` | string | e.g. `image/jpeg` |

Images that come from the media library: site logo, footer logo, footer image,
consultant photos, core-value icons, article featured images, images inside page
sections, and the Open Graph image. There are **no** hard-coded image paths in
the data. Static design assets (backgrounds that are part of the theme, icons in
CSS) remain the frontend's own files.

> How the URL is built (for information only): uploaded files are stored on the
> public disk and served from `APP_URL/storage/<path>`. Always use the `url`
> field; never construct this yourself.

### SEO (`seo`)

Every page view receives `$seo`:

| Field | Type | Use it for |
|---|---|---|
| `title` | string | `<title>` and `og:title` (already includes the site name where appropriate) |
| `description` | string, nullable | `<meta name="description">` and `og:description` |
| `canonical_url` | string | `<link rel="canonical">` and `og:url` |
| `og_image` | image, nullable | `og:image` (use `og_image['url']`) |

Articles, services and CMS pages also carry the same block inside their own
data (`$post['seo']`, `$service['seo']`, `$page['seo']`); it is identical to
`$seo`.

### Dates

Each dated item has `published_at` (ISO 8601, for `<time datetime="…">` and
structured data) and `published_on` (display text, e.g. `3 September 2026`).
Both are nullable.

### Service summary (`service summary`)

| Field | Type |
|---|---|
| `title` | string |
| `slug` | string |
| `url` | string |
| `short_description` | string, nullable |

### Consultant (`consultant`)

| Field | Type | Notes |
|---|---|---|
| `name` | string | |
| `title` | string, nullable | Job title |
| `bio` | string, nullable | |
| `photo` | image, nullable | |
| `expertise` | list of strings | |
| `qualifications` | list of strings | |
| `email` | string, nullable | Only present when the client approved publishing it |
| `links` | map of network → URL | e.g. `['linkedin' => 'https://…']`; empty when none |
| `services` | list of `{title, url}` | **Only on the Consultants page**; active services only |
| `is_lead` | bool | **Only on the service detail page**: lead consultant for that service |

### Core value (`core value`)

| Field | Type |
|---|---|
| `title` | string |
| `slug` | string |
| `description` | string, nullable |
| `icon` | image, nullable |

### Article summary (`article summary`)

| Field | Type | Notes |
|---|---|---|
| `title` | string | |
| `slug` | string | |
| `url` | string | |
| `excerpt` | string, nullable | |
| `published_at` / `published_on` | see Dates | |
| `author` | `{name}` | Name only |
| `category` | `{name, slug}`, nullable | |
| `tags` | list of `{name, slug}` | |
| `featured_image` | image, nullable | |

### CMS page (`page`)

| Field | Type | Notes |
|---|---|---|
| `title` | string | |
| `slug` | string | |
| `url` | string | |
| `sections` | list of sections | In display order |
| `published_at` / `published_on` | see Dates | |
| `seo` | SEO | |

Each **section** is `{type, data}`. `type` is a short name chosen in the CMS;
`data` holds that section's fields. Any image inside a section arrives as an
image (or `null`) under a key ending in `_media`, e.g. `background_media`.

Section types, exactly as offered by the CMS page editor (`App\Support\PageSections`)
and rendered by `public.partials.sections` and the home hero. Every field is
optional plain text unless noted; buttons are `{label, url}` and only present
when they have a link (links are http(s), mailto:, tel: or site paths).

| `type` | `data` fields |
|---|---|
| `hero` | `heading` (≤200), `body` (≤1000), `primary_cta`, `secondary_cta`, `background_media` (image) |
| `intro` | `heading` (≤200), `body` (≤2000) |
| `feature` | `heading` (≤200), `body` (≤2000), `primary_cta`, `secondary_cta`, `background_media` (image) |
| `text` | `heading` (≤200), `body` (≤10000) |

On the home page the first `hero` is the page's `<h1>` banner; elsewhere a
`hero` renders as a banner with an `<h2>`. A page's sharing image
(`seo.og_image`) is its own chosen image, else the first section image, else
the site default.

Render unknown section types as nothing (skip them) rather than failing.

---

## 4. Shared variables (every `public.*` view)

### `$settings`

Site-wide settings from the CMS, grouped by the part before the dot:

| Field | Type |
|---|---|
| `$settings['branding']['site_name']` | string |
| `$settings['branding']['logo']` | image, nullable |
| `$settings['branding']['footer_logo']` | image, nullable |
| `$settings['branding']['footer_image']` | image, nullable |
| `$settings['contact']['email']` | string, nullable |
| `$settings['contact']['phone']` | string, nullable |
| `$settings['contact']['address']` | string, nullable |
| `$settings['social']['linkedin' \| 'x' \| 'facebook' \| 'instagram']` | string, nullable |
| `$settings['seo']['default_title']` | string, nullable |
| `$settings['seo']['default_description']` | string, nullable |
| `$settings['seo']['default_og_image']` | image, nullable (already used as the `og_image` fallback) |
| `$settings['analytics']['tracking_id']` | string, nullable |

Settings can be missing entirely on a fresh install. Read them with a fallback,
e.g. `data_get($settings, 'contact.email')`.

### `$navigation`

Main menu, in order, following the site map. Each item:

| Field | Type | Notes |
|---|---|---|
| `label` | string | Home, About, Services, Consultants, Core Values, Insights, Contact |
| `url` | string | |
| `active` | bool | The current page is this item (or inside it) |
| `children` | list of `{label, url, active}` | Only Services has children: one per active service |

"About" is left out while the About page is unpublished, so the menu never links
to a 404.

### `$footer`

| Field | Type | Notes |
|---|---|---|
| `site_name` | string, nullable | |
| `logo` | image, nullable | |
| `image` | image, nullable | Decorative footer image |
| `contact` | `{email, phone, address}` | each nullable |
| `social` | list of `{network, url}` | Only networks with a URL |
| `services` | list of `{label, url}` | Active services |
| `legal` | list of `{label, url}` | Privacy Policy / Terms of Service, only once published |
| `year` | int | For the copyright line |

---

## 5. Page by page

### Home — `public.home`

| Variable | Type | Notes |
|---|---|---|
| `$page` | CMS page, **nullable** | `null` while the Home page is unpublished (the site still works). Hero/intro/feature content comes from its sections. |
| `$services` | list of service summaries | Featured operational areas (FR-HOME-03), all active services |
| `$coreValues` | list of core values | |
| `$consultants` | list of consultants | Up to 4 |
| `$insights` | list of article summaries | Latest 3 |
| `$seo` | SEO | |

When `$page` is `null`, show a sensible default hero (e.g. the site name) and
the remaining sections.

### About — `public.about`

| Variable | Type | Notes |
|---|---|---|
| `$page` | CMS page | Vision, mission, mandate, story, approach — from its sections |
| `$consultants` | list of consultants | Leadership / consultant overview (FR-ABOUT-04) |
| `$seo` | SEO | |

### Services — `public.services.index`

| Variable | Type |
|---|---|
| `$services` | list of service summaries, in display order |
| `$seo` | SEO |

### Service detail — `public.services.show`

`$service` is a service summary plus:

| Field | Type | Notes |
|---|---|---|
| `description` | string, nullable | Full description |
| `capabilities` | list of strings | |
| `outcomes` | list of strings | Outcomes / deliverables |
| `consultants` | list of consultants (with `is_lead`) | Active consultants on this service, in order |
| `lead_consultants` | list of consultants | The subset with `is_lead = true` |
| `seo` | SEO | |

Also `$seo`. The call-to-action (FR-SVC-02) links to `route('contact')`.

### Consultants — `public.consultants.index`

| Variable | Type | Notes |
|---|---|---|
| `$consultants` | list of consultants | Active only, in display order; each includes `services` |
| `$seo` | SEO | |

### Core values — `public.core-values.index`

| Variable | Type |
|---|---|
| `$coreValues` | list of core values, in display order |
| `$seo` | SEO |

### Insights — `public.insights.index`

| Variable | Type | Notes |
|---|---|---|
| `$posts` | paginated list of article summaries | 9 per page, newest first |
| `$seo` | SEO | |

`$posts` is a paginator: loop over it with `@foreach ($posts as $post)`, check
`$posts->isEmpty()`, and render page links with `{{ $posts->links() }}` (you may
pass your own pagination view). Each `$post` is an article summary array.

### Article — `public.insights.show`

`$post` is an article summary plus:

| Field | Type | Notes |
|---|---|---|
| `content` | string | Plain text with newlines (see ground rule 4) |
| `related` | list of article summaries | Up to 3 sharing the category or a tag (FR-BLOG-04) |
| `seo` | SEO | `og_image` is the featured image when there is one |

Also `$seo`. For share and copy-link buttons (FR-BLOG-05) use
`$post['seo']['canonical_url']` as the shared URL.

### Contact — `public.contact`

| Variable | Type | Notes |
|---|---|---|
| `$page` | CMS page, **nullable** | Optional intro content; the page always renders |
| `$seo` | SEO | |

#### Contact form (FR-CONT-01 to 05)

`POST` to `route('contact.store')` with `@csrf`.

| Field | Type | Rules |
|---|---|---|
| `name` | text | required, max 255 |
| `email` | email | required, valid email, max 255 |
| `phone` | tel | optional, max 50, digits, spaces and `+ ( ) - .` only |
| `organization` | text | optional, max 255 |
| `subject` | text | required, max 255 |
| `message` | textarea | required, max 5000 |
| `consent` | checkbox, `value="1"` | required (privacy acknowledgement, FR-CONT-02) |
| `website` | text | **anti-bot trap: must be present, empty, and hidden** from people and assistive technology (`aria-hidden="true"` wrapper, `tabindex="-1"`, `autocomplete="off"`, positioned off-screen). Never fill or remove it. |

Outcomes:

- **Success:** redirect back to `/contact` with `session('status')`, shown only
  after the enquiry is stored. Show it in an element with `role="status"`.
- **Validation error:** redirect back with `$errors` (per field, plus
  `$errors->first('website')` when the trap was filled) and old input
  (`old('name')` etc.).
- **Rate limited** (3 per minute or 10 per hour per IP): **429** page
  (`errors/429`).

The backend queues two emails: a notification to the enquiry address from
settings, and an acknowledgement to the visitor.

### Privacy Policy / Terms of Service — `public.privacy-policy`, `public.terms-of-service`

| Variable | Type |
|---|---|
| `$page` | CMS page |
| `$seo` | SEO |

---

## 6. Decisions made since the draft

This document started as a proposal; these are the points it originally left
open, and how each was resolved during the build:

1. URL, route and view names above (especially `consultants` and `insights`)
   are what actually shipped, unchanged from the proposal.
2. The home page shows **all active services**, not a curated subset
   (`PageController`: no limit applied, unlike consultants/insights which are
   capped).
3. Category/tag archive pages and blog search were **not built**, per the
   "Not included" list in section 2 — categories and tags remain plain labels.
4. The default Open Graph image (`seo.default_og_image`) is a normal settings
   field, empty until someone sets it from the CMS's Theme & Content screen;
   until then `og_image` is `null` on pages without their own image. This was
   never a decision to make, just the expected state of a fresh install.
