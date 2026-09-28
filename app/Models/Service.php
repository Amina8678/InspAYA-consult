<?php

namespace App\Models;

use App\Models\Concerns\HasAuditTrail;
use App\Models\Concerns\Sortable;
use Database\Factories\ServiceFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Service extends Model
{
    /** @use HasFactory<ServiceFactory> */
    use HasAuditTrail, HasFactory, Sortable;

    protected $fillable = [
        'title',
        'slug',
        'short_description',
        'description',
        'capabilities',
        'outcomes',
        'is_active',
        'sort_order',
        'meta_title',
        'meta_description',
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
            'capabilities' => 'array',
            'outcomes' => 'array',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    /**
     * @return BelongsToMany<Consultant, $this>
     */
    public function consultants(): BelongsToMany
    {
        return $this->belongsToMany(Consultant::class, 'service_consultant')
            ->withPivot(['is_lead', 'sort_order'])
            ->withTimestamps()
            ->orderByPivot('sort_order');
    }

    /**
     * Lead consultants for this service (FR-TEAM-02).
     *
     * @return BelongsToMany<Consultant, $this>
     */
    public function leadConsultants(): BelongsToMany
    {
        return $this->consultants()->wherePivot('is_lead', true);
    }
}
