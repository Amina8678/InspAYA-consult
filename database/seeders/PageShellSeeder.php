<?php

namespace Database\Seeders;

use App\Enums\PageStatus;
use App\Models\Page;
use Illuminate\Database\Seeder;

/**
 * Production only: empty draft shells for the core pages, so editors fill in
 * approved content in the CMS. No content, no SEO metadata, never published.
 * Outside production, Demo\PageSeeder creates these pages with placeholder
 * content instead.
 *
 * Idempotent and non-destructive: matched by slug, never overwritten.
 */
class PageShellSeeder extends Seeder
{
    /**
     * @var array<string, string> slug => title
     */
    public const PAGES = [
        'home' => 'Home',
        'about' => 'About',
        'privacy-policy' => 'Privacy Policy',
        'terms-of-service' => 'Terms of Service',
    ];

    public function run(): void
    {
        foreach (self::PAGES as $slug => $title) {
            Page::firstOrCreate(
                ['slug' => $slug],
                [
                    'title' => $title,
                    'status' => PageStatus::Draft,
                    'structured_content' => null,
                    'published_at' => null,
                ],
            );
        }
    }
}
