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

        @include('admin.partials.errors', ['fields' => ['reason' => 'field-reason']])

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
            <form method="POST" action="{{ route('admin.content-approvals.approve', $revision) }}">
                @csrf
                <button type="submit" class="button">Approve and publish this change</button>
            </form>
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
