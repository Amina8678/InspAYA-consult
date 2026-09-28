<?php

namespace Tests\Feature\Admin;

use App\Models\AuditLog;
use App\Models\BlogPost;
use App\Models\Consultant;
use App\Models\CoreValue;
use App\Models\Media;
use App\Models\Page;
use App\Models\SiteSetting;
use App\Models\User;
use App\Services\Media\FileInspector;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class MediaLibraryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
        Storage::fake('public');
    }

    private function as(string $role): User
    {
        $user = User::factory()->withRole($role)->create();
        $this->actingAs($user);

        return $user;
    }

    private function upload(UploadedFile $file, array $extra = [])
    {
        return $this->from(route('admin.media.index'))
            ->post(route('admin.media.store'), ['file' => $file] + $extra);
    }

    private static function jpegBytes(int $width = 40, int $height = 30): string
    {
        $image = imagecreatetruecolor($width, $height);
        ob_start();
        imagejpeg($image);

        return (string) ob_get_clean();
    }

    // Upload: accepted -----------------------------------------------------

    public function test_image_upload_is_stored_under_a_random_name_with_its_details(): void
    {
        $user = $this->as('editor');

        $this->upload(UploadedFile::fake()->image('Team photo.jpg', 800, 600), ['alt_text' => 'Our team', 'caption' => 'Annual retreat'])
            ->assertRedirect(route('admin.media.index'))
            ->assertSessionHasNoErrors();

        $media = Media::sole();
        $this->assertSame('Team photo.jpg', $media->file_name);
        $this->assertMatchesRegularExpression('~^media/\d{4}/\d{2}/[0-9a-f-]{36}\.jpg$~', $media->storage_path);
        $this->assertStringNotContainsString('Team', $media->storage_path);
        $this->assertSame('image/jpeg', $media->mime_type);
        $this->assertSame([800, 600], [$media->width, $media->height]);
        $this->assertGreaterThan(0, $media->size);
        $this->assertSame($user->id, $media->uploader_id);
        $this->assertSame('Our team', $media->alt_text);
        Storage::disk('public')->assertExists($media->storage_path);
        $this->assertDatabaseHas('audit_logs', ['action' => 'created', 'entity_type' => 'media', 'entity_id' => $media->id]);
    }

    public function test_png_gif_and_pdf_are_accepted(): void
    {
        $this->as('author');

        $this->upload(UploadedFile::fake()->image('a.png', 10, 10), ['alt_text' => 'A'])->assertSessionHasNoErrors();
        $this->upload(UploadedFile::fake()->image('b.gif', 10, 10), ['alt_text' => 'B'])->assertSessionHasNoErrors();
        $this->upload(UploadedFile::fake()->createWithContent('Brochure.pdf', "%PDF-1.4\n1 0 obj\n<<>>\nendobj\ntrailer\n<<>>\n%%EOF\n"))
            ->assertSessionHasNoErrors();

        $this->assertEqualsCanonicalizing(['image/png', 'image/gif', 'application/pdf'], Media::pluck('mime_type')->all());
        $this->assertStringEndsWith('.pdf', Media::firstWhere('mime_type', 'application/pdf')->storage_path);
        $this->assertNull(Media::firstWhere('mime_type', 'application/pdf')->width);
    }

    public function test_extension_comes_from_the_content_not_the_name(): void
    {
        $this->as('editor');

        // A genuine JPEG named like a script is stored as .jpg, never .php.
        $this->upload(UploadedFile::fake()->createWithContent('shell.php', self::jpegBytes()), ['alt_text' => 'x'])
            ->assertSessionHasNoErrors();

        $this->assertStringEndsWith('.jpg', Media::sole()->storage_path);
    }

    // Upload: rejected -----------------------------------------------------

    /**
     * @return array<string, array{callable(): UploadedFile}>
     */
    public static function rejectedFiles(): array
    {
        return [
            'text with a .jpg extension' => [fn () => UploadedFile::fake()->createWithContent('photo.jpg', 'just some text, not an image')],
            'empty file claiming image/jpeg' => [fn () => UploadedFile::fake()->create('photo.jpg', 10, 'image/jpeg')],
            'php script' => [fn () => UploadedFile::fake()->createWithContent('shell.php', '<?php system($_GET["c"]); ?>')],
            'php script named .jpg' => [fn () => UploadedFile::fake()->createWithContent('shell.jpg', '<?php system($_GET["c"]); ?>')],
            'svg with script' => [fn () => UploadedFile::fake()->createWithContent('logo.svg', '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>')],
            'html page' => [fn () => UploadedFile::fake()->createWithContent('page.html', '<!DOCTYPE html><html><script>alert(1)</script></html>')],
            'pdf extension without pdf content' => [fn () => UploadedFile::fake()->createWithContent('doc.pdf', 'not a pdf')],
            'jpeg header then garbage' => [fn () => UploadedFile::fake()->createWithContent('bad.jpg', "\xFF\xD8\xFF\xE0".str_repeat('x', 100))],
        ];
    }

    #[DataProvider('rejectedFiles')]
    public function test_disallowed_content_is_rejected(callable $makeFile): void
    {
        $this->as('super-admin');

        $this->upload($makeFile(), ['alt_text' => 'x'])->assertSessionHasErrors('file');

        $this->assertDatabaseCount('media', 0);
        $this->assertSame([], Storage::disk('public')->allFiles());
    }

    public function test_oversized_files_are_rejected(): void
    {
        $this->as('super-admin');

        $this->upload(UploadedFile::fake()->image('big.jpg', 20, 20)->size(5 * 1024 + 1), ['alt_text' => 'x'])
            ->assertSessionHasErrors(['file' => 'This file is too large. The limit for this type is 5 MB.']);

        $this->upload(UploadedFile::fake()->createWithContent('big.pdf', "%PDF-1.4\n")->size(10 * 1024 + 1))
            ->assertSessionHasErrors('file');

        $this->assertDatabaseCount('media', 0);
    }

    public function test_images_with_extreme_dimensions_are_rejected(): void
    {
        $this->as('super-admin');

        $this->upload(UploadedFile::fake()->createWithContent('wide.jpg', self::jpegBytes(FileInspector::MAX_DIMENSION + 1, 2)), ['alt_text' => 'x'])
            ->assertSessionHasErrors('file');

        $this->assertDatabaseCount('media', 0);
    }

    public function test_alt_text_is_required_for_images_but_not_documents(): void
    {
        $this->as('editor');

        $this->upload(UploadedFile::fake()->image('a.jpg', 10, 10), ['alt_text' => '   '])->assertSessionHasErrors('alt_text');
        $this->assertDatabaseCount('media', 0);
        $this->assertSame([], Storage::disk('public')->allFiles());

        $this->upload(UploadedFile::fake()->createWithContent('a.pdf', "%PDF-1.4\n"))->assertSessionHasNoErrors();
        $this->assertDatabaseCount('media', 1);
    }

    // List -----------------------------------------------------------------

    public function test_list_is_paginated_and_filterable_by_type(): void
    {
        $this->as('author');
        Media::factory()->count(3)->create();
        Media::factory()->pdf()->create(['file_name' => 'Annual report.pdf']);

        $this->get(route('admin.media.index'))->assertOk()
            ->assertViewHas('media', fn ($p) => $p->total() === 4);

        $this->get(route('admin.media.index', ['type' => 'document']))->assertOk()
            ->assertSee('Annual report.pdf')
            ->assertViewHas('media', fn ($p) => $p->total() === 1);

        $this->get(route('admin.media.index', ['type' => 'image']))
            ->assertViewHas('media', fn ($p) => $p->total() === 3);

        Media::factory()->count(30)->create();
        $this->get(route('admin.media.index'))->assertViewHas('media', fn ($p) => $p->count() === 24 && $p->lastPage() === 2);
    }

    public function test_file_names_and_alt_text_are_escaped(): void
    {
        $this->as('editor');
        Media::factory()->create(['file_name' => '<script>alert(1)</script>.jpg', 'alt_text' => '"><img src=x onerror=alert(2)>']);

        $response = $this->get(route('admin.media.index'))->assertOk();

        $response->assertDontSee('<script>alert(1)</script>', false)
            ->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;.jpg', false)
            ->assertDontSee('"><img src=x onerror=alert(2)>', false);
    }

    // Edit / replace -------------------------------------------------------

    public function test_edit_updates_alt_text_and_caption_and_audits(): void
    {
        $this->as('editor');
        $media = Media::factory()->create(['alt_text' => 'Old']);

        $this->put(route('admin.media.update', $media), ['alt_text' => 'New alt', 'caption' => 'New caption'])
            ->assertRedirect(route('admin.media.edit', $media))
            ->assertSessionHasNoErrors();

        $this->assertSame(['New alt', 'New caption'], [$media->fresh()->alt_text, $media->fresh()->caption]);
        $log = AuditLog::firstWhere('action', 'updated');
        $this->assertSame('Old', $log->old_values['alt_text']);
        $this->assertSame('New alt', $log->new_values['alt_text']);
    }

    public function test_image_alt_text_cannot_be_cleared(): void
    {
        $this->as('editor');
        $media = Media::factory()->create(['alt_text' => 'Keep']);

        $this->put(route('admin.media.update', $media), ['alt_text' => ''])->assertSessionHasErrors('alt_text');

        $this->assertSame('Keep', $media->fresh()->alt_text);
    }

    public function test_replacing_the_file_keeps_the_id_and_removes_the_old_file(): void
    {
        $this->as('editor');
        $this->upload(UploadedFile::fake()->image('first.jpg', 10, 10), ['alt_text' => 'x']);
        $media = Media::sole();
        $oldPath = $media->storage_path;
        $post = BlogPost::factory()->create(['featured_image_id' => $media->id]);

        $this->put(route('admin.media.update', $media), ['alt_text' => 'x', 'file' => UploadedFile::fake()->image('second.png', 20, 10)])
            ->assertSessionHasNoErrors();

        $media->refresh();
        $this->assertSame('second.png', $media->file_name);
        $this->assertSame('image/png', $media->mime_type);
        $this->assertSame([20, 10], [$media->width, $media->height]);
        Storage::disk('public')->assertMissing($oldPath);
        Storage::disk('public')->assertExists($media->storage_path);
        $this->assertSame($media->id, $post->fresh()->featured_image_id);
    }

    public function test_replacement_goes_through_the_same_content_checks(): void
    {
        $this->as('editor');
        $media = Media::factory()->create();
        $path = $media->storage_path;

        $this->put(route('admin.media.update', $media), ['alt_text' => 'x', 'file' => UploadedFile::fake()->createWithContent('x.jpg', '<?php echo 1;')])
            ->assertSessionHasErrors('file');

        $this->assertSame($path, $media->fresh()->storage_path);
    }

    // Delete ---------------------------------------------------------------

    public function test_delete_page_lists_every_usage_including_page_sections(): void
    {
        $this->as('super-admin');
        $media = Media::factory()->create();
        BlogPost::factory()->create(['title' => 'Post using it', 'featured_image_id' => $media->id]);
        Consultant::factory()->create(['name' => 'Consultant using it', 'photo_id' => $media->id]);
        CoreValue::factory()->create(['title' => 'Value using it', 'icon_id' => $media->id]);
        SiteSetting::factory()->create(['key' => 'branding.logo', 'type' => 'media', 'media_id' => $media->id]);
        Page::factory()->create(['title' => 'Home page', 'structured_content' => [
            ['type' => 'hero', 'data' => ['background_media_id' => $media->id]],
            ['type' => 'text', 'data' => ['body' => 'no image']],
        ]]);
        Page::factory()->create(['title' => 'Other page', 'structured_content' => [['type' => 'hero', 'data' => ['background_media_id' => $media->id + 1]]]]);

        $this->get(route('admin.media.delete', $media))->assertOk()
            ->assertSee('used in 5 places')
            ->assertSeeInOrder(['Post using it', 'Consultant using it', 'Value using it', 'branding.logo', 'Home page', 'Section 1 (hero): background_media_id'])
            ->assertDontSee('Other page');
    }

    public function test_delete_requires_confirmation(): void
    {
        $this->as('super-admin');
        $media = Media::factory()->create();

        $this->delete(route('admin.media.destroy', $media))->assertSessionHasErrors('confirm');

        $this->assertModelExists($media);
    }

    public function test_confirmed_delete_clears_usages_removes_the_file_and_audits_them(): void
    {
        $this->as('super-admin');
        $this->upload(UploadedFile::fake()->image('used.jpg', 10, 10), ['alt_text' => 'x']);
        $media = Media::sole();
        $post = BlogPost::factory()->create(['featured_image_id' => $media->id]);
        $setting = SiteSetting::factory()->media()->create(['media_id' => $media->id]);
        $page = Page::factory()->create(['structured_content' => [
            ['type' => 'hero', 'data' => ['heading' => 'Hi', 'background_media_id' => $media->id]],
            ['type' => 'feature', 'data' => ['background_media_id' => (string) $media->id]],
        ]]);

        $this->delete(route('admin.media.destroy', $media), ['confirm' => '1'])
            ->assertRedirect(route('admin.media.index'))
            ->assertSessionHas('status', 'Deleted "used.jpg" and removed it from 4 places.');

        $this->assertModelMissing($media);
        Storage::disk('public')->assertMissing($media->storage_path);
        $this->assertNull($post->fresh()->featured_image_id);
        $this->assertNull($setting->fresh()->media_id);
        $content = $page->fresh()->structured_content;
        $this->assertNull($content[0]['data']['background_media_id']);
        $this->assertSame('Hi', $content[0]['data']['heading']);
        $this->assertNull($content[1]['data']['background_media_id']);

        $log = AuditLog::firstWhere('action', 'deleted');
        $this->assertSame('media', $log->entity_type);
        $this->assertSame('used.jpg', $log->old_values['file_name']);
        $this->assertEqualsCanonicalizing(['blog_post', 'site_setting', 'page', 'page'], array_column($log->old_values['cleared_usages'], 'type'));
    }

    // Access per role (plan §6 rows 23–26) ---------------------------------

    public function test_author_uploads_and_manages_only_own_files(): void
    {
        $author = $this->as('author');
        $own = Media::factory()->create(['uploader_id' => $author->id]);
        $other = Media::factory()->create();

        $this->get(route('admin.media.index'))->assertOk()->assertSee('Upload a file');
        $this->get(route('admin.media.edit', $own))->assertOk();
        $this->get(route('admin.media.delete', $own))->assertOk();
        $this->get(route('admin.media.edit', $other))->assertForbidden();
        $this->put(route('admin.media.update', $other), ['alt_text' => 'x'])->assertForbidden();
        $this->get(route('admin.media.delete', $other))->assertForbidden();
        $this->delete(route('admin.media.destroy', $other), ['confirm' => '1'])->assertForbidden();
        $this->assertModelExists($other);

        $this->delete(route('admin.media.destroy', $own), ['confirm' => '1'])->assertRedirect();
        $this->assertModelMissing($own);
    }

    public function test_author_does_not_see_edit_links_for_other_peoples_files(): void
    {
        $author = $this->as('author');
        $other = Media::factory()->create();

        $this->get(route('admin.media.index'))->assertDontSee(route('admin.media.edit', $other), false);
    }

    public function test_editor_and_administrators_manage_any_file(): void
    {
        foreach (['editor', 'administrator', 'super-admin'] as $role) {
            $this->as($role);
            $media = Media::factory()->create();

            $this->get(route('admin.media.edit', $media))->assertOk();
            $this->delete(route('admin.media.destroy', $media), ['confirm' => '1'])->assertRedirect();
            $this->assertModelMissing($media);
        }
    }

    public function test_user_without_media_permissions_is_refused(): void
    {
        $role = \App\Models\Role::factory()->create(['slug' => 'no-media']);
        $this->as('no-media');
        $media = Media::factory()->create();

        $this->get(route('admin.media.index'))->assertForbidden();
        $this->upload(UploadedFile::fake()->image('a.jpg'), ['alt_text' => 'x'])->assertForbidden();
        $this->get(route('admin.media.edit', $media))->assertForbidden();
        $this->assertDatabaseCount('media', 1);
    }
}
