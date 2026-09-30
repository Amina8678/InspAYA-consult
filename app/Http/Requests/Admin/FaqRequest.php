<?php

namespace App\Http\Requests\Admin;

use App\Models\Faq;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Create or edit an FAQ (stage 17). Editors may update content; creating
 * and the active flag need faqs.manage (FaqPolicy, same EditOrManage
 * pattern as core values/services/consultants/pages).
 */
class FaqRequest extends FormRequest
{
    public function authorize(): bool
    {
        $faq = $this->route('faq');

        return $faq instanceof Faq
            ? $this->user()->can('update', $faq)
            : $this->user()->can('create', Faq::class);
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'question' => ['required', 'string', 'max:500'],
            'answer' => ['required', 'string', 'max:5000'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
