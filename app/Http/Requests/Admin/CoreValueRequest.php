<?php

namespace App\Http\Requests\Admin;

use App\Models\CoreValue;
use App\Support\MediaPicker;
use App\Support\Slug;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Create or edit a core value (FR-VAL-01/02, FR-ADM-06). Editors may update
 * content; creating and the active flag need values.manage (plan §6, enforced
 * by CoreValuePolicy). An empty slug is generated from the title.
 */
class CoreValueRequest extends FormRequest
{
    public function authorize(): bool
    {
        $value = $this->route('coreValue');

        return $value instanceof CoreValue
            ? $this->user()->can('update', $value)
            : $this->user()->can('create', CoreValue::class);
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'slug' => trim((string) $this->input('slug')) === '' ? null : strtolower(trim((string) $this->input('slug'))),
        ]);
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        $value = $this->route('coreValue');

        return [
            'title' => ['required', 'string', 'max:255'],
            'slug' => [
                'nullable', 'string', 'max:'.Slug::MAX, 'regex:'.Slug::PATTERN,
                Rule::unique('core_values', 'slug')->ignore($value instanceof CoreValue ? $value->id : null),
            ],
            'description' => ['nullable', 'string', 'max:2000'],
            'icon_id' => ['nullable', 'integer', MediaPicker::rule()],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'slug.regex' => 'The slug may only contain lowercase letters, numbers and single hyphens between words (e.g. "integrity" or "client-first").',
            'slug.unique' => 'Another core value already uses this slug.',
            'icon_id.exists' => 'Choose an image from the media library.',
        ];
    }
}
