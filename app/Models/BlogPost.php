<?php

namespace App\Models;

use App\Enums\PostStatus;
use App\Models\Concerns\HasAuditTrail;
use Database\Factories\BlogPostFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;

class BlogPost extends Model
{
    /** @use HasFactory<BlogPostFactory> */
    use HasAuditTrail, HasFactory;

    /**
     * author_id is set explicitly from the authenticated user so a request
     * can't attribute a post to someone else.
     *
     * @var list<string>
     */
    protected $fillable = [
        'title',
        'slug',
        'excerpt',
        'content',
        'category_id',
        'featured_image_id',
        'status',
        'published_at',
        'meta_title',
        'meta_description',
        'canonical_url',
    ];

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'draft',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => PostStatus::class,
            'published_at' => 'datetime',
        ];
    }

    /**
     * Publicly visible: published with a publication date that has passed.
     * A future date (or none) keeps the post hidden.
     *
     * @param  Builder<self>  $query
     */
    public function scopePublished(Builder $query): void
    {
        $query->where('status', PostStatus::Published)
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now());
    }

    /**
     * Status is Published but the publish date is still in the future
     * (stage 17 scheduled publishing): not yet visible on the public site,
     * so the admin should never call it "Published" outright.
     */
    public function isScheduled(): bool
    {
        return $this->status === PostStatus::Published && $this->published_at?->isFuture() === true;
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    /**
     * @return BelongsTo<Category, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /**
     * @return BelongsTo<Media, $this>
     */
    public function featuredImage(): BelongsTo
    {
        return $this->belongsTo(Media::class, 'featured_image_id');
    }

    /**
     * @return BelongsToMany<Tag, $this>
     */
    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class);
    }

    /**
     * This record's most recent pending revision, whatever its status
     * (stage 16). A resubmission after a rejection creates a new row rather
     * than reusing the rejected one, so "most recent" is what actually
     * reflects the current state: pending (awaiting review), rejected (needs
     * the Editor's attention) or approved/none (nothing outstanding).
     *
     * @return MorphOne<PendingRevision, $this>
     */
    public function currentRevision(): MorphOne
    {
        return $this->morphOne(PendingRevision::class, 'revisable')->latestOfMany();
    }
}
