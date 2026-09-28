<?php

namespace App\Models;

use App\Models\Concerns\HasAuditTrail;
use Database\Factories\CategoryFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Category extends Model
{
    /** @use HasFactory<CategoryFactory> */
    use HasAuditTrail, HasFactory;

    protected $fillable = ['name', 'slug', 'description'];

    /**
     * Deleting a category leaves its posts uncategorised
     * (blog_posts.category_id SET NULL).
     *
     * @return HasMany<BlogPost, $this>
     */
    public function blogPosts(): HasMany
    {
        return $this->hasMany(BlogPost::class);
    }
}
