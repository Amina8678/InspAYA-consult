<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ConsultantRequest;
use App\Http\Requests\Admin\ServiceRequest;
use App\Models\Consultant;
use App\Models\Service;
use App\Services\Assignments\ServiceConsultantAssignments;
use App\Services\Ordering\SortOrder;
use App\Support\AuditLogger;
use App\Support\MediaPicker;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Consultants (FR-TEAM-01/02, FR-ADM-07). Routes carry permission:
 * middleware; every action also authorizes against ConsultantPolicy
 * (Editors: update content; Admin+: create, delete, reorder, activate,
 * assign services). Consultants have no slug or detail page (plan D5).
 */
class ConsultantController extends Controller
{
    public const PER_PAGE = 20;

    private const AUDITED = [
        'name', 'title', 'bio', 'photo_id', 'expertise', 'qualifications', 'email', 'links', 'is_active', 'sort_order',
    ];

    public function __construct(
        private AuditLogger $audit,
        private SortOrder $sortOrder,
        private MediaPicker $mediaPicker,
        private ServiceConsultantAssignments $assignments,
    ) {}

    public function index(Request $request): View
    {
        $this->authorize('viewAny', Consultant::class);

        $search = trim((string) $request->query('q'));
        $like = '%'.addcslashes($search, '%_\\').'%';

        $consultants = Consultant::query()
            ->with('photo')
            ->withCount('services')
            ->when($search !== '', fn ($q) => $q->where(fn ($q) => $q->where('name', 'like', $like)->orWhere('title', 'like', $like)))
            ->ordered()
            ->paginate(self::PER_PAGE)
            ->withQueryString();

        return view('admin.consultants.index', ['consultants' => $consultants, 'search' => $search]);
    }

    public function create(): View
    {
        $this->authorize('create', Consultant::class);

        return view('admin.consultants.form', [
            'consultant' => new Consultant(['is_active' => true]),
            'mediaOptions' => $this->mediaPicker->options(),
            'services' => $this->serviceOptions(),
            'assignments' => [],
        ]);
    }

    public function store(ConsultantRequest $request): RedirectResponse
    {
        $consultant = new Consultant($this->contentFields($request));
        $consultant->is_active = $request->boolean('is_active', true);

        DB::transaction(function () use ($request, $consultant) {
            // Appended at the end under a row lock (never an unprotected max + 1).
            $this->sortOrder->append($consultant);
            $this->syncAssignments($request, $consultant);
        });

        $this->audit->record('created', $request->user(), $consultant,
            new: $consultant->only(self::AUDITED) + ['services' => $this->assignments->rolesForConsultant($consultant)]);

        return redirect()->route('admin.consultants.edit', $consultant)->with('status', 'Created "'.$consultant->name.'".');
    }

    public function edit(Consultant $consultant): View
    {
        $this->authorize('update', $consultant);

        return view('admin.consultants.form', [
            'consultant' => $consultant,
            'mediaOptions' => $this->mediaPicker->options([$consultant->photo_id]),
            'services' => $this->serviceOptions(),
            'assignments' => $this->assignments->rolesForConsultant($consultant),
        ]);
    }

    public function update(ConsultantRequest $request, Consultant $consultant): RedirectResponse
    {
        $before = $consultant->only(self::AUDITED);
        $assignmentsBefore = $this->assignments->rolesForConsultant($consultant);

        $consultant->fill($this->contentFields($request));

        // The active flag is a manage-level change; ignored for Editors.
        if ($request->has('is_active') && $request->user()->can('changeStatus', $consultant)) {
            $consultant->is_active = $request->boolean('is_active');
        }

        $changed = array_keys($consultant->getDirty());

        DB::transaction(function () use ($request, $consultant) {
            $consultant->save();
            $this->syncAssignments($request, $consultant);
        });

        $assignmentsAfter = $this->assignments->rolesForConsultant($consultant);
        $old = array_intersect_key($before, array_flip($changed));
        $new = $consultant->only(array_intersect(self::AUDITED, $changed));
        if ($assignmentsBefore !== $assignmentsAfter) {
            $old['services'] = $assignmentsBefore;
            $new['services'] = $assignmentsAfter;
        }

        if ($new === []) {
            return redirect()->route('admin.consultants.edit', $consultant)->with('status', 'No changes to save.');
        }

        $this->audit->record('updated', $request->user(), $consultant, $old, $new);

        return redirect()->route('admin.consultants.edit', $consultant)->with('status', 'Saved "'.$consultant->name.'".');
    }

