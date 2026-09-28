<?php

namespace App\Http\Requests\Admin;

use App\Models\ContactSubmission;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Change an enquiry's status and/or assignment (FR-CONT-06, FR-ADM-10, plan
 * §6 rows 27-29). Status needs enquiries.respond; assignment needs
 * enquiries.assign; each is ignored (not saved) for a user who lacks it,
 * checked again in the controller against ContactSubmissionPolicy.
 */
class ContactSubmissionRequest extends FormRequest
{
    public function authorize(): bool
    {
        $submission = $this->route('submission');

        return $submission instanceof ContactSubmission
            && ($this->user()->can('respond', $submission) || $this->user()->can('assign', $submission));
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'status' => ['sometimes', 'string', Rule::in(['new', 'in_progress', 'responded', 'closed'])],
            'assigned_to' => ['sometimes', 'nullable', 'integer', Rule::exists('users', 'id')],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'assigned_to.exists' => 'Choose a CMS user to assign this enquiry to.',
        ];
    }
}
