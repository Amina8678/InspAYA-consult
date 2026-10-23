<?php

namespace App\Support;

use App\Enums\PageStatus;
use App\Enums\PendingRevisionStatus;
use App\Enums\PostStatus;
use App\Models\BlogPost;
use App\Models\Page;
use App\Models\PendingRevision;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Content approval workflow (stage 16): a Page or BlogPost that is already
 * live no longer takes a content edit from someone without content.publish
 * immediately — it is saved here as a pending revision instead, and an
 * Administrator/Super Admin applies or discards it. Unpublished content
 * (a draft, a post in review) is unaffected; Editors already fully control
 * that, and publishing itself is already gated separately (BlogPostPolicy /
 * PagePolicy ::publish).
 */
class ContentApproval
{
    public function __construct(private AuditLogger $audit) {}

    /**
     * True when $user's edit to $revisable must be deferred: the record is
     * already live and this user cannot publish (i.e. cannot self-approve).
     */
    public function needsApproval(Model $revisable, User $user): bool
    {
        return $this->isLive($revisable) && ! $user->can('publish', $revisable);
    }

    /**
     * Save $changes as this record's pending revision, never touching the
     * live record. Replaces any existing pending revision for the same
     * record (there is only ever one proposed "next" version at a time) —
     * looked up under a row lock inside a transaction, so two submissions
     * arriving close together are serialised instead of the second one's
     * read missing the first one's still-in-flight write (the same race the
     * sort_order fix guards against — see App\Services\Ordering\SortOrder).
     *
     * @param  array<string, mixed>  $changes  proposed field => new value
     * @param  int|null  $baseVersion  the version of the record the editing form was loaded with;
     *                                 when null (no form involved), the version read under the lock now
     */
    public function submit(Model $revisable, User $user, array $changes, ?int $baseVersion = null): PendingRevision
    {
        return DB::transaction(function () use ($revisable, $user, $changes, $baseVersion) {
            // Lock the live page/post row, not the pending row: when this is
            // the first submission there is no pending row yet, and a
            // FOR UPDATE on a row that does not exist locks nothing useful
            // (only a gap, which lets two inserts through, or deadlocks).
            // Every submission and approval for one record takes this lock
            // first, so they queue up behind each other.
            $live = $revisable->newQuery()->whereKey($revisable->getKey())->lockForUpdate()->firstOrFail();

            $revision = PendingRevision::query()
                ->where('revisable_type', $live->getMorphClass())
                ->where('revisable_id', $live->getKey())
                ->where('status', PendingRevisionStatus::Pending)
                ->first() ?? new PendingRevision([
                    'revisable_type' => $live->getMorphClass(),
                    'revisable_id' => $live->getKey(),
                ]);

            $revision->submitted_by = $user->id;
            $revision->changes = $changes;
            // The baseline approval compares against: what the Editor was
            // looking at, not what the record is now.
            $revision->base_version = $baseVersion === null ? $live->version : min($baseVersion, $live->version);
            $revision->status = PendingRevisionStatus::Pending;
            $revision->reviewed_by = null;
            $revision->reviewed_at = null;
            $revision->rejection_reason = null;
            $revision->save();

            $this->audit->record('revision_submitted', $user, $live,
                old: $this->currentValues($live, $changes),
                new: $changes,
            );

            return $revision;
        });
    }

