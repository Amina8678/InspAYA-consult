<?php

namespace App\Http\Requests\Admin;

use App\Models\Category;
use App\Support\Slug;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Create or edit a blog category (FR-BLOG-01, plan §6 row 21). Needs
 * taxonomy.manage, enforced by CategoryPolicy in the controller.
 */
class CategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        $category = $this->route('category');

        return $category instanceof Category
            ? $this->user()->can('update', $category)
            : $this->user()->can('create', Category::class);
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
        $category = $this->route('category');

        return [
            'name' => ['required', 'string', 'max:100'],
            'slug' => [
                'nullable', 'string', 'max:'.Slug::MAX, 'regex:'.Slug::PATTERN,
                Rule::unique('categories', 'slug')->ignore($category instanceof Category ? $category->id : null),
            ],
            'description' => ['nullable', 'string', 'max:500'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'slug.regex' => 'The slug may only contain lowercase letters, numbers and single hyphens between words.',
            'slug.unique' => 'Another category already uses this slug.',
        ];
    }
}
