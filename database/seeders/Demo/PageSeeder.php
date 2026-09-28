<?php

namespace Database\Seeders\Demo;

use App\Enums\PageStatus;
use App\Models\Page;
use Illuminate\Database\Seeder;

/**
 * Demo content (non-production only). Image slots in structured_content are
 * null: no media files are seeded. Legal pages stay draft because placeholder
 * legal text must never be published.
 */
class PageSeeder extends Seeder
{
    public function run(): void
    {
        $pages = [
            'home' => ['Home', PageStatus::Published, [
                ['type' => 'hero', 'data' => [
                    'heading' => '[PLACEHOLDER] Approved tagline',
                    'primary_cta' => ['label' => 'Contact us', 'url' => '/contact'],
                    'secondary_cta' => ['label' => 'Our services', 'url' => '/services'],
                    'background_media_id' => null,
                ]],
                ['type' => 'intro', 'data' => ['body' => '[PLACEHOLDER] Company introduction and mission summary.']],
                ['type' => 'feature', 'data' => [
                    'heading' => '[PLACEHOLDER] Why InspAya Consult',
                    'body' => '[PLACEHOLDER] Differentiators.',
                    'background_media_id' => null,
                ]],
            ]],
            'about' => ['About', PageStatus::Published, [
                ['type' => 'text', 'data' => ['heading' => 'Vision', 'body' => '[PLACEHOLDER] Vision statement.']],
                ['type' => 'text', 'data' => ['heading' => 'Mission', 'body' => '[PLACEHOLDER] Mission statement.']],
                ['type' => 'text', 'data' => ['heading' => 'Corporate mandate', 'body' => '[PLACEHOLDER] Corporate mandate.']],
            ]],
            'contact' => ['Contact', PageStatus::Published, [
                ['type' => 'text', 'data' => ['body' => '[PLACEHOLDER] Contact introduction.']],
            ]],
            'privacy-policy' => ['Privacy Policy', PageStatus::Draft, [
                ['type' => 'text', 'data' => ['body' => '[PLACEHOLDER] Client-supplied privacy policy required (SRS Appendix B).']],
            ]],
            'terms-of-service' => ['Terms of Service', PageStatus::Draft, [
                ['type' => 'text', 'data' => ['body' => '[PLACEHOLDER] Client-supplied terms of service required (SRS Appendix B).']],
            ]],
        ];

        foreach ($pages as $slug => [$title, $status, $content]) {
            Page::firstOrCreate(
                ['slug' => $slug],
                [
                    'title' => $title,
                    'status' => $status,
                    'structured_content' => $content,
                    'meta_title' => $title.' | InspAya Consult',
                    'meta_description' => '[PLACEHOLDER] '.$title.' page description.',
                    'published_at' => $status === PageStatus::Published ? now() : null,
                ],
            );
        }
    }
}
