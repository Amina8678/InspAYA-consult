<?php

namespace App\Http\Controllers\Admin;

use App\Enums\EnquiryStatus;
use App\Enums\PendingRevisionStatus;
use App\Enums\PostStatus;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\BlogPost;
use App\Models\Consultant;
use App\Models\ContactSubmission;
use App\Models\Page;
use App\Models\PendingRevision;
use App\Models\Service;
use App\Models\User;
use App\Notifications\EnquiryAcknowledgement;
use App\Notifications\EnquiryReceived;
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

    /** A queued job older than this means the worker is probably not running. */
    private const STUCK_JOB_MINUTES = 15;

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
                ? AuditLog::query()->with(['user:id,name,role_id', 'user.role:id,name'])->latest('created_at')->latest('id')->limit(self::ACTIVITY)->get()
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
     * A plain string is shown as text; ['message', 'url'] is shown as a link
     * (e.g. into the enquiry inbox).
     *
     * @return list<string|array{message: string, url: string}>
     */
    private function alerts(User $user): array
    {
        $alerts = [];

        if ($user->can('enquiries.view')) {
            $new = ContactSubmission::where('status', EnquiryStatus::New)->count();
            if ($new > 0) {
                $alerts[] = [
                    'message' => "{$new} new ".str('enquiry')->plural($new).' awaiting a response.',
                    'url' => route('admin.enquiries.index', ['status' => 'new']),
                ];
            }

            // The enquiry is saved before any email is queued, so a failed
            // notification means staff were never told, not that it was lost.
            $unsent = DB::table('failed_jobs')->where('payload', 'like', '%'.class_basename(EnquiryReceived::class).'%')->count();
            if ($unsent > 0) {
                $alerts[] = [
                    'message' => "{$unsent} contact-form ".str('notification')->plural($unsent).' to the company could not be emailed after several attempts. '
                        .'The enquiries are saved: check the inbox for any you have not seen.',
                    'url' => route('admin.enquiries.index'),
                ];
            }
        }

        if ($user->can('content.publish')) {
            $pending = PendingRevision::query()->pending()->withLiveRecord()->count();
            if ($pending > 0) {
                $alerts[] = [
                    'message' => "{$pending} content ".str('change')->plural($pending).' awaiting approval.',
                    'url' => route('admin.content-approvals.index'),
                ];
            }
        } else {
            // Only non-publishers ever submit a pending revision, so this is
            // empty (and skipped) for anyone who publishes directly.
            $mine = PendingRevision::query()
                ->where('submitted_by', $user->id)
                ->whereIn('status', [PendingRevisionStatus::Pending, PendingRevisionStatus::Rejected])
                ->with('revisable')
                ->latest('id')
                ->get()
                ->filter(fn (PendingRevision $r) => $r->revisable !== null)
                // Keep only the latest row per record: a rejection followed
                // by a resubmission leaves the old rejected row in place as
                // history, but it's superseded and shouldn't still alert.
                ->unique(fn (PendingRevision $r) => $r->revisable_type.':'.$r->revisable_id);

            foreach ($mine as $revision) {
                $alerts[] = $revision->status === PendingRevisionStatus::Pending
                    ? 'Your edit to "'.$revision->revisable->title.'" is awaiting approval.'
                    : 'Your edit to "'.$revision->revisable->title.'" was rejected: '.$revision->rejection_reason;
            }
        }

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

            $alerts = [...$alerts, ...$this->queueAlerts()];
        }

        return $alerts;
    }

    /**
     * Background-queue problems, for people who can fix the server side.
     *
     * @return list<string>
     */
    private function queueAlerts(): array
    {
        $alerts = [];

        $failed = DB::table('failed_jobs')->count();
        $enquiryEmails = DB::table('failed_jobs')->where('payload', 'like', '%'.class_basename(EnquiryReceived::class).'%')->count();
        $visitorEmails = DB::table('failed_jobs')->where('payload', 'like', '%'.class_basename(EnquiryAcknowledgement::class).'%')->count();
        $other = $failed - $enquiryEmails;

        if ($other > 0) {
            $alerts[] = "{$other} background ".str('job')->plural($other).' failed'
                .($visitorEmails > 0 ? " (including {$visitorEmails} confirmation ".str('email')->plural($visitorEmails).' to visitors)' : '')
                .'. Run "php artisan queue:failed" on the server, fix the cause (usually mail settings), then "php artisan queue:retry all".';
        }

        // A worker that is not running leaves jobs sitting here unsent.
        $stuck = DB::table('jobs')->where('created_at', '<', now()->subMinutes(self::STUCK_JOB_MINUTES)->timestamp)->count();
        if ($stuck > 0) {
            $alerts[] = 'Queued jobs (such as emails) have been waiting over '.self::STUCK_JOB_MINUTES." minutes ({$stuck} so far). The queue worker may not be running.";
        }

        return $alerts;
    }
}
