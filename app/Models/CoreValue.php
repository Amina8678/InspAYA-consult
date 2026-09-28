<?php

namespace App\Models;

use App\Models\Concerns\HasAuditTrail;
use Database\Factories\CoreValueFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CoreValue extends Model
{
    /** @use HasFactory<CoreValueFactory> */
    use HasAuditTrail, HasFactory;

    protected $fillable = [
        'title',
        'slug',
        'description',
        'icon_id',
        'is_active',
        'sort_order',
    ];

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'is_active' => true,
        'sort_order' => 0,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Media, $this>
     */
    public function icon(): BelongsTo
    {
        return $this->belongsTo(Media::class, 'icon_id');
    }
}