    /**
     * Apply a pending revision's changes to its live record and mark it
     * approved. Re-checks the revision is still pending under a row lock,
     * so a double-click (or two reviewers) can't apply it twice.
     *
     * Refuses with a RevisionConflict when the revision's slug now belongs to
     * another record, or, unless $force, when the live record has been edited
     * since the revision was submitted.
     *
     * @throws RevisionConflict
     */
    public function apply(PendingRevision $revision, User $approver, bool $force = false): void
    {
        DB::transaction(function () use ($revision, $approver, $force) {
            // Same lock order as submit(): the live record first, then the
            // revision, so the two can never wait on each other.
            $live = $revision->revisable()->lockForUpdate()->first();
            $revision = PendingRevision::query()->whereKey($revision->getKey())->lockForUpdate()->firstOrFail();
            if ($revision->status !== PendingRevisionStatus::Pending || $live === null) {
                return;
            }
            $revision->setRelation('revisable', $live);

            $changes = $revision->changes;
            $liveChanged = $revision->liveRecordChanged();

            if (! $force && $liveChanged) {
                throw RevisionConflict::liveRecordChanged();
            }

            if (isset($changes['slug']) && $changes['slug'] !== $live->slug && $this->slugBelongsToAnother($live, $changes['slug'])) {
                throw RevisionConflict::slugTaken($changes['slug']);
            }

            $before = $this->currentValues($live, $changes);

            $tags = null;
            if (array_key_exists('tags', $changes)) {
                $tags = $changes['tags'];
                unset($changes['tags']);
            }

            $live->fill($changes);
            try {
                $live->save();
            } catch (UniqueConstraintViolationException) {
                // Another request took the slug between the check and the write.
                throw RevisionConflict::slugTaken((string) ($changes['slug'] ?? $live->slug));
            }
            if ($tags !== null && method_exists($live, 'tags')) {
                $synced = $live->tags()->sync($tags);
                // A tag-only change leaves the row itself untouched, so count it.
                if (! $live->wasChanged() && array_filter($synced)) {
                    $live->bumpVersion();
                }
            }

            $revision->status = PendingRevisionStatus::Approved;
            $revision->reviewed_by = $approver->id;
            $revision->reviewed_at = now();
            $revision->save();

            $this->audit->record('revision_approved', $approver, $live, $before,
                $revision->changes + ($liveChanged ? ['approved_after_live_change' => true] : []));
        });
    }

    /**
     * Mark a pending revision rejected, with a reason, leaving the live
     * record untouched. Re-checks it is still pending under a row lock, same
     * reasoning as apply().
     */
    public function reject(PendingRevision $revision, User $reviewer, string $reason): void
    {
        DB::transaction(function () use ($revision, $reviewer, $reason) {
            $revision = PendingRevision::query()->whereKey($revision->getKey())->with('revisable')->lockForUpdate()->firstOrFail();
            if ($revision->status !== PendingRevisionStatus::Pending) {
                return;
            }

            $revision->status = PendingRevisionStatus::Rejected;
            $revision->reviewed_by = $reviewer->id;
            $revision->reviewed_at = now();
            $revision->rejection_reason = $reason;
            $revision->save();

            $this->audit->record('revision_rejected', $reviewer, $revision->revisable, null, [
                'rejected_changes' => $revision->changes,
                'reason' => $reason,
            ]);
        });
    }

    private function slugBelongsToAnother(Model $live, string $slug): bool
    {
        return $live->newQuery()->where('slug', $slug)->whereKeyNot($live->getKey())->exists();
    }

    private function isLive(Model $revisable): bool
    {
        return match (true) {
            $revisable instanceof Page => $revisable->status === PageStatus::Published,
            $revisable instanceof BlogPost => $revisable->status === PostStatus::Published,
            default => throw new InvalidArgumentException('Unsupported revisable type ['.$revisable::class.'].'),
        };
    }

    /**
     * The live record's current values for the given proposed fields — the
     * "before" side of the diff, since the live record is the baseline a
     * pending revision is compared against.
     *
     * @param  array<string, mixed>  $changes
     * @return array<string, mixed>
     */
    private function currentValues(Model $revisable, array $changes): array
    {
        $columns = array_diff(array_keys($changes), ['tags']);
        $current = $revisable->only($columns);

        if (array_key_exists('tags', $changes) && method_exists($revisable, 'tags')) {
            $current['tags'] = $revisable->tags()->pluck('tags.id')->sort()->values()->all();
        }

        return $current;
    }
}
