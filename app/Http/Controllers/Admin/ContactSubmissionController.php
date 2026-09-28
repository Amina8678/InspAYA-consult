<?php

namespace App\Http\Controllers\Admin;

use App\Enums\EnquiryStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ContactSubmissionRequest;
use App\Models\ContactSubmission;
use App\Models\User;
use App\Support\AuditLogger;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

/**
 * Enquiry inbox (FR-CONT-04/06, FR-ADM-10). Routes carry permission:
 * middleware; every action also authorizes against ContactSubmissionPolicy.
 * Editors view and respond (status, notes); only Admin+ assign or delete
 * (plan §6 rows 27-31). Visitor-supplied fields are never editable here and
 * are always output escaped — see ground rule in frontend-contract.md §1.4.
 */
class ContactSubmissionController extends Controller
{
    public const PER_PAGE = 20;

    /** Status and assignment only: never the visitor's personal data (see destroy()). */
    private const AUDITED = ['status', 'assigned_to', 'responded_at'];

    public function __construct(private AuditLogger $audit) {}

    public function index(Request $request): View
    {
        $this->authorize('viewAny', ContactSubmission::class);

        $search = trim((string) $request->query('q'));
        $like = '%'.addcslashes($search, '%_\\').'%';
        $statusFilter = in_array($request->query('status'), array_column(EnquiryStatus::cases(), 'value'), true)
            ? $request->query('status')
            : null;

        $submissions = ContactSubmission::query()
            ->with('assignee:id,name')
            ->when($statusFilter !== null, fn ($q) => $q->where('status', $statusFilter))
            ->when($search !== '', fn ($q) => $q->where(fn ($q) => $q
                ->where('name', 'like', $like)
                ->orWhere('email', 'like', $like)
                ->orWhere('organization', 'like', $like)))
            ->latest('created_at')
            ->latest('id')
            ->paginate(self::PER_PAGE, ['id', 'name', 'email', 'organization', 'subject', 'status', 'assigned_to', 'created_at'])
            ->withQueryString();

        return view('admin.enquiries.index', [
            'submissions' => $submissions,
            'search' => $search,
            'statusFilter' => $statusFilter,
            'statuses' => EnquiryStatus::cases(),
        ]);
    }

    public function show(ContactSubmission $submission): View
    {
        $this->authorize('view', $submission);

        return view('admin.enquiries.show', [
            'submission' => $submission->load(['assignee:id,name', 'notes.user:id,name']),
            'assignees' => $this->assigneeOptions(),
        ]);
    }

    public function update(ContactSubmissionRequest $request, ContactSubmission $submission): RedirectResponse
    {
        $before = $submission->only(self::AUDITED);
        $user = $request->user();

        if ($request->filled('status') && $user->can('respond', $submission)) {
            $target = EnquiryStatus::from($request->validated('status'));
            $submission->status = $target;
            // Status changes are explicit only (plan §6/§7 define no
            // auto-read-on-view rule): responded_at is set here, not by viewing.
            if ($target === EnquiryStatus::Responded && $submission->responded_at === null) {
                $submission->responded_at = now();
            }
        }

        if ($request->has('assigned_to') && $user->can('assign', $submission)) {
            $submission->assigned_to = $request->validated('assigned_to') ? (int) $request->validated('assigned_to') : null;
        }

        $changed = array_keys($submission->getDirty());

        if ($changed === []) {
            return redirect()->route('admin.enquiries.show', $submission)->with('status', 'No changes to save.');
        }

        $submission->save();

        $this->audit->record('updated', $user, $submission,
            array_intersect_key($before, array_flip($changed)),
            $submission->only(array_intersect(self::AUDITED, $changed)),
        );

        return redirect()->route('admin.enquiries.show', $submission)->with('status', 'Saved changes.');
    }

    public function confirmDelete(ContactSubmission $submission): View
    {
        $this->authorize('delete', $submission);

        return view('admin.enquiries.delete', ['submission' => $submission->loadCount('notes')]);
    }

    public function destroy(Request $request, ContactSubmission $submission): RedirectResponse
    {
        $this->authorize('delete', $submission);

        $request->validate(['confirm' => ['accepted']], [
            'confirm.accepted' => 'Confirm that you want to delete this enquiry.',
        ]);

        // The audit trail never copies the visitor's personal data (name,
        // email, phone, organization, subject, message, IP): only the
        // handling metadata, which is what staff actually changed.
        $before = $submission->only(self::AUDITED) + ['had_notes' => $submission->notes()->exists()];
        $submission->delete();

        $this->audit->record('deleted', $request->user(), $submission, $before);

        return redirect()->route('admin.enquiries.index')->with('status', 'Deleted the enquiry.');
    }

    /**
     * Every CMS user (the plan places no extra restriction on assigned_to
     * beyond the users.id foreign key, plan §3.15).
     *
     * @return Collection<int, User>
     */
    private function assigneeOptions()
    {
        return User::query()->orderBy('name')->get(['id', 'name']);
    }
}
