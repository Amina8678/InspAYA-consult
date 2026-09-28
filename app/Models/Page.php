<?php

namespace App\Models;

use App\Enums\PageStatus;
use App\Models\Concerns\HasAuditTrail;
use Database\Factories\PageFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

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
}
