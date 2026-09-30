<?php

namespace Tests\Feature\Admin;

use App\Http\Controllers\Admin\TestimonialController;
use App\Models\AuditLog;
use App\Models\Media;
use App\Models\Testimonial;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class TestimonialAdminTest extends TestCase
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
     * @return list<string>
     */
    private function publicAuthors(): array
    {
        $this->app->forgetScopedInstances();

        return array_column($this->get(route('home'))->assertOk()->viewData('testimonials'), 'author_name');
    }

    // Create -----------------------------------------------------------------

    public function test_admin_creates_a_testimonial_at_the_end_with_audit(): void
    {
        $admin = $this->as('administrator');
        Testimonial::factory()->create(['sort_order' => 4]);
        $photo = Media::factory()->create();

        $response = $this->post(route('admin.testimonials.store'), [
            'quote' => 'They transformed our governance.',
            'author_name' => 'Jamie Rivera',
            'author_title' => 'COO',
            'organization' => 'Acme Inc',
            'photo_id' => (string) $photo->id,
            'is_active' => '1',
        ]);

        $testimonial = Testimonial::firstWhere('author_name', 'Jamie Rivera');
        $response->assertRedirect(route('admin.testimonials.edit', $testimonial))->assertSessionHasNoErrors();
        $this->assertSame(5, $testimonial->sort_order);
        $this->assertSame($photo->id, $testimonial->photo_id);
        $this->assertTrue($testimonial->is_active);

        $log = AuditLog::firstWhere('action', 'created');
        $this->assertSame([$admin->id, 'testimonial', $testimonial->id], [$log->user_id, $log->entity_type, $log->entity_id]);
        $this->assertSame('Jamie Rivera', $log->new_values['author_name']);
    }

    /**
     * @return array<string, array{array<string, mixed>, string}>
     */
    public static function invalidInput(): array
    {
        return [
            'quote missing' => [['quote' => ''], 'quote'],
            'quote too long' => [['quote' => str_repeat('a', 2001)], 'quote'],
            'author name missing' => [['author_name' => ''], 'author_name'],
            'author name too long' => [['author_name' => str_repeat('a', 256)], 'author_name'],
            'photo missing' => [['photo_id' => '999999'], 'photo_id'],
            'photo is a pdf' => [['photo_id' => 'pdf'], 'photo_id'],
        ];
    }

    #[DataProvider('invalidInput')]
    public function test_invalid_input_is_rejected(array $input, string $field): void
    {
        $this->as('administrator');
        if (($input['photo_id'] ?? null) === 'pdf') {
            $input['photo_id'] = (string) Media::factory()->pdf()->create()->id;
        }

        $this->from(route('admin.testimonials.create'))
            ->post(route('admin.testimonials.store'), $input + ['quote' => 'Valid quote', 'author_name' => 'Valid Name'])
            ->assertRedirect(route('admin.testimonials.create'))
            ->assertSessionHasErrors($field);

        $this->assertSame(0, Testimonial::count());
        $this->assertSame(0, AuditLog::count());
    }

    // Update -------------------------------------------------------------------

    public function test_update_records_only_the_changed_fields(): void
    {
        $this->as('administrator');
        $testimonial = Testimonial::factory()->create([
            'author_name' => 'Old Name', 'quote' => 'Same quote', 'author_title' => null, 'organization' => null,
        ]);

        $this->put(route('admin.testimonials.update', $testimonial), [
            'quote' => 'Same quote', 'author_name' => 'New Name', 'is_active' => '1',
        ])->assertRedirect(route('admin.testimonials.edit', $testimonial));

        $log = AuditLog::firstWhere('action', 'updated');
        $this->assertSame(['author_name' => 'Old Name'], $log->old_values);
        $this->assertSame(['author_name' => 'New Name'], $log->new_values);
    }

    public function test_editor_edits_content_but_not_status_and_cannot_create_delete_or_reorder(): void
    {
        $this->as('editor');
        $testimonial = Testimonial::factory()->create(['author_name' => 'Old', 'is_active' => true]);

        $this->get(route('admin.testimonials.edit', $testimonial))->assertOk()
            ->assertDontSee('id="field-is_active"', false)
            ->assertSee('Only administrators can change this.');

        // A crafted is_active is ignored for Editors.
        $this->put(route('admin.testimonials.update', $testimonial), ['quote' => 'x', 'author_name' => 'Edited', 'is_active' => '0'])
            ->assertSessionHasNoErrors();
        $this->assertSame('Edited', $testimonial->fresh()->author_name);
        $this->assertTrue($testimonial->fresh()->is_active);

        $this->get(route('admin.testimonials.create'))->assertForbidden();
        $this->post(route('admin.testimonials.store'), ['quote' => 'x', 'author_name' => 'X'])->assertForbidden();
        $this->post(route('admin.testimonials.move', $testimonial), ['direction' => 'up'])->assertForbidden();
        $this->delete(route('admin.testimonials.destroy', $testimonial), ['confirm' => '1'])->assertForbidden();
        $this->assertModelExists($testimonial);
    }

    public function test_author_cannot_reach_testimonials(): void
    {
        $this->as('author');
        $testimonial = Testimonial::factory()->create();

        $this->get(route('admin.testimonials.index'))->assertForbidden();
        $this->get(route('admin.testimonials.edit', $testimonial))->assertForbidden();
        $this->get('/admin')->assertDontSee(route('admin.testimonials.index'), false);
    }

    // Reorder and delete ---------------------------------------------------

    public function test_move_reorders_and_audits(): void
    {
        $this->as('administrator');
        $a = Testimonial::factory()->create(['author_name' => 'A', 'sort_order' => 1]);
        $b = Testimonial::factory()->create(['author_name' => 'B', 'sort_order' => 2]);

        $this->post(route('admin.testimonials.move', $b), ['direction' => 'up'])->assertRedirect();

        $this->assertSame(['B', 'A'], Testimonial::query()->ordered()->pluck('author_name')->all());
        $this->assertSame('reordered', AuditLog::sole()->action);
    }

    public function test_delete_needs_confirmation_keeps_the_photo_and_audits(): void
    {
        $this->as('administrator');
        $photo = Media::factory()->create();
        $testimonial = Testimonial::factory()->create(['author_name' => 'Temporary', 'photo_id' => $photo->id]);

        $this->get(route('admin.testimonials.delete', $testimonial))->assertOk()->assertSee('Its photo stays in the media library.');

        $this->delete(route('admin.testimonials.destroy', $testimonial))->assertSessionHasErrors('confirm');
        $this->assertModelExists($testimonial);

        $this->delete(route('admin.testimonials.destroy', $testimonial), ['confirm' => '1'])
            ->assertRedirect(route('admin.testimonials.index'));

        $this->assertModelMissing($testimonial);
        $this->assertModelExists($photo);
    }

    // List -----------------------------------------------------------------

    public function test_list_is_searchable_paginated_escaped_and_free_of_n_plus_one(): void
    {
        $this->as('administrator');
        Testimonial::factory()->withPhoto()->create(['author_name' => '<script>alert(1)</script> Jordan']);
        Testimonial::factory()->withPhoto()->count(3)->create();

        $this->get(route('admin.testimonials.index', ['q' => 'Jordan']))->assertOk()
            ->assertViewHas('testimonials', fn ($p) => $p->total() === 1)
            ->assertDontSee('<script>alert(1)</script>', false)
            ->assertSee('&lt;script&gt;alert(1)&lt;/script&gt; Jordan', false);

        $queries = function () {
            $count = 0;
            DB::listen(function () use (&$count) {
                $count++;
            });
            $this->get(route('admin.testimonials.index'))->assertOk();
            DB::getEventDispatcher()->forget('Illuminate\Database\Events\QueryExecuted');

            return $count;
        };
        $few = $queries();
        Testimonial::factory()->withPhoto()->count(10)->create();
        $this->assertSame($few, $queries());

        Testimonial::factory()->count(TestimonialController::PER_PAGE)->create();
        $this->get(route('admin.testimonials.index'))->assertViewHas('testimonials', fn ($p) => $p->count() === TestimonialController::PER_PAGE);
    }

    // Public site ----------------------------------------------------------

    public function test_public_home_page_reflects_admin_changes(): void
    {
        $this->as('administrator');
        $this->post(route('admin.testimonials.store'), ['quote' => 'Great work.', 'author_name' => 'Excellence Co', 'is_active' => '1']);
        $this->post(route('admin.testimonials.store'), ['quote' => 'Superb.', 'author_name' => 'Integrity LLC', 'is_active' => '1']);
        $this->assertSame(['Excellence Co', 'Integrity LLC'], $this->publicAuthors());

        $second = Testimonial::firstWhere('author_name', 'Integrity LLC');
        $this->put(route('admin.testimonials.update', $second), ['quote' => 'Superb.', 'author_name' => 'Integrity LLC', 'is_active' => '0']);
        $this->assertSame(['Excellence Co'], $this->publicAuthors());
    }

    public function test_the_home_page_hides_the_section_entirely_when_there_are_no_active_testimonials(): void
    {
        Testimonial::factory()->inactive()->create();

        $this->app->forgetScopedInstances();
        $this->get(route('home'))->assertOk()->assertDontSee('What our clients say');
    }
}
