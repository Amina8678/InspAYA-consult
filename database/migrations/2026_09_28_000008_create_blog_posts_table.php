<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('blog_posts', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('slug', 191)->unique();
            $table->text('excerpt')->nullable();
            $table->longText('content');
            // RESTRICT: public author attribution (FR-BLOG-03) must never
            // silently disappear; users are deactivated, not deleted.
            $table->foreignId('author_id')->constrained('users')->restrictOnDelete();
            // SET NULL: deleting a category leaves its posts uncategorised.
            $table->foreignId('category_id')->nullable()->constrained()->nullOnDelete();
            // SET NULL: the post survives losing its featured image.
            $table->foreignId('featured_image_id')->nullable()->constrained('media')->nullOnDelete();
            $table->string('status', 20)->default('draft');
            $table->timestamp('published_at')->nullable();
            $table->string('meta_title')->nullable();
            $table->string('meta_description', 500)->nullable();
            $table->string('canonical_url', 500)->nullable();
            $table->timestamps();

            $table->index(['status', 'published_at']);
            $table->index('author_id');
            $table->index('category_id');
            $table->index('featured_image_id');
        });

        Schema::create('blog_post_tag', function (Blueprint $table) {
            // CASCADE both sides: a tagging dies with its post or tag.
            $table->foreignId('blog_post_id')->constrained()->cascadeOnDelete();
            $table->foreignId('tag_id')->constrained()->cascadeOnDelete();

            $table->primary(['blog_post_id', 'tag_id']);
            $table->index('tag_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('blog_post_tag');
        Schema::dropIfExists('blog_posts');
    }
};
