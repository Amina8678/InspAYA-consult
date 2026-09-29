<?php

namespace Tests\Feature\Admin;

use App\Models\AuditLog;
use App\Models\Media;
use App\Models\SiteSetting;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\SiteSettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class SiteSettingsAdminTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(SiteSettingsSeeder::class);
    }

    private function asAdmin(): User
    {
        $user = User::factory()->withRole('administrator')->create();
        $this->actingAs($user);

        return $user;
    }

    private function value(string $key): ?string
    {
        return SiteSetting::where('key', $key)->value('value');
    }

    public function test_screen_shows_grouped_labelled_fields_and_a_media_picker(): void
    {
        $this->asAdmin();
        $logo = Media::factory()->create(['file_name' => 'logo.png', 'alt_text' => 'Logo']);
        SiteSetting::where('key', 'branding.logo')->update(['media_id' => $logo->id]);

        $html = $this->get(route('admin.settings.edit'))->assertOk()->getContent();

        foreach (['Branding', 'Contact details', 'Social links', 'Search and sharing defaults', 'Analytics', 'Email'] as $group) {
            $this->assertStringContainsString('<h2>'.$group.'</h2>', $html);
        }
        $this->assertStringContainsString('<label for="field-contact__email">', $html);
        $this->assertStringContainsString('type="url"', $html);
        $this->assertMatchesRegularExpression('~<select id="field-branding__logo" name="branding__logo"~', $html);
        $this->assertMatchesRegularExpression('~<option value="'.$logo->id.'"[^>]*selected~', $html);
        $this->assertSame(1, substr_count($html, '<h1'));
    }

    public function test_valid_changes_are_saved_and_audited_with_old_and_new_values(): void
    {
        $user = $this->asAdmin();
        $image = Media::factory()->create();

        $this->put(route('admin.settings.update'), [
            'branding__site_name' => 'InspAya Consult Ltd',
            'contact__email' => 'office@example.com',
            'social__linkedin' => 'https://www.linkedin.com/company/example',
            'seo__default_og_image' => (string) $image->id,
        ])->assertRedirect(route('admin.settings.edit'))->assertSessionHas('status', 'Settings saved (4 changed).');

        $this->assertSame('InspAya Consult Ltd', $this->value('branding.site_name'));
        $this->assertSame('office@example.com', $this->value('contact.email'));
        $this->assertSame($image->id, SiteSetting::where('key', 'seo.default_og_image')->value('media_id'));

        $log = AuditLog::firstWhere('action', 'settings_updated');
        $this->assertSame($user->id, $log->user_id);
        $this->assertSame('hello@example.com', $log->old_values['contact.email']);
        $this->assertSame('office@example.com', $log->new_values['contact.email']);
        $this->assertCount(4, $log->new_values);
    }

    public function test_a_partial_submission_leaves_other_settings_alone(): void
    {
        $this->asAdmin();

        $this->put(route('admin.settings.update'), ['contact__phone' => '+1 555 0199'])->assertSessionHasNoErrors();

        $this->assertSame('+1 555 0199', $this->value('contact.phone'));
        $this->assertSame('hello@example.com', $this->value('contact.email'));
        $this->assertSame('InspAya Consult', $this->value('branding.site_name'));
    }

    public function test_emptying_a_field_clears_it_and_no_change_writes_no_audit(): void
    {
        $this->asAdmin();

        $this->put(route('admin.settings.update'), ['contact__address' => '   ']);
        $this->assertNull($this->value('contact.address'));

        $count = AuditLog::count();
        $this->put(route('admin.settings.update'), ['contact__email' => 'hello@example.com'])
            ->assertSessionHas('status', 'No changes to save.');
        $this->assertSame($count, AuditLog::count());
    }

    /**
     * @return array<string, array{string, mixed}>
     */
    public static function invalidValues(): array
    {
        return [
            'site name required' => ['branding__site_name', ''],
            'site name too long' => ['branding__site_name', str_repeat('a', 256)],
            'bad email' => ['contact__email', 'not-an-email'],
            'phone with letters' => ['contact__phone', 'call us'],
            'javascript link' => ['social__linkedin', 'javascript:alert(1)'],
            'mailto is not a profile' => ['social__x', 'mailto:a@example.com'],
            'site path is not a profile' => ['social__facebook', '/about'],
            'no scheme' => ['social__instagram', 'instagram.com/example'],
            'tracking id with script' => ['analytics__tracking_id', '<script>'],
            'well-formed tracking id is still blocked (FR-LEGAL-02 stopgap)' => ['analytics__tracking_id', 'G-ABC123'],
            'description too long' => ['seo__default_description', str_repeat('a', 301)],
            'media id not an image' => ['branding__logo', 'pdf'],
            'media id missing' => ['branding__footer_logo', '999999'],
        ];
    }

    #[DataProvider('invalidValues')]
    public function test_invalid_values_are_rejected_and_nothing_is_saved(string $field, mixed $value): void
    {
        $this->asAdmin();
        if ($value === 'pdf') {
            $value = (string) Media::factory()->pdf()->create()->id;
        }

        $this->from(route('admin.settings.edit'))
            ->put(route('admin.settings.update'), [$field => $value, 'contact__address' => 'Canary address'])
            ->assertRedirect(route('admin.settings.edit'))
            ->assertSessionHasErrors($field);

        $this->assertNotSame('Canary address', $this->value('contact.address'), 'Nothing is saved when any field is invalid.');
        $this->assertSame(0, AuditLog::where('action', 'settings_updated')->count());
    }

    public function test_errors_are_summarised_and_linked_to_their_fields(): void
    {
        $this->asAdmin();

        $this->followingRedirects()->from(route('admin.settings.edit'))
            ->put(route('admin.settings.update'), ['contact__email' => 'bad'])
            ->assertSee('There is a problem')
            ->assertSee('href="#field-contact__email"', false)
            ->assertSee('aria-invalid="true"', false);
    }

    public function test_missing_settings_are_listed_and_never_created(): void
    {
        $this->asAdmin();
        SiteSetting::where('key', 'analytics.tracking_id')->delete();

        $this->get(route('admin.settings.edit'))->assertOk()
            ->assertSee('not set up in this database')
            ->assertSee('analytics.tracking_id')
            ->assertDontSee('id="field-analytics__tracking_id"', false);

        $this->put(route('admin.settings.update'), ['analytics__tracking_id' => 'G-NEW']);
        $this->assertDatabaseMissing('site_settings', ['key' => 'analytics.tracking_id']);
    }

    public function test_setting_a_tracking_id_is_refused_until_cookie_consent_exists(): void
    {
        $this->asAdmin();

        $this->from(route('admin.settings.edit'))
            ->put(route('admin.settings.update'), ['analytics__tracking_id' => 'G-ABC123'])
            ->assertRedirect(route('admin.settings.edit'))
            ->assertSessionHasErrors(['analytics__tracking_id' => "Analytics can't be enabled yet: this site has no cookie-consent mechanism in place (FR-LEGAL-02), so setting a tracking ID is refused until that ships. Leave it empty for now."]);

        $this->assertNull($this->value('analytics.tracking_id'));
    }

    public function test_clearing_an_existing_tracking_id_is_still_allowed(): void
    {
        $this->asAdmin();
        SiteSetting::where('key', 'analytics.tracking_id')->update(['value' => 'G-OLD']);

        $this->put(route('admin.settings.update'), ['analytics__tracking_id' => ''])
            ->assertRedirect(route('admin.settings.edit'))
            ->assertSessionHasNoErrors();

        $this->assertNull($this->value('analytics.tracking_id'));
    }

    public function test_editor_and_author_cannot_see_or_change_settings(): void
    {
        foreach (['editor', 'author'] as $role) {
            $this->actingAs(User::factory()->withRole($role)->create());

            $this->get(route('admin.settings.edit'))->assertForbidden();
            $this->put(route('admin.settings.update'), ['contact__email' => 'x@example.com'])->assertForbidden();
        }

        $this->assertSame('hello@example.com', $this->value('contact.email'));
    }

    public function test_sidebar_shows_settings_only_to_those_who_can_manage_them(): void
    {
        $this->asAdmin();
        $this->get('/admin')->assertSee('href="'.route('admin.settings.edit').'"', false);

        $this->actingAs(User::factory()->withRole('editor')->create());
        $this->get('/admin')->assertDontSee('href="'.route('admin.settings.edit').'"', false);
    }

    public function test_stored_values_are_escaped_on_the_settings_screen(): void
    {
        $this->asAdmin();
        SiteSetting::where('key', 'contact.address')->update(['value' => '</textarea><script>alert(1)</script>']);

        $this->get(route('admin.settings.edit'))
            ->assertDontSee('<script>alert(1)</script>', false)
            ->assertSee('&lt;/textarea&gt;&lt;script&gt;', false);
    }

    public function test_public_site_uses_the_new_values(): void
    {
        $this->asAdmin();

        $this->put(route('admin.settings.update'), [
            'contact__email' => 'office@example.com',
            'social__linkedin' => 'https://www.linkedin.com/company/example',
        ]);
        $this->app->forgetScopedInstances(); // a new request

        $this->get('/')->assertOk()
            ->assertSee('mailto:office@example.com', false)
            ->assertSee('href="https://www.linkedin.com/company/example"', false);
    }

    public function test_public_site_still_renders_safely_with_bad_values_already_stored(): void
    {
        // Values written before validation existed (or directly in the DB).
        SiteSetting::where('key', 'social.linkedin')->update(['value' => 'javascript:alert(1)']);
        SiteSetting::where('key', 'branding.site_name')->update(['value' => '<script>alert(2)</script>']);
        SiteSetting::where('key', 'analytics.tracking_id')->update(['value' => "G-1');alert(3);//"]);

        $html = $this->get('/')->assertOk()->getContent();

        $this->assertStringNotContainsString('javascript:alert(1)', $html);
        $this->assertStringNotContainsString('<script>alert(2)</script>', $html);
        $this->assertStringContainsString('&lt;script&gt;alert(2)&lt;/script&gt;', $html);
        $this->assertStringNotContainsString('alert(3)', $html, 'The analytics ID is never output on the public site.');
    }
}
