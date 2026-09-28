<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\CoreValueRequest;
use App\Models\CoreValue;
use App\Services\Ordering\SortOrder;
use App\Support\AuditLogger;
use App\Support\MediaPicker;
use App\Support\Slug;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Core values (FR-VAL-01/02, FR-ADM-06). Routes carry permission:
 * middleware; every action also authorizes against CoreValuePolicy
 * (Editors: update content only; Admin+: create, delete, reorder, activate).
 */
class CoreValueController extends Controller
{
    public const PER_PAGE = 20;

    private const AUDITED = ['title', 'slug', 'description', 'icon_id', 'is_active', 'sort_order'];

    public function __construct(
        private AuditLogger $audit,
        private SortOrder $sortOrder,
        private MediaPicker $mediaPicker,
    ) {}

    public function index(Request $request): View
    {
        $this->authorize('viewAny', CoreValue::class);

        $search = trim((string) $request->query('q'));

        $values = CoreValue::query()
            ->with('icon')
            ->when($search !== '', fn ($q) => $q->where('title', 'like', '%'.addcslashes($search, '%_\\').'%'))
            ->ordered()
            ->paginate(self::PER_PAGE)
            ->withQueryString();

        return view('admin.core-values.index', ['values' => $values, 'search' => $search]);
    }

    public function create(): View
    {
        $this->authorize('create', CoreValue::class);

        return view('admin.core-values.form', [
            'value' => new CoreValue(['is_active' => true]),
            'mediaOptions' => $this->mediaPicker->options(),
        ]);
    }

    public function store(CoreValueRequest $request): RedirectResponse
    {
        $value = new CoreValue($this->contentFields($request));
        $value->slug = $request->validated('slug') ?: Slug::unique($value, $value->title);
        $value->is_active = $request->boolean('is_active', true);

        // Appended at the end under a row lock (never an unprotected max + 1).
        $this->sortOrder->append($value);

        $this->audit->record('created', $request->user(), $value, new: $value->only(self::AUDITED));

        return redirect()->route('admin.core-values.edit', $value)->with('status', 'Created "'.$value->title.'".');
    }

    public function edit(CoreValue $coreValue): View
    {
        $this->authorize('update', $coreValue);

        return view('admin.core-values.form', [
            'value' => $coreValue,
            'mediaOptions' => $this->mediaPicker->options([$coreValue->icon_id]),
        ]);
    }

    public function update(CoreValueRequest $request, CoreValue $coreValue): RedirectResponse
    {
        $before = $coreValue->only(self::AUDITED);

        $coreValue->fill($this->contentFields($request));
        $coreValue->slug = $request->validated('slug') ?: Slug::unique($coreValue, $coreValue->title);

        // The active flag is a manage-level change; ignored for Editors.
        if ($request->has('is_active') && $request->user()->can('changeStatus', $coreValue)) {
            $coreValue->is_active = $request->boolean('is_active');
        }

        $changed = array_keys($coreValue->getDirty());
        $coreValue->save();

        if ($changed === []) {
            return redirect()->route('admin.core-values.edit', $coreValue)->with('status', 'No changes to save.');
        }

        $this->audit->record('updated', $request->user(), $coreValue,
            array_intersect_key($before, array_flip($changed)),
            $coreValue->only(array_intersect(self::AUDITED, $changed)),
        );

        return redirect()->route('admin.core-values.edit', $coreValue)->with('status', 'Saved "'.$coreValue->title.'".');
    }

    public function move(Request $request, CoreValue $coreValue): RedirectResponse
    {
        $this->authorize('reorder', $coreValue);

        $direction = $request->validate(['direction' => ['required', 'in:up,down']])['direction'];
        $result = $this->sortOrder->move($coreValue, $direction);

        if ($result['from'] !== $result['to']) {
            $this->audit->record('reordered', $request->user(), $coreValue, ['sort_order' => $result['from']], ['sort_order' => $result['to']]);
        }

        return redirect()->back(fallback: route('admin.core-values.index'))
            ->with('status', sprintf('Moved "%s" to position %d.', $coreValue->title, $result['to']));
    }

    public function confirmDelete(CoreValue $coreValue): View
    {
        $this->authorize('delete', $coreValue);

        return view('admin.core-values.delete', ['value' => $coreValue->load('icon')]);
    }

    public function destroy(Request $request, CoreValue $coreValue): RedirectResponse
    {
        $this->authorize('delete', $coreValue);

        $request->validate(['confirm' => ['accepted']], [
            'confirm.accepted' => 'Confirm that you want to delete this core value.',
        ]);

        $before = $coreValue->only(self::AUDITED);
        $coreValue->delete();

        $this->audit->record('deleted', $request->user(), $coreValue, $before);

        return redirect()->route('admin.core-values.index')->with('status', 'Deleted "'.$coreValue->title.'".');
    }

    /**
     * @return array{title: string, description: ?string, icon_id: ?int}
     */
    private function contentFields(CoreValueRequest $request): array
    {
        return [
            'title' => $request->validated('title'),
            'description' => $request->validated('description'),
            'icon_id' => $request->validated('icon_id') ? (int) $request->validated('icon_id') : null,
        ];
    }
}
