<?php

namespace Database\Seeders\Demo;

use App\Models\CoreValue;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Demo content (non-production only). The six values are named in FR-VAL-01;
 * descriptions are placeholders. No icons (no image files are seeded).
 */
class CoreValueSeeder extends Seeder
{
    /**
     * @var list<string>
     */
    public const VALUES = ['Excellence', 'Integrity', 'Innovation', 'Collaboration', 'Resilience', 'Sustainability'];

    public function run(): void
    {
        foreach (self::VALUES as $index => $title) {
            CoreValue::firstOrCreate(
                ['slug' => Str::slug($title)],
                [
                    'title' => $title,
                    'description' => '[PLACEHOLDER] What '.$title.' means at InspAya Consult.',
                    'is_active' => true,
                    'sort_order' => $index + 1,
                ],
            );
        }
    }
}
