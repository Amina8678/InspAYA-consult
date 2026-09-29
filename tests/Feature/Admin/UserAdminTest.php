<?php

namespace Tests\Feature\Admin;

use App\Enums\UserStatus;
use App\Models\AuditLog;
use App\Models\Role;
use App\Models\User;
use App\Notifications\AdminResetPassword;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class UserAdminTest extends TestCase
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
    private function payload(User $target, array $overrides = []): array
    {
        return $overrides + [
            'name' => $target->name,
            'email' => $target->email,
            'username' => $target->username,
            'role_id' => (string) $target->role_id,
        ];
    }

    // List -------------------------------------------------------------------

    public function test_list_shows_role_status_last_login_and_is_searchable(): void
    {
        $this->as('administrator');
        User::factory()->withRole('editor')->create(['name' => 'Findable Person', 'last_login_at' => now()]);
        User::factory()->inactive()->create(['name' => 'Someone Else']);

        $this->get(route('admin.users.index'))->assertOk()
            ->assertSee('Findable Person')
            ->assertSee('Editor')
            ->assertSee('Active')
            ->assertSee('Inactive')
            ->assertSee('Someone Else');

        $this->get(route('admin.users.index', ['q' => 'Findable']))->assertOk()
            ->assertViewHas('users', fn ($p) => $p->total() === 1);
    }

    // Creation -----------------------------------------------------------------

    public function test_creating_a_user_sends_a_password_setup_link_and_is_audited(): void
    {
        Notification::fake();
        $admin = $this->as('administrator');
        $editorRole = Role::firstWhere('slug', 'editor');

        $this->post(route('admin.users.store'), [
            'name' => 'New Editor', 'email' => 'new.editor@example.com', 'username' => 'new-editor',
            'role_id' => (string) $editorRole->id,
        ])->assertSessionHasNoErrors();

        $user = User::firstWhere('email', 'new.editor@example.com');
        $this->assertNotNull($user);
        $this->assertSame($editorRole->id, $user->role_id);
        $this->assertSame(UserStatus::Active, $user->status);

        Notification::assertSentTo($user, AdminResetPassword::class);

        $log = AuditLog::where('action', 'created')->sole();
        $this->assertSame($admin->id, $log->user_id);
        $this->assertSame($user->id, $log->entity_id);
        $this->assertSame('New Editor', $log->new_values['name']);
    }

    public function test_only_a_super_admin_can_create_a_super_admin_account(): void
    {
        $this->as('administrator');
        $superRole = Role::firstWhere('slug', 'super-admin');

        $this->post(route('admin.users.store'), [
            'name' => 'New SA', 'email' => 'newsa@example.com', 'username' => 'newsa', 'role_id' => (string) $superRole->id,
        ])->assertSessionHasErrors('role_id');
        $this->assertSame(0, User::where('email', 'newsa@example.com')->count());

        $this->get(route('admin.users.create'))->assertOk()->assertDontSee('Super Admin');

        $this->as('super-admin');
        $this->post(route('admin.users.store'), [
            'name' => 'New SA', 'email' => 'newsa@example.com', 'username' => 'newsa', 'role_id' => (string) $superRole->id,
        ])->assertSessionHasNoErrors();
        $this->assertSame(1, User::where('email', 'newsa@example.com')->count());
    }

    public function test_editors_and_authors_cannot_reach_user_management(): void
    {
        foreach (['editor', 'author'] as $role) {
            $this->as($role);
            $target = User::factory()->create();

            $this->get(route('admin.users.index'))->assertForbidden();
            $this->get(route('admin.users.create'))->assertForbidden();
            $this->get(route('admin.users.edit', $target))->assertForbidden();
            $this->get('/admin')->assertDontSee(route('admin.users.index'), false);
        }
    }

    // Self-protection ------------------------------------------------------

    public function test_nobody_changes_their_own_role_or_deactivates_themselves(): void
    {
        $admin = $this->as('administrator');
        $editorRole = Role::firstWhere('slug', 'editor');

        $this->put(route('admin.users.update', $admin), $this->payload($admin, [
            'role_id' => (string) $editorRole->id, 'status' => 'inactive',
        ]))->assertSessionHasNoErrors();

        $this->assertSame('administrator', $admin->fresh()->role->slug);
        $this->assertSame(UserStatus::Active, $admin->fresh()->status);

        $this->get(route('admin.users.edit', $admin))->assertOk()
            ->assertSee('You cannot change your own role.')
            ->assertSee('You cannot deactivate your own account.');
    }

    // Super Admin protection -------------------------------------------------

    public function test_an_administrator_cannot_act_on_a_super_admin_but_a_super_admin_can(): void
    {
        $this->as('administrator');
        $superAdmin = User::factory()->withRole('super-admin')->create();

        $this->get(route('admin.users.edit', $superAdmin))->assertForbidden();
        $this->put(route('admin.users.update', $superAdmin), $this->payload($superAdmin))->assertForbidden();

        $this->as('super-admin');
        $this->put(route('admin.users.update', $superAdmin), $this->payload($superAdmin, ['name' => 'Renamed']))
            ->assertSessionHasNoErrors();
        $this->assertSame('Renamed', $superAdmin->fresh()->name);
    }

    // Deactivation, sessions and audit ----------------------------------------

    public function test_deactivating_signs_out_the_targets_active_session_and_is_audited(): void
    {
        $this->as('administrator');
        $target = User::factory()->withRole('editor')->create();

        // The target is signed in elsewhere, mid-session.
        $this->actingAs($target)->get(route('admin.dashboard'))->assertOk();

        $this->as('administrator');
        $this->put(route('admin.users.update', $target), $this->payload($target, ['status' => 'inactive']))
            ->assertSessionHasNoErrors();
        $this->assertSame(UserStatus::Inactive, $target->fresh()->status);

        // Their session's next request is caught by the `active` middleware.
        $this->actingAs($target->fresh())->get(route('admin.dashboard'))->assertRedirect(route('admin.login'));
        $this->assertGuest();

        $this->assertSame('deactivated', AuditLog::latest('id')->first()->action);

        $this->as('administrator');
        $this->put(route('admin.users.update', $target), $this->payload($target, ['status' => 'active']))
            ->assertSessionHasNoErrors();
        $this->assertSame(UserStatus::Active, $target->fresh()->status);
        $this->assertSame('reactivated', AuditLog::latest('id')->first()->action);
    }

    // Role changes take effect on the next request only ----------------------

    public function test_a_role_change_takes_effect_on_the_targets_next_request_not_retroactively(): void
    {
        $this->as('super-admin');
        $target = User::factory()->withRole('editor')->create();

        // Editors can reach the pages list.
        $this->actingAs($target)->get(route('admin.pages.index'))->assertOk();

        $this->as('super-admin');
        $authorRole = Role::firstWhere('slug', 'author');
        $this->put(route('admin.users.update', $target), $this->payload($target, ['role_id' => (string) $authorRole->id]))
            ->assertSessionHasNoErrors();
        $this->assertSame($authorRole->id, $target->fresh()->role_id);

        // A fresh request for that same (still-authenticated) user reflects
        // the new role immediately — no stale cache to bust.
        $this->actingAs($target->fresh())->get(route('admin.pages.index'))->assertForbidden();
    }

    // No delete path (D6) -----------------------------------------------------

    public function test_there_is_no_delete_action_or_route_for_users(): void
    {
        $this->as('super-admin');
        User::factory()->create(['name' => 'Cannot Be Deleted']);

        $html = $this->get(route('admin.users.index'))->assertOk()->getContent();
        $this->assertStringNotContainsString('>Delete<', $html);

        // Proven exhaustively by AdminRoutesTest::test_every_admin_route_is_covered_and_protected too.
        $this->assertFalse(Route::has('admin.users.delete'));
        $this->assertFalse(Route::has('admin.users.destroy'));
    }

    // Password reset link (FR-ADM-11) -----------------------------------------

    public function test_sending_a_reset_link_to_an_existing_user_uses_the_same_mechanism_as_creation_and_is_audited(): void
    {
        Notification::fake();
        $admin = $this->as('administrator');
        $target = User::factory()->withRole('editor')->create();

        $this->post(route('admin.users.reset-password', $target))
            ->assertRedirect(route('admin.users.edit', $target))
            ->assertSessionHas('status', 'A password reset link has been sent to '.$target->email.'.');

        Notification::assertSentTo($target, AdminResetPassword::class);

        $log = AuditLog::where('action', 'password_reset_link_sent')->sole();
        $this->assertSame($admin->id, $log->user_id);
        $this->assertSame($target->id, $log->entity_id);
    }

    public function test_an_administrator_can_send_themselves_a_reset_link_but_not_to_a_super_admin(): void
    {
        Notification::fake();
        $admin = $this->as('administrator');
        $superAdmin = User::factory()->withRole('super-admin')->create();

        // UserPolicy::resetPassword has no self-exclusion, unlike deactivate
        // and assignRole: sending yourself a reset link is allowed.
        $this->post(route('admin.users.reset-password', $admin))->assertSessionHasNoErrors();
        Notification::assertSentTo($admin, AdminResetPassword::class);

        // Same Super Admin protection as every other user-management action.
        $this->post(route('admin.users.reset-password', $superAdmin))->assertForbidden();
        Notification::assertNotSentTo($superAdmin, AdminResetPassword::class);

        $this->as('super-admin');
        $this->post(route('admin.users.reset-password', $superAdmin))->assertSessionHasNoErrors();
        Notification::assertSentTo($superAdmin, AdminResetPassword::class);
    }

    public function test_editors_and_authors_cannot_send_a_reset_link(): void
    {
        foreach (['editor', 'author'] as $role) {
            $this->as($role);
            $target = User::factory()->create();

            $this->post(route('admin.users.reset-password', $target))->assertForbidden();
        }
    }

    // Validation -------------------------------------------------------------

    public function test_email_and_username_must_be_unique(): void
    {
        $this->as('administrator');
        $existing = User::factory()->create();
        $editorRole = Role::firstWhere('slug', 'editor');

        $this->post(route('admin.users.store'), [
            'name' => 'Duplicate', 'email' => $existing->email, 'username' => 'someone-new', 'role_id' => (string) $editorRole->id,
        ])->assertSessionHasErrors('email');

        $this->post(route('admin.users.store'), [
            'name' => 'Duplicate', 'email' => 'someone-new@example.com', 'username' => $existing->username, 'role_id' => (string) $editorRole->id,
        ])->assertSessionHasErrors('username');
    }
}
