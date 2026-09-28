<?php

namespace App\Providers;

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
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Stable aliases for audit_logs.entity_type, so stored rows don't
        // depend on PHP class names.
        Relation::enforceMorphMap([
            'audit_log' => AuditLog::class,
            'blog_post' => BlogPost::class,
            'category' => Category::class,
            'consultant' => Consultant::class,
            'contact_submission' => ContactSubmission::class,
            'contact_submission_note' => ContactSubmissionNote::class,
            'core_value' => CoreValue::class,
            'media' => Media::class,
            'page' => Page::class,
            'permission' => Permission::class,
            'role' => Role::class,
            'service' => Service::class,
            'site_setting' => SiteSetting::class,
            'tag' => Tag::class,
            'user' => User::class,
        ]);
    }
}