    public function move(Request $request, Consultant $consultant): RedirectResponse
    {
        $this->authorize('reorder', $consultant);

        $direction = $request->validate(['direction' => ['required', 'in:up,down']])['direction'];

        $result = DB::transaction(function () use ($consultant, $direction) {
            $result = $this->sortOrder->move($consultant, $direction);
            // Service pages list consultants in this order too.
            $this->assignments->consultantMoved($consultant);

            return $result;
        });

        if ($result['from'] !== $result['to']) {
            $this->audit->record('reordered', $request->user(), $consultant, ['sort_order' => $result['from']], ['sort_order' => $result['to']]);
        }

        return redirect()->back(fallback: route('admin.consultants.index'))
            ->with('status', sprintf('Moved "%s" to position %d.', $consultant->name, $result['to']));
    }

    /**
     * Confirmation page listing the service assignments removed with it.
     */
    public function confirmDelete(Consultant $consultant): View
    {
        $this->authorize('delete', $consultant);

        return view('admin.consultants.delete', [
            'consultant' => $consultant->load('photo'),
            'assigned' => $consultant->services()->ordered()->get(['services.id', 'title']),
        ]);
    }

    public function destroy(Request $request, Consultant $consultant): RedirectResponse
    {
        $this->authorize('delete', $consultant);

        $request->validate(['confirm' => ['accepted']], [
            'confirm.accepted' => 'Confirm that you want to delete this consultant and their service assignments.',
        ]);

        $before = $consultant->only(self::AUDITED) + ['services' => $this->assignments->rolesForConsultant($consultant)];
        $serviceIds = $consultant->services()->pluck('services.id')->all();

        DB::transaction(function () use ($consultant, $serviceIds) {
            // Assignments go with it (service_consultant ON DELETE CASCADE).
            $consultant->delete();
            // Close the gap in each affected service's consultant order.
            foreach (Service::whereKey($serviceIds)->get() as $service) {
                $this->assignments->setForService($service, $this->assignments->rolesForService($service));
            }
        });

        $this->audit->record('deleted', $request->user(), $consultant, $before);

        return redirect()->route('admin.consultants.index')->with('status', 'Deleted "'.$consultant->name.'".');
    }

    /**
     * Assign services. Manage-level only; ignored for Editors, and untouched
     * when the form didn't include the section.
     */
    private function syncAssignments(ConsultantRequest $request, Consultant $consultant): void
    {
        if (! $request->has('services') || ! $request->user()->can('assignServices', $consultant)) {
            return;
        }

        // Shared with the services screen, so both sides of the pivot agree.
        $this->assignments->setForConsultant($consultant, (array) $request->validated('services'));
    }

    /**
     * @return Collection<int, Service>
     */
    private function serviceOptions()
    {
        return Service::query()->ordered()->get(['id', 'title', 'is_active']);
    }

    /**
     * @return array<string, mixed>
     */
    private function contentFields(ConsultantRequest $request): array
    {
        return [
            'name' => $request->validated('name'),
            'title' => $request->validated('title'),
            'bio' => $request->validated('bio'),
            'photo_id' => $request->validated('photo_id') ? (int) $request->validated('photo_id') : null,
            'expertise' => ServiceRequest::lines($request->validated('expertise')),
            'qualifications' => ServiceRequest::lines($request->validated('qualifications')),
            'email' => $request->validated('email'),
            'links' => $request->links(),
        ];
    }
}
