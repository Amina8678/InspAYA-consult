@extends('admin.layouts.app')

@php
    // Checked against the resolved model, not revisable_type: that column
    // stores the enforced morph map alias ('page', 'blog_post'), not the FQCN.
    $editRoute = $revision->revisable instanceof App\Models\Page
        ? route('admin.pages.edit', $revision->revisable)
        : route('admin.posts.edit', $revision->revisable);
@endphp

@section('title', 'Review change to '.$revision->revisable->title)

@section('content')
    <div class="page-head">
        <h1>Review change</h1>
        <a href="{{ route('admin.content-approvals.index') }}">Back to content approvals</a>
    </div>

    <section class="panel" aria-labelledby="details-heading">
        <h2 id="details-heading">{{ $typeLabel }}: {{ $revision->revisable->title }}</h2>
        <p class="muted small">
            Submitted by {{ $revision->submitter?->name ?? 'a removed user' }},
            <time datetime="{{ $revision->created_at->toIso8601String() }}">{{ $revision->created_at->format('j M Y, H:i') }}</time>.
            <a href="{{ $editRoute }}">View the current live version</a>.
        </p>

        @include('admin.partials.errors', ['fields' => ['approve' => 'details-heading', 'reason' => 'field-reason']])

        @if ($slugTaken)
            <div class="alert alert--error" role="alert">
                <p><strong>The proposed URL is now used by another page or post.</strong>
                This change cannot be approved as it is. Reject it below and ask for a different URL.</p>
            </div>
        @elseif ($liveChanged)
            <div class="alert alert--warning" role="alert">
                <p><strong>This {{ strtolower($typeLabel) }} changed since the edit was submitted.</strong>
                The "Currently live" column below is the version live now. Approving replaces every field
                listed with the proposed value, including any change made since. Reject the edit and ask for
                a fresh one, or tick the box to approve it anyway.</p>
            </div>
        @endif

        <table class="table">
            <caption class="visually-hidden">Proposed changes</caption>
            <thead>
                <tr>
                    <th scope="col">Field</th>
                    <th scope="col">Currently live</th>
                    <th scope="col">Proposed</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($diff as $row)
                    <tr>
                        <th scope="row">{{ $row['label'] }}</th>
                        <td data-label="Currently live"><div class="pre-line">{{ $row['old'] }}</div></td>
                        <td data-label="Proposed"><div class="pre-line">{{ $row['new'] }}</div></td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        <div class="actions">
            @unless ($slugTaken)
                <form method="POST" action="{{ route('admin.content-approvals.approve', $revision) }}">
                    @csrf
                    @if ($liveChanged)
                        <div class="field">
                            <label>
                                <input type="checkbox" name="confirm_live_change" value="1" required>
                                I have compared the differences and want to overwrite the live changes
                            </label>
                        </div>
                    @endif
                    <button type="submit" class="button">{{ $liveChanged ? 'Approve anyway' : 'Approve and publish this change' }}</button>
                </form>
            @endunless
        </div>
    </section>

    <section class="panel" aria-labelledby="reject-heading">
        <h2 id="reject-heading">Reject this change</h2>
        <form method="POST" action="{{ route('admin.content-approvals.reject', $revision) }}">
            @csrf
            @include('admin.partials.field', ['name' => 'reason', 'label' => 'Reason', 'type' => 'textarea', 'required' => true,
                'value' => old('reason'), 'maxlength' => 2000,
                'hint' => 'Shown to '.($revision->submitter?->name ?? 'the submitter').' so they know why.'])
            <div class="actions">
                <button type="submit" class="button button--danger">Reject with this reason</button>
            </div>
        </form>
    </section>
@endsection
