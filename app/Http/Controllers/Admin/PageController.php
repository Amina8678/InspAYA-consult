<?php

namespace App\Http\Controllers\Admin;

use App\Enums\PageStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\PageRequest;
use App\Models\Page;
use App\Support\AuditLogger;
use App\Support\CorePages;
use App\Support\MediaPicker;
use App\Support\PageSections;
use App\Support\Slug;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Pages (FR-ADM-04). Routes carry permission: middleware; every action also
 * authorizes against PagePolicy. Editors edit content (and save drafts);
 * Admin+ create and delete; publishing and unpublishing need content.publish
 * (D12). Statuses are draft and published only (D2).
 */
class PageController extends Controller
{
    public const PER_PAGE = 20;

    private const AUDITED = [
        'title', 'slug', 'status', 'structured_content', 'meta_title', 'meta_description', 'canonical_url', 'og_image_id', 'published_at',
    ];

    public function __construct(private AuditLogger $audit, private MediaPicker $mediaPicker) {}

    public function index(Request $request): View
    {
        $this->authorize('viewAny', Page::class);

        $search = trim((string) $request->query('q'));
        $like = '%'.addcslashes($search, '%_\\').'%';

        $pages = Page::query()
            ->when($search !== '', fn ($q) => $q->where(fn ($q) => $q->where('title', 'like', $like)->orWhere('slug', 'like', $like)))
            ->orderBy('title')
            ->orderBy('id')
            ->paginate(self::PER_PAGE, ['id', 'title', 'slug', 'status', 'published_at', 'updated_at'])
            ->withQueryString();

        return view('admin.pages.index', ['pages' => $pages, 'search' => $search]);
    }

    public function create(): View
    {
        $this->authorize('create', Page::class);

        return $this->form(new Page);
    }

    public function store(PageRequest $request): RedirectResponse
    {
        if ($request->isSectionAction()) {
            return $this->rebuild($request);
        }

        $page = new Page($this->contentFields($request));
        $page->slug = $request->validated('slug') ?: Slug::unique($page, $page->title);
        $page->status = PageStatus::Draft;
        $published = $this->applyStatus($request, $page);
        $page->save();

        $this->audit->record('created', $request->user(), $page, new: $page->only(self::AUDITED));
        if ($published === true) {
            $this->audit->record('published', $request->user(), $page, new: ['status' => 'published']);
        }

        return redirect()->route('admin.pages.edit', $page)->with('status', 'Created "'.$page->title.'".');
    }

    public function edit(Page $page): View
    {
        $this->authorize('update', $page);

        return $this->form($page);
    }

    public function update(PageRequest $request, Page $page): RedirectResponse
    {
        if ($request->isSectionAction()) {
            return $this->rebuild($request);
        }

        $before = $page->only(self::AUDITED);

        $page->fill($this->contentFields($request));
        // An empty slug keeps the current one, so a URL never changes by accident.
        $page->slug = $request->validated('slug') ?: $page->slug;
        $statusChange = $this->applyStatus($request, $page);

        $changed = array_keys($page->getDirty());
        $page->save();

        if ($changed === []) {
            return redirect()->route('admin.pages.edit', $page)->with('status', 'No changes to save.');
        }

        $this->audit->record('updated', $request->user(), $page,
            array_intersect_key($before, array_flip($changed)),
            $page->only(array_intersect(self::AUDITED, $changed)),
        );
        if ($statusChange !== null) {
            $this->audit->record($statusChange ? 'published' : 'unpublished', $request->user(), $page,
                ['status' => $before['status']->value], ['status' => $page->status->value]);
        }

        return redirect()->route('admin.pages.edit', $page)->with('status', match ($statusChange) {
            true => 'Published "'.$page->title.'".',
            false => 'Unpublished "'.$page->title.'". It is now a draft.',
            null => 'Saved "'.$page->title.'".',
        });
    }

    public function confirmDelete(Page $page): View
    {
        $this->authorize('delete', $page);
        abort_unless(CorePages::deletable($page->slug), 403, 'This page is part of the site and can\'t be deleted.');

        return view('admin.pages.delete', ['page' => $page]);
    }

    public function destroy(Request $request, Page $page): RedirectResponse
    {
        $this->authorize('delete', $page);
        abort_unless(CorePages::deletable($page->slug), 403, 'This page is part of the site and can\'t be deleted.');

        $request->validate(['confirm' => ['accepted']], [
            'confirm.accepted' => 'Confirm that you want to delete this page.',
        ]);

        $before = $page->only(self::AUDITED);
        $page->delete();

        $this->audit->record('deleted', $request->user(), $page, $before);

        return redirect()->route('admin.pages.index')->with('status', 'Deleted "'.$page->title.'".');
    }

    /**
     * Add, remove or move a section without saving: rebuild the form from
     * what was submitted (works without JavaScript).
     */
    private function rebuild(PageRequest $request): RedirectResponse
    {
        $result = PageSections::apply(
            (array) $request->input('sections', []),
            (string) $request->validated('section_action'),
            $request->validated('new_section_type'),
        );

        $input = $request->except(['section_action', '_token', '_method']);
        $input['sections'] = $result['rows'];

        return back()->withInput($input)->with('status', $result['message']);
    }

    /**
     * Status changes need content.publish; without it the status stays as
     * it is. Returns true (published), false (unpublished) or null (no change).
     */
    private function applyStatus(PageRequest $request, Page $page): ?bool
    {
        if (! $request->has('status') || ! $request->user()->can('publish', $page)) {
            return null;
        }

        $target = PageStatus::from($request->validated('status'));
        if ($target === $page->status) {
            return null;
        }

        $page->status = $target;
        if ($target === PageStatus::Published) {
            $page->published_at = now();
        }

        return $target === PageStatus::Published;
    }

    private function form(Page $page): View
    {
        $sections = old('sections', PageSections::toForm($page->structured_content));
        $mediaIds = array_merge([$page->og_image_id], array_column((array) $sections, 'background_media_id'));

        return view('admin.pages.form', [
            'page' => $page,
            'sections' => array_values((array) $sections),
            'mediaOptions' => $this->mediaPicker->options(array_filter($mediaIds, 'is_numeric')),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function contentFields(PageRequest $request): array
    {
        return [
            'title' => $request->validated('title'),
            'structured_content' => PageSections::fromForm((array) $request->validated('sections', [])),
            'meta_title' => $request->validated('meta_title'),
            'meta_description' => $request->validated('meta_description'),
            'canonical_url' => $request->validated('canonical_url'),
            'og_image_id' => $request->validated('og_image_id') ? (int) $request->validated('og_image_id') : null,
        ];
    }
}
