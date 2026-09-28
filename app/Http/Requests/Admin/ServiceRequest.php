<?php

namespace App\Http\Requests\Admin;

use App\Models\Consultant;
use App\Models\Service;
use App\Support\Slug;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Create or edit a service (FR-SVC-01/02, FR-ADM-05). Editors may update
 * content; creating, the active flag and consultant assignments need
 * services.manage (plan §6, enforced by ServicePolicy in the controller).
 *
 * Capabilities and outcomes are textareas with one item per line.
 * Consultant assignments arrive as consultants[<id>] = none|supporting|lead.
 */
class ServiceRequest extends FormRequest
{
    public const MAX_LIST_ITEMS = 30;

    public const MAX_ITEM_LENGTH = 255;

    public function authorize(): bool
    {
        $service = $this->route('service');

        return $service instanceof Service
            ? $this->user()->can('update', $service)
            : $this->user()->can('create', Service::class);
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
        $service = $this->route('service');

        return [
            'title' => ['required', 'string', 'max:255'],
            'slug' => [
                'nullable', 'string', 'max:'.Slug::MAX, 'regex:'.Slug::PATTERN,
                Rule::unique('services', 'slug')->ignore($service instanceof Service ? $service->id : null),
            ],
            'short_description' => ['nullable', 'string', 'max:500'],
            'description' => ['nullable', 'string', 'max:20000'],
            'capabilities' => ['nullable', 'string', 'max:10000'],
            'outcomes' => ['nullable', 'string', 'max:10000'],
            'meta_title' => ['nullable', 'string', 'max:255'],
            'meta_description' => ['nullable', 'string', 'max:500'],
            'is_active' => ['sometimes', 'boolean'],
            'confirm_slug_change' => ['sometimes', 'boolean'],
            'consultants' => ['sometimes', 'array'],
            'consultants.*' => ['string', 'in:none,supporting,lead'],
        ];
    }

    /**
     * Rules that span several fields.
     *
     * @return list<callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator) {
                foreach (['capabilities', 'outcomes'] as $field) {
                    $items = self::lines($this->input($field));

                    if (count($items) > self::MAX_LIST_ITEMS) {
                        $validator->errors()->add($field, "Enter at most ".self::MAX_LIST_ITEMS." {$field}, one per line.");
                    }
                    foreach ($items as $item) {
                        if (mb_strlen($item) > self::MAX_ITEM_LENGTH) {
                            $validator->errors()->add($field, 'Each line must be '.self::MAX_ITEM_LENGTH.' characters or fewer.');
                            break;
                        }
                    }
                }

                $ids = array_keys((array) $this->input('consultants', []));
                if ($ids !== [] && Consultant::whereKey($ids)->count() !== count(array_unique($ids))) {
                    $validator->errors()->add('consultants', 'One of the consultants no longer exists. Reload the page and try again.');
                }

                // Changing the web address of a live service needs an explicit OK.
                $service = $this->route('service');
                $newSlug = $this->input('slug');
                if ($service instanceof Service && $service->is_active && $newSlug !== null && $newSlug !== $service->slug
                    && ! $this->boolean('confirm_slug_change')) {
                    $validator->errors()->add('confirm_slug_change',
                        'This service is live. Tick the box to confirm you want to change its web address; existing links to it will stop working.');
                }
            },
        ];
    }

    /**
     * A one-item-per-line textarea as a clean list: trimmed, empty lines dropped.
     *
     * @return list<string>
     */
    public static function lines(mixed $text): array
    {
        return array_values(array_filter(
            array_map('trim', preg_split('/\R/u', (string) $text) ?: []),
            fn (string $line) => $line !== '',
        ));
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'slug.regex' => 'The slug may only contain lowercase letters, numbers and single hyphens between words (e.g. "energy-policy").',
            'slug.unique' => 'Another service already uses this slug.',
            'consultants.*.in' => 'Choose "Not assigned", "Supporting" or "Lead" for each consultant.',
        ];
    }
}
