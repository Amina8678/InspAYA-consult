<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\CategoryRequest;
use App\Models\Category;
use App\Support\AuditLogger;
use App\Support\Slug;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Blog categories (FR-BLOG-01, plan §6 row 21). Routes carry permission:
 * middleware; every action also authorizes against CategoryPolicy. Anyone
 * who writes posts can view the list to pick from; only taxonomy.manage
 * changes it.
 */
class CategoryController extends Controller
{
    public const PER_PAGE = 30;

    private const AUDITED = ['name', 'slug', 'description'];

    public function __construct(private AuditLogger $audit) {}

    public function index(Request $request): View
    {
        $this->authorize('viewAny', Category::class);

        $search = trim((string) $request->query('q'));

        $categories = Category::query()
            ->withCount('blogPosts')
            ->when($search !== '', fn ($q) => $q->where('name', 'like', '%'.addcslashes($search, '%_\\').'%'))
            ->orderBy('name')
            ->paginate(self::PER_PAGE)
            ->withQueryString();

        return view('admin.categories.index', ['categories' => $categories, 'search' => $search]);
    }

    public function create(): View
    {
        $this->authorize('create', Category::class);

        return view('admin.categories.form', ['category' => new Category]);
    }

    public function store(CategoryRequest $request): RedirectResponse
    {
        $category = new Category($request->only(['name', 'description']));
        $category->slug = $request->validated('slug') ?: Slug::unique($category, $category->name);
        $category->save();

        $this->audit->record('created', $request->user(), $category, new: $category->only(self::AUDITED));

        return redirect()->route('admin.categories.edit', $category)->with('status', 'Created "'.$category->name.'".');
    }

    public function edit(Category $category): View
    {
        $this->authorize('update', $category);

        return view('admin.categories.form', ['category' => $category]);
    }

    public function update(CategoryRequest $request, Category $category): RedirectResponse
    {
        $before = $category->only(self::AUDITED);

        $category->fill($request->only(['name', 'description']));
        // An empty slug keeps the current one, so existing links never break by accident.
        $category->slug = $request->validated('slug') ?: $category->slug;

        $changed = array_keys($category->getDirty());
        $category->save();

        if ($changed === []) {
            return redirect()->route('admin.categories.edit', $category)->with('status', 'No changes to save.');
        }

        $this->audit->record('updated', $request->user(), $category,
            array_intersect_key($before, array_flip($changed)),
            $category->only(array_intersect(self::AUDITED, $changed)),
        );

        return redirect()->route('admin.categories.edit', $category)->with('status', 'Saved "'.$category->name.'".');
    }

    /**
     * Confirmation page showing how many posts are affected (they become
     * uncategorised, not deleted: blog_posts.category_id SET NULL).
     */
    public function confirmDelete(Category $category): View
    {
        $this->authorize('delete', $category);

        return view('admin.categories.delete', ['category' => $category, 'postCount' => $category->blogPosts()->count()]);
    }

    public function destroy(Request $request, Category $category): RedirectResponse
    {
        $this->authorize('delete', $category);

        $request->validate(['confirm' => ['accepted']], [
            'confirm.accepted' => 'Confirm that you want to delete this category.',
        ]);

        $before = $category->only(self::AUDITED);
        $category->delete();

        $this->audit->record('deleted', $request->user(), $category, $before);

        return redirect()->route('admin.categories.index')->with('status', 'Deleted "'.$category->name.'".');
    }
}
