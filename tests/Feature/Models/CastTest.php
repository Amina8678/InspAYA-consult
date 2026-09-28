<?php

namespace Tests\Feature\Models;

use App\Enums\EnquiryStatus;
use App\Enums\PageStatus;
use App\Enums\PostStatus;
use App\Enums\SettingType;
use App\Enums\UserStatus;
use App\Models\AuditLog;
use App\Models\BlogPost;
use App\Models\ContactSubmission;
use App\Models\Page;
use App\Models\Service;
use App\Models\SiteSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use LogicException;
use Tests\TestCase;
use ValueError;

class CastTest extends TestCase
{
    use RefreshDatabase;

    public function test_status_fields_cast_to_enums_and_store_their_values(): void
    {
        $user = User::factory()->inactive()->create();
        $page = Page::factory()->published()->create();
        $post = BlogPost::factory()->inReview()->create();
        $enquiry = ContactSubmission::factory()->inProgress()->create();
        $setting = SiteSetting::factory()->media()->create();

        $this->assertSame(UserStatus::Inactive, $user->fresh()->status);
        $this->assertSame(PageStatus::Published, $page->fresh()->status);
        $this->assertSame(PostStatus::Review, $post->fresh()->status);
        $this->assertSame(EnquiryStatus::InProgress, $enquiry->fresh()->status);
        $this->assertSame(SettingType::Media, $setting->fresh()->type);

        $this->assertDatabaseHas('contact_submissions', ['id' => $enquiry->id, 'status' => 'in_progress']);
    }

    public function test_new_models_default_to_initial_status(): void
    {
        $this->assertSame(UserStatus::Active, (new User)->status);
        $this->assertSame(PageStatus::Draft, (new Page)->status);
        $this->assertSame(PostStatus::Draft, (new BlogPost)->status);
        $this->assertSame(EnquiryStatus::New, (new ContactSubmission)->status);
        $this->assertSame(SettingType::String, (new SiteSetting)->type);
    }

    public function test_unknown_status_value_in_database_fails_loudly(): void
    {
        $post = BlogPost::factory()->create();
        DB::table('blog_posts')->where('id', $post->id)->update(['status' => 'scheduled']);

        $this->expectException(ValueError::class);

        $post->fresh()->status;
    }

    public function test_json_columns_round_trip_as_arrays(): void
    {
        $service = Service::factory()->create([
            'capabilities' => ['Board evaluations', 'Policy design'],
            'outcomes' => ['Clear governance framework'],
        ]);
        $page = Page::factory()->create([
            'structured_content' => [['type' => 'text', 'data' => ['body' => 'Hello']]],
        ]);

        $service = $service->fresh();
        $this->assertSame(['Board evaluations', 'Policy design'], $service->capabilities);
        $this->assertSame(['Clear governance framework'], $service->outcomes);
        $this->assertSame('Hello', $page->fresh()->structured_content[0]['data']['body']);
        $this->assertIsBool($service->is_active);
    }

    public function test_two_factor_secrets_are_encrypted_at_rest_and_hidden(): void
    {
        $user = User::factory()->create();
        $user->forceFill(['two_factor_secret' => 'SECRET123'])->save();

        $this->assertSame('SECRET123', $user->fresh()->two_factor_secret);
        $this->assertNotSame('SECRET123', DB::table('users')->where('id', $user->id)->value('two_factor_secret'));
        $this->assertArrayNotHasKey('two_factor_secret', $user->fresh()->toArray());
    }

    public function test_access_fields_are_not_mass_assignable(): void
    {
        $user = new User(['role_id' => 1, 'status' => 'inactive', 'name' => 'Placeholder']);
        $post = new BlogPost(['author_id' => 1, 'title' => 'Placeholder']);

        $this->assertNull($user->role_id);
        $this->assertSame(UserStatus::Active, $user->status);
        $this->assertNull($post->author_id);
    }

    public function test_audit_logs_are_append_only(): void
    {
        $log = AuditLog::factory()->create();

        try {
            $log->update(['action' => 'tampered']);
            $this->fail('Updating an audit log should throw.');
        } catch (LogicException) {
        }

        try {
            $log->delete();
            $this->fail('Deleting an audit log should throw.');
        } catch (LogicException) {
        }

        $this->assertDatabaseHas('audit_logs', ['id' => $log->id, 'action' => 'login']);
    }
}
