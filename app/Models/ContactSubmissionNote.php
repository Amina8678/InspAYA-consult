<?php

namespace App\Models;

use Database\Factories\ContactSubmissionNoteFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ContactSubmissionNote extends Model
{
    /** @use HasFactory<ContactSubmissionNoteFactory> */
    use HasFactory;

    /**
     * contact_submission_id and user_id are set through the relations.
     *
     * @var list<string>
     */
    protected $fillable = ['body'];

    /**
     * @return BelongsTo<ContactSubmission, $this>
     */
    public function submission(): BelongsTo
    {
        return $this->belongsTo(ContactSubmission::class, 'contact_submission_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
