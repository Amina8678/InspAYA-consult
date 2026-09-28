<?php

namespace App\Models;

use App\Models\Concerns\HasAuditTrail;
use Database\Factories\TagFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Tag extends Model
{
    /** @use HasFactory<TagFactory> */
    use HasAuditTrail, HasFactory;

    protected $fillable = ['name', 'slug'];

    /**
     * @return BelongsToMany<BlogPost, $this>
     */
    public function blogPosts(): BelongsToMany
    {
        return $this->belongsToMany(BlogPost::class);
    }
}
