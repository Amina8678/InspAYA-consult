<?php

namespace App\Models;

use App\Enums\PageStatus;
use App\Models\Concerns\HasAuditTrail;
use Database\Factories\PageFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * structured_content is an ordered list of section blocks:
 * [{"type": "hero", "data": {"heading": "…", "background_media_id": 12}}, …].
 * Media ids inside it are not database-enforced foreign keys.
 */
class Page extends Model
{
    /** @use HasFactory<PageFactory> */
    use HasAuditTrail, HasFactory;

    protected $fillable = [
        'title',
        'slug',
        'status',
        'structured_content',
        'meta_title',
        'meta_description',
        'canonical_url',
        'og_image_id',
        'published_at',
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
            'status' => PageStatus::class,
            'structured_content' => 'array',
            'published_at' => 'datetime',
        ];
    }

    /**
     * Publicly visible: published with a publication date that has passed.
     *
     * @param  Builder<self>  $query
     */
    public function scopePublished(Builder $query): void
    {
        $query->where('status', PageStatus::Published)
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now());
    }

    /**
     * Sharing image for this page (NFR-SEO-04); SET NULL when the media goes.
     *
     * @return BelongsTo<Media, $this>
     */
    public function ogImage(): BelongsTo
    {
        return $this->belongsTo(Media::class, 'og_image_id');
    }
}
