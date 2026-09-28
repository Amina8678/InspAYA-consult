# Media Library

> **Status: DRAFT.** The allowed types and limits below are a **proposal**: the
> SRS (FR-ADM-09, NFR-SEC-10) requires "approved types" and "size limits" but
> names neither. Confirm or adjust them with the client.

## What it does (FR-ADM-09)

| Action | URL | Who (plan §6) |
|---|---|---|
| List, filter by type (images / PDFs), 24 per page | `GET /admin/media` | everyone with `media.view` (all four roles) |
| Upload | `POST /admin/media` | `media.upload` (all four roles) |
| Edit alt text and caption; replace the file | `GET /admin/media/{id}/edit`, `PUT /admin/media/{id}` | `media.manage` (Editor+), or `media.manage-own` for your own uploads (Authors) |
| Delete (with a usage check) | `GET /admin/media/{id}/delete`, `DELETE /admin/media/{id}` | same as edit |

Every upload, edit and delete is written to the audit log (FR-ADM-12).

## Upload rules (NFR-SEC-10)

| Type | Detected as | Stored extension | Max size |
|---|---|---|---|
| JPEG | `image/jpeg` | `.jpg` | 5 MB |
| PNG | `image/png` | `.png` | 5 MB |
| WebP | `image/webp` | `.webp` | 5 MB |
| GIF | `image/gif` | `.gif` | 5 MB |
| PDF | `application/pdf` | `.pdf` | 10 MB |

- **The type comes from the file's content**, not its name or the MIME type the
  browser sends. `finfo` (libmagic) reads the bytes. Images must also decode
  with `getimagesize()` as the same image type. PDFs must start with `%PDF-`.
  A `.jpg` containing text, PHP or HTML is rejected.
- **SVG is not allowed.** It can contain JavaScript. Neither are HTML, scripts,
  office documents or archives.
- **Images:** at most 10,000 px on either side and 40 megapixels in total, which
  protects against decompression bombs.
- **Alt text is required for images** (NFR-ACC-01), both on upload and when
  editing. It's optional for PDFs.
- **Stored names are random:** `media/YYYY/MM/<uuid>.<ext>`. The extension comes
  from the detected type, so nothing is ever stored as `.php`, `.phtml` or
  `.html`, whatever the upload was called. The original name is kept only as a
  label and is always escaped when shown.
- Width, height, size in bytes, MIME type and uploader are recorded.

## Deleting (plan §3.6)

The delete page lists every usage before anything happens:

- blog post featured images, consultant photos, core value icons and image
  settings (logo, footer images, default Open Graph image), which are real
  foreign keys;
- **media IDs inside page sections** (`pages.structured_content`, any key ending
  in `_media_id`), which the database can't track.

The user has to tick a confirmation box. Then, in one transaction, every usage
is set to `null`, the row is deleted, and the file is removed. An audit log
`deleted` entry records the file details and the list of cleared usages.

## Deployment requirements

| Setting | Why |
|---|---|
| `php.ini`: `upload_max_filesize = 12M`, `post_max_size = 14M` | PHP's defaults (2M / 8M, the current local values) would reject uploads well below the limits above, before Laravel sees them |
| `php artisan storage:link` | Serves `storage/app/public` at `/storage` so media URLs work |
| **Web server must never execute scripts under `/storage`** (nginx: no `fastcgi_pass` for that location; Apache: `php_flag engine off` / `RemoveHandler .php` there) | Defence in depth. Stored extensions are already restricted, but the upload directory itself must not be executable (NFR-SEC-10) |
| `X-Content-Type-Options: nosniff` for `/storage` | Stops browsers guessing a different type from the file's content |

## Not done yet

- **Responsive image variants (`srcset`, NFR-PERF-02):** each upload is stored
  once at its original size.
- **Re-encoding images** to strip metadata such as EXIF/GPS: files are stored
  as uploaded. GD is available if the client wants it.
- **Virus scanning.**
