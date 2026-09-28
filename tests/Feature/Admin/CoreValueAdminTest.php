<?php

namespace Tests\Feature\Admin;

use App\Http\Controllers\Admin\CoreValueController;
use App\Models\AuditLog;
use App\Models\CoreValue;
use App\Models\Media;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class CoreValueAdminTest extends TestCase
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
    private function publicTitles(): array
    {
        $this->app->forgetScopedInstances();

        return array_column($this->get(route('core-values.index'))->assertOk()->viewData('coreValues'), 'title');
    }

    // Create ---------------------------------------------------------------

    public function test_admin_creates_a_value_at_the_end_with_a_generated_slug_and_audit(): void
    {
        $admin = $this->as('administrator');
        CoreValue::factory()->create(['sort_order' => 4]);
        $icon = Media::factory()->create();

        $response = $this->post(route('admin.core-values.store'), [
            'title' => 'Client First',
            'slug' => '',
            'description' => 'We put clients first.',
            'icon_id' => (string) $icon->id,
            'is_active' => '1',
        ]);

        $value = CoreValue::firstWhere('title', 'Client First');
        $response->assertRedirect(route('admin.core-values.edit', $value))->assertSessionHasNoErrors();
        $this->assertSame('client-first', $value->slug);
        $this->assertSame(5, $value->sort_order);
        $this->assertSame($icon->id, $value->icon_id);
        $this->assertTrue($value->is_active);

        $log = AuditLog::firstWhere('action', 'created');
        $this->assertSame([$admin->id, 'core_value', $value->id], [$log->user_id, $log->entity_type, $log->entity_id]);
        $this->assertSame('Client First', $log->new_values['title']);
    }

    public function test_a_value_can_be_created_hidden_and_a_duplicate_title_gets_a_unique_slug(): void
    {
        $this->as('super-admin');
        CoreValue::factory()->create(['title' => 'Integrity', 'slug' => 'integrity']);

        $this->post(route('admin.core-values.store'), ['title' => 'Integrity', 'is_active' => '0'])->assertSessionHasNoErrors();

        $copy = CoreValue::where('title', 'Integrity')->latest('id')->first();
        $this->assertSame('integrity-2', $copy->slug);
        $this->assertFalse($copy->is_active);
    }

    /**
     * @return array<string, array{array<string, mixed>, string}>
     */
    public static function invalidInput(): array
    {
        return [
            'title missing' => [['title' => ''], 'title'],
            'title too long' => [['title' => str_repeat('a', 256)], 'title'],
            'slug with capitals' => [['slug' => 'Client_First!'], 'slug'],
            'slug with double hyphen' => [['slug' => 'client--first'], 'slug'],
            'slug taken' => [['slug' => 'taken'], 'slug'],
            'description too long' => [['description' => str_repeat('a', 2001)], 'description'],
            'icon missing' => [['icon_id' => '999999'], 'icon_id'],
            'icon is a pdf' => [['icon_id' => 'pdf'], 'icon_id'],
        ];
    }

    #[DataProvider('invalidInput')]
    public function test_invalid_input_is_rejected(array $input, string $field): void
    {
        $this->as('administrator');
        CoreValue::factory()->create(['slug' => 'taken']);
        if (($input['icon_id'] ?? null) === 'pdf') {
            $input['icon_id'] = (string) Media::factory()->pdf()->create()->id;
        }

        $this->from(route('admin.core-values.create'))
            ->post(route('admin.core-values.store'), $input + ['title' => 'Valid title'])
            ->assertRedirect(route('admin.core-values.create'))
            ->assertSessionHasErrors($field);

        $this->assertSame(1, CoreValue::count());
        $this->assertSame(0, AuditLog::count());
    }

    // Update ---------------------------------------------------------------

    public function test_update_records_only_the_changed_fields(): void
    {
        $this->as('administrator');
        $value = CoreValue::factory()->create(['title' => 'Old', 'slug' => 'old', 'description' => 'Same']);

        $this->put(route('admin.core-values.update', $value), ['title' => 'New', 'slug' => 'old', 'description' => 'Same', 'is_active' => '1'])
            ->assertRedirect(route('admin.core-values.edit', $value));

        $log = AuditLog::firstWhere('action', 'updated');
        $this->assertSame(['title' => 'Old'], $log->old_values);
        $this->assertSame(['title' => 'New'], $log->new_values);
    }

    public function test_editor_edits_content_but_not_status_and_cannot_create_delete_or_reorder(): void
    {
        $this->as('editor');
        $value = CoreValue::factory()->create(['title' => 'Old', 'is_active' => true]);

        $this->get(route('admin.core-values.edit', $value))->assertOk()
            ->assertDontSee('id="field-is_active"', false)
            ->assertSee('Only administrators can change this.');

        // A crafted is_active is ignored for Editors.
        $this->put(route('admin.core-values.update', $value), ['title' => 'Edited', 'is_active' => '0'])->assertSessionHasNoErrors();
        $this->assertSame('Edited', $value->fresh()->title);
        $this->assertTrue($value->fresh()->is_active);

        $this->get(route('admin.core-values.create'))->assertForbidden();
        $this->post(route('admin.core-values.store'), ['title' => 'X'])->assertForbidden();
        $this->post(route('admin.core-values.move', $value), ['direction' => 'up'])->assertForbidden();
        $this->delete(route('admin.core-values.destroy', $value), ['confirm' => '1'])->assertForbidden();
        $this->assertModelExists($value);

        $this->get(route('admin.core-values.index'))->assertOk()
            ->assertDontSee(route('admin.core-values.create'), false)
            ->assertDontSee('Move up', false);
    }

    public function test_author_cannot_reach_core_values(): void
    {
        $this->as('author');
        $value = CoreValue::factory()->create();

        $this->get(route('admin.core-values.index'))->assertForbidden();
        $this->get(route('admin.core-values.edit', $value))->assertForbidden();
        $this->put(route('admin.core-values.update', $value), ['title' => 'X'])->assertForbidden();
        $this->get('/admin')->assertDontSee(route('admin.core-values.index'), false);
    }

    // Reorder and delete ---------------------------------------------------

    public function test_move_reorders_and_audits(): void
    {
        $this->as('administrator');
        $a = CoreValue::factory()->create(['title' => 'A', 'sort_order' => 1]);
        $b = CoreValue::factory()->create(['title' => 'B', 'sort_order' => 2]);

        $this->from(route('admin.core-values.index'))
            ->post(route('admin.core-values.move', $b), ['direction' => 'up'])
            ->assertRedirect(route('admin.core-values.index'))
            ->assertSessionHas('status', 'Moved "B" to position 1.');

        $this->assertSame(['B', 'A'], CoreValue::query()->ordered()->pluck('title')->all());
        $log = AuditLog::firstWhere('action', 'reordered');
        $this->assertSame([['sort_order' => 2], ['sort_order' => 1]], [$log->old_values, $log->new_values]);

        // Moving past the top changes nothing and writes no audit entry.
        $this->post(route('admin.core-values.move', $b), ['direction' => 'up']);
        $this->assertSame(1, AuditLog::where('action', 'reordered')->count());

        $this->post(route('admin.core-values.move', $a), ['direction' => 'sideways'])->assertSessionHasErrors('direction');
    }

    public function test_delete_needs_confirmation_keeps_the_icon_and_audits(): void
    {
        $this->as('administrator');
        $icon = Media::factory()->create();
        $value = CoreValue::factory()->create(['title' => 'Temporary', 'icon_id' => $icon->id]);

        $this->get(route('admin.core-values.delete', $value))->assertOk()->assertSee('Its icon stays in the media library.');

        $this->delete(route('admin.core-values.destroy', $value))->assertSessionHasErrors('confirm');
        $this->assertModelExists($value);

        $this->delete(route('admin.core-values.destroy', $value), ['confirm' => '1'])
            ->assertRedirect(route('admin.core-values.index'));

        $this->assertModelMissing($value);
        $this->assertModelExists($icon);
        $this->assertSame('Temporary', AuditLog::firstWhere('action', 'deleted')->old_values['title']);
    }

    // List -----------------------------------------------------------------

    public function test_list_is_searchable_paginated_escaped_and_free_of_n_plus_one(): void
    {
        $this->as('administrator');
        CoreValue::factory()->withIcon()->create(['title' => '<script>alert(1)</script> Integrity']);
        CoreValue::factory()->withIcon()->count(3)->create();

        $this->get(route('admin.core-values.index', ['q' => 'Integrity']))->assertOk()
            ->assertViewHas('values', fn ($p) => $p->total() === 1)
            ->assertDontSee('<script>alert(1)</script>', false)
            ->assertSee('&lt;script&gt;alert(1)&lt;/script&gt; Integrity', false)
            ->assertSee('Clear the search to change the display order.');

        $this->get(route('admin.core-values.index', ['q' => '100%']))->assertOk()->assertSee('No core values match your search.');

        $queries = function () {
            $count = 0;
            DB::listen(function () use (&$count) {
                $count++;
            });
            $this->get(route('admin.core-values.index'))->assertOk();
            DB::getEventDispatcher()->forget('Illuminate\Database\Events\QueryExecuted');

            return $count;
        };
        $few = $queries();
        CoreValue::factory()->withIcon()->count(10)->create();
        $this->assertSame($few, $queries());

        CoreValue::factory()->count(CoreValueController::PER_PAGE)->create();
        $this->get(route('admin.core-values.index'))->assertViewHas('values', fn ($p) => $p->count() === CoreValueController::PER_PAGE);
    }

    // Public site ----------------------------------------------------------

    public function test_public_core_values_page_reflects_admin_changes(): void
    {
        $this->as('administrator');
        $this->post(route('admin.core-values.store'), ['title' => 'Excellence', 'is_active' => '1']);
        $this->post(route('admin.core-values.store'), ['title' => 'Integrity', 'is_active' => '1']);
        $this->assertSame(['Excellence', 'Integrity'], $this->publicTitles());

        $integrity = CoreValue::firstWhere('title', 'Integrity');
        $this->post(route('admin.core-values.move', $integrity), ['direction' => 'up']);
        $this->assertSame(['Integrity', 'Excellence'], $this->publicTitles());

        $this->put(route('admin.core-values.update', $integrity), ['title' => 'Integrity', 'is_active' => '0']);
        $this->assertSame(['Excellence'], $this->publicTitles());
    }
}
