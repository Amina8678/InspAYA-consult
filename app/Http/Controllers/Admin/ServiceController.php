<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ServiceRequest;
use App\Models\Consultant;
use App\Models\Service;
use App\Services\Assignments\ServiceConsultantAssignments;
use App\Services\Ordering\SortOrder;
use App\Support\AuditLogger;
use App\Support\Slug;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Services (FR-SVC-01/02/03, FR-ADM-05). Routes carry permission:
 * middleware; every action also authorizes against ServicePolicy (Editors:
 * update content; Admin+: create, delete, reorder, activate, assign
 * consultants).
 */
class ServiceController extends Controller
{
    public const PER_PAGE = 20;

    private const AUDITED = [
        'title', 'slug', 'short_description', 'description', 'capabilities', 'outcomes',
        'is_active', 'sort_order', 'meta_title', 'meta_description',
    ];

    public function __construct(
        private AuditLogger $audit,
        private SortOrder $sortOrder,
        private ServiceConsultantAssignments $assignments,
    ) {}

    public function index(Request $request): View
    {
        $this->authorize('viewAny', Service::class);

        $search = trim((string) $request->query('q'));

        $services = Service::query()
            ->withCount(['consultants', 'leadConsultants'])
            ->when($search !== '', fn ($q) => $q->where('title', 'like', '%'.addcslashes($search, '%_\\').'%'))
            ->ordered()
            ->paginate(self::PER_PAGE)
            ->withQueryString();

        return view('admin.services.index', ['services' => $services, 'search' => $search]);
    }

    public function create(): View
    {
        $this->authorize('create', Service::class);

        return view('admin.services.form', [
            'service' => new Service(['is_active' => true]),
            'consultants' => $this->consultantOptions(),
            'assignments' => [],
        ]);
    }

    public function store(ServiceRequest $request): RedirectResponse
    {
        $service = new Service($this->contentFields($request));
        $service->slug = $request->validated('slug') ?: Slug::unique($service, $service->title);
        $service->is_active = $request->boolean('is_active', true);

        DB::transaction(function () use ($request, $service) {
            // Appended at the end under a row lock (never an unprotected max + 1).
            $this->sortOrder->append($service);
            $this->syncAssignments($request, $service);
        });

        $this->audit->record('created', $request->user(), $service,
            new: $service->only(self::AUDITED) + ['consultants' => $this->assignmentsOf($service)]);

        return redirect()->route('admin.services.edit', $service)->with('status', 'Created "'.$service->title.'".');
    }

    public function edit(Service $service): View
    {
        $this->authorize('update', $service);

        return view('admin.services.form', [
            'service' => $service,
            'consultants' => $this->consultantOptions(),
            'assignments' => $this->assignmentsOf($service),
        ]);
    }

    public function update(ServiceRequest $request, Service $service): RedirectResponse
    {
        $before = $service->only(self::AUDITED);
        $assignmentsBefore = $this->assignmentsOf($service);

        $service->fill($this->contentFields($request));
        // An empty slug keeps the current one, so a live URL never changes by accident.
        $service->slug = $request->validated('slug') ?: $service->slug;

        // The active flag is a manage-level change; ignored for Editors.
        if ($request->has('is_active') && $request->user()->can('changeStatus', $service)) {
            $service->is_active = $request->boolean('is_active');
        }

        $changed = array_keys($service->getDirty());

        DB::transaction(function () use ($request, $service) {
            $service->save();
            $this->syncAssignments($request, $service);
        });

        $assignmentsAfter = $this->assignmentsOf($service);
        $old = array_intersect_key($before, array_flip($changed));
        $new = $service->only(array_intersect(self::AUDITED, $changed));
        if ($assignmentsBefore !== $assignmentsAfter) {
            $old['consultants'] = $assignmentsBefore;
            $new['consultants'] = $assignmentsAfter;
        }

        if ($new === []) {
            return redirect()->route('admin.services.edit', $service)->with('status', 'No changes to save.');
        }

        $this->audit->record('updated', $request->user(), $service, $old, $new);

        return redirect()->route('admin.services.edit', $service)->with('status', 'Saved "'.$service->title.'".');
    }

    public function move(Request $request, Service $service): RedirectResponse
    {
        $this->authorize('reorder', $service);

        $direction = $request->validate(['direction' => ['required', 'in:up,down']])['direction'];
        $result = $this->sortOrder->move($service, $direction);

        if ($result['from'] !== $result['to']) {
            $this->audit->record('reordered', $request->user(), $service, ['sort_order' => $result['from']], ['sort_order' => $result['to']]);
        }

        return redirect()->back(fallback: route('admin.services.index'))
            ->with('status', sprintf('Moved "%s" to position %d.', $service->title, $result['to']));
    }

    /**
     * Confirmation page listing the consultant assignments removed with it.
     */
    public function confirmDelete(Service $service): View
    {
        $this->authorize('delete', $service);

        return view('admin.services.delete', [
            'service' => $service,
            'assigned' => $service->consultants()->get(['consultants.id', 'name', 'title']),
        ]);
    }

    public function destroy(Request $request, Service $service): RedirectResponse
    {
        $this->authorize('delete', $service);

        $request->validate(['confirm' => ['accepted']], [
            'confirm.accepted' => 'Confirm that you want to delete this service and its consultant assignments.',
        ]);

        $before = $service->only(self::AUDITED) + ['consultants' => $this->assignmentsOf($service)];

        // Assignments go with it (service_consultant ON DELETE CASCADE).
        $service->delete();

        $this->audit->record('deleted', $request->user(), $service, $before);

        return redirect()->route('admin.services.index')->with('status', 'Deleted "'.$service->title.'".');
    }

    /**
     * Assign lead/supporting consultants. Manage-level only; ignored for
     * Editors, and untouched when the form didn't include the section.
     */
    private function syncAssignments(ServiceRequest $request, Service $service): void
    {
        if (! $request->has('consultants') || ! $request->user()->can('assignConsultants', $service)) {
            return;
        }

        // Shared with the consultants screen, so both sides of the pivot agree.
        $this->assignments->setForService($service, (array) $request->validated('consultants'));
    }

    /**
     * @return array<int, string> consultant id => "lead" | "supporting"
     */
    private function assignmentsOf(Service $service): array
    {
        return $this->assignments->rolesForService($service);
    }

    /**
     * @return \Illuminate\Support\Collection<int, Consultant>
     */
    private function consultantOptions()
    {
        return Consultant::query()->ordered()->get(['id', 'name', 'title', 'is_active']);
    }

    /**
     * @return array<string, mixed>
     */
    private function contentFields(ServiceRequest $request): array
    {
        return [
            'title' => $request->validated('title'),
            'short_description' => $request->validated('short_description'),
            'description' => $request->validated('description'),
            'capabilities' => ServiceRequest::lines($request->validated('capabilities')),
            'outcomes' => ServiceRequest::lines($request->validated('outcomes')),
            'meta_title' => $request->validated('meta_title'),
            'meta_description' => $request->validated('meta_description'),
        ];
    }
}
