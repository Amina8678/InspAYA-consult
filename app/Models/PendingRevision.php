<?php

namespace App\Models;

use App\Enums\PendingRevisionStatus;
use Database\Factories\PendingRevisionFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * A content edit by someone without content.publish, made to an already-
 * published Page or BlogPost, waiting for an Administrator/Super Admin to
 * apply or discard it (stage 16). `changes` is diffed against the live
 * record's current values at review time — there is no stored "before"
 * snapshot, because the live record cannot change while this is pending.
 */
class PendingRevision extends Model
{
    /** @use HasFactory<PendingRevisionFactory> */
    use HasFactory;

    protected $fillable = [
        'revisable_type',
        'revisable_id',
        'submitted_by',
        'changes',
        'base_updated_at',
        'status',
        'reviewed_by',
        'reviewed_at',
        'rejection_reason',
    ];

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'pending',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'changes' => 'array',
            'base_updated_at' => 'datetime',
            'status' => PendingRevisionStatus::class,
            'reviewed_at' => 'datetime',
        ];
    }

    /**
     * True when the live record was edited after this revision was submitted.
     * Revisions without a recorded baseline (submitted before it existed)
     * report false: there is nothing to compare against.
     */
    public function liveRecordChanged(): bool
    {
        $live = $this->revisable?->updated_at;

        return $this->base_updated_at !== null && $live !== null && ! $live->equalTo($this->base_updated_at);
    }

    /**
     * @param  Builder<self>  $query
     */
    public function scopePending(Builder $query): void
    {
        $query->where('status', PendingRevisionStatus::Pending);
    }

    /**
     * Pending revisions whose Page or BlogPost still exists. Orphans (from
     * before deletes cleaned up after themselves) are left out.
     *
     * @param  Builder<self>  $query
     */
    public function scopeWithLiveRecord(Builder $query): void
    {
        $query->whereHasMorph('revisable', [Page::class, BlogPost::class]);
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function revisable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function submitter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}
