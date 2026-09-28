<?php

namespace App\Http\Controllers\Admin;

use App\Enums\EnquiryStatus;
use App\Enums\PostStatus;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\BlogPost;
use App\Models\Consultant;
use App\Models\ContactSubmission;
use App\Models\Page;
use App\Models\Service;
use App\Models\User;
use App\Support\SiteSettings;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * FR-ADM-01: content counts, recent enquiries, recent posts, publication
 * activity and system alerts. Every panel is gated by the viewer's
 * permissions; Authors see their own posts only.
 */
class DashboardController extends Controller
{
    private const RECENT = 5;

    private const ACTIVITY = 10;

    public function index(Request $request): View
    {
        $user = $request->user();
        $posts = $this->postScope($user);

        return view('admin.dashboard', [
            'stats' => $this->stats($user, $posts),
            'recentPosts' => $posts?->clone()->with('author:id,name')->latest('updated_at')->latest('id')->limit(self::RECENT)->get(),
            'ownPostsOnly' => $posts !== null && ! $user->can('posts.edit-any'),
            'recentEnquiries' => $user->can('enquiries.view')
                ? ContactSubmission::query()->latest()->latest('id')->limit(self::RECENT)->get(['id', 'name', 'subject', 'status', 'created_at'])
                : null,
            'activity' => $user->can('audit-logs.view')
                ? AuditLog::query()->with('user:id,name')->latest('created_at')->latest('id')->limit(self::ACTIVITY)->get()
                : null,
            'alerts' => $this->alerts($user),
        ]);
    }

    /**
     * Posts the user may see: all (posts.edit-any), their own (posts.edit-own),
     * or none.
     *
     * @return Builder<BlogPost>|null
     */
    private function postScope(User $user): ?Builder
    {
        if ($user->can('posts.edit-any')) {
            return BlogPost::query();
        }

        return $user->can('posts.edit-own') ? BlogPost::query()->where('author_id', $user->id) : null;
    }

    /**
     * @param  Builder<BlogPost>|null  $posts
     * @return list<array{label: string, value: int}>
     */
    private function stats(User $user, ?Builder $posts): array
    {
        $stats = [];
        $canAny = fn (string $area) => $user->can("{$area}.edit") || $user->can("{$area}.manage");

        if ($canAny('pages')) {
            $stats[] = ['label' => 'Pages', 'value' => Page::count()];
        }
        if ($canAny('services')) {
            $stats[] = ['label' => 'Services', 'value' => Service::count()];
        }
        if ($canAny('consultants')) {
            $stats[] = ['label' => 'Consultants', 'value' => Consultant::count()];
        }
        if ($posts !== null) {
            $prefix = $user->can('posts.edit-any') ? '' : 'Your ';
            $stats[] = ['label' => $prefix.'published posts', 'value' => $posts->clone()->published()->count()];
            $stats[] = ['label' => $prefix.'posts in review', 'value' => $posts->clone()->where('status', PostStatus::Review)->count()];
            $stats[] = ['label' => $prefix.'draft posts', 'value' => $posts->clone()->where('status', PostStatus::Draft)->count()];
        }
        if ($user->can('enquiries.view')) {
            $stats[] = ['label' => 'New enquiries', 'value' => ContactSubmission::where('status', EnquiryStatus::New)->count()];
        }

        return array_map(fn (array $s) => ['label' => ucfirst($s['label']), 'value' => $s['value']], $stats);
    }

    /**
     * System alerts (FR-ADM-01), shown only to people who can act on them.
     *
     * @return list<string>
     */
    private function alerts(User $user): array
    {
        $alerts = [];

        if ($user->can('pages.manage') || $user->can('content.publish')) {
            $published = Page::query()->published()->whereIn('slug', ['privacy-policy', 'terms-of-service'])->pluck('slug');

            foreach (['privacy-policy' => 'Privacy Policy', 'terms-of-service' => 'Terms of Service'] as $slug => $title) {
                if (! $published->contains($slug)) {
                    $alerts[] = "The {$title} page is not published. It is required before launch (FR-LEGAL-01).";
                }
            }
        }

        if ($user->can('settings.manage')) {
            if (blank(app(SiteSettings::class)->get('email.enquiry_recipient'))) {
                $alerts[] = 'No enquiry notification address is set, so enquiry emails go to the default sender address.';
            }

            $failed = DB::table('failed_jobs')->count();
            if ($failed > 0) {
                $alerts[] = "{$failed} background ".str('job')->plural($failed).' (such as emails) failed. Check the queue worker logs.';
            }
        }

        return $alerts;
    }
}
