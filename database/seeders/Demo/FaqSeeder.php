<?php

namespace Database\Seeders\Demo;

use App\Models\Faq;
use Illuminate\Database\Seeder;

/**
 * Demo content (non-production only, stage 17). Clearly fictional
 * placeholder FAQs: real questions and answers come from the client (SRS
 * Appendix B).
 */
class FaqSeeder extends Seeder
{
    public const QUESTIONS = [
        '[PLACEHOLDER] What industries does InspAya Consult work with?',
        '[PLACEHOLDER] How long does a typical engagement take?',
        '[PLACEHOLDER] Do you offer ongoing support after a project ends?',
    ];

    public function run(): void
    {
        foreach (self::QUESTIONS as $index => $question) {
            Faq::firstOrCreate(
                ['question' => $question],
                [
                    'answer' => '[PLACEHOLDER] Answer awaiting client-supplied content.',
                    'is_active' => true,
                    'sort_order' => $index + 1,
                ],
            );
        }
    }
}
