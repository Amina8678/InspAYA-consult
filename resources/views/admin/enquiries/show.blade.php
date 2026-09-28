@extends('admin.layouts.app')

@php
    $canRespond = auth()->user()->can('respond', $submission);
    $canAssign = auth()->user()->can('assign', $submission);
    $mailto = App\Support\SafeUrl::sanitize('mailto:'.$submission->email);
    $tel = $submission->phone ? App\Support\SafeUrl::sanitize('tel:'.preg_replace('/[^0-9+]/', '', $submission->phone)) : null;
@endphp

@section('title', 'Enquiry from '.$submission->name)

@section('content')
    <div class="page-head">
        <h1>Enquiry from {{ $submission->name }}</h1>
        <a href="{{ route('admin.enquiries.index') }}">Back to enquiries</a>
    </div>

    <div class="grid-2">
        <section class="panel" aria-labelledby="details-heading">
            <h2 id="details-heading">Submitted details</h2>
            <dl>
                <dt>Name</dt>
                <dd>{{ $submission->name }}</dd>
                <dt>Email</dt>
                <dd>@if ($mailto)<a href="{{ $mailto }}">{{ $submission->email }}</a>@else{{ $submission->email }}@endif</dd>
                @if ($submission->phone)
                    <dt>Phone</dt>
                    <dd>@if ($tel)<a href="{{ $tel }}">{{ $submission->phone }}</a>@else{{ $submission->phone }}@endif</dd>
                @endif
                @if ($submission->organization)
                    <dt>Organisation</dt>
                    <dd>{{ $submission->organization }}</dd>
                @endif
                <dt>Subject</dt>
                <dd>{{ $submission->subject }}</dd>
                <dt>Message</dt>
                <dd style="white-space: pre-line;">{{ $submission->message }}</dd>
                <dt>Received</dt>
                <dd><time datetime="{{ $submission->created_at->toIso8601String() }}">{{ $submission->created_at->format('j M Y, H:i') }}</time></dd>
                <dt>Consent given</dt>
                <dd><time datetime="{{ $submission->consent_at->toIso8601String() }}">{{ $submission->consent_at->format('j M Y, H:i') }}</time></dd>
                @if ($submission->responded_at)
                    <dt>Responded</dt>
                    <dd><time datetime="{{ $submission->responded_at->toIso8601String() }}">{{ $submission->responded_at->format('j M Y, H:i') }}</time></dd>
                @endif
            </dl>
        </section>

        <section class="panel" aria-labelledby="handling-heading">
            <h2 id="handling-heading">Handling</h2>

            @include('admin.partials.errors', ['fields' => ['status' => 'field-status', 'assigned_to' => 'field-assigned_to']])

            @if ($canRespond || $canAssign)
                <form method="POST" action="{{ route('admin.enquiries.update', $submission) }}">
                    @csrf
                    @method('PUT')

                    @if ($canRespond)
                        <div class="field">
                            <label for="field-status">Status</label>
                            <select id="field-status" name="status">
                                @foreach (\App\Enums\EnquiryStatus::cases() as $status)
                                    <option value="{{ $status->value }}" @selected(old('status', $submission->status->value) === $status->value)>{{ str($status->value)->headline() }}</option>
                                @endforeach
                            </select>
                        </div>
                    @else
                        <p>Status: <strong>{{ str($submission->status->value)->headline() }}</strong>. Responding needs an editor or administrator.</p>
                    @endif

                    @if ($canAssign)
                        <div class="field">
                            <label for="field-assigned_to">Assigned to</label>
                            <select id="field-assigned_to" name="assigned_to">
                                <option value="">Unassigned</option>
                                @foreach ($assignees as $assignee)
                                    <option value="{{ $assignee->id }}" @selected((string) old('assigned_to', $submission->assigned_to) === (string) $assignee->id)>{{ $assignee->name }}</option>
                                @endforeach
                            </select>
                        </div>
                    @else
                        <p>Assigned to: <strong>{{ $submission->assignee?->name ?? 'Unassigned' }}</strong>. Only an administrator can change this.</p>
                    @endif

                    <div class="actions">
                        <button type="submit" class="button">Save changes</button>
                    </div>
                </form>
            @else
                <p>Status: <strong>{{ str($submission->status->value)->headline() }}</strong>.
                    Assigned to: <strong>{{ $submission->assignee?->name ?? 'Unassigned' }}</strong>.</p>
            @endif

            @can('delete', $submission)
                <p><a href="{{ route('admin.enquiries.delete', $submission) }}">Delete this enquiry</a></p>
            @endcan
        </section>
    </div>

    <section class="panel" aria-labelledby="notes-heading">
        <h2 id="notes-heading">Internal notes</h2>
        <p class="muted small">Notes are for staff only; the visitor never sees them.</p>

        @if ($submission->notes->isEmpty())
            <p class="muted">No notes yet.</p>
        @else
            <ul class="notes">
                @foreach ($submission->notes as $note)
                    <li class="panel">
                        <p style="white-space: pre-line;">{{ $note->body }}</p>
                        <p class="muted small">
                            {{ $note->user?->name ?? 'A removed user' }},
                            <time datetime="{{ $note->created_at->toIso8601String() }}">{{ $note->created_at->format('j M Y, H:i') }}</time>
                            @can('update', $note)
                                &middot; <a href="{{ route('admin.enquiries.notes.edit', [$submission, $note]) }}">Edit</a>
                            @endcan
                            @can('delete', $note)
                                &middot; <a href="{{ route('admin.enquiries.notes.delete', [$submission, $note]) }}">Delete</a>
                            @endcan
                        </p>
                    </li>
                @endforeach
            </ul>
        @endif

        @can('create', App\Models\ContactSubmissionNote::class)
            <form method="POST" action="{{ route('admin.enquiries.notes.store', $submission) }}">
                @csrf
                @include('admin.partials.field', ['name' => 'body', 'label' => 'Add a note', 'type' => 'textarea', 'required' => true,
                    'value' => old('body'), 'maxlength' => 5000])
                <div class="actions">
                    <button type="submit" class="button">Add note</button>
                </div>
            </form>
        @endcan
    </section>
@endsection
