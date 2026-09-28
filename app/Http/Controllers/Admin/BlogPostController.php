<?php

namespace App\Http\Controllers\Admin;

use App\Enums\PostStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\BlogPostRequest;
use App\Models\BlogPost;
use App\Models\Category;
use App\Models\Tag;
use App\Models\User;
use App\Support\AuditLogger;
use App\Support\MediaPicker;
use App\Support\Slug;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Blog posts (FR-BLOG-01 to 05, FR-ADM-08). Routes carry permission:
 * middleware; every action also authorizes against BlogPostPolicy. Authors
 * write and submit only their own drafts; Editors edit any post and move it
 * between draft and review; publishing and unpublishing need content.publish
 * (plan §6, D11/D12).
 */
class BlogPostController extends Controller
{
    public const PER_PAGE = 20;

    private const AUDITED = [
        'title', 'slug', 'excerpt', 'content', 'category_id', 'featured_image_id',
        'status', 'published_at', 'meta_title', 'meta_description', 'canonical_url',
    ];

    public function __construct(private AuditLogger $audit, private MediaPicker $mediaPicker) {}

    public function index(Request $request): View
    {
        $this->authorize('viewAny', BlogPost::class);
        $user = $request->user();

        $search = trim((string) $request->query('q'));
        $like = '%'.addcslashes($search, '%_\\').'%';

        $posts = BlogPost::query()
            ->with(['author:id,name', 'category'])
            ->when(! $user->can('posts.edit-any'), fn ($q) => $q->where('author_id', $user->id))
            ->when($search !== '', fn ($q) => $q->where(fn ($q) => $q->where('title', 'like', $like)->orWhere('slug', 'like', $like)))
            ->latest('updated_at')
            ->latest('id')
            ->paginate(self::PER_PAGE)
            ->withQueryString();

        return view('admin.blog-posts.index', ['posts' => $posts, 'search' => $search, 'ownOnly' => ! $user->can('posts.edit-any')]);
    }

    public function create(): View
    {
        $this->authorize('create', BlogPost::class);

        return $this->form(new BlogPost);
    }

    public function store(BlogPostRequest $request): RedirectResponse
    {
        $post = new BlogPost($this->contentFields($request));
        $post->slug = $request->validated('slug') ?: Slug::unique($post, $post->title);
        $post->status = PostStatus::Draft;
        // Not mass assignable by design (a request must never attribute a post to someone else).
        $post->author_id = $request->user()->id;
        $published = $this->applyStatus($request, $post);

        DB::transaction(function () use ($request, $post) {
            $post->save();
            $this->syncTags($request, $post);
        });

        $this->audit->record('created', $request->user(), $post,
            new: $post->only(self::AUDITED) + ['tags' => $this->tagsOf($post)]);
        if ($published === true) {
            $this->audit->record('published', $request->user(), $post, new: ['status' => 'published']);
        }

        return redirect()->route('admin.posts.edit', $post)->with('status', 'Created "'.$post->title.'".');
    }

    public function edit(BlogPost $post): View
    {
        $this->authorize('update', $post);

        return $this->form($post);
    }

    public function update(BlogPostRequest $request, BlogPost $post): RedirectResponse
    {
        $before = $post->only(self::AUDITED);
        $tagsBefore = $this->tagsOf($post);
        $statusBefore = $post->status;

        $post->fill($this->contentFields($request));
        // An empty slug keeps the current one, so a live URL never changes by accident.
        $post->slug = $request->validated('slug') ?: $post->slug;
        $statusChange = $this->applyStatus($request, $post);

        $changed = array_keys($post->getDirty());

        DB::transaction(function () use ($request, $post) {
            $post->save();
            $this->syncTags($request, $post);
        });

        $tagsAfter = $this->tagsOf($post);
        $old = array_intersect_key($before, array_flip($changed));
        $new = $post->only(array_intersect(self::AUDITED, $changed));
        if ($tagsBefore !== $tagsAfter) {
            $old['tags'] = $tagsBefore;
            $new['tags'] = $tagsAfter;
        }

        if ($new === []) {
            return redirect()->route('admin.posts.edit', $post)->with('status', 'No changes to save.');
        }

        $this->audit->record('updated', $request->user(), $post, $old, $new);
        if ($statusChange !== null) {
            $this->audit->record($statusChange ? 'published' : 'unpublished', $request->user(), $post,
                ['status' => $statusBefore->value], ['status' => $post->status->value]);
        }

        return redirect()->route('admin.posts.edit', $post)->with('status', $this->statusMessage($post, $statusBefore, $statusChange));
    }

    public function confirmDelete(BlogPost $post): View
    {
        $this->authorize('delete', $post);

        return view('admin.blog-posts.delete', ['post' => $post->load(['author', 'category', 'tags'])]);
    }

