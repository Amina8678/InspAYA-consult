<?php

namespace App\Models;

use App\Models\Concerns\HasAuditTrail;
use Database\Factories\ConsultantFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Consultant extends Model
{
    /** @use HasFactory<ConsultantFactory> */
    use HasAuditTrail, HasFactory;

    protected $fillable = [
        'name',
        'title',
        'bio',
        'photo_id',
        'expertise',
        'qualifications',
        'email',
        'links',
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
            'expertise' => 'array',
            'qualifications' => 'array',
            'links' => 'array',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Media, $this>
     */
    public function photo(): BelongsTo
    {
        return $this->belongsTo(Media::class, 'photo_id');
    }

    /**
     * @return BelongsToMany<Service, $this>
     */
    public function services(): BelongsToMany
    {
        return $this->belongsToMany(Service::class, 'service_consultant')
            ->withPivot(['is_lead', 'sort_order'])
            ->withTimestamps();
    }
}
