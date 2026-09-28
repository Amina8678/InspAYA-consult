<?php

namespace Database\Seeders;

use App\Models\CoreValue;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * The six core values named in FR-VAL-01, seeded in every environment.
 * Descriptions are placeholders until the client supplies approved copy.
 * No icons (no image files are seeded).
 *
 * Idempotent and non-destructive: matched by slug, never overwritten.
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