    public function destroy(Request $request, BlogPost $post): RedirectResponse
    {
        $this->authorize('delete', $post);

        $request->validate(['confirm' => ['accepted']], [
            'confirm.accepted' => 'Confirm that you want to delete this post.',
        ]);

        $before = $post->only(self::AUDITED) + ['tags' => $this->tagsOf($post)];
        $post->delete();

        $this->audit->record('deleted', $request->user(), $post, $before);

        return redirect()->route('admin.posts.index')->with('status', 'Deleted "'.$post->title.'".');
    }

    private function form(BlogPost $post): View
    {
        $user = auth()->user();

        return view('admin.blog-posts.form', [
            'post' => $post,
            'categories' => Category::query()->orderBy('name')->get(['id', 'name']),
            'tags' => Tag::query()->orderBy('name')->get(['id', 'name']),
            'selectedTags' => old('tags', $post->exists ? $this->tagsOf($post) : []),
            'mediaOptions' => $this->mediaPicker->options([$post->featured_image_id]),
            'canPublish' => $user->can('publish', $post),
            'statusOptions' => $this->statusOptions($user, $post),
        ]);
    }

    /**
     * Statuses this user may move $post to from where it is now, including
     * its current status (so the form always has something to submit).
     *
     * @return list<PostStatus>
     */
    private function statusOptions(User $user, BlogPost $post): array
    {
        $current = $post->exists ? $post->status : PostStatus::Draft;
        $options = [$current];

        foreach (PostStatus::cases() as $target) {
            if ($target === $current) {
                continue;
            }

            $involvesPublished = $target === PostStatus::Published || $current === PostStatus::Published;
            $allowed = $involvesPublished ? $user->can('publish', $post) : $user->can('moveStatus', [$post, $target]);

            if ($allowed) {
                $options[] = $target;
            }
        }

        return $options;
    }

    /**
     * Applies a requested status change if this user is allowed to make it;
     * otherwise the status is left as it is (no error — the same "ignored,
     * not refused with a 422" convention as PageController). Returns true
     * (published), false (unpublished) or null (no published/unpublished
     * transition, whether or not some other status change happened).
     *
     * The publish date is bound whenever this user can publish, independently
     * of whether the status itself changes in this request, so a scheduled
     * (future) date can be corrected without also flipping the status.
     */
    private function applyStatus(BlogPostRequest $request, BlogPost $post): ?bool
    {
        $current = $post->status ?? PostStatus::Draft;
        $canPublish = $request->user()->can('publish', $post);

        if ($canPublish && $request->has('published_at')) {
            $post->published_at = $request->validated('published_at') ? Carbon::parse($request->validated('published_at')) : null;
        }

        if (! $request->filled('status')) {
            return null;
        }

        $target = PostStatus::from($request->validated('status'));
        if ($target === $current) {
            return null;
        }

        $involvesPublished = $target === PostStatus::Published || $current === PostStatus::Published;
        $allowed = $involvesPublished ? $canPublish : $request->user()->can('moveStatus', [$post, $target]);

        if (! $allowed) {
            return null;
        }

        $post->status = $target;
        // Published posts need a publish date (FR-BLOG-03); a future date
        // schedules it, matching BlogPost::scopePublished. No date typed in
        // and none already set defaults to now — no other scheduling exists.
        if ($target === PostStatus::Published && $post->published_at === null) {
            $post->published_at = now();
        }

        return $involvesPublished ? $target === PostStatus::Published : null;
    }

    private function statusMessage(BlogPost $post, PostStatus $before, ?bool $statusChange): string
    {
        return match (true) {
            $statusChange === true => 'Published "'.$post->title.'".',
            $statusChange === false => 'Unpublished "'.$post->title.'". It is now a draft.',
            $before !== $post->status && $post->status === PostStatus::Review => 'Submitted "'.$post->title.'" for review.',
            $before !== $post->status && $post->status === PostStatus::Draft => 'Moved "'.$post->title.'" back to draft.',
            default => 'Saved "'.$post->title.'".',
        };
    }

    /**
     * Tags are content, not a manage-level assignment: anyone who can edit
     * the post's content may change them, untouched when the form didn't
     * include the field (e.g. a status-only crafted request).
     */
    private function syncTags(BlogPostRequest $request, BlogPost $post): void
    {
        if (! $request->has('tags')) {
            return;
        }

        $post->tags()->sync((array) $request->validated('tags'));
    }

    /**
     * @return list<int>
     */
    private function tagsOf(BlogPost $post): array
    {
        return $post->tags()->pluck('tags.id')->sort()->values()->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function contentFields(BlogPostRequest $request): array
    {
        return [
            'title' => $request->validated('title'),
            'excerpt' => $request->validated('excerpt'),
            'content' => $request->validated('content'),
            'category_id' => $request->validated('category_id') ? (int) $request->validated('category_id') : null,
            'featured_image_id' => $request->validated('featured_image_id') ? (int) $request->validated('featured_image_id') : null,
            'meta_title' => $request->validated('meta_title'),
            'meta_description' => $request->validated('meta_description'),
            'canonical_url' => $request->validated('canonical_url'),
        ];
    }
}
