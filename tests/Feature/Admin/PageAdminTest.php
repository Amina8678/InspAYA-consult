<?php

namespace Tests\Feature\Admin;

use App\Enums\PageStatus;
use App\Models\AuditLog;
use App\Models\Media;
use App\Models\Page;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class PageAdminTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    private function as(string $role): User
    {
        $user = User::factory()->withRole($role)->create();
        $this->actingAs($user);

        return $user;
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return $overrides + [
            'title' => 'Our approach',
            'slug' => '',
            'meta_title' => '',
            'meta_description' => '',
            'canonical_url' => '',
            'og_image_id' => '',
            'sections' => [
                ['type' => 'text', 'heading' => 'How we work', 'body' => "First paragraph.\n\nSecond paragraph."],
            ],
        ];
    }

    private function publicPage(string $route)
    {
        $this->app->forgetScopedInstances();

        return $this->get(route($route));
    }

    // Round trip -----------------------------------------------------------

    public function test_a_page_built_in_the_editor_appears_on_the_public_site(): void
    {
        $this->as('administrator');
        $image = Media::factory()->create(['alt_text' => 'Team at work']);
        $home = Page::factory()->draft()->create(['slug' => 'home', 'title' => 'Home', 'structured_content' => []]);

        $this->put(route('admin.pages.update', $home), [
            'title' => 'Home',
            'slug' => 'home',
            'status' => 'published',
            'sections' => [
                ['type' => 'hero', 'heading' => 'Advice you can act on', 'body' => 'Multidisciplinary consulting.',
                    'primary_cta_label' => 'Talk to us', 'primary_cta_url' => '/contact',
                    'secondary_cta_label' => 'Email us', 'secondary_cta_url' => 'mailto:hello@example.com',
                    'background_media_id' => (string) $image->id],
                ['type' => 'intro', 'heading' => '', 'body' => 'We help organisations decide.'],
                ['type' => 'feature', 'heading' => 'Why choose us', 'body' => 'Depth and breadth.', 'background_media_id' => (string) $image->id],
            ],
        ])->assertSessionHasNoErrors()->assertSessionHas('status', 'Published "Home".');

        $html = $this->publicPage('home')->assertOk()->getContent();

        $this->assertMatchesRegularExpression('~<h1 id="hero-heading" class="hero__title">Advice you can act on</h1>~', $html);
        $this->assertStringContainsString('href="/contact">Talk to us</a>', $html);
        $this->assertStringContainsString('href="mailto:hello@example.com">Email us</a>', $html);
        $this->assertStringContainsString('We help organisations decide.', $html);
        $this->assertStringContainsString('<h2>Why choose us</h2>', $html);
        $this->assertStringContainsString($image->storage_path, $html);

        $stored = $home->fresh()->structured_content;
        // assertEquals: MySQL's JSON type reorders object keys.
        $this->assertEquals(['label' => 'Talk to us', 'url' => '/contact'], $stored[0]['data']['primary_cta']);
        $this->assertSame($image->id, $stored[0]['data']['background_media_id']);
        $this->assertSame(['body' => 'We help organisations decide.'], $stored[1]['data']);
    }

    public function test_an_about_page_built_in_the_editor_renders_text_sections_with_line_breaks(): void
    {
        $this->as('administrator');
        $about = Page::factory()->draft()->create(['slug' => 'about', 'title' => 'About']);

        $this->put(route('admin.pages.update', $about), $this->payload(['title' => 'About us', 'slug' => 'about', 'status' => 'published']))
            ->assertSessionHasNoErrors();

        $this->publicPage('about')->assertOk()
            ->assertSee('<h1>About us</h1>', false)
            ->assertSee('<h2>How we work</h2>', false)
            ->assertSee("First paragraph.\n\nSecond paragraph.", false);
    }

    // Section editor without JavaScript ------------------------------------

    public function test_add_move_and_remove_rebuild_the_form_without_saving(): void
    {
        $this->as('administrator');
        $page = Page::factory()->create(['title' => 'Stored', 'structured_content' => []]);
        $form = fn (array $extra) => $this->from(route('admin.pages.edit', $page))
            ->put(route('admin.pages.update', $page), $extra + ['title' => 'Unsaved title', 'slug' => $page->slug]);

        $form(['section_action' => 'add', 'new_section_type' => 'hero', 'sections' => [['type' => 'text', 'body' => 'Kept']]])
            ->assertRedirect(route('admin.pages.edit', $page))
            ->assertSessionHas('status', 'Hero banner section added at the end. Fill it in, then save.');

        $this->assertSame('Stored', $page->fresh()->title, 'Nothing is saved by a section action.');
        $this->assertSame([], $page->fresh()->structured_content);

        // The rebuilt form shows the unsaved input with the new section.
        $html = $this->get(route('admin.pages.edit', $page))->getContent();
        $this->assertStringContainsString('value="Unsaved title"', $html);
        $this->assertStringContainsString('Section 1: Text', $html);
        $this->assertStringContainsString('Section 2: Hero banner', $html);
        $this->assertStringContainsString('>Kept</textarea>', $html);

        $sections = [['type' => 'text', 'body' => 'A'], ['type' => 'intro', 'body' => 'B']];
        $form(['section_action' => 'down:0', 'sections' => $sections]);
        $this->assertSame(['intro', 'text'], array_column(session()->getOldInput('sections'), 'type'));

        $form(['section_action' => 'remove:1', 'sections' => $sections]);
        $this->assertSame([['type' => 'text', 'body' => 'A']], session()->getOldInput('sections'));
    }

    public function test_the_first_submit_button_is_save_so_enter_never_moves_a_section(): void
    {
        $this->as('administrator');
        $page = Page::factory()->create(['structured_content' => [['type' => 'text', 'data' => ['body' => 'x']]]]);

        $html = $this->get(route('admin.pages.edit', $page))->getContent();

        // The page editor form (not the header's sign-out form), up to its first button.
        $action = preg_quote(route('admin.pages.update', $page), '~');
        $this->assertMatchesRegularExpression('~<form method="POST" action="'.$action.'">.*?<button (?![^>]*section_action)[^>]*>Save changes</button>~s', $html);
        preg_match('~<form method="POST" action="'.$action.'">.*?(<button[^>]*>)~s', $html, $m);
        $this->assertStringNotContainsString('section_action', $m[1]);
    }

    // Validation -----------------------------------------------------------

    /**
     * @return array<string, array{list<array<string, mixed>>, string}>
     */
    public static function invalidSections(): array
    {
        return [
            'unknown type' => [[['type' => 'gallery', 'body' => 'x']], 'sections.0.type'],
            'unknown key' => [[['type' => 'text', 'body' => 'x', 'onclick' => 'alert(1)']], 'sections.0.onclick'],
            'image on a text section' => [[['type' => 'text', 'body' => 'x', 'background_media_id' => '1']], 'sections.0.background_media_id'],
            'button on an intro section' => [[['type' => 'intro', 'body' => 'x', 'primary_cta_url' => '/a', 'primary_cta_label' => 'A']], 'sections.0.primary_cta_url'],
            'javascript button' => [[['type' => 'hero', 'heading' => 'x', 'primary_cta_label' => 'Go', 'primary_cta_url' => 'javascript:alert(1)']], 'sections.0.primary_cta_url'],
            'button without link' => [[['type' => 'hero', 'heading' => 'x', 'primary_cta_label' => 'Go', 'primary_cta_url' => '']], 'sections.0.primary_cta_url'],
            'link without label' => [[['type' => 'feature', 'heading' => 'x', 'secondary_cta_label' => '', 'secondary_cta_url' => '/a']], 'sections.0.secondary_cta_label'],
            'html in heading' => [[['type' => 'text', 'heading' => '<b>Bold</b>', 'body' => 'x']], 'sections.0.heading'],
            'script in body' => [[['type' => 'text', 'body' => '<script>alert(1)</script>']], 'sections.0.body'],
            'heading too long' => [[['type' => 'text', 'heading' => str_repeat('a', 201)]], 'sections.0.heading'],
            'hero text over its limit' => [[['type' => 'hero', 'body' => str_repeat('a', 1001)]], 'sections.0.body'],
            'image missing' => [[['type' => 'feature', 'heading' => 'x', 'background_media_id' => '999999']], 'sections.0.background_media_id'],
            'empty section' => [[['type' => 'text', 'heading' => '', 'body' => '']], 'sections.0.type'],
        ];
    }

    #[DataProvider('invalidSections')]
    public function test_invalid_sections_are_rejected_and_nothing_is_saved(array $sections, string $field): void
    {
        $this->as('administrator');

        $this->from(route('admin.pages.create'))
            ->post(route('admin.pages.store'), $this->payload(['sections' => $sections]))
            ->assertRedirect(route('admin.pages.create'))
            ->assertSessionHasErrors($field);

        $this->assertSame(0, Page::count());
    }

    public function test_page_level_fields_are_validated(): void
    {
        $this->as('administrator');
        $pdf = Media::factory()->pdf()->create();

        $this->post(route('admin.pages.store'), $this->payload([
            'title' => '<em>x</em>',
            'slug' => 'Not A Slug',
            'canonical_url' => '/relative',
            'og_image_id' => (string) $pdf->id,
            'status' => 'scheduled',
            'sections' => array_fill(0, 31, ['type' => 'text', 'body' => 'x']),
        ]))->assertSessionHasErrors(['title', 'slug', 'canonical_url', 'og_image_id', 'status', 'sections']);
    }

    // Publishing and permissions -------------------------------------------

    public function test_publish_and_unpublish_are_audited(): void
    {
        $admin = $this->as('administrator');
        $page = Page::factory()->draft()->create(['slug' => 'about', 'title' => 'About']);
        $data = $this->payload(['title' => 'About', 'slug' => 'about']);

        $this->put(route('admin.pages.update', $page), $data + ['status' => 'published']);
        $this->assertSame(PageStatus::Published, $page->fresh()->status);
        $this->assertNotNull($page->fresh()->published_at);
        $this->publicPage('about')->assertOk();

        $this->put(route('admin.pages.update', $page), $data + ['status' => 'draft'])
            ->assertSessionHas('status', 'Unpublished "About". It is now a draft.');
        $this->publicPage('about')->assertNotFound();

        $this->assertSame(['published', 'unpublished'], AuditLog::whereIn('action', ['published', 'unpublished'])->orderBy('id')->pluck('action')->all());
        $this->assertSame($admin->id, AuditLog::firstWhere('action', 'unpublished')->user_id);
        $this->assertSame(2, AuditLog::where('action', 'updated')->count());
    }

    public function test_editor_saves_drafts_and_edits_content_but_cannot_publish_or_unpublish(): void
    {
        $this->as('editor');
        $draft = Page::factory()->draft()->create();
        $live = Page::factory()->published()->create(['slug' => 'about']);

        $this->get(route('admin.pages.edit', $draft))->assertOk()
            ->assertDontSee('id="field-status"', false)
            ->assertSee('Publishing and unpublishing need an administrator.');

        $this->put(route('admin.pages.update', $draft), $this->payload(['slug' => $draft->slug, 'status' => 'published']))->assertSessionHasNoErrors();
        $this->assertSame(PageStatus::Draft, $draft->fresh()->status);
        $this->assertSame('Our approach', $draft->fresh()->title);

        $this->put(route('admin.pages.update', $live), $this->payload(['slug' => 'about', 'status' => 'draft']))->assertSessionHasNoErrors();
        $this->assertSame(PageStatus::Published, $live->fresh()->status);

        $this->assertSame(0, AuditLog::whereIn('action', ['published', 'unpublished'])->count());
        $this->get(route('admin.pages.create'))->assertForbidden();
        $this->delete(route('admin.pages.destroy', $draft), ['confirm' => '1'])->assertForbidden();
    }

    public function test_author_cannot_reach_pages(): void
    {
        $this->as('author');

        $this->get(route('admin.pages.index'))->assertForbidden();
        $this->get('/admin')->assertDontSee(route('admin.pages.index'), false);
    }

    // Slugs and core pages -------------------------------------------------

    public function test_core_page_slugs_are_fixed_and_core_pages_cannot_be_deleted(): void
    {
        $this->as('super-admin');

        foreach (['home', 'about', 'privacy-policy', 'terms-of-service'] as $slug) {
            $page = Page::factory()->create(['slug' => $slug]);

            $this->put(route('admin.pages.update', $page), $this->payload(['slug' => $slug.'-new']))->assertSessionHasErrors('slug');
            $this->assertSame($slug, $page->fresh()->slug);

            $this->get(route('admin.pages.delete', $page))->assertForbidden();
            $this->delete(route('admin.pages.destroy', $page), ['confirm' => '1'])->assertForbidden();
            $this->assertModelExists($page);
            $this->get(route('admin.pages.index'))->assertDontSee(route('admin.pages.delete', $page), false);
        }

        // The contact page's slug is fixed too, but it may be deleted.
        $contact = Page::factory()->create(['slug' => 'contact']);
        $this->put(route('admin.pages.update', $contact), $this->payload(['slug' => 'get-in-touch']))->assertSessionHasErrors('slug');
        $this->delete(route('admin.pages.destroy', $contact), ['confirm' => '1'])->assertRedirect(route('admin.pages.index'));
        $this->assertModelMissing($contact);
        $this->get(route('contact'))->assertOk();
        $this->assertSame('contact', AuditLog::firstWhere('action', 'deleted')->old_values['slug']);
    }

    public function test_changing_the_slug_of_a_published_page_needs_confirmation(): void
    {
        $this->as('administrator');
        $page = Page::factory()->published()->create(['slug' => 'careers']);
        $data = $this->payload(['slug' => 'jobs']);

        $this->put(route('admin.pages.update', $page), $data)->assertSessionHasErrors('confirm_slug_change');
        $this->assertSame('careers', $page->fresh()->slug);

        $this->put(route('admin.pages.update', $page), $data + ['confirm_slug_change' => '1'])->assertSessionHasNoErrors();
        $this->assertSame('jobs', $page->fresh()->slug);
    }

    // Robustness -----------------------------------------------------------

    public function test_a_page_whose_section_image_was_deleted_still_renders(): void
    {
        $this->as('super-admin');
        Storage::fake('public');
        $image = Media::factory()->create();
        $about = Page::factory()->published()->create(['slug' => 'about', 'structured_content' => [
            ['type' => 'feature', 'data' => ['heading' => 'Still here', 'background_media_id' => $image->id]],
            ['type' => 'hero', 'data' => ['heading' => 'Missing', 'background_media_id' => 999999]],
        ]]);

        $this->delete(route('admin.media.destroy', $image), ['confirm' => '1'])->assertRedirect();

        $this->publicPage('about')->assertOk()->assertSee('Still here')->assertSee('Missing');
        $this->assertNull($about->fresh()->structured_content[0]['data']['background_media_id']);

        // The editor still opens and saves the page.
        $this->get(route('admin.pages.edit', $about))->assertOk();
    }

    public function test_a_draft_home_page_still_renders(): void
    {
        Page::factory()->draft()->create(['slug' => 'home', 'structured_content' => [
            ['type' => 'hero', 'data' => ['heading' => 'Draft heading']],
        ]]);

        $this->get(route('home'))->assertOk()->assertDontSee('Draft heading');
    }

    public function test_list_is_searchable_and_escaped(): void
    {
        $this->as('administrator');
        Page::factory()->create(['title' => '<script>alert(1)</script> Careers', 'slug' => 'careers']);
        Page::factory()->create(['title' => 'Other']);

        $this->get(route('admin.pages.index', ['q' => 'careers']))->assertOk()
            ->assertViewHas('pages', fn ($p) => $p->total() === 1)
            ->assertDontSee('<script>alert(1)</script>', false)
            ->assertSee('No public address yet');
    }
}
