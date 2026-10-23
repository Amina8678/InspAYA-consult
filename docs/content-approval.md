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

- **Deleting a page or post deletes its pending revisions** with it. Older
  orphans (from before that was added) are skipped in the queue and on the
  dashboard, and their review URL returns 404.
- Sharing image, featured image and similar media fields are included in the
  revision like any other field.
- Slug changes go through the same path, so a live URL never changes until
  approved.

## Limits (the "stage 16b" hardening, not built)

- A revision stores the proposed **values**, not a diff. Approving writes those
  values over the fields it contains, even if someone with publish rights
  edited the live record in the meantime. Review soon after submission.
- Nothing locks the live record while a revision is pending.
- Only one pending revision per record, and no comment thread between
  reviewer and Editor beyond the rejection reason.
- Editors do not get `content.publish`; there is no per-item "approved by"
  column on pages or posts, only the audit log.
