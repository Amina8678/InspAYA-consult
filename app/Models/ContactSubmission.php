<?php

namespace App\Models;

use App\Enums\EnquiryStatus;
use App\Models\Concerns\HasAuditTrail;
use Database\Factories\ContactSubmissionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ContactSubmission extends Model
{
    /** @use HasFactory<ContactSubmissionFactory> */
    use HasAuditTrail, HasFactory;

    /**
     * Visitor-supplied fields plus server-set consent_at and ip_address.
     * status, assigned_to and responded_at are staff actions and are set
     * explicitly.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'phone',
        'organization',
        'subject',
        'message',
        'consent_at',
        'ip_address',
    ];

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'new',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => EnquiryStatus::class,
            'consent_at' => 'datetime',
            'responded_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    /**
     * @return HasMany<ContactSubmissionNote, $this>
     */
    public function notes(): HasMany
    {
        return $this->hasMany(ContactSubmissionNote::class);
    }
}
