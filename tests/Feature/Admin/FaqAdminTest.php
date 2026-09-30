<?php

namespace Tests\Feature\Admin;

use App\Http\Controllers\Admin\FaqController;
use App\Models\AuditLog;
use App\Models\Faq;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class FaqAdminTest extends TestCase
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
    private function publicQuestions(): array
    {
        $this->app->forgetScopedInstances();

        return array_column($this->get(route('contact'))->assertOk()->viewData('faqs'), 'question');
    }

    // Create -----------------------------------------------------------------

    public function test_admin_creates_an_faq_at_the_end_with_audit(): void
    {
        $admin = $this->as('administrator');
        Faq::factory()->create(['sort_order' => 4]);

        $response = $this->post(route('admin.faqs.store'), [
            'question' => 'Do you work internationally?',
            'answer' => 'Yes, across the region.',
            'is_active' => '1',
        ]);

        $faq = Faq::firstWhere('question', 'Do you work internationally?');
        $response->assertRedirect(route('admin.faqs.edit', $faq))->assertSessionHasNoErrors();
        $this->assertSame(5, $faq->sort_order);
        $this->assertTrue($faq->is_active);

        $log = AuditLog::firstWhere('action', 'created');
        $this->assertSame([$admin->id, 'faq', $faq->id], [$log->user_id, $log->entity_type, $log->entity_id]);
        $this->assertSame('Do you work internationally?', $log->new_values['question']);
    }

    /**
     * @return array<string, array{array<string, mixed>, string}>
     */
    public static function invalidInput(): array
    {
        return [
            'question missing' => [['question' => ''], 'question'],
            'question too long' => [['question' => str_repeat('a', 501)], 'question'],
            'answer missing' => [['answer' => ''], 'answer'],
            'answer too long' => [['answer' => str_repeat('a', 5001)], 'answer'],
        ];
    }

    #[DataProvider('invalidInput')]
    public function test_invalid_input_is_rejected(array $input, string $field): void
    {
        $this->as('administrator');

        $this->from(route('admin.faqs.create'))
            ->post(route('admin.faqs.store'), $input + ['question' => 'Valid question?', 'answer' => 'Valid answer.'])
            ->assertRedirect(route('admin.faqs.create'))
            ->assertSessionHasErrors($field);

        $this->assertSame(0, Faq::count());
        $this->assertSame(0, AuditLog::count());
    }

    // Update -------------------------------------------------------------------

    public function test_update_records_only_the_changed_fields(): void
    {
        $this->as('administrator');
        $faq = Faq::factory()->create(['question' => 'Old question?', 'answer' => 'Same answer.']);

        $this->put(route('admin.faqs.update', $faq), [
            'question' => 'New question?', 'answer' => 'Same answer.', 'is_active' => '1',
        ])->assertRedirect(route('admin.faqs.edit', $faq));

        $log = AuditLog::firstWhere('action', 'updated');
        $this->assertSame(['question' => 'Old question?'], $log->old_values);
        $this->assertSame(['question' => 'New question?'], $log->new_values);
    }

    public function test_editor_edits_content_but_not_status_and_cannot_create_delete_or_reorder(): void
    {
        $this->as('editor');
        $faq = Faq::factory()->create(['question' => 'Old?', 'is_active' => true]);

        $this->get(route('admin.faqs.edit', $faq))->assertOk()
            ->assertDontSee('id="field-is_active"', false)
            ->assertSee('Only administrators can change this.');

        // A crafted is_active is ignored for Editors.
        $this->put(route('admin.faqs.update', $faq), ['question' => 'Edited?', 'answer' => 'x', 'is_active' => '0'])
            ->assertSessionHasNoErrors();
        $this->assertSame('Edited?', $faq->fresh()->question);
        $this->assertTrue($faq->fresh()->is_active);

        $this->get(route('admin.faqs.create'))->assertForbidden();
        $this->post(route('admin.faqs.store'), ['question' => 'X?', 'answer' => 'x'])->assertForbidden();
        $this->post(route('admin.faqs.move', $faq), ['direction' => 'up'])->assertForbidden();
        $this->delete(route('admin.faqs.destroy', $faq), ['confirm' => '1'])->assertForbidden();
        $this->assertModelExists($faq);
    }

    public function test_author_cannot_reach_faqs(): void
    {
        $this->as('author');
        $faq = Faq::factory()->create();

        $this->get(route('admin.faqs.index'))->assertForbidden();
        $this->get(route('admin.faqs.edit', $faq))->assertForbidden();
        $this->get('/admin')->assertDontSee(route('admin.faqs.index'), false);
    }

    // Reorder and delete ---------------------------------------------------

    public function test_move_reorders_and_audits(): void
    {
        $this->as('administrator');
        $a = Faq::factory()->create(['question' => 'A?', 'sort_order' => 1]);
        $b = Faq::factory()->create(['question' => 'B?', 'sort_order' => 2]);

        $this->post(route('admin.faqs.move', $b), ['direction' => 'up'])->assertRedirect();

        $this->assertSame(['B?', 'A?'], Faq::query()->ordered()->pluck('question')->all());
        $this->assertSame('reordered', AuditLog::sole()->action);
    }

    public function test_delete_needs_confirmation_and_audits(): void
    {
        $this->as('administrator');
        $faq = Faq::factory()->create(['question' => 'Temporary?']);

        $this->delete(route('admin.faqs.destroy', $faq))->assertSessionHasErrors('confirm');
        $this->assertModelExists($faq);

        $this->delete(route('admin.faqs.destroy', $faq), ['confirm' => '1'])
            ->assertRedirect(route('admin.faqs.index'));

        $this->assertModelMissing($faq);
        $this->assertSame('Temporary?', AuditLog::firstWhere('action', 'deleted')->old_values['question']);
    }

    // List -----------------------------------------------------------------

    public function test_list_is_searchable_paginated_and_escaped(): void
    {
        $this->as('administrator');
        Faq::factory()->create(['question' => '<script>alert(1)</script> question?']);
        Faq::factory()->count(3)->create();

        $this->get(route('admin.faqs.index', ['q' => 'script']))->assertOk()
            ->assertViewHas('faqs', fn ($p) => $p->total() === 1)
            ->assertDontSee('<script>alert(1)</script>', false)
            ->assertSee('&lt;script&gt;alert(1)&lt;/script&gt; question?', false);

        Faq::factory()->count(FaqController::PER_PAGE)->create();
        $this->get(route('admin.faqs.index'))->assertViewHas('faqs', fn ($p) => $p->count() === FaqController::PER_PAGE);
    }

    // Public site ----------------------------------------------------------

    public function test_public_contact_page_reflects_admin_changes(): void
    {
        $this->as('administrator');
        $this->post(route('admin.faqs.store'), ['question' => 'First question?', 'answer' => 'a', 'is_active' => '1']);
        $this->post(route('admin.faqs.store'), ['question' => 'Second question?', 'answer' => 'a', 'is_active' => '1']);
        $this->assertSame(['First question?', 'Second question?'], $this->publicQuestions());

        $second = Faq::firstWhere('question', 'Second question?');
        $this->put(route('admin.faqs.update', $second), ['question' => 'Second question?', 'answer' => 'a', 'is_active' => '0']);
        $this->assertSame(['First question?'], $this->publicQuestions());
    }

    public function test_the_contact_page_hides_the_section_entirely_when_there_are_no_active_faqs(): void
    {
        Faq::factory()->inactive()->create();

        $this->app->forgetScopedInstances();
        $this->get(route('contact'))->assertOk()->assertDontSee('Frequently asked questions');
    }
}
