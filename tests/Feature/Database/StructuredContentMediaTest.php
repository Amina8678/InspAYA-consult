<?php

namespace Tests\Feature\Database;

use App\Models\Media;
use App\Models\Page;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

/**
 * Documents a known limitation (schema plan §3.6): media ids stored inside
 * pages.structured_content are plain JSON values, not foreign keys. The
 * database neither validates them nor clears them when the media row is
 * deleted; the media-delete flow and page rendering must handle that.
 */
#[Group('constraints')]
class StructuredContentMediaTest extends TestCase
{
    use RefreshDatabase;

    public function test_deleting_media_leaves_a_dangling_id_in_structured_content(): void
    {
        $media = Media::factory()->create();
        $page = Page::factory()->create([
            'structured_content' => [
                ['type' => 'hero', 'data' => ['background_media_id' => $media->id]],
            ],
        ]);

        $media->delete();

        $this->assertModelMissing($media);
        $this->assertSame(
            $media->id,
            $page->fresh()->structured_content[0]['data']['background_media_id'],
            'The id is not cleared: structured_content media ids are not DB-enforced.',
        );
    }

    public function test_nonexistent_media_id_is_accepted_in_structured_content(): void
    {
        $page = Page::factory()->create([
            'structured_content' => [
                ['type' => 'hero', 'data' => ['background_media_id' => 999_999]],
            ],
        ]);

        $this->assertModelExists($page);
        $this->assertNull(Media::find(999_999));
    }
}
