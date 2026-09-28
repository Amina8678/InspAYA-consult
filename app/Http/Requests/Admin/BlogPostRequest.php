<?php

namespace App\Http\Requests\Admin;

use App\Models\BlogPost;
use App\Rules\SafeLink;
use App\Support\MediaPicker;
use App\Support\Slug;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Create or edit a blog post (FR-BLOG-01 to 05, FR-ADM-08). Authors write
 * their own drafts; editing any post, or moving one between draft and
 * review, needs posts.edit-any; publishing needs content.publish (plan §6,
 * D11/D12), all enforced by BlogPostPolicy in the controller.
 */
class BlogPostRequest extends FormRequest
{
    /** Content fields are plain text; posts carry no raw HTML (matches pages/services). */
    private const NO_HTML = 'not_regex:/<\s*\/?\s*[a-z!]/i';

    public function authorize(): bool
    {
        $post = $this->route('post');

        return $post instanceof BlogPost
            ? $this->user()->can('update', $post)
            : $this->user()->can('create', BlogPost::class);
    }

    protected function prepareForValidation(): void
    {
        $slug = strtolower(trim((string) $this->input('slug')));

        $this->merge(['slug' => $slug === '' ? null : $slug]);
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        $post = $this->route('post');

        return [
            'title' => ['required', 'string', 'max:255', self::NO_HTML],
            'slug' => [
                'nullable', 'string', 'max:'.Slug::MAX, 'regex:'.Slug::PATTERN,
                Rule::unique('blog_posts', 'slug')->ignore($post instanceof BlogPost ? $post->id : null),
            ],
            'excerpt' => ['nullable', 'string', 'max:1000', self::NO_HTML],
            'content' => ['required', 'string', 'max:50000', self::NO_HTML],
            'category_id' => ['nullable', 'integer', Rule::exists('categories', 'id')],
            'tags' => ['sometimes', 'array'],
            'tags.*' => ['integer', Rule::exists('tags', 'id')],
            'featured_image_id' => ['nullable', 'integer', MediaPicker::rule()],
            'meta_title' => ['nullable', 'string', 'max:255', self::NO_HTML],
            'meta_description' => ['nullable', 'string', 'max:500', self::NO_HTML],
            'canonical_url' => ['nullable', 'string', 'max:500', new SafeLink(webOnly: true)],
            'status' => ['sometimes', 'string', Rule::in(['draft', 'review', 'published'])],
            'published_at' => ['nullable', 'date'],
            'confirm_slug_change' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @return list<callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator) {
                $post = $this->route('post');
                $newSlug = $this->input('slug');

                // Changing the web address of a published post needs an explicit OK.
                if ($post instanceof BlogPost && $post->status->value === 'published' && $newSlug !== null && $newSlug !== $post->slug
                    && ! $this->boolean('confirm_slug_change')) {
                    $validator->errors()->add('confirm_slug_change',
                        'This post is published. Tick the box to confirm you want to change its slug; existing links to it will stop working.');
                }
            },
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            '*.not_regex' => 'HTML isn\'t allowed here. Write plain text; line breaks are kept.',
            'slug.regex' => 'The slug may only contain lowercase letters, numbers and single hyphens between words.',
            'slug.unique' => 'Another post already uses this slug.',
            'category_id.exists' => 'Choose a category from the list.',
            'tags.*.exists' => 'One of the chosen tags no longer exists. Reload the page and try again.',
            'featured_image_id.exists' => 'Choose an image from the media library.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'featured_image_id' => 'featured image',
            'canonical_url' => 'canonical URL',
            'published_at' => 'publication date',
        ];
    }
}
