<?php

namespace Tests\Feature\Admin;

use App\Enums\EnquiryStatus;
use App\Http\Controllers\Admin\ContactSubmissionController;
use App\Http\Controllers\Site\ContactController;
use App\Models\AuditLog;
use App\Models\ContactSubmission;
use App\Models\ContactSubmissionNote;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\SiteSettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class EnquiryAdminTest extends TestCase
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

    // Round trip -------------------------------------------------------------

    public function test_a_public_submission_appears_escaped_in_the_admin_inbox_and_detail_page(): void
    {
        $this->seed(SiteSettingsSeeder::class);
        Notification::fake();

        $this->withServerVariables(['REMOTE_ADDR' => '192.0.2.50'])->from(route('contact'))->post(route('contact.store'), [
            'name' => 'Ada <script>alert(1)</script> Example',
            'email' => 'ada@example.com',
            'phone' => '+1 555 0123',
            'organization' => 'Example Ltd',
            'subject' => 'Governance review',
            'message' => "Hello,\nWe would like to discuss a review.",
            'consent' => '1',
            ContactController::HONEYPOT => '',
        ])->assertSessionHasNoErrors();

        $submission = ContactSubmission::sole();
        $this->assertSame(EnquiryStatus::New, $submission->status);

        $this->as('administrator');

        $this->get(route('admin.enquiries.index'))->assertOk()
            ->assertSee('Governance review')
            ->assertDontSee('<script>alert(1)</script>', false)
            ->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false)
            // The IP address is a detail-page-only field (item 2), never the list.
            ->assertDontSee('192.0.2.50');

        $show = $this->get(route('admin.enquiries.show', $submission))->assertOk();
        $show->assertDontSee('<script>alert(1)</script>', false)
            ->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false)
            ->assertSee('href="mailto:ada@example.com"', false)
            ->assertSee('href="tel:+15550123"', false)
            ->assertSee("Hello,\nWe would like to discuss a review.", false)
            // Administrator holds enquiries.respond, so the detail page shows the IP.
            ->assertSee('192.0.2.50');
    }

    public function test_ip_address_is_hidden_from_roles_without_enquiries_respond(): void
    {
        $submission = ContactSubmission::factory()->create(['ip_address' => '192.0.2.99']);

        // No seeded role has enquiries.view without enquiries.respond (they
        // are granted together), so this checks the view's own gate with an
        // ad-hoc role, rather than relying on that always being true.
        $role = Role::factory()->create(['slug' => 'view-only-enquiries']);
        $role->permissions()->attach(Permission::firstOrCreate(
            ['slug' => 'enquiries.view'],
            ['name' => 'Enquiries View', 'group' => 'enquiries'],
        ));
        $this->actingAs(User::factory()->withRole($role)->create());

        $this->get(route('admin.enquiries.show', $submission))->assertOk()
            ->assertDontSee('192.0.2.99');
    }

    // Status, assignment and notes -------------------------------------------

    public function test_status_changes_are_explicit_viewing_never_changes_status(): void
    {
        $admin = $this->as('administrator');
        $submission = ContactSubmission::factory()->create();

        $this->get(route('admin.enquiries.show', $submission))->assertOk();
        $this->assertSame(EnquiryStatus::New, $submission->fresh()->status);

        $this->put(route('admin.enquiries.update', $submission), ['status' => 'responded'])->assertSessionHasNoErrors();
        $this->assertSame(EnquiryStatus::Responded, $submission->fresh()->status);
        $this->assertNotNull($submission->fresh()->responded_at);

        $this->assertSame('updated', AuditLog::sole()->action);
        $this->assertSame($admin->id, AuditLog::sole()->user_id);
    }

    public function test_an_editor_responds_but_cannot_assign_or_delete(): void
    {
        $this->as('editor');
        $submission = ContactSubmission::factory()->create();
        $staff = User::factory()->withRole('administrator')->create();

        $this->put(route('admin.enquiries.update', $submission), ['status' => 'in_progress', 'assigned_to' => (string) $staff->id])
            ->assertSessionHasNoErrors();

        $this->assertSame(EnquiryStatus::InProgress, $submission->fresh()->status);
        $this->assertNull($submission->fresh()->assigned_to, 'Editors may not assign (plan §6 row 29).');

        $this->get(route('admin.enquiries.delete', $submission))->assertForbidden();
        $this->delete(route('admin.enquiries.destroy', $submission), ['confirm' => '1'])->assertForbidden();
    }

    public function test_an_author_cannot_reach_the_inbox_at_all(): void
    {
        $this->as('author');
        $submission = ContactSubmission::factory()->create();

        $this->get(route('admin.enquiries.index'))->assertForbidden();
        $this->get(route('admin.enquiries.show', $submission))->assertForbidden();
        $this->get('/admin')->assertDontSee(route('admin.enquiries.index'), false);
    }

    public function test_notes_are_added_and_only_their_author_can_edit_them(): void
    {
        $editor = $this->as('editor');
        $submission = ContactSubmission::factory()->create();

        $this->post(route('admin.enquiries.notes.store', $submission), ['body' => 'Called back, left a message.'])
            ->assertSessionHasNoErrors();

        $note = ContactSubmissionNote::sole();
        $this->assertSame($editor->id, $note->user_id);
        $this->assertSame('note_created', AuditLog::sole()->action);

        $this->get(route('admin.enquiries.show', $submission))->assertOk()->assertSee('Called back, left a message.');

        // The author edits their own note.
        $this->put(route('admin.enquiries.notes.update', [$submission, $note]), ['body' => 'Called back, no answer.'])
            ->assertSessionHasNoErrors();
        $this->assertSame('Called back, no answer.', $note->fresh()->body);

        // A different editor cannot, even though they can also respond.
        $this->as('editor');
        $this->get(route('admin.enquiries.notes.edit', [$submission, $note]))->assertForbidden();
        $this->put(route('admin.enquiries.notes.update', [$submission, $note]), ['body' => 'Overwritten'])->assertForbidden();
        $this->assertSame('Called back, no answer.', $note->fresh()->body);
    }

    public function test_a_note_is_deleted_by_its_own_author_or_by_anyone_with_enquiries_delete(): void
    {
        $editor = $this->as('editor');
        $submission = ContactSubmission::factory()->create();
        $note = ContactSubmissionNote::factory()->for($submission, 'submission')->create(['user_id' => $editor->id]);

        // The editor lacks enquiries.delete, but wrote this note.
        $this->get(route('admin.enquiries.notes.delete', [$submission, $note]))->assertOk();
        $this->delete(route('admin.enquiries.notes.destroy', [$submission, $note]), ['confirm' => '1'])
            ->assertRedirect(route('admin.enquiries.show', $submission));
        $this->assertModelMissing($note);
        $this->assertSame('note_deleted', AuditLog::latest('id')->first()->action);
    }

    public function test_an_editor_without_enquiries_delete_still_cannot_delete_someone_elses_note(): void
    {
        $this->as('editor');
        $submission = ContactSubmission::factory()->create();
        $note = ContactSubmissionNote::factory()->for($submission, 'submission')->create();

        $this->get(route('admin.enquiries.notes.delete', [$submission, $note]))->assertForbidden();
        $this->delete(route('admin.enquiries.notes.destroy', [$submission, $note]), ['confirm' => '1'])->assertForbidden();
        $this->assertModelExists($note);
    }

    /**
     * A note's id is unique globally; a crafted URL that pairs a real note
     * with the wrong parent enquiry must 404, not leak or act on it.
     */
    public function test_a_note_cannot_be_reached_through_the_wrong_enquiry(): void
    {
        $editor = $this->as('editor');
        $ownEnquiry = ContactSubmission::factory()->create();
        $otherEnquiry = ContactSubmission::factory()->create();
        $note = ContactSubmissionNote::factory()->for($ownEnquiry, 'submission')->create(['user_id' => $editor->id]);

        $this->get(route('admin.enquiries.notes.edit', [$otherEnquiry, $note]))->assertNotFound();
    }

    // Deletion and its audit entry --------------------------------------------

    public function test_deleting_an_enquiry_shows_the_note_count_and_the_audit_entry_omits_personal_data(): void
    {
        $admin = $this->as('administrator');
        $submission = ContactSubmission::factory()->create([
            'name' => 'Ada Example', 'email' => 'ada@example.com', 'phone' => '+1 555 0123',
            'organization' => 'Example Ltd', 'subject' => 'Confidential matter', 'message' => 'Sensitive details.',
            'ip_address' => '192.0.2.77',
        ]);
        ContactSubmissionNote::factory()->for($submission, 'submission')->count(2)->create();

        $this->get(route('admin.enquiries.delete', $submission))->assertOk()->assertSee('2 internal notes');

        $this->delete(route('admin.enquiries.destroy', $submission), ['confirm' => '1'])
            ->assertRedirect(route('admin.enquiries.index'));

        $this->assertModelMissing($submission);
        $this->assertDatabaseCount('contact_submission_notes', 0);

        $log = AuditLog::where('action', 'deleted')->sole();
        $this->assertSame($admin->id, $log->user_id);
        $dumped = json_encode($log->old_values);
        foreach (['Ada Example', 'ada@example.com', '+1 555 0123', 'Example Ltd', 'Confidential matter', 'Sensitive details.', '192.0.2.77'] as $personal) {
            $this->assertStringNotContainsString($personal, $dumped, "Audit log must not contain \"{$personal}\".");
        }
    }

    // Listing: search, filter, pagination, N+1 --------------------------------

    public function test_list_is_searchable_filterable_and_free_of_n_plus_one(): void
    {
        $this->as('administrator');
        ContactSubmission::factory()->create(['name' => 'Findable Person', 'organization' => 'Acme']);
        ContactSubmission::factory()->closed()->create(['name' => 'Someone Else']);
        // At least one assignee from the start, so the eager-load query for
        // "assignee" already fires in the baseline count below: Eloquent
        // skips a BelongsTo eager query entirely when nothing on the page
        // has a foreign key to look up, which would otherwise make the
        // "before" and "after" query counts incomparable.
        ContactSubmission::factory()->assignedTo()->create();

        $this->get(route('admin.enquiries.index', ['q' => 'Findable']))->assertOk()
            ->assertViewHas('submissions', fn ($p) => $p->total() === 1);

        $this->get(route('admin.enquiries.index', ['status' => 'closed']))->assertOk()
            ->assertViewHas('submissions', fn ($p) => $p->total() === 1)
            ->assertSee('Someone Else');

        $count = function () {
            $n = 0;
            DB::listen(function () use (&$n) {
                $n++;
            });
            $this->get(route('admin.enquiries.index'))->assertOk();
            DB::getEventDispatcher()->forget('Illuminate\Database\Events\QueryExecuted');

            return $n;
        };
        $few = $count();
        ContactSubmission::factory()->assignedTo()->count(10)->create();
        $this->assertSame($few, $count());

        ContactSubmission::factory()->count(ContactSubmissionController::PER_PAGE)->create();
        $this->get(route('admin.enquiries.index'))
            ->assertViewHas('submissions', fn ($p) => $p->count() === ContactSubmissionController::PER_PAGE);
    }

    // Export (FR-ADM-10) --------------------------------------------------------

    public function test_export_streams_a_csv_of_all_visible_fields_and_is_audited(): void
    {
        $admin = $this->as('administrator');
        $submission = ContactSubmission::factory()->create([
            'name' => 'Ada Example', 'email' => 'ada@example.com', 'phone' => '+1 555 0123',
            'organization' => 'Example Ltd', 'subject' => 'Governance review', 'message' => 'Hello there',
            'ip_address' => '192.0.2.60',
        ]);

        $csv = $this->get(route('admin.enquiries.export'))
            ->assertOk()
            ->assertHeader('Content-Type', 'text/csv; charset=UTF-8')
            ->streamedContent();

        $this->assertStringContainsString('Ada Example', $csv);
        $this->assertStringContainsString('ada@example.com', $csv);
        $this->assertStringContainsString('Example Ltd', $csv);
        $this->assertStringContainsString('Governance review', $csv);
        $this->assertStringContainsString('Hello there', $csv);
        // Administrator holds enquiries.respond, so IP is included here too.
        $this->assertStringContainsString('192.0.2.60', $csv);
        $this->assertStringContainsString('IP address', $csv, 'Column header must be present when IP is included.');

        $log = AuditLog::firstWhere('action', 'exported');
        $this->assertSame($admin->id, $log->user_id);
        $this->assertSame(1, $log->new_values['row_count']);
        foreach (['Ada Example', 'ada@example.com', 'Example Ltd', 'Governance review', 'Hello there'] as $personal) {
            $this->assertStringNotContainsString($personal, json_encode($log->new_values), "Audit log must not contain \"{$personal}\".");
        }
    }

    public function test_export_respects_the_current_search_and_status_filters(): void
    {
        $this->as('administrator');
        ContactSubmission::factory()->create(['name' => 'Findable Person', 'organization' => 'Acme']);
        ContactSubmission::factory()->closed()->create(['name' => 'Someone Else']);

        $csv = $this->get(route('admin.enquiries.export', ['q' => 'Findable']))->assertOk()->streamedContent();
        $this->assertStringContainsString('Findable Person', $csv);
        $this->assertStringNotContainsString('Someone Else', $csv);

        $csv = $this->get(route('admin.enquiries.export', ['status' => 'closed']))->assertOk()->streamedContent();
        $this->assertStringContainsString('Someone Else', $csv);
        $this->assertStringNotContainsString('Findable Person', $csv);
    }

    public function test_ip_address_is_excluded_from_the_export_without_enquiries_respond(): void
    {
        ContactSubmission::factory()->create(['name' => 'Ada Example', 'ip_address' => '192.0.2.61']);

        // No seeded role holds enquiries.export without enquiries.respond
        // (plan §6 grants them together), so this exercises the export's own
        // gate with an ad-hoc role rather than relying on that always being
        // true — the same defensive check the detail page already needs.
        $role = Role::factory()->create(['slug' => 'export-only-enquiries']);
        $role->permissions()->attach([
            Permission::firstOrCreate(['slug' => 'enquiries.view'], ['name' => 'Enquiries View', 'group' => 'enquiries'])->id,
            Permission::firstOrCreate(['slug' => 'enquiries.export'], ['name' => 'Enquiries Export', 'group' => 'enquiries'])->id,
        ]);
        $this->actingAs(User::factory()->withRole($role)->create());

        $csv = $this->get(route('admin.enquiries.export'))->assertOk()->streamedContent();

        $this->assertStringContainsString('Ada Example', $csv);
        $this->assertStringNotContainsString('192.0.2.61', $csv);
        $this->assertStringNotContainsString('IP address', $csv);
    }

    public function test_editors_and_authors_cannot_export(): void
    {
        foreach (['editor', 'author'] as $role) {
            $this->as($role);
            $this->get(route('admin.enquiries.export'))->assertForbidden();
        }
    }

    // Dashboard links ----------------------------------------------------------

    public function test_dashboard_links_recent_enquiries_and_the_new_enquiries_alert_to_the_inbox(): void
    {
        $this->as('administrator');
        $submission = ContactSubmission::factory()->create(['subject' => 'Needs a reply']);

        $html = $this->get(route('admin.dashboard'))->assertOk()->getContent();

        $this->assertStringContainsString('href="'.route('admin.enquiries.show', $submission).'"', $html);
        $this->assertStringContainsString('href="'.route('admin.enquiries.index', ['status' => 'new']).'"', $html);
        $this->assertStringContainsString('1 new enquiry awaiting a response.', $html);
    }
}
