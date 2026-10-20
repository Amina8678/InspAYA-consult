<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\RejectRevisionRequest;
use App\Models\BlogPost;
use App\Models\Category;
use App\Models\Media;
use App\Models\Page;
use App\Models\PendingRevision;
use App\Models\Tag;
use App\Support\ContentApproval;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Str;

/**
 * Content approval queue (stage 16): pending edits to already-live pages and
 * posts, from someone without content.publish. Gated the same as publishing
 * itself — see PendingRevisionPolicy.
 */
class PendingRevisionController extends Controller
{
    public const PER_PAGE = 20;

    /** Field key => display label, for the review screen's diff. */
    private const LABELS = [
        'title' => 'Title',
        'slug' => 'Slug',
        'excerpt' => 'Excerpt',
        'content' => 'Content',
        'category_id' => 'Category',
        'featured_image_id' => 'Featured image',
        'og_image_id' => 'Sharing image',
        'meta_title' => 'Meta title',
        'meta_description' => 'Meta description',
        'canonical_url' => 'Canonical URL',
        'structured_content' => 'Page sections',
        'tags' => 'Tags',
    ];

    public function __construct(private ContentApproval $approval) {}

    public function index(): View
    {
        $this->authorize('viewAny', PendingRevision::class);

        $revisions = PendingRevision::query()
            ->pending()
            ->withLiveRecord()
            ->with(['revisable', 'submitter:id,name'])
            ->oldest('created_at')
            ->paginate(self::PER_PAGE);

        return view('admin.content-approvals.index', [
            'revisions' => $revisions,
            'typeLabel' => $this->typeLabel(...),
        ]);
    }

    public function show(PendingRevision $pendingRevision): View
    {
        $this->authorize('view', $pendingRevision);
        $this->abortIfRecordDeleted($pendingRevision);

        return view('admin.content-approvals.show', [
            'revision' => $pendingRevision->load(['revisable', 'submitter:id,name']),
            'diff' => $this->diff($pendingRevision),
            'typeLabel' => $this->typeLabel($pendingRevision),
        ]);
    }

    public function approve(PendingRevision $pendingRevision): RedirectResponse
    {
        $this->authorize('approve', $pendingRevision);
        $this->abortIfRecordDeleted($pendingRevision);

        $title = $pendingRevision->load('revisable')->revisable->title;
        $this->approval->apply($pendingRevision, auth()->user());

        return redirect()->route('admin.content-approvals.index')->with('status', 'Approved the change to "'.$title.'".');
    }

    public function reject(RejectRevisionRequest $request, PendingRevision $pendingRevision): RedirectResponse
    {
        $this->abortIfRecordDeleted($pendingRevision);
        $title = $pendingRevision->revisable->title;
        $this->approval->reject($pendingRevision, $request->user(), $request->validated('reason'));

        return redirect()->route('admin.content-approvals.index')->with('status', 'Rejected the change to "'.$title.'".');
    }

    /**
     * A revision whose record was deleted (an orphan from before deletes
     * cleaned up their revisions) has nothing to review or apply.
     */
    private function abortIfRecordDeleted(PendingRevision $revision): void
    {
        abort_if($revision->revisable === null, 404, 'The page or post this change was for has been deleted.');
    }

    private function typeLabel(PendingRevision $revision): string
    {
        // revisable_type stores the enforced morph map alias ('page',
        // 'blog_post' — see AppServiceProvider), not the FQCN.
        return match ($revision->revisable_type) {
            (new Page)->getMorphClass() => 'Page',
            (new BlogPost)->getMorphClass() => 'Blog post',
            default => Str::headline($revision->revisable_type),
        };
    }

    /**
     * @return list<array{label: string, old: string, new: string}>
     */
    private function diff(PendingRevision $revision): array
    {
        $revisable = $revision->revisable;
        $rows = [];

        foreach ($revision->changes as $field => $new) {
            $rows[] = [
                'label' => self::LABELS[$field] ?? Str::headline($field),
                'old' => $this->present($field, $field === 'tags'
                    ? $revisable->tags()->pluck('tags.id')->sort()->values()->all()
                    : $revisable->{$field}),
                'new' => $this->present($field, $new),
            ];
        }

        return $rows;
    }

    private function present(string $field, mixed $value): string
    {
        return match ($field) {
            'category_id' => $value ? (Category::find($value)?->name ?? "#{$value}") : '—',
            'featured_image_id', 'og_image_id' => $value ? (Media::find($value)?->file_name ?? "#{$value}") : '—',
            'tags' => $value === [] ? '—' : Tag::whereIn('id', $value)->pluck('name')->implode(', '),
            'structured_content' => $this->summariseSections($value),
            default => $this->presentScalar($value),
        };
    }

    private function presentScalar(mixed $value): string
    {
        if ($value === null || $value === '') {
            return '—';
        }

        return is_string($value) ? $value : (string) $value;
    }

    /**
     * structured_content is a page-builder JSON blob (schema plan §3.6):
     * too free-form for a useful field-by-field diff, so this just names
     * the sections, in order, for a reviewer to sanity-check the shape of
     * the change before trusting the full page preview.
     */
    private function summariseSections(mixed $sections): string
    {
        if (! is_array($sections) || $sections === []) {
            return '—';
        }

        return collect($sections)->map(fn (array $section) => Str::headline($section['type'] ?? 'unknown'))->implode(', ');
    }
}
