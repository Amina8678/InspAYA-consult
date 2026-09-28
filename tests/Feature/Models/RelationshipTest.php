<?php

namespace Tests\Feature\Models;

use App\Models\AuditLog;
use App\Models\BlogPost;
use App\Models\Category;
use App\Models\Consultant;
use App\Models\ContactSubmission;
use App\Models\ContactSubmissionNote;
use App\Models\CoreValue;
use App\Models\Media;
use App\Models\Page;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Service;
use App\Models\SiteSetting;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Every relationship resolves from both sides.
 */
class RelationshipTest extends TestCase
{
    use RefreshDatabase;

    public function test_role_and_users(): void
    {
        $role = Role::factory()->create();
        $user = User::factory()->withRole($role)->create();

        $this->assertTrue($user->role->is($role));
        $this->assertTrue($role->users->contains($user));
    }

    public function test_user_factory_reuses_the_author_role(): void
    {
        User::factory()->count(3)->create();

        $this->assertSame(1, Role::where('slug', 'author')->count());
        $this->assertSame(3, Role::where('slug', 'author')->first()->users()->count());
    }

    public function test_roles_and_permissions(): void
    {
        $role = Role::factory()->create();
        $permission = Permission::factory()->create();
        $role->permissions()->attach($permission);

        $this->assertTrue($role->permissions->contains($permission));
        $this->assertTrue($permission->roles->contains($role));
    }

    public function test_blog_post_author_category_image_and_tags(): void
    {
        $post = BlogPost::factory()->categorised()->withFeaturedImage()->create();
        $tag = Tag::factory()->create();
        $post->tags()->attach($tag);

        $this->assertTrue($post->author->blogPosts->contains($post));
        $this->assertTrue($post->category->blogPosts->contains($post));
        $this->assertTrue($post->featuredImage->blogPosts->contains($post));
        $this->assertTrue($post->tags->contains($tag));
        $this->assertTrue($tag->blogPosts->contains($post));
    }

    public function test_media_uploader(): void
    {
        $media = Media::factory()->create();

        $this->assertTrue($media->uploader->uploadedMedia->contains($media));
    }

    public function test_consultant_photo_and_core_value_icon(): void
    {
        $consultant = Consultant::factory()->withPhoto()->create();
        $value = CoreValue::factory()->withIcon()->create();

        $this->assertTrue($consultant->photo->consultants->contains($consultant));
        $this->assertTrue($value->icon->coreValues->contains($value));
    }

    public function test_services_and_consultants_with_pivot_data(): void
    {
        $service = Service::factory()->create();
        [$lead, $support] = Consultant::factory()->count(2)->create();
        $service->consultants()->attach([
            $support->id => ['is_lead' => false, 'sort_order' => 2],
            $lead->id => ['is_lead' => true, 'sort_order' => 1],
        ]);

        $this->assertSame([$lead->id, $support->id], $service->consultants->pluck('id')->all());
        $this->assertSame([$lead->id], $service->leadConsultants->pluck('id')->all());
        $this->assertTrue($lead->services->contains($service));
        $this->assertTrue((bool) $lead->services->first()->pivot->is_lead);
    }

    public function test_site_setting_media(): void
    {
        $setting = SiteSetting::factory()->media()->create();

        $this->assertTrue($setting->media->siteSettings->contains($setting));
    }

    public function test_contact_submission_assignee_and_notes(): void
    {
        $staff = User::factory()->create();
        $enquiry = ContactSubmission::factory()->assignedTo($staff)->create();
        $note = ContactSubmissionNote::factory()
            ->for($enquiry, 'submission')
            ->for($staff, 'user')
            ->create();

        $this->assertTrue($enquiry->assignee->is($staff));
        $this->assertTrue($staff->assignedEnquiries->contains($enquiry));
        $this->assertTrue($enquiry->notes->contains($note));
        $this->assertTrue($note->submission->is($enquiry));
        $this->assertTrue($note->user->is($staff));
        $this->assertTrue($staff->enquiryNotes->contains($note));
    }

    public function test_audit_log_actor_and_polymorphic_entity(): void
    {
        $page = Page::factory()->create();
        $log = AuditLog::factory()->forEntity($page)->create();

        $this->assertSame('page', $log->entity_type);
        $this->assertTrue($log->auditable->is($page));
        $this->assertTrue($page->auditTrail->contains($log));
        $this->assertTrue($log->user->auditLogs->contains($log));
    }
}
