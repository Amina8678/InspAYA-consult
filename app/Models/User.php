<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Enums\UserStatus;
use App\Models\Concerns\HasAuditTrail;
use App\Notifications\AdminResetPassword;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasAuditTrail, HasFactory, Notifiable;

    /**
     * role_id, status, last_login_at and the two-factor columns are
     * deliberately not mass assignable: they grant or record access and are
     * set explicitly (e.g. $user->role()->associate($role)).
     *
     * @var list<string>
     */
    protected $fillable = ['name', 'username', 'email', 'password'];

    /**
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
        'two_factor_secret',
        'two_factor_recovery_codes',
    ];

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'active',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'status' => UserStatus::class,
            'last_login_at' => 'datetime',
            'two_factor_secret' => 'encrypted',
            'two_factor_recovery_codes' => 'encrypted',
            'two_factor_confirmed_at' => 'datetime',
        ];
    }

    /**
     * Permission slugs for the current role, remembered on this instance only
     * (i.e. for one request). Never cached across requests, so a role or
     * permission change applies on the user's next request.
     *
     * @var array{role_id: int|null, slugs: list<string>}|null
     */
    private ?array $permissionSlugs = null;

    public function hasPermission(string $slug): bool
    {
        return in_array($slug, $this->permissionSlugs(), true);
    }

    public function hasRole(string $slug): bool
    {
        return $this->currentRole()?->slug === $slug;
    }

    public function isSuperAdmin(): bool
    {
        return $this->hasRole('super-admin');
    }

    /**
     * @return list<string>
     */
    public function permissionSlugs(): array
    {
        if ($this->permissionSlugs === null || $this->permissionSlugs['role_id'] !== $this->role_id) {
            $this->permissionSlugs = [
                'role_id' => $this->role_id,
                'slugs' => $this->currentRole()?->permissions->pluck('slug')->all() ?? [],
            ];
        }

        return $this->permissionSlugs['slugs'];
    }

    /**
     * The role matching role_id, reloaded if role_id changed on this instance.
     */
    private function currentRole(): ?Role
    {
        if (! $this->relationLoaded('role') || $this->getRelation('role')?->getKey() !== $this->role_id) {
            $this->load('role.permissions');
        }

        $role = $this->getRelation('role');
        $role?->loadMissing('permissions');

        return $role;
    }

    /**
     * Queued admin reset email instead of Laravel's synchronous default.
     */
    public function sendPasswordResetNotification(#[\SensitiveParameter] $token): void
    {
        $this->notify(new AdminResetPassword($token));
    }

    /**
     * @return BelongsTo<Role, $this>
     */
    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    /**
     * Deleting a user with posts is refused by the database
     * (blog_posts.author_id RESTRICT); deactivate instead.
     *
     * @return HasMany<BlogPost, $this>
     */
    public function blogPosts(): HasMany
    {
        return $this->hasMany(BlogPost::class, 'author_id');
    }

    /**
     * @return HasMany<Media, $this>
     */
    public function uploadedMedia(): HasMany
    {
        return $this->hasMany(Media::class, 'uploader_id');
    }

    /**
     * @return HasMany<ContactSubmission, $this>
     */
    public function assignedEnquiries(): HasMany
    {
        return $this->hasMany(ContactSubmission::class, 'assigned_to');
    }

    /**
     * @return HasMany<ContactSubmissionNote, $this>
     */
    public function enquiryNotes(): HasMany
    {
        return $this->hasMany(ContactSubmissionNote::class);
    }

    /**
     * Actions this user performed. For changes made *to* this user, see
     * auditTrail().
     *
     * @return HasMany<AuditLog, $this>
     */
    public function auditLogs(): HasMany
    {
        return $this->hasMany(AuditLog::class);
    }
}
