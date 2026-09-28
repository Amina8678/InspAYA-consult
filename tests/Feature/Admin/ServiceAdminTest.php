<?php

namespace Tests\Feature\Admin;

use App\Http\Controllers\Admin\ServiceController;
use App\Models\AuditLog;
use App\Models\Consultant;
use App\Models\Service;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ServiceAdminTest extends TestCase
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
            'title' => 'Energy Policy',
            'slug' => '',
            'short_description' => 'Summary.',
            'description' => 'Full description.',
            'capabilities' => "Policy review\n\n  Regulatory advice  \n",
            'outcomes' => 'A clear roadmap',
            'meta_title' => '',
            'meta_description' => '',
            'is_active' => '1',
        ];
    }

    // Create ---------------------------------------------------------------

    public function test_admin_creates_a_service_with_lists_assignments_and_audit(): void
    {
        $admin = $this->as('administrator');
        Service::factory()->create(['sort_order' => 3]);
        [$lead, $support, $other] = Consultant::factory()->count(3)->sequence(
            ['sort_order' => 1], ['sort_order' => 2], ['sort_order' => 3],
        )->create();

        $response = $this->post(route('admin.services.store'), $this->payload([
            'consultants' => [$lead->id => 'lead', $support->id => 'supporting', $other->id => 'none'],
        ]));

        $service = Service::firstWhere('title', 'Energy Policy');
        $response->assertRedirect(route('admin.services.edit', $service))->assertSessionHasNoErrors();
        $this->assertSame('energy-policy', $service->slug);
        $this->assertSame(4, $service->sort_order);
        $this->assertSame(['Policy review', 'Regulatory advice'], $service->capabilities);
        $this->assertSame(['A clear roadmap'], $service->outcomes);
        $this->assertSame([$lead->id, $support->id], $service->consultants->pluck('id')->all());
        $this->assertSame([$lead->id], $service->leadConsultants->pluck('id')->all());

        $log = AuditLog::firstWhere('action', 'created');
        $this->assertSame([$admin->id, 'service', $service->id], [$log->user_id, $log->entity_type, $log->entity_id]);
        $this->assertEquals([$lead->id => 'lead', $support->id => 'supporting'], $log->new_values['consultants']);
    }

    /**
     * @return array<string, array{array<string, mixed>, string}>
     */
    public static function invalidInput(): array
    {
        return [
            'title missing' => [['title' => ''], 'title'],
            'slug with capitals' => [['slug' => 'Energy_Policy'], 'slug'],
            'slug taken' => [['slug' => 'taken'], 'slug'],
            'summary too long' => [['short_description' => str_repeat('a', 501)], 'short_description'],
            'too many capabilities' => [['capabilities' => implode("\n", range(1, 31))], 'capabilities'],
            'capability too long' => [['capabilities' => str_repeat('a', 256)], 'capabilities'],
            'meta description too long' => [['meta_description' => str_repeat('a', 501)], 'meta_description'],
            'unknown role' => [['consultants' => ['CONSULTANT' => 'owner']], 'consultants.CONSULTANT'],
            'unknown consultant' => [['consultants' => [999999 => 'lead']], 'consultants'],
        ];
    }

    #[DataProvider('invalidInput')]
    public function test_invalid_input_is_rejected(array $overrides, string $field): void
    {
        $this->as('administrator');
        Service::factory()->create(['slug' => 'taken']);
        $consultant = Consultant::factory()->create();
        if (isset($overrides['consultants']['CONSULTANT'])) {
            $overrides['consultants'] = [$consultant->id => 'owner'];
            $field = 'consultants.'.$consultant->id;
        }

        $this->from(route('admin.services.create'))
            ->post(route('admin.services.store'), $this->payload($overrides))
            ->assertRedirect(route('admin.services.create'))
            ->assertSessionHasErrors($field);

        $this->assertSame(1, Service::count());
        $this->assertSame(0, AuditLog::count());
    }

    // Slug on a live service -----------------------------------------------

    public function test_changing_the_slug_of_a_live_service_needs_confirmation(): void
    {
        $this->as('administrator');
        $service = Service::factory()->create(['title' => 'Energy', 'slug' => 'energy', 'is_active' => true]);

        $this->get(route('admin.services.edit', $service))->assertSee('This service is live.');

        $this->from(route('admin.services.edit', $service))
            ->put(route('admin.services.update', $service), $this->payload(['title' => 'Energy', 'slug' => 'energy-advice']))
            ->assertSessionHasErrors('confirm_slug_change');
        $this->assertSame('energy', $service->fresh()->slug);

        $this->put(route('admin.services.update', $service), $this->payload(['title' => 'Energy', 'slug' => 'energy-advice', 'confirm_slug_change' => '1']))
            ->assertSessionHasNoErrors();
        $this->assertSame('energy-advice', $service->fresh()->slug);

        $this->get(route('services.show', 'energy'))->assertNotFound();
        $this->get(route('services.show', 'energy-advice'))->assertOk();
    }

    public function test_hidden_services_change_slug_without_confirmation_and_empty_slug_keeps_the_current_one(): void
    {
        $this->as('administrator');
        $hidden = Service::factory()->inactive()->create(['slug' => 'draft-service']);
        $live = Service::factory()->create(['slug' => 'live-service']);

        $this->get(route('admin.services.edit', $hidden))->assertDontSee('This service is live.');
        $this->put(route('admin.services.update', $hidden), $this->payload(['slug' => 'renamed', 'is_active' => '0']))->assertSessionHasNoErrors();
        $this->assertSame('renamed', $hidden->fresh()->slug);

        $this->put(route('admin.services.update', $live), $this->payload(['title' => 'A New Title', 'slug' => '']))->assertSessionHasNoErrors();
        $this->assertSame('live-service', $live->fresh()->slug);
    }

    // Permissions (plan §6) ------------------------------------------------

    public function test_editor_edits_content_only(): void
    {
        $this->as('editor');
        $consultant = Consultant::factory()->create(['name' => 'Assigned Person']);
        $service = Service::factory()->create(['title' => 'Old', 'is_active' => true]);
        $service->consultants()->attach($consultant, ['is_lead' => true]);

        $this->get(route('admin.services.edit', $service))->assertOk()
            ->assertDontSee('id="field-is_active"', false)
            ->assertDontSee('name="consultants[', false)
            ->assertSee('Assigned Person')
            ->assertSee('Only administrators can change consultant assignments.');

        // Crafted status and assignment changes are ignored.
        $this->put(route('admin.services.update', $service), $this->payload([
            'title' => 'Edited', 'is_active' => '0', 'consultants' => [$consultant->id => 'none'],
        ]))->assertSessionHasNoErrors();

        $service->refresh();
        $this->assertSame('Edited', $service->title);
        $this->assertTrue($service->is_active);
        $this->assertSame(1, $service->consultants()->count());

        $this->get(route('admin.services.create'))->assertForbidden();
        $this->post(route('admin.services.store'), $this->payload())->assertForbidden();
        $this->post(route('admin.services.move', $service), ['direction' => 'up'])->assertForbidden();
        $this->delete(route('admin.services.destroy', $service), ['confirm' => '1'])->assertForbidden();
        $this->assertModelExists($service);
    }

    public function test_author_cannot_reach_services(): void
    {
        $this->as('author');
        $service = Service::factory()->create();

        $this->get(route('admin.services.index'))->assertForbidden();
        $this->put(route('admin.services.update', $service), $this->payload())->assertForbidden();
        $this->get('/admin')->assertDontSee(route('admin.services.index'), false);
    }

    // Assignments, reorder, delete -----------------------------------------

    public function test_assignment_changes_are_audited(): void
    {
        $this->as('administrator');
        $service = Service::factory()->create(['slug' => 'svc']);
        $a = Consultant::factory()->create();
        $service->consultants()->attach($a, ['is_lead' => false]);

        $this->put(route('admin.services.update', $service), $this->payload([
            'title' => $service->title, 'slug' => 'svc', 'consultants' => [$a->id => 'lead'],
        ]))->assertSessionHasNoErrors();

        $log = AuditLog::firstWhere('action', 'updated');
        $this->assertEquals([$a->id => 'supporting'], $log->old_values['consultants']);
        $this->assertEquals([$a->id => 'lead'], $log->new_values['consultants']);
    }

    public function test_move_reorders_and_audits(): void
    {
        $this->as('administrator');
        $a = Service::factory()->create(['title' => 'A', 'sort_order' => 1]);
        $b = Service::factory()->create(['title' => 'B', 'sort_order' => 2]);

        $this->post(route('admin.services.move', $a), ['direction' => 'down'])->assertSessionHas('status', 'Moved "A" to position 2.');

        $this->assertSame(['B', 'A'], Service::query()->ordered()->pluck('title')->all());
        $this->assertSame(1, AuditLog::where('action', 'reordered')->count());
    }

    public function test_delete_page_lists_assignments_and_delete_removes_them_but_keeps_consultants(): void
    {
        $this->as('administrator');
        $service = Service::factory()->create(['title' => 'Going away']);
        $lead = Consultant::factory()->create(['name' => 'Lead Person']);
        $support = Consultant::factory()->create(['name' => 'Support Person']);
        $service->consultants()->attach([$lead->id => ['is_lead' => true], $support->id => ['is_lead' => false]]);

        $this->get(route('admin.services.delete', $service))->assertOk()
            ->assertSee('2 consultant assignments will be removed')
            ->assertSeeInOrder(['Lead Person', '(Lead)'])
            ->assertSeeInOrder(['Support Person', '(Supporting)']);

        $this->delete(route('admin.services.destroy', $service))->assertSessionHasErrors('confirm');
        $this->assertModelExists($service);

        $this->delete(route('admin.services.destroy', $service), ['confirm' => '1'])->assertRedirect(route('admin.services.index'));

        $this->assertModelMissing($service);
        $this->assertDatabaseCount('service_consultant', 0);
        $this->assertModelExists($lead);
        $log = AuditLog::firstWhere('action', 'deleted');
        $this->assertSame('Going away', $log->old_values['title']);
        $this->assertCount(2, $log->old_values['consultants']);
    }

    // List -----------------------------------------------------------------

    public function test_list_is_searchable_escaped_and_free_of_n_plus_one(): void
    {
        $this->as('administrator');
        $service = Service::factory()->create(['title' => '<script>alert(1)</script> Governance']);
        $service->consultants()->attach(Consultant::factory()->create(), ['is_lead' => true]);
        Service::factory()->count(3)->create();

        $this->get(route('admin.services.index', ['q' => 'Governance']))->assertOk()
            ->assertViewHas('services', fn ($p) => $p->total() === 1)
            ->assertDontSee('<script>alert(1)</script>', false)
            ->assertSee('&lt;script&gt;alert(1)&lt;/script&gt; Governance', false)
            ->assertSee('(1 lead)');

        $count = function () {
            $n = 0;
            DB::listen(function () use (&$n) {
                $n++;
            });
            $this->get(route('admin.services.index'))->assertOk();
            DB::getEventDispatcher()->forget('Illuminate\Database\Events\QueryExecuted');

            return $n;
        };
        $few = $count();
        Service::factory()->count(10)->create()->each(fn ($s) => $s->consultants()->attach(Consultant::factory()->create()));
        $this->assertSame($few, $count());

        Service::factory()->count(ServiceController::PER_PAGE)->create();
        $this->get(route('admin.services.index'))->assertViewHas('services', fn ($p) => $p->count() === ServiceController::PER_PAGE && $p->lastPage() === 2);
    }

    // Public site ----------------------------------------------------------

    public function test_public_service_page_reflects_admin_changes(): void
    {
        $this->as('administrator');
        $lead = Consultant::factory()->create(['name' => 'Lead Person']);

        $this->post(route('admin.services.store'), $this->payload([
            'title' => 'Forensic Audits', 'capabilities' => "Fraud review\nAsset tracing",
            'consultants' => [$lead->id => 'lead'],
        ]));
        $this->app->forgetScopedInstances();

        $this->get(route('services.show', 'forensic-audits'))->assertOk()
            ->assertSeeInOrder(['Forensic Audits', 'Fraud review', 'Asset tracing', 'Lead consultant', 'Lead Person']);

        $service = Service::firstWhere('slug', 'forensic-audits');
        $this->put(route('admin.services.update', $service), $this->payload(['title' => 'Forensic Audits', 'slug' => 'forensic-audits', 'is_active' => '0']));
        $this->app->forgetScopedInstances();

        $this->get(route('services.show', 'forensic-audits'))->assertNotFound();
    }
}
