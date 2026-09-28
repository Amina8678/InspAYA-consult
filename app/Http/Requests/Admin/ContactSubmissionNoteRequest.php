<?php

namespace App\Http\Requests\Admin;

use App\Models\ContactSubmissionNote;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Add or edit an internal enquiry note (D1, plan §6 row 28). Adding needs
 * enquiries.respond; editing needs enquiries.respond and being the note's own
 * author (ContactSubmissionNotePolicy) — nobody, not even an administrator,
 * silently rewrites someone else's note.
 */
class ContactSubmissionNoteRequest extends FormRequest
{
    /** Notes are plain text, like every other free-text field in the CMS. */
    private const NO_HTML = 'not_regex:/<\s*\/?\s*[a-z!]/i';

    public function authorize(): bool
    {
        $note = $this->route('note');

        return $note instanceof ContactSubmissionNote
            ? $this->user()->can('update', $note)
            : $this->user()->can('create', ContactSubmissionNote::class);
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'body' => ['required', 'string', 'max:5000', self::NO_HTML],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'body.not_regex' => 'HTML isn\'t allowed here. Write plain text; line breaks are kept.',
        ];
    }
}
