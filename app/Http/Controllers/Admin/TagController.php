<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\TagRequest;
use App\Models\Tag;
use App\Support\AuditLogger;
use App\Support\Slug;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Blog tags (FR-BLOG-01, plan §6 row 21). Routes carry permission:
 * middleware; every action also authorizes against TagPolicy. Anyone who
 * writes posts can view the list to pick from; only taxonomy.manage
 * changes it.
 */
class TagController extends Controller
{
    public const PER_PAGE = 30;

    private const AUDITED = ['name', 'slug'];

    public function __construct(private AuditLogger $audit) {}

    public function index(Request $request): View
    {
        $this->authorize('viewAny', Tag::class);

        $search = trim((string) $request->query('q'));

        $tags = Tag::query()
            ->withCount('blogPosts')
            ->when($search !== '', fn ($q) => $q->where('name', 'like', '%'.addcslashes($search, '%_\\').'%'))
            ->orderBy('name')
            ->paginate(self::PER_PAGE)
            ->withQueryString();

        return view('admin.tags.index', ['tags' => $tags, 'search' => $search]);
    }

    public function create(): View
    {
        $this->authorize('create', Tag::class);

        return view('admin.tags.form', ['tag' => new Tag]);
    }

    public function store(TagRequest $request): RedirectResponse
    {
        $tag = new Tag($request->only(['name']));
        $tag->slug = $request->validated('slug') ?: Slug::unique($tag, $tag->name);
        $tag->save();

        $this->audit->record('created', $request->user(), $tag, new: $tag->only(self::AUDITED));

        return redirect()->route('admin.tags.edit', $tag)->with('status', 'Created "'.$tag->name.'".');
    }

    public function edit(Tag $tag): View
    {
        $this->authorize('update', $tag);

        return view('admin.tags.form', ['tag' => $tag]);
    }

    public function update(TagRequest $request, Tag $tag): RedirectResponse
    {
        $before = $tag->only(self::AUDITED);

        $tag->fill($request->only(['name']));
        // An empty slug keeps the current one, so existing links never break by accident.
        $tag->slug = $request->validated('slug') ?: $tag->slug;

        $changed = array_keys($tag->getDirty());
        $tag->save();

        if ($changed === []) {
            return redirect()->route('admin.tags.edit', $tag)->with('status', 'No changes to save.');
        }

        $this->audit->record('updated', $request->user(), $tag,
            array_intersect_key($before, array_flip($changed)),
            $tag->only(array_intersect(self::AUDITED, $changed)),
        );

        return redirect()->route('admin.tags.edit', $tag)->with('status', 'Saved "'.$tag->name.'".');
    }

    /**
     * Confirmation page showing how many posts are affected (the tagging is
     * removed, the posts themselves are kept: blog_post_tag ON DELETE CASCADE).
     */
    public function confirmDelete(Tag $tag): View
    {
        $this->authorize('delete', $tag);

        return view('admin.tags.delete', ['tag' => $tag, 'postCount' => $tag->blogPosts()->count()]);
    }

    public function destroy(Request $request, Tag $tag): RedirectResponse
    {
        $this->authorize('delete', $tag);

        $request->validate(['confirm' => ['accepted']], [
            'confirm.accepted' => 'Confirm that you want to delete this tag.',
        ]);

        $before = $tag->only(self::AUDITED);
        $tag->delete();

        $this->audit->record('deleted', $request->user(), $tag, $before);

        return redirect()->route('admin.tags.index')->with('status', 'Deleted "'.$tag->name.'".');
    }
}
