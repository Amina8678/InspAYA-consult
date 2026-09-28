<?php

namespace App\Models;

use App\Enums\SettingType;
use App\Models\Concerns\HasAuditTrail;
use Database\Factories\SiteSettingFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SiteSetting extends Model
{
    /** @use HasFactory<SiteSettingFactory> */
    use HasAuditTrail, HasFactory;

    protected $fillable = ['key', 'group', 'type', 'value', 'media_id'];

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'type' => 'string',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => SettingType::class,
        ];
    }

    /**
     * Set when type is media (logo, footer images).
     *
     * @return BelongsTo<Media, $this>
     */
    public function media(): BelongsTo
    {
        return $this->belongsTo(Media::class);
    }
}
