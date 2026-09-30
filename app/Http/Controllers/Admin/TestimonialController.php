<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\TestimonialRequest;
use App\Models\Testimonial;
use App\Services\Ordering\SortOrder;
use App\Support\AuditLogger;
use App\Support\MediaPicker;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Testimonials (stage 17, SRS §1.4 / FR-HOME-07). Routes carry permission:
 * middleware; every action also authorizes against TestimonialPolicy
 * (Editors: update content only; Admin+: create, delete, reorder, activate).
 */
class TestimonialController extends Controller
{
    public const PER_PAGE = 20;

    private const AUDITED = ['quote', 'author_name', 'author_title', 'organization', 'photo_id', 'is_active', 'sort_order'];

    public function __construct(
        private AuditLogger $audit,
        private SortOrder $sortOrder,
        private MediaPicker $mediaPicker,
    ) {}

    public function index(Request $request): View
    {
        $this->authorize('viewAny', Testimonial::class);

        $search = trim((string) $request->query('q'));

        $testimonials = Testimonial::query()
            ->with('photo')
            ->when($search !== '', fn ($q) => $q->where('author_name', 'like', '%'.addcslashes($search, '%_\\').'%'))
            ->ordered()
            ->paginate(self::PER_PAGE)
            ->withQueryString();

        return view('admin.testimonials.index', ['testimonials' => $testimonials, 'search' => $search]);
    }

    public function create(): View
    {
        $this->authorize('create', Testimonial::class);

        return view('admin.testimonials.form', [
            'testimonial' => new Testimonial(['is_active' => true]),
            'mediaOptions' => $this->mediaPicker->options(),
        ]);
    }

    public function store(TestimonialRequest $request): RedirectResponse
    {
        $testimonial = new Testimonial($this->contentFields($request));
        $testimonial->is_active = $request->boolean('is_active', true);

        // Appended at the end under a row lock (never an unprotected max + 1).
        $this->sortOrder->append($testimonial);

        $this->audit->record('created', $request->user(), $testimonial, new: $testimonial->only(self::AUDITED));

        return redirect()->route('admin.testimonials.edit', $testimonial)->with('status', 'Created testimonial from "'.$testimonial->author_name.'".');
    }

    public function edit(Testimonial $testimonial): View
    {
        $this->authorize('update', $testimonial);

        return view('admin.testimonials.form', [
            'testimonial' => $testimonial,
            'mediaOptions' => $this->mediaPicker->options([$testimonial->photo_id]),
        ]);
    }

    public function update(TestimonialRequest $request, Testimonial $testimonial): RedirectResponse
    {
        $before = $testimonial->only(self::AUDITED);

        $testimonial->fill($this->contentFields($request));

        // The active flag is a manage-level change; ignored for Editors.
        if ($request->has('is_active') && $request->user()->can('changeStatus', $testimonial)) {
            $testimonial->is_active = $request->boolean('is_active');
        }

        $changed = array_keys($testimonial->getDirty());
        $testimonial->save();

        if ($changed === []) {
            return redirect()->route('admin.testimonials.edit', $testimonial)->with('status', 'No changes to save.');
        }

        $this->audit->record('updated', $request->user(), $testimonial,
            array_intersect_key($before, array_flip($changed)),
            $testimonial->only(array_intersect(self::AUDITED, $changed)),
        );

        return redirect()->route('admin.testimonials.edit', $testimonial)->with('status', 'Saved testimonial from "'.$testimonial->author_name.'".');
    }

    public function move(Request $request, Testimonial $testimonial): RedirectResponse
    {
        $this->authorize('reorder', $testimonial);

        $direction = $request->validate(['direction' => ['required', 'in:up,down']])['direction'];
        $result = $this->sortOrder->move($testimonial, $direction);

        if ($result['from'] !== $result['to']) {
            $this->audit->record('reordered', $request->user(), $testimonial, ['sort_order' => $result['from']], ['sort_order' => $result['to']]);
        }

        return redirect()->back(fallback: route('admin.testimonials.index'))
            ->with('status', sprintf('Moved testimonial from "%s" to position %d.', $testimonial->author_name, $result['to']));
    }

    public function confirmDelete(Testimonial $testimonial): View
    {
        $this->authorize('delete', $testimonial);

        return view('admin.testimonials.delete', ['testimonial' => $testimonial->load('photo')]);
    }

    public function destroy(Request $request, Testimonial $testimonial): RedirectResponse
    {
        $this->authorize('delete', $testimonial);

        $request->validate(['confirm' => ['accepted']], [
            'confirm.accepted' => 'Confirm that you want to delete this testimonial.',
        ]);

        $before = $testimonial->only(self::AUDITED);
        $testimonial->delete();

        $this->audit->record('deleted', $request->user(), $testimonial, $before);

        return redirect()->route('admin.testimonials.index')->with('status', 'Deleted testimonial from "'.$testimonial->author_name.'".');
    }

    /**
     * @return array<string, mixed>
     */
    private function contentFields(TestimonialRequest $request): array
    {
        return [
            'quote' => $request->validated('quote'),
            'author_name' => $request->validated('author_name'),
            'author_title' => $request->validated('author_title'),
            'organization' => $request->validated('organization'),
            'photo_id' => $request->validated('photo_id') ? (int) $request->validated('photo_id') : null,
        ];
    }
}
