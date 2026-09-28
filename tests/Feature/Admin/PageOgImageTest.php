<?php

namespace Tests\Feature\Admin;

use App\Models\Media;
use App\Models\Page;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class PageOgImageTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_page_image_is_used_for_sharing_before_section_images(): void
    {
        $section = Media::factory()->create();
        $og = Media::factory()->create();
        Page::factory()->published()->create([
            'slug' => 'about',
            'og_image_id' => $og->id,
            'structured_content' => [['type' => 'feature', 'data' => ['background_media_id' => $section->id]]],
        ]);

        $this->get(route('about'))->assertOk()
            ->assertViewHas('seo', fn (array $seo) => str_ends_with($seo['og_image']['url'], $og->storage_path));
    }

    public function test_deleting_the_image_lists_and_clears_the_page_usage(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->actingAs(User::factory()->withRole('super-admin')->create());
        $og = Media::factory()->create();
        $page = Page::factory()->create(['title' => 'Shared page', 'og_image_id' => $og->id]);

        $this->get(route('admin.media.delete', $og))->assertSeeInOrder(['Shared page', 'Sharing image']);
        $this->delete(route('admin.media.destroy', $og), ['confirm' => '1'])->assertRedirect();

        $this->assertNull($page->fresh()->og_image_id);
    }

    public function test_the_migration_rolls_back_cleanly(): void
    {
        $this->artisan('migrate:rollback', ['--step' => 1])->assertSuccessful();
        $this->assertFalse(Schema::hasColumn('pages', 'og_image_id'));

        $this->artisan('migrate')->assertSuccessful();
        $this->assertTrue(Schema::hasColumn('pages', 'og_image_id'));
    }
}
