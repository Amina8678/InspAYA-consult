<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ContactSubmissionNoteRequest;
use App\Models\ContactSubmission;
use App\Models\ContactSubmissionNote;
use App\Support\AuditLogger;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Internal enquiry notes (D1, plan §6 row 28). Routes carry permission:
 * middleware; every action also authorizes against ContactSubmissionNotePolicy
 * (a note is edited only by its own author; deleting follows enquiry deletion
 * rights, not authorship). Audited against the parent enquiry, not the note,
 * so its history reads alongside the enquiry's own.
 */
class ContactSubmissionNoteController extends Controller
{
    public function __construct(private AuditLogger $audit) {}

    public function store(ContactSubmissionNoteRequest $request, ContactSubmission $submission): RedirectResponse
    {
        $note = new ContactSubmissionNote(['body' => $request->validated('body')]);
        $note->submission()->associate($submission);
        // Not mass assignable by design (a request must never attribute a note to someone else).
        $note->user_id = $request->user()->id;
        $note->save();

        $this->audit->record('note_created', $request->user(), $submission, new: ['note_id' => $note->id, 'body' => $note->body]);

        return redirect()->route('admin.enquiries.show', $submission)->with('status', 'Note added.');
    }

    public function edit(ContactSubmission $submission, ContactSubmissionNote $note): View
    {
        $this->authorizeNote($submission, $note, 'update');

        return view('admin.enquiries.notes.edit', ['submission' => $submission, 'note' => $note]);
    }

    public function update(ContactSubmissionNoteRequest $request, ContactSubmission $submission, ContactSubmissionNote $note): RedirectResponse
    {
        $this->authorizeNote($submission, $note, 'update');

        $before = $note->body;
        $note->body = $request->validated('body');

        if ($note->isDirty()) {
            $note->save();
            $this->audit->record('note_updated', $request->user(), $submission, ['note_id' => $note->id, 'body' => $before], ['note_id' => $note->id, 'body' => $note->body]);
        }

        return redirect()->route('admin.enquiries.show', $submission)->with('status', 'Note saved.');
    }

    public function confirmDelete(ContactSubmission $submission, ContactSubmissionNote $note): View
    {
        $this->authorizeNote($submission, $note, 'delete');

        return view('admin.enquiries.notes.delete', ['submission' => $submission, 'note' => $note]);
    }

    public function destroy(Request $request, ContactSubmission $submission, ContactSubmissionNote $note): RedirectResponse
    {
        $this->authorizeNote($submission, $note, 'delete');

        $request->validate(['confirm' => ['accepted']], [
            'confirm.accepted' => 'Confirm that you want to delete this note.',
        ]);

        $note->delete();

        $this->audit->record('note_deleted', $request->user(), $submission, ['note_id' => $note->id, 'body' => $note->body]);

        return redirect()->route('admin.enquiries.show', $submission)->with('status', 'Note deleted.');
    }

    /**
     * A note's id is only unique globally, so a crafted URL could mix a real
     * note with an unrelated enquiry in the path; refuse that combination
     * with the same 404 an unknown id would get, before the ability check.
     */
    private function authorizeNote(ContactSubmission $submission, ContactSubmissionNote $note, string $ability): void
    {
        abort_unless($note->contact_submission_id === $submission->id, 404);
        $this->authorize($ability, $note);
    }
}
