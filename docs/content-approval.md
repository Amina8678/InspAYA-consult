# Content approval

How edits to live content are reviewed before they go public (stage 16, SRS §7
"approved items only"). Code: `App\Support\ContentApproval`,
`PendingRevisionController`, `PendingRevisionPolicy`, table `pending_revisions`.

## The rule

An edit to a **page or blog post that is already published**, made by someone
**without `content.publish`**, is not applied. The live page stays exactly as
it was. The proposed changes are saved as a *pending revision*, and a person
who can publish (Administrator or Super Admin) approves or rejects it.

| Who | What happens when they save an edit to a *published* page or post |
|---|---|
| Super Admin, Administrator (`content.publish`) | Applied immediately, as before. |
| Editor | Becomes a pending revision. "Submitted your changes … for approval." |
| Author | Authors can only edit their own drafts, which are never live, so they are not affected. |

Not affected: drafts and posts in review (Editors already control those; going
live is gated separately by the publish policy), and all other content types
(services, consultants, core values, testimonials, FAQs, settings), which
Editors still edit directly.

## Flow

1. The Editor saves a change on a published page or post. One pending
   revision per record exists at a time; saving again **replaces** their
   previous pending version (it is not a history). The submission is audited
   as `revision_submitted`.
2. The Editor's dashboard shows "Your edit to … is awaiting approval" (or
   "was rejected: <reason>").
3. Anyone with `content.publish` sees "N content changes awaiting approval" on
   the dashboard, linking to **Content approvals** in the admin menu.
4. The review screen shows each changed field, old value beside new. Two
   actions:
   - **Approve**: the stored values are written to the live record (and tags
     synced for posts), the revision is marked approved, audited as
     `revision_approved`.
   - **Reject**: a reason is required; the live record is untouched; the
     revision stays as `rejected` with that reason, audited as
     `revision_rejected`. The Editor sees the reason and can edit again, which
     creates a new pending revision.
5. Approve and reject re-check, under a row lock, that the revision is still
   pending, so a double click or two reviewers cannot apply it twice.

## Edge cases

- **The live record changed after the Editor opened the form.** Pages and
  posts carry an integer `version`, increased by one on every change to the
  live record. The edit form includes the version it was loaded with in a
  hidden field, and a revision stores that number as its baseline (not the
  version at the moment of submitting, so an Editor who sat on a stale form is
  caught). If the live version has moved by approval time, the review screen
  says so and the normal Approve button is replaced by a confirmation: tick "I
  have compared the differences and want to overwrite the live changes" and
  press **Approve anyway**, or reject. Without the tick the server refuses to
  approve. An approve-anyway is audited (`approved_after_live_change`).
  Revisions created before versions existed have no baseline and are never
  called stale.
- **Two people with publish rights editing the same page or post.** The same
  hidden version protects direct saves: if the version has moved since the
  form was opened, the save is refused ("changed by someone else since you
  opened it"), nothing is written, what was typed stays in the form, and a
  link opens the current version in a new tab. The check and the write happen
  under one row lock, so two simultaneous saves cannot both succeed. A form
  that carries no version (left open across a deploy) is saved without the
  check.
- **The slug was taken in the meantime.** If the revision's URL now belongs to
  another page or post, approval is refused with a message (even with the
  confirmation) and only **Reject** is possible; there is no 500.
- **Two first submissions at once** are serialised by locking the live page or
  post row, so only one pending revision ever exists per record.
- **Deleting a page or post deletes its pending revisions** with it. Older
  orphans (from before that was added) are skipped in the queue and on the
  dashboard, and their review URL returns 404.
- Sharing image, featured image and similar media fields are included in the
  revision like any other field.
- Slug changes go through the same path, so a live URL never changes until
  approved.

## How the version is kept

`App\Models\Concerns\HasVersion` (on `Page` and `BlogPost`) adds one to
`version` in the model's `updating` event, as the SQL expression
`version + 1`, so it is atomic and covers every Eloquent `save()`, `update()`
and `touch()`. A save that changes nothing does not count. Writes that do not
go through a model save must count themselves:

- tag changes on a post (a pivot sync leaves the row untouched) call
  `bumpVersion()`, in the post editor and when a revision is approved;
- deleting a media file clears `og_image_id` / `featured_image_id` with two
  query-builder updates that add `version + 1` themselves
  (`MediaUsage::clear()`).

`RecordVersionTest` fails if any other code under `app/` or
`database/seeders/` writes `pages` or `blog_posts` around the model, so a new
write path cannot be added without deciding how it counts.

## Limits

- A revision stores the proposed **values**, not a diff. Approving writes those
  values over the fields it contains; the version check (above) is what stops
  that silently discarding someone else's edit.
- Nothing locks the live record while a revision is pending.
- After a refused direct save the form keeps the old version, so saving again
  without reloading is refused again. Open the current version, compare, and
  re-enter or copy across what you need.
- Only one pending revision per record, and no comment thread between
  reviewer and Editor beyond the rejection reason.
- Editors do not get `content.publish`; there is no per-item "approved by"
  column on pages or posts, only the audit log.
