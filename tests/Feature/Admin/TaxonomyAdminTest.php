<?php

namespace Tests\Feature\Admin;

use App\Models\BlogPost;
use App\Models\Category;
use App\Models\Tag;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Categories and tags (FR-BLOG-01, plan §6 row 21): CRUD needs
 * taxonomy.manage; anyone who writes posts can view the list to pick from.
 */
class TaxonomyAdminTest extends TestCase
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

    // Categories ---------------------------------------------------------

    public function test_a_category_is_created_with_a_slug_generated_from_its_name(): void
    {
        $this->as('administrator');

        $this->post(route('admin.categories.store'), ['name' => 'Energy Policy', 'slug' => '', 'description' => ''])
            ->assertSessionHasNoErrors();

        $category = Category::sole();
        $this->assertSame('Energy Policy', $category->name);
        $this->assertSame('energy-policy', $category->slug);
    }

    public function test_a_category_slug_must_be_unique(): void
    {
        $this->as('administrator');
        Category::factory()->create(['slug' => 'governance']);

        $this->post(route('admin.categories.store'), ['name' => 'Governance again', 'slug' => 'governance'])
            ->assertSessionHasErrors('slug');
        $this->assertSame(1, Category::count());
    }

    public function test_deleting_a_category_shows_how_many_posts_are_affected_and_uncategorises_them(): void
    {
        $this->as('administrator');
        $category = Category::factory()->create(['name' => 'Finance']);
        BlogPost::factory()->count(2)->create(['category_id' => $category->id]);

        $this->get(route('admin.categories.delete', $category))->assertOk()
            ->assertSee('2 posts')
            ->assertViewHas('postCount', 2);

        $this->delete(route('admin.categories.destroy', $category), ['confirm' => '1'])
            ->assertRedirect(route('admin.categories.index'));

        $this->assertModelMissing($category);
        $this->assertSame(2, BlogPost::whereNull('category_id')->count());
    }

    public function test_an_editor_manages_categories_but_an_author_can_only_view_them(): void
    {
        Category::factory()->create(['name' => 'Viewable']);

        $this->as('editor');
        $this->get(route('admin.categories.index'))->assertOk();
        $this->post(route('admin.categories.store'), ['name' => 'New', 'slug' => ''])->assertSessionHasNoErrors();

        $this->as('author');
        $this->get(route('admin.categories.index'))->assertOk()->assertSee('Viewable');
        $this->get(route('admin.categories.create'))->assertForbidden();
        $this->post(route('admin.categories.store'), ['name' => 'Nope', 'slug' => ''])->assertForbidden();
    }

    // Tags -----------------------------------------------------------------

    public function test_a_tag_is_created_with_a_slug_generated_from_its_name(): void
    {
        $this->as('administrator');

        $this->post(route('admin.tags.store'), ['name' => 'Digital Transformation', 'slug' => ''])
            ->assertSessionHasNoErrors();

        $tag = Tag::sole();
        $this->assertSame('digital-transformation', $tag->slug);
    }

    public function test_a_tag_slug_must_be_unique(): void
    {
        $this->as('administrator');
        Tag::factory()->create(['slug' => 'strategy']);

        $this->post(route('admin.tags.store'), ['name' => 'Strategy again', 'slug' => 'strategy'])
            ->assertSessionHasErrors('slug');
        $this->assertSame(1, Tag::count());
    }

    public function test_deleting_a_tag_shows_how_many_posts_are_affected_and_detaches_it(): void
    {
        $this->as('administrator');
        $tag = Tag::factory()->create(['name' => 'Leadership']);
        $posts = BlogPost::factory()->count(3)->create();
        foreach ($posts as $post) {
            $post->tags()->attach($tag);
        }

        $this->get(route('admin.tags.delete', $tag))->assertOk()
            ->assertSee('3 posts')
            ->assertViewHas('postCount', 3);

        $this->delete(route('admin.tags.destroy', $tag), ['confirm' => '1'])
            ->assertRedirect(route('admin.tags.index'));

        $this->assertModelMissing($tag);
        foreach ($posts as $post) {
            $this->assertSame(0, $post->tags()->count());
        }
    }

    public function test_an_author_cannot_manage_tags(): void
    {
        $tag = Tag::factory()->create();
        $this->as('author');

        $this->get(route('admin.tags.create'))->assertForbidden();
        $this->get(route('admin.tags.edit', $tag))->assertForbidden();
        $this->put(route('admin.tags.update', $tag), ['name' => 'Changed', 'slug' => ''])->assertForbidden();
        $this->get(route('admin.tags.delete', $tag))->assertForbidden();
        $this->delete(route('admin.tags.destroy', $tag), ['confirm' => '1'])->assertForbidden();
    }
}
