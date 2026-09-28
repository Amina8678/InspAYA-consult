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
use App\Support\SiteSettings;
use App\View\Composers\SiteLayoutComposer;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\View;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Once per request (scoped, not singleton, so long-running workers
        // never serve stale settings).
        $this->app->scoped(SiteSettings::class);
        $this->app->scoped(SiteLayoutComposer::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Fail loudly on N+1 queries outside production; views receive plain
        // arrays, so every relation must be eager loaded by the controller.
        Model::preventLazyLoading(! $this->app->isProduction());

        View::composer('public.*', SiteLayoutComposer::class);

        // Password policy for every password set through the app (changes and
        // resets). The SRS defines none beyond hashing (NFR-SEC-01); see
        // docs/admin-auth.md.
        Password::defaults(fn () => Password::min(12)->letters()->mixedCase()->numbers()->symbols());

        // Reset emails link to the admin reset page (there is no public one).
        ResetPassword::createUrlUsing(fn (User $user, string $token) => route('admin.password.reset', [
            'token' => $token,
            'email' => $user->getEmailForPasswordReset(),
        ]));

        // Forgot/reset forms: per email + IP, on top of the broker's own
        // one-link-per-minute throttle.
        RateLimiter::for('password-reset', fn (Request $request) => Limit::perMinute(5)
            ->by(Str::lower((string) $request->input('email')).'|'.$request->ip()));

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
