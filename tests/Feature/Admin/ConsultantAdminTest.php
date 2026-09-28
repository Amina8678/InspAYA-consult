<?php

namespace Tests\Feature\Admin;

use App\Http\Controllers\Admin\ConsultantController;
use App\Models\AuditLog;
use App\Models\Consultant;
use App\Models\Media;
use App\Models\Service;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ConsultantAdminTest extends TestCase
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
            'name' => 'Ada Example',
            'title' => 'Lead Consultant',
            'bio' => 'Biography.',
            'photo_id' => '',
            'expertise' => "Governance\n\n  Risk  \n",
            'qualifications' => 'MBA',
            'email' => '',
            'links_linkedin' => 'https://www.linkedin.com/in/example',
            'links_x' => '',
            'links_facebook' => '',
            'links_instagram' => '',
            'links_website' => '',
            'is_active' => '1',
        ];
    }

    /**
     * Pivot rows for a service, in pivot order: [consultant id, role].
     *
     * @return list<array{int, string}>
     */
    private function pivot(Service $service): array
    {
        return DB::table('service_consultant')->where('service_id', $service->id)->orderBy('sort_order')
            ->get()->map(fn ($r) => [(int) $r->consultant_id, $r->is_lead ? 'lead' : 'supporting'])->all();
    }

    // Create ---------------------------------------------------------------

    public function test_admin_creates_a_consultant_with_lists_links_photo_services_and_audit(): void
    {
        $admin = $this->as('administrator');
        Consultant::factory()->create(['sort_order' => 2]);
        $photo = Media::factory()->create();
        [$a, $b] = Service::factory()->count(2)->create();

        $response = $this->post(route('admin.consultants.store'), $this->payload([
            'photo_id' => (string) $photo->id,
            'services' => [$a->id => 'lead', $b->id => 'none'],
        ]));

        $consultant = Consultant::firstWhere('name', 'Ada Example');
        $response->assertRedirect(route('admin.consultants.edit', $consultant))->assertSessionHasNoErrors();
        $this->assertSame(3, $consultant->sort_order);
        $this->assertSame(['Governance', 'Risk'], $consultant->expertise);
        $this->assertSame(['MBA'], $consultant->qualifications);
        $this->assertSame(['linkedin' => 'https://www.linkedin.com/in/example'], $consultant->links);
        $this->assertSame($photo->id, $consultant->photo_id);
        $this->assertSame([[$consultant->id, 'lead']], $this->pivot($a));
        $this->assertSame([], $this->pivot($b));

        $log = AuditLog::firstWhere('action', 'created');
        $this->assertSame([$admin->id, 'consultant', $consultant->id], [$log->user_id, $log->entity_type, $log->entity_id]);
        $this->assertEquals([$a->id => 'lead'], $log->new_values['services']);
    }

    /**
     * @return array<string, array{array<string, mixed>, string}>
     */
    public static function invalidInput(): array
    {
        return [
            'name missing' => [['name' => ''], 'name'],
            'bad email' => [['email' => 'not-an-email'], 'email'],
            'javascript profile link' => [['links_linkedin' => 'javascript:alert(1)'], 'links_linkedin'],
            'mailto is not a profile' => [['links_x' => 'mailto:a@example.com'], 'links_x'],
            'site path is not a profile' => [['links_website' => '/about'], 'links_website'],
            'bare domain' => [['links_facebook' => 'facebook.com/example'], 'links_facebook'],
            'photo missing' => [['photo_id' => '999999'], 'photo_id'],
            'photo is a pdf' => [['photo_id' => 'pdf'], 'photo_id'],
            'too many expertise lines' => [['expertise' => implode("\n", range(1, 31))], 'expertise'],
            'unknown role' => [['services' => ['SERVICE' => 'owner']], 'services.SERVICE'],
            'unknown service' => [['services' => [999999 => 'lead']], 'services'],
        ];
    }

    #[DataProvider('invalidInput')]
    public function test_invalid_input_is_rejected(array $overrides, string $field): void
    {
        $this->as('administrator');
        if (($overrides['photo_id'] ?? null) === 'pdf') {
            $overrides['photo_id'] = (string) Media::factory()->pdf()->create()->id;
        }
        if (isset($overrides['services']['SERVICE'])) {
            $service = Service::factory()->create();
            $overrides['services'] = [$service->id => 'owner'];
            $field = 'services.'.$service->id;
        }

        $this->from(route('admin.consultants.create'))
            ->post(route('admin.consultants.store'), $this->payload($overrides))
            ->assertRedirect(route('admin.consultants.create'))
            ->assertSessionHasErrors($field);

        $this->assertSame(0, Consultant::count());
        $this->assertSame(0, AuditLog::count());
    }

    // Permissions (plan §6) ------------------------------------------------

    public function test_editor_edits_content_only(): void
    {
        $this->as('editor');
        $service = Service::factory()->create(['title' => 'Assigned Service']);
        $consultant = Consultant::factory()->create(['name' => 'Old', 'is_active' => true]);
        $service->consultants()->attach($consultant, ['is_lead' => true]);

        $this->get(route('admin.consultants.edit', $consultant))->assertOk()
            ->assertDontSee('id="field-is_active"', false)
            ->assertDontSee('name="services[', false)
            ->assertSee('Assigned Service')
            ->assertSee('Only administrators can change service assignments.');

        // Crafted status and assignment changes are ignored.
        $this->put(route('admin.consultants.update', $consultant), $this->payload([
            'name' => 'Edited', 'is_active' => '0', 'services' => [$service->id => 'none'],
        ]))->assertSessionHasNoErrors();

        $consultant->refresh();
        $this->assertSame('Edited', $consultant->name);
        $this->assertTrue($consultant->is_active);
        $this->assertSame([[$consultant->id, 'lead']], $this->pivot($service));

        $this->get(route('admin.consultants.create'))->assertForbidden();
        $this->post(route('admin.consultants.store'), $this->payload())->assertForbidden();
        $this->post(route('admin.consultants.move', $consultant), ['direction' => 'up'])->assertForbidden();
        $this->delete(route('admin.consultants.destroy', $consultant), ['confirm' => '1'])->assertForbidden();
        $this->assertModelExists($consultant);
    }

    public function test_author_cannot_reach_consultants(): void
    {
        $this->as('author');
        $consultant = Consultant::factory()->create();

        $this->get(route('admin.consultants.index'))->assertForbidden();
        $this->put(route('admin.consultants.update', $consultant), $this->payload())->assertForbidden();
        $this->get('/admin')->assertDontSee(route('admin.consultants.index'), false);
    }

    // Both sides of the pivot agree ----------------------------------------

    public function test_assignments_made_on_either_side_show_on_the_other(): void
    {
        $this->as('administrator');
        $service = Service::factory()->create(['slug' => 'svc']);
        $consultant = Consultant::factory()->create(['name' => 'Shared Person']);

        $this->put(route('admin.consultants.update', $consultant), $this->payload([
            'name' => 'Shared Person', 'services' => [$service->id => 'lead'],
        ]))->assertSessionHasNoErrors();

        $html = $this->get(route('admin.services.edit', $service))->assertSee('Shared Person')->getContent();
        $this->assertMatchesRegularExpression('~<select id="consultant-'.$consultant->id.'"[^>]*>(?:(?!</select>).)*<option value="lead" selected~s', $html);

        $this->put(route('admin.services.update', $service), [
            'title' => $service->title, 'slug' => 'svc', 'is_active' => '1', 'consultants' => [$consultant->id => 'supporting'],
        ])->assertSessionHasNoErrors();

        $html = $this->get(route('admin.consultants.edit', $consultant))->getContent();
        $this->assertMatchesRegularExpression('~<select id="service-'.$service->id.'"[^>]*>(?:(?!</select>).)*<option value="supporting" selected~s', $html);
    }

    public function test_consultant_side_changes_leave_other_consultants_on_the_service_alone(): void
    {
        $this->as('administrator');
        $service = Service::factory()->create();
        $first = Consultant::factory()->create(['sort_order' => 1]);
        $second = Consultant::factory()->create(['sort_order' => 2]);
        $service->consultants()->attach($first, ['is_lead' => true, 'sort_order' => 1]);

        $this->put(route('admin.consultants.update', $second), $this->payload([
            'name' => $second->name, 'services' => [$service->id => 'supporting'],
        ]))->assertSessionHasNoErrors();

        $this->assertSame([[$first->id, 'lead'], [$second->id, 'supporting']], $this->pivot($service));

        $this->put(route('admin.consultants.update', $second), $this->payload([
            'name' => $second->name, 'services' => [$service->id => 'none'],
        ]));
        $this->assertSame([[$first->id, 'lead']], $this->pivot($service));
    }

    public function test_moving_a_consultant_reorders_them_on_every_service_page(): void
    {
        $this->as('administrator');
        $service = Service::factory()->create(['slug' => 'svc']);
        $a = Consultant::factory()->create(['name' => 'Anna', 'sort_order' => 1]);
        $b = Consultant::factory()->create(['name' => 'Ben', 'sort_order' => 2]);
        $this->put(route('admin.services.update', $service), [
            'title' => $service->title, 'slug' => 'svc', 'is_active' => '1', 'consultants' => [$a->id => 'lead', $b->id => 'supporting'],
        ]);
        $this->assertSame([$a->id, $b->id], array_column($this->pivot($service), 0));

        $this->post(route('admin.consultants.move', $b), ['direction' => 'up'])->assertSessionHas('status', 'Moved "Ben" to position 1.');

        $this->assertSame([$b->id, $a->id], array_column($this->pivot($service), 0));
        $this->app->forgetScopedInstances();
        $this->get(route('services.show', 'svc'))->assertSeeInOrder(['Ben', 'Anna']);
        $this->assertSame(1, AuditLog::where('action', 'reordered')->count());
    }

    // Delete ---------------------------------------------------------------

    public function test_delete_page_lists_assignments_and_delete_removes_them_but_keeps_services_and_photo(): void
    {
        $this->as('administrator');
        $photo = Media::factory()->create();
        $consultant = Consultant::factory()->create(['name' => 'Leaving', 'photo_id' => $photo->id, 'sort_order' => 1]);
        $stays = Consultant::factory()->create(['sort_order' => 2]);
        $lead = Service::factory()->create(['title' => 'Led Service']);
        $support = Service::factory()->create(['title' => 'Supported Service']);
        $lead->consultants()->attach([$consultant->id => ['is_lead' => true, 'sort_order' => 1], $stays->id => ['is_lead' => false, 'sort_order' => 2]]);
        $support->consultants()->attach($consultant, ['is_lead' => false, 'sort_order' => 1]);

        $this->get(route('admin.consultants.delete', $consultant))->assertOk()
            ->assertSee('2 service assignments will be removed')
            ->assertSeeInOrder(['Led Service', '(Lead)'])
            ->assertSeeInOrder(['Supported Service', '(Supporting)'])
            ->assertSee('Their photo stays in the media library.');

        $this->delete(route('admin.consultants.destroy', $consultant))->assertSessionHasErrors('confirm');
        $this->assertModelExists($consultant);

        $this->delete(route('admin.consultants.destroy', $consultant), ['confirm' => '1'])->assertRedirect(route('admin.consultants.index'));

        $this->assertModelMissing($consultant);
        $this->assertModelExists($lead);
        $this->assertModelExists($photo);
        $this->assertSame([[$stays->id, 'supporting']], $this->pivot($lead));
        $this->assertSame(1, DB::table('service_consultant')->where('service_id', $lead->id)->value('sort_order'));
        $this->assertCount(2, AuditLog::firstWhere('action', 'deleted')->old_values['services']);
    }

    // List and public site -------------------------------------------------

    public function test_list_is_searchable_escaped_and_free_of_n_plus_one(): void
    {
        $this->as('administrator');
        Consultant::factory()->withPhoto()->create(['name' => '<script>alert(1)</script> Ada', 'title' => 'Partner']);
        Consultant::factory()->withPhoto()->count(3)->create(['title' => 'Analyst']);

        $this->get(route('admin.consultants.index', ['q' => 'Partner']))->assertOk()
            ->assertViewHas('consultants', fn ($p) => $p->total() === 1)
            ->assertDontSee('<script>alert(1)</script>', false)
            ->assertSee('&lt;script&gt;alert(1)&lt;/script&gt; Ada', false);

        $count = function () {
            $n = 0;
            DB::listen(function () use (&$n) {
                $n++;
            });
            $this->get(route('admin.consultants.index'))->assertOk();
            DB::getEventDispatcher()->forget('Illuminate\Database\Events\QueryExecuted');

            return $n;
        };
        $few = $count();
        Consultant::factory()->withPhoto()->count(10)->create();
        $this->assertSame($few, $count());

        Consultant::factory()->count(ConsultantController::PER_PAGE)->create();
        $this->get(route('admin.consultants.index'))->assertViewHas('consultants', fn ($p) => $p->count() === ConsultantController::PER_PAGE);
    }

    public function test_public_consultants_page_reflects_admin_changes(): void
    {
        $this->as('administrator');
        $this->post(route('admin.consultants.store'), $this->payload(['name' => 'Public Person', 'links_website' => 'https://example.com/profile']));
        $this->app->forgetScopedInstances();

        $this->get(route('consultants.index'))->assertOk()
            ->assertSee('Public Person')
            ->assertSee('href="https://example.com/profile"', false);

        $consultant = Consultant::firstWhere('name', 'Public Person');
        $this->put(route('admin.consultants.update', $consultant), $this->payload(['name' => 'Public Person', 'is_active' => '0']));
        $this->app->forgetScopedInstances();

        $this->get(route('consultants.index'))->assertDontSee('Public Person');
    }
}
