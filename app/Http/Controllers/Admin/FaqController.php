<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\FaqRequest;
use App\Models\Faq;
use App\Services\Ordering\SortOrder;
use App\Support\AuditLogger;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * FAQs (stage 17, SRS §1.4). Routes carry permission: middleware; every
 * action also authorizes against FaqPolicy (Editors: update content only;
 * Admin+: create, delete, reorder, activate).
 */
class FaqController extends Controller
{
    public const PER_PAGE = 20;

    private const AUDITED = ['question', 'answer', 'is_active', 'sort_order'];

    public function __construct(private AuditLogger $audit, private SortOrder $sortOrder) {}

    public function index(Request $request): View
    {
        $this->authorize('viewAny', Faq::class);

        $search = trim((string) $request->query('q'));

        $faqs = Faq::query()
            ->when($search !== '', fn ($q) => $q->where('question', 'like', '%'.addcslashes($search, '%_\\').'%'))
            ->ordered()
            ->paginate(self::PER_PAGE)
            ->withQueryString();

        return view('admin.faqs.index', ['faqs' => $faqs, 'search' => $search]);
    }

    public function create(): View
    {
        $this->authorize('create', Faq::class);

        return view('admin.faqs.form', ['faq' => new Faq(['is_active' => true])]);
    }

    public function store(FaqRequest $request): RedirectResponse
    {
        $faq = new Faq($this->contentFields($request));
        $faq->is_active = $request->boolean('is_active', true);

        // Appended at the end under a row lock (never an unprotected max + 1).
        $this->sortOrder->append($faq);

        $this->audit->record('created', $request->user(), $faq, new: $faq->only(self::AUDITED));

        return redirect()->route('admin.faqs.edit', $faq)->with('status', 'Created "'.$faq->question.'".');
    }

    public function edit(Faq $faq): View
    {
        $this->authorize('update', $faq);

        return view('admin.faqs.form', ['faq' => $faq]);
    }

    public function update(FaqRequest $request, Faq $faq): RedirectResponse
    {
        $before = $faq->only(self::AUDITED);

        $faq->fill($this->contentFields($request));

        // The active flag is a manage-level change; ignored for Editors.
        if ($request->has('is_active') && $request->user()->can('changeStatus', $faq)) {
            $faq->is_active = $request->boolean('is_active');
        }

        $changed = array_keys($faq->getDirty());
        $faq->save();

        if ($changed === []) {
            return redirect()->route('admin.faqs.edit', $faq)->with('status', 'No changes to save.');
        }

        $this->audit->record('updated', $request->user(), $faq,
            array_intersect_key($before, array_flip($changed)),
            $faq->only(array_intersect(self::AUDITED, $changed)),
        );

        return redirect()->route('admin.faqs.edit', $faq)->with('status', 'Saved "'.$faq->question.'".');
    }

    public function move(Request $request, Faq $faq): RedirectResponse
    {
        $this->authorize('reorder', $faq);

        $direction = $request->validate(['direction' => ['required', 'in:up,down']])['direction'];
        $result = $this->sortOrder->move($faq, $direction);

        if ($result['from'] !== $result['to']) {
            $this->audit->record('reordered', $request->user(), $faq, ['sort_order' => $result['from']], ['sort_order' => $result['to']]);
        }

        return redirect()->back(fallback: route('admin.faqs.index'))
            ->with('status', sprintf('Moved "%s" to position %d.', $faq->question, $result['to']));
    }

    public function confirmDelete(Faq $faq): View
    {
        $this->authorize('delete', $faq);

        return view('admin.faqs.delete', ['faq' => $faq]);
    }

    public function destroy(Request $request, Faq $faq): RedirectResponse
    {
        $this->authorize('delete', $faq);

        $request->validate(['confirm' => ['accepted']], [
            'confirm.accepted' => 'Confirm that you want to delete this FAQ.',
        ]);

        $before = $faq->only(self::AUDITED);
        $faq->delete();

        $this->audit->record('deleted', $request->user(), $faq, $before);

        return redirect()->route('admin.faqs.index')->with('status', 'Deleted "'.$faq->question.'".');
    }

    /**
     * @return array{question: string, answer: string}
     */
    private function contentFields(FaqRequest $request): array
    {
        return [
            'question' => $request->validated('question'),
            'answer' => $request->validated('answer'),
        ];
    }
}
