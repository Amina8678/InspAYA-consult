<?php

namespace App\Models;

use App\Models\Concerns\HasAuditTrail;
use Database\Factories\MediaFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Every reference to a media row is SET NULL on delete: removing a file blanks
 * the image on posts, consultants, core values and settings. Media ids inside
 * pages.structured_content are not foreign keys and are not cleared.
 */
class Media extends Model
{
    /** @use HasFactory<MediaFactory> */
    use HasAuditTrail, HasFactory;

    protected $table = 'media';

    /**
     * uploader_id is set explicitly from the authenticated user.
     *
     * @var list<string>
     */
    protected $fillable = [
        'disk',
        'file_name',
        'storage_path',
        'mime_type',
        'size',
        'width',
        'height',
        'alt_text',
        'caption',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'size' => 'integer',
            'width' => 'integer',
            'height' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploader_id');
    }

    /**
     * @return HasMany<BlogPost, $this>
     */
    public function blogPosts(): HasMany
    {
        return $this->hasMany(BlogPost::class, 'featured_image_id');
    }

    /**
     * @return HasMany<Consultant, $this>
     */
    public function consultants(): HasMany
    {
        return $this->hasMany(Consultant::class, 'photo_id');
    }

    /**
     * @return HasMany<CoreValue, $this>
     */
    public function coreValues(): HasMany
    {
        return $this->hasMany(CoreValue::class, 'icon_id');
    }

    /**
     * @return HasMany<SiteSetting, $this>
     */
    public function siteSettings(): HasMany
    {
        return $this->hasMany(SiteSetting::class);
    }
}
