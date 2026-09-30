<?php

namespace Database\Seeders\Demo;

use App\Models\Testimonial;
use Illuminate\Database\Seeder;

/**
 * Demo content (non-production only, stage 17). Clearly fictional
 * placeholder testimonials: real quotes and attributions come from the
 * client (SRS Appendix B). No photos.
 */
class TestimonialSeeder extends Seeder
{
    public function run(): void
    {
        $testimonials = [
            [
                'quote' => '[PLACEHOLDER] Working with InspAya Consult transformed how our organization approaches governance. Their team brought clarity to a process that had felt overwhelming for years.',
                'author_name' => '[PLACEHOLDER] Jordan Blake',
                'author_title' => 'Chief Executive Officer',
                'organization' => '[PLACEHOLDER] Northbridge Holdings',
            ],
            [
                'quote' => '[PLACEHOLDER] The financial analytics engagement paid for itself within the first quarter. Responsive, rigorous, and genuinely invested in our outcomes.',
                'author_name' => '[PLACEHOLDER] Morgan Reyes',
                'author_title' => 'Chief Financial Officer',
                'organization' => '[PLACEHOLDER] Reyes & Okafor Group',
            ],
        ];

        foreach ($testimonials as $index => $data) {
            Testimonial::firstOrCreate(
                ['author_name' => $data['author_name']],
                $data + ['is_active' => true, 'sort_order' => $index + 1],
            );
        }
    }
}
