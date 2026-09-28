<?php

namespace Tests\Feature\Admin;

use App\Models\Media;
use App\Support\MediaPicker;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class MediaPickerTest extends TestCase
{
    use RefreshDatabase;

    public function test_options_list_images_only_newest_first(): void
    {
        $old = Media::factory()->create(['file_name' => 'old.jpg', 'alt_text' => 'Old']);
        Media::factory()->pdf()->create(['file_name' => 'brochure.pdf']);
        $new = Media::factory()->create(['file_name' => 'new.jpg', 'alt_text' => 'New']);

        $options = app(MediaPicker::class)->options();

        $this->assertSame([$new->id, $old->id], array_keys($options));
        $this->assertSame('new.jpg — New', $options[$new->id]['label']);
        $this->assertStringEndsWith($new->storage_path, $options[$new->id]['url']);
    }

    public function test_a_selected_image_beyond_the_limit_is_still_offered(): void
    {
        $oldest = Media::factory()->create();
        Media::factory()->count(3)->create();
        $picker = new MediaPicker(limit: 2);

        $this->assertCount(2, $picker->options());
        $this->assertArrayNotHasKey($oldest->id, $picker->options());

        $withSelected = $picker->options([$oldest->id]);
        $this->assertCount(3, $withSelected);
        $this->assertArrayHasKey($oldest->id, $withSelected);
    }

    public function test_rule_accepts_only_existing_images(): void
    {
        $image = Media::factory()->create();
        $pdf = Media::factory()->pdf()->create();
        $check = fn ($value) => Validator::make(['m' => $value], ['m' => ['nullable', 'integer', MediaPicker::rule()]])->passes();

        $this->assertTrue($check($image->id));
        $this->assertTrue($check(null));
        $this->assertFalse($check($pdf->id));
        $this->assertFalse($check(999_999));
    }
}
