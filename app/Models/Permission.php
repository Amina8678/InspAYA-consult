<?php

namespace App\Models;

use Database\Factories\PermissionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Permission extends Model
{
    /** @use HasFactory<PermissionFactory> */
    use HasFactory;

    /**
     * Shape of a permission slug ("posts.edit-own"). Gate abilities of this
     * shape are permission checks; anything else goes to the model policies.
     */
    public const SLUG_PATTERN = '/^[a-z][a-z-]*\.[a-z][a-z-]*$/';

    protected $fillable = ['name', 'slug', 'group', 'description'];

    public static function isSlug(string $ability): bool
    {
        return preg_match(self::SLUG_PATTERN, $ability) === 1;
    }

    /**
     * @return BelongsToMany<Role, $this>
     */
    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class);
    }
}
