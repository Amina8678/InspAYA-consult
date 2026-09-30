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
     */
    public function submit(Model $revisable, User $user, array $changes): PendingRevision
    {
        return DB::transaction(function () use ($revisable, $user, $changes) {
            $revision = PendingRevision::query()
                ->where('revisable_type', $revisable->getMorphClass())
                ->where('revisable_id', $revisable->getKey())
                ->where('status', PendingRevisionStatus::Pending)
                ->lockForUpdate()
                ->first() ?? new PendingRevision([
                    'revisable_type' => $revisable->getMorphClass(),
                    'revisable_id' => $revisable->getKey(),
                ]);

            $revision->submitted_by = $user->id;
            $revision->changes = $changes;
            $revision->status = PendingRevisionStatus::Pending;
            $revision->reviewed_by = null;
            $revision->reviewed_at = null;
            $revision->rejection_reason = null;
            $revision->save();

            $this->audit->record('revision_submitted', $user, $revisable,
                old: $this->currentValues($revisable, $changes),
                new: $changes,
            );

            return $revision;
        });
    }

    /**
     * Apply a pending revision's changes to its live record and mark it
     * approved. Re-checks the revision is still pending under a row lock,
     * so a double-click (or two reviewers) can't apply it twice.
     */
    public function apply(PendingRevision $revision, User $approver): void
    {
        DB::transaction(function () use ($revision, $approver) {
            $revision = PendingRevision::query()->whereKey($revision->getKey())->with('revisable')->lockForUpdate()->firstOrFail();
            if ($revision->status !== PendingRevisionStatus::Pending) {
                return;
            }

            $revisable = $revision->revisable;
            $changes = $revision->changes;
            $before = $this->currentValues($revisable, $changes);

            $tags = null;
            if (array_key_exists('tags', $changes)) {
                $tags = $changes['tags'];
                unset($changes['tags']);
            }

            $revisable->fill($changes);
            $revisable->save();
            if ($tags !== null && method_exists($revisable, 'tags')) {
                $revisable->tags()->sync($tags);
            }

            $revision->status = PendingRevisionStatus::Approved;
            $revision->reviewed_by = $approver->id;
            $revision->reviewed_at = now();
            $revision->save();

            $this->audit->record('revision_approved', $approver, $revisable, $before, $revision->changes);
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
