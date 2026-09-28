<?php

namespace App\Http\Requests\Admin;

use App\Models\Tag;
use App\Support\Slug;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Create or edit a blog tag (FR-BLOG-01, plan §6 row 21). Needs
 * taxonomy.manage, enforced by TagPolicy in the controller.
 */
class TagRequest extends FormRequest
{
    public function authorize(): bool
    {
        $tag = $this->route('tag');

        return $tag instanceof Tag
            ? $this->user()->can('update', $tag)
            : $this->user()->can('create', Tag::class);
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
        $tag = $this->route('tag');

        return [
            'name' => ['required', 'string', 'max:100'],
            'slug' => [
                'nullable', 'string', 'max:'.Slug::MAX, 'regex:'.Slug::PATTERN,
                Rule::unique('tags', 'slug')->ignore($tag instanceof Tag ? $tag->id : null),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'slug.regex' => 'The slug may only contain lowercase letters, numbers and single hyphens between words.',
            'slug.unique' => 'Another tag already uses this slug.',
        ];
    }
}
