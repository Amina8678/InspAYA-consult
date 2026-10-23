<?php

namespace Tests\Feature\Admin;

use App\Models\BlogPost;
use App\Models\Media;
use App\Models\Page;
use App\Models\PendingRevision;
use App\Models\Tag;
use App\Models\User;
use App\Support\ContentApproval;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * The `version` counter on pages and blog posts: every kind of write bumps it
 * exactly where it should, and nothing that writes these tables is missed.
 */
class RecordVersionTest extends TestCase
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

    // The counter itself ---------------------------------------------------------

    public function test_new_records_start_at_version_1(): void
    {
        $this->assertSame(1, Page::factory()->create()->version);
        $this->assertSame(1, BlogPost::factory()->create()->version);
        $this->assertSame(1, Page::create(['title' => 'T', 'slug' => 't'])->fresh()->version);
    }

    public function test_the_demo_seed_works_and_everything_it_creates_is_version_1(): void
    {
        $this->seed(DatabaseSeeder::class);

        $this->assertGreaterThan(0, Page::count());
        $this->assertGreaterThan(0, BlogPost::count());
        $this->assertSame(0, Page::where('version', '!=', 1)->count());
        $this->assertSame(0, BlogPost::where('version', '!=', 1)->count());
    }

    public function test_each_real_change_adds_one_and_a_save_that_changes_nothing_adds_nothing(): void
    {
        $page = Page::factory()->create(['title' => 'A']);

        $page->update(['title' => 'B']);
        $this->assertSame(2, $page->version, 'The instance shows the stored value, not an expression.');
        $this->assertSame(2, $page->fresh()->version);

        $page->title = 'B';
        $page->save();
        $page->update([]);
        $this->assertSame(2, $page->fresh()->version);

        $page->title = 'C';
        $page->save();
        $this->assertSame(3, $page->fresh()->version);
    }

    public function test_two_instances_loaded_at_the_same_version_both_count(): void
    {
        $page = Page::factory()->create();
        $a = Page::find($page->id);
        $b = Page::find($page->id);

        $a->update(['title' => 'From A']);
        $b->update(['meta_title' => 'From B']);

        $this->assertSame(3, Page::find($page->id)->version, 'An atomic increment, not a read-then-write that loses one.');
    }

    public function test_bump_version_counts_a_change_the_row_itself_does_not_show(): void
    {
        $post = BlogPost::factory()->create();

        $post->bumpVersion();

        $this->assertSame(2, $post->version);
        $this->assertSame(2, $post->fresh()->version);
        $this->assertFalse($post->isDirty());
    }

    // Every write path -------------------------------------------------------------

    public function test_the_page_and_post_admin_saves_bump_the_version(): void
    {
        $page = Page::factory()->published()->create(['slug' => 'about']);
        $post = BlogPost::factory()->published()->create();
        $this->as('administrator');

        $this->put(route('admin.pages.update', $page), ['title' => 'New title', 'version' => 1, 'slug' => '', 'meta_title' => '', 'meta_description' => '', 'canonical_url' => '', 'og_image_id' => '', 'sections' => [['type' => 'text', 'heading' => 'H', 'body' => 'B']]])->assertSessionHasNoErrors();
        $this->put(route('admin.posts.update', $post), ['title' => 'New post title', 'version' => 1, 'slug' => '', 'excerpt' => 'x', 'content' => 'y', 'category_id' => '', 'meta_title' => '', 'meta_description' => '', 'canonical_url' => '', 'featured_image_id' => '', 'published_at' => ''])->assertSessionHasNoErrors();

        $this->assertSame(2, $page->fresh()->version);
        $this->assertSame(2, $post->fresh()->version);
    }

    public function test_a_tag_only_change_to_a_post_bumps_the_version(): void
    {
        $post = BlogPost::factory()->published()->create(['title' => 'Same', 'slug' => 'same', 'excerpt' => 'x', 'content' => 'y']);
        $tag = Tag::factory()->create();
        $this->as('administrator');

        $this->put(route('admin.posts.update', $post), [
            'title' => 'Same', 'version' => 1, 'slug' => '', 'excerpt' => 'x', 'content' => 'y', 'category_id' => '', 'meta_title' => '',
            'meta_description' => '', 'canonical_url' => '', 'featured_image_id' => '', 'published_at' => '', 'tags' => [$tag->id],
        ])->assertSessionHasNoErrors();

        $this->assertSame([$tag->id], $post->tags()->pluck('tags.id')->all());
        $this->assertSame(2, $post->fresh()->version);
    }

    public function test_deleting_a_media_file_that_a_page_or_post_uses_bumps_their_version(): void
    {
        $media = Media::factory()->create();
        $page = Page::factory()->published()->create(['og_image_id' => $media->id]);
        $post = BlogPost::factory()->published()->create(['featured_image_id' => $media->id]);
        $other = Page::factory()->published()->create();
        $this->as('administrator');

        $this->delete(route('admin.media.destroy', $media), ['confirm' => '1'])->assertSessionHasNoErrors();

        $this->assertNull($page->fresh()->og_image_id);
        $this->assertSame(2, $page->fresh()->version);
        $this->assertSame(2, $post->fresh()->version);
        $this->assertSame(1, $other->fresh()->version, 'Unrelated records are untouched.');
    }

    public function test_approving_a_revision_bumps_the_version_including_a_tags_only_one(): void
    {
        $post = BlogPost::factory()->published()->create();
        $tag = Tag::factory()->create();
        $editor = User::factory()->withRole('editor')->create();
        $admin = User::factory()->withRole('administrator')->create();
        $approval = app(ContentApproval::class);

        $approval->submit($post, $editor, ['title' => 'Renamed']);
        $approval->apply(PendingRevision::sole(), $admin);
        $this->assertSame(2, $post->fresh()->version);

        $approval->submit($post->fresh(), $editor, ['tags' => [$tag->id]]);
        $approval->apply(PendingRevision::where('status', 'pending')->sole(), $admin);
        $this->assertSame(3, $post->fresh()->version);
    }

    public function test_nothing_in_the_app_writes_pages_or_posts_around_the_model(): void
    {
        // Query-builder writes skip model events and so skip the version
        // counter. This lists every one in app/ and database/seeders; each
        // must either be allow-listed here (and bump the version itself) or
        // not exist.
        $allowed = ['app/Services/Media/MediaUsage.php'];
        $found = [];

        $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(base_path('app')));
        $files = array_merge(iterator_to_array($files), iterator_to_array(new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(base_path('database/seeders')))));
        foreach ($files as $file) {
            if (! str_ends_with((string) $file, '.php')) {
                continue;
            }
            $code = file_get_contents((string) $file);
            $relative = str_replace(base_path().'/', '', (string) $file);

            if (preg_match("/DB::table\\(['\"](pages|blog_posts)['\"]\\)/", $code)
                || preg_match('/\\b(Page|BlogPost)::[^;]*->(update|delete|increment|decrement|upsert|insert|insertOrIgnore|truncate)\\(/s', $code)
                || preg_match('/->(saveQuietly|updateQuietly|withoutEvents|timestamps\\(false\\))/', $code)
                || str_contains($code, 'withoutEvents')) {
                $found[] = $relative;
            }
        }

        $this->assertEqualsCanonicalizing($allowed, array_values(array_unique($found)), 'A new write path to pages or blog_posts skips the version counter: bump it, or use the model.');
        $this->assertStringContainsString("'version' => DB::raw('version + 1')", file_get_contents(base_path($allowed[0])));
    }

    // Migrations ---------------------------------------------------------------------

    public function test_the_version_migration_is_safe_on_tables_that_already_have_rows(): void
    {
        $migration = require database_path('migrations/2026_10_02_000001_add_version_to_pages_and_blog_posts.php');
        $migration->down();
        $this->assertFalse(Schema::hasColumn('pages', 'version'));

        $author = User::factory()->withRole('administrator')->create();
        DB::table('pages')->insert(['title' => 'Old page', 'slug' => 'old-page', 'status' => 'published', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('blog_posts')->insert(['title' => 'Old post', 'slug' => 'old-post', 'excerpt' => 'x', 'content' => 'y', 'status' => 'published', 'author_id' => $author->id, 'created_at' => now(), 'updated_at' => now()]);

        $migration->up();

        $this->assertSame(1, Page::where('slug', 'old-page')->sole()->version);
        $this->assertSame(1, BlogPost::where('slug', 'old-post')->sole()->version);
        Page::where('slug', 'old-page')->sole()->update(['title' => 'Renamed']);
        $this->assertSame(2, Page::where('slug', 'old-page')->sole()->version);
    }

    public function test_the_baseline_migration_keeps_existing_revisions_and_leaves_them_never_stale(): void
    {
        $migration = require database_path('migrations/2026_10_02_000002_replace_base_updated_at_with_base_version.php');
        $migration->down();

        $page = Page::factory()->published()->create(['title' => 'Live']);
        $editor = User::factory()->withRole('editor')->create();
        DB::table('pending_revisions')->insert([
            'revisable_type' => 'page', 'revisable_id' => $page->id, 'submitted_by' => $editor->id,
            'changes' => json_encode(['title' => 'Proposed']), 'base_updated_at' => now()->subDay(), 'status' => 'pending',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $migration->up();

        $this->assertFalse(Schema::hasColumn('pending_revisions', 'base_updated_at'));
        $revision = PendingRevision::sole();
        $this->assertNull($revision->base_version);
        $this->assertSame(['title' => 'Proposed'], $revision->changes);

        $page->update(['title' => 'Edited since']);
        $this->assertFalse($revision->fresh()->liveRecordChanged());
        app(ContentApproval::class)->apply($revision, User::factory()->withRole('administrator')->create());
        $this->assertSame('Proposed', $page->fresh()->title);
    }
}
