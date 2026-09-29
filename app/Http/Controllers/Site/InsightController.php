<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Models\BlogPost;
use App\View\Presenters\ContentPresenter;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Insights (SRS site map name for the blog). Only published posts whose
 * publication date has passed are visible; everything else 404s.
 */
class InsightController extends Controller
{
    public const PER_PAGE = 9;

    private const RELATED = 3;

    /** Relations every post summary needs (see ContentPresenter::postSummary). */
    public const SUMMARY_RELATIONS = ['author:id,name', 'category', 'featuredImage', 'tags'];

    public function __construct(private ContentPresenter $presenter) {}

    public function index(): View
    {
        $posts = BlogPost::query()->published()
            ->with(self::SUMMARY_RELATIONS)
            ->latest('published_at')
            ->latest('id')
            ->paginate(self::PER_PAGE)
            ->through(fn (BlogPost $p) => $this->presenter->postSummary($p));

        return view('public.insights.index', [
            'posts' => $posts,
            'seo' => $this->presenter->seo(
                $this->presenter->pageTitle('Insights'),
                canonicalUrl: route('insights.index'),
            ),
        ]);
    }

    public function show(string $slug): View
    {
        $post = BlogPost::query()->published()->where('slug', $slug)
            ->with(self::SUMMARY_RELATIONS)
            ->firstOrFail();

        $data = $this->presenter->postDetail($post, $this->related($post));

        return view('public.insights.show', ['post' => $data, 'seo' => $data['seo']]);
    }

    /**
     * Related articles (FR-BLOG-04): latest published posts sharing the
     * category or a tag.
     *
     * @return Collection<int, BlogPost>
     */
    private function related(BlogPost $post)
    {
        $tagIds = $post->tags->pluck('id');

        if ($post->category_id === null && $tagIds->isEmpty()) {
            return collect();
        }

        return BlogPost::query()->published()
            ->whereKeyNot($post->id)
            ->where(function (Builder $query) use ($post, $tagIds) {
                if ($post->category_id !== null) {
                    $query->orWhere('category_id', $post->category_id);
                }

                if ($tagIds->isNotEmpty()) {
                    $query->orWhereHas('tags', fn (Builder $tags) => $tags->whereKey($tagIds));
                }
            })
            ->with(self::SUMMARY_RELATIONS)
            ->latest('published_at')
            ->limit(self::RELATED)
            ->get();
    }
}
