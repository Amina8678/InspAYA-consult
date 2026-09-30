<?php

namespace App\Http\Requests\Admin;

use App\Models\PendingRevision;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Rejecting a pending revision (stage 16) always needs a reason: the
 * submitting Editor sees it as the only explanation for why their change
 * didn't go live.
 */
class RejectRevisionRequest extends FormRequest
{
    /** Reasons are plain text, like every other free-text field in the CMS. */
    private const NO_HTML = 'not_regex:/<\s*\/?\s*[a-z!]/i';

    public function authorize(): bool
    {
        return $this->user()->can('reject', $this->route('pendingRevision') ?? PendingRevision::class);
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'reason' => ['required', 'string', 'max:2000', self::NO_HTML],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'reason.not_regex' => 'HTML isn\'t allowed here. Write plain text; line breaks are kept.',
        ];
    }
}
