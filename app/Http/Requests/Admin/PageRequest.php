<?php

namespace App\Http\Requests\Admin;

use App\Models\Page;
use App\Rules\SafeLink;
use App\Support\CorePages;
use App\Support\MediaPicker;
use App\Support\PageSections;
use App\Support\Slug;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Create or edit a page (FR-ADM-04). Editors update content; creating needs
 * pages.manage and publishing/unpublishing needs content.publish (plan §6,
 * D2, D12), enforced by PagePolicy in the controller.
 *
 * When the form is submitted with a section_action (add/remove/move a
 * section), nothing is validated or saved: the controller rebuilds the form.
 */
class PageRequest extends FormRequest
{
    /** Anything that looks like an HTML tag: pages store plain text only. */
    private const NO_HTML = 'not_regex:/<\s*\/?\s*[a-z!]/i';

    public function authorize(): bool
    {
        $page = $this->route('page');

        return $page instanceof Page
            ? $this->user()->can('update', $page)
            : $this->user()->can('create', Page::class);
    }

    public function isSectionAction(): bool
    {
        return $this->filled('section_action');
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
        if ($this->isSectionAction()) {
            return [
                'section_action' => ['required', 'string', 'regex:/^(add|remove:\d+|up:\d+|down:\d+)$/'],
                'new_section_type' => ['nullable', 'string', Rule::in(array_keys(PageSections::TYPES))],
            ];
        }

        $page = $this->route('page');

        return [
            'title' => ['required', 'string', 'max:255', self::NO_HTML],
            'slug' => [
                'nullable', 'string', 'max:'.Slug::MAX, 'regex:'.Slug::PATTERN,
                Rule::unique('pages', 'slug')->ignore($page instanceof Page ? $page->id : null),
            ],
            'status' => ['sometimes', 'string', Rule::in(['draft', 'published'])],
            'meta_title' => ['nullable', 'string', 'max:255', self::NO_HTML],
            'meta_description' => ['nullable', 'string', 'max:500', self::NO_HTML],
            'canonical_url' => ['nullable', 'string', 'max:'.PageSections::URL_MAX, new SafeLink(webOnly: true)],
            'og_image_id' => ['nullable', 'integer', MediaPicker::rule()],
            'confirm_slug_change' => ['sometimes', 'boolean'],

            'sections' => ['nullable', 'array', 'max:'.PageSections::MAX_SECTIONS],
            'sections.*' => ['array'],
            'sections.*.type' => ['required', 'string', Rule::in(array_keys(PageSections::TYPES))],
            'sections.*.heading' => ['nullable', 'string', 'max:'.PageSections::HEADING_MAX, self::NO_HTML],
            'sections.*.body' => ['nullable', 'string', 'max:10000', self::NO_HTML],
            'sections.*.primary_cta_label' => ['nullable', 'string', 'max:'.PageSections::BUTTON_LABEL_MAX, self::NO_HTML],
            'sections.*.primary_cta_url' => ['nullable', 'string', 'max:'.PageSections::URL_MAX, new SafeLink],
            'sections.*.secondary_cta_label' => ['nullable', 'string', 'max:'.PageSections::BUTTON_LABEL_MAX, self::NO_HTML],
            'sections.*.secondary_cta_url' => ['nullable', 'string', 'max:'.PageSections::URL_MAX, new SafeLink],
            'sections.*.background_media_id' => ['nullable', 'integer', MediaPicker::rule()],
        ];
    }

    /**
     * Per-section rules that depend on the section type.
     *
     * @return list<callable>
     */
    public function after(): array
    {
        if ($this->isSectionAction()) {
            return [];
        }

        return [
            function (Validator $validator) {
                foreach ((array) $this->input('sections', []) as $i => $row) {
                    $type = is_array($row) ? ($row['type'] ?? null) : null;
                    if (! is_string($type) || ! isset(PageSections::TYPES[$type])) {
                        continue; // reported by sections.*.type
                    }

                    // Only this type's fields: unknown or foreign keys are rejected.
                    $extra = array_diff(array_keys($row), PageSections::formKeys($type));
                    foreach ($extra as $key) {
                        $validator->errors()->add("sections.$i.$key", 'Section '.($i + 1).' ('.PageSections::TYPES[$type]['label'].') has no "'.$key.'" field.');
                    }

                    $max = PageSections::TYPES[$type]['body_max'];
                    if (mb_strlen((string) ($row['body'] ?? '')) > $max) {
                        $validator->errors()->add("sections.$i.body", 'The text in section '.($i + 1)." may not be longer than {$max} characters.");
                    }

                    foreach (['primary_cta', 'secondary_cta'] as $button) {
                        $label = trim((string) ($row[$button.'_label'] ?? ''));
                        $url = trim((string) ($row[$button.'_url'] ?? ''));
                        if (($label === '') !== ($url === '') && in_array($button.'_url', PageSections::formKeys($type), true)) {
                            $validator->errors()->add("sections.$i.{$button}_".($label === '' ? 'label' : 'url'),
                                'Section '.($i + 1).': a button needs both a label and a link.');
                        }
                    }

                    $filled = collect($row)->except('type')->filter(fn ($v) => trim((string) $v) !== '');
                    if ($filled->isEmpty()) {
                        $validator->errors()->add("sections.$i.type", 'Section '.($i + 1).' is empty. Fill it in or remove it.');
                    }
                }

                $page = $this->route('page');
                if ($page instanceof Page) {
                    $newSlug = $this->input('slug');

                    if (CorePages::slugLocked($page->slug) && $newSlug !== null && $newSlug !== $page->slug) {
                        $validator->errors()->add('slug', 'The address of the '.$page->title.' page is fixed; its slug can\'t change.');
                    } elseif ($page->status->value === 'published' && $newSlug !== null && $newSlug !== $page->slug
                        && ! $this->boolean('confirm_slug_change')) {
                        $validator->errors()->add('confirm_slug_change',
                            'This page is published. Tick the box to confirm you want to change its slug; links to it will change.');
                    }
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
            'sections.*.*.not_regex' => 'HTML isn\'t allowed here. Write plain text; line breaks are kept.',
            'slug.regex' => 'The slug may only contain lowercase letters, numbers and single hyphens between words.',
            'slug.unique' => 'Another page already uses this slug.',
            'sections.max' => 'A page can have at most '.PageSections::MAX_SECTIONS.' sections.',
            'sections.*.type.in' => 'Unknown section type.',
            'sections.*.background_media_id.exists' => 'Choose an image from the media library.',
            'og_image_id.exists' => 'Choose an image from the media library.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'og_image_id' => 'sharing image',
            'canonical_url' => 'canonical URL',
            'sections.*.heading' => 'section heading',
            'sections.*.body' => 'section text',
            'sections.*.primary_cta_label' => 'first button label',
            'sections.*.primary_cta_url' => 'first button link',
            'sections.*.secondary_cta_label' => 'second button label',
            'sections.*.secondary_cta_url' => 'second button link',
            'sections.*.background_media_id' => 'section image',
        ];
    }
}
