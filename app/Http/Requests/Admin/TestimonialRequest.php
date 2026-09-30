<?php

namespace App\Http\Requests\Admin;

use App\Models\Testimonial;
use App\Support\MediaPicker;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Create or edit a testimonial (stage 17). Editors may update content;
 * creating and the active flag need testimonials.manage (TestimonialPolicy,
 * same EditOrManage pattern as core values/services/consultants/pages).
 */
class TestimonialRequest extends FormRequest
{
    public function authorize(): bool
    {
        $testimonial = $this->route('testimonial');

        return $testimonial instanceof Testimonial
            ? $this->user()->can('update', $testimonial)
            : $this->user()->can('create', Testimonial::class);
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'quote' => ['required', 'string', 'max:2000'],
            'author_name' => ['required', 'string', 'max:255'],
            'author_title' => ['nullable', 'string', 'max:255'],
            'organization' => ['nullable', 'string', 'max:255'],
            'photo_id' => ['nullable', 'integer', MediaPicker::rule()],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'photo_id.exists' => 'Choose an image from the media library.',
        ];
    }
}
