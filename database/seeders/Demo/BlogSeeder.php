<?php

namespace Database\Seeders\Demo;

use App\Enums\PostStatus;
use App\Models\BlogPost;
use App\Models\Category;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Demo content (non-production only): categories, tags and posts in each
 * workflow state, authored by the seeded Super Admin. No featured images.
 */
class BlogSeeder extends Seeder
{
    public function run(): void
    {
        $author = User::firstWhere('email', config('inspaya.super_admin.email'))
            ?? throw new RuntimeException('Demo posts need an author: run SuperAdminSeeder first.');

        $categories = collect(['Governance', 'Finance', 'Energy', 'Digital'])
            ->mapWithKeys(fn (string $name) => [$name => Category::firstOrCreate(
                ['slug' => Str::slug($name)],
                ['name' => $name, 'description' => "[PLACEHOLDER] Insights on {$name}."],
            )]);

        $tags = collect(['Strategy', 'Compliance', 'Policy', 'Technology', 'Leadership'])
            ->mapWithKeys(fn (string $name) => [$name => Tag::firstOrCreate(
                ['slug' => Str::slug($name)],
                ['name' => $name],
            )]);

        $posts = [
            ['Placeholder insight on board effectiveness', 'Governance', ['Strategy', 'Leadership'], PostStatus::Published, 30],
            ['Placeholder insight on forensic audit readiness', 'Finance', ['Compliance'], PostStatus::Published, 14],
            ['Placeholder insight on energy policy trends', 'Energy', ['Policy'], PostStatus::Published, 3],
            ['Placeholder insight on digital transformation', 'Digital', ['Technology', 'Strategy'], PostStatus::Review, null],
            ['Placeholder draft on regulatory change', 'Governance', ['Compliance', 'Policy'], PostStatus::Draft, null],
        ];

        foreach ($posts as [$title, $category, $tagNames, $status, $daysAgo]) {
            $post = BlogPost::firstOrNew(['slug' => Str::slug($title)]);

            if (! $post->exists) {
                $post->fill([
                    'title' => $title,
                    'excerpt' => '[PLACEHOLDER] Short summary for demo purposes.',
                    'content' => "[PLACEHOLDER] Demo article body.\n\nReplace with client-approved content.",
                    'category_id' => $categories[$category]->id,
                    'status' => $status,
                    'published_at' => $daysAgo === null ? null : now()->subDays($daysAgo),
                    'meta_title' => $title,
                    'meta_description' => '[PLACEHOLDER] Demo article description.',
                ]);
                // Not mass assignable by design, so set explicitly.
                $post->author_id = $author->id;
                $post->save();
            }

            $post->tags()->syncWithoutDetaching(collect($tagNames)->map(fn ($n) => $tags[$n]->id)->all());
        }
    }
}
