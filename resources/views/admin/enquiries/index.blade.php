@extends('admin.layouts.app')

@section('title', 'Enquiries')

@section('content')
    <div class="page-head">
        <h1>Enquiries</h1>
        @can('export', App\Models\ContactSubmission::class)
            <a class="button" href="{{ route('admin.enquiries.export', request()->only(['q', 'status'])) }}">Export CSV</a>
        @endcan
    </div>

    <section class="panel" aria-labelledby="list-heading">
        <h2 id="list-heading" class="visually-hidden">All enquiries</h2>

        <form class="filters" method="GET" action="{{ route('admin.enquiries.index') }}" role="search">
            <div class="field">
                <label for="list-search">Search by name, email or organisation</label>
                <input id="list-search" type="text" name="q" value="{{ $search }}" maxlength="100">
            </div>
            <div class="field">
                <label for="status-filter">Status</label>
                <select id="status-filter" name="status">
                    <option value="">All statuses</option>
                    @foreach ($statuses as $status)
                        <option value="{{ $status->value }}" @selected($statusFilter === $status->value)>{{ str($status->value)->headline() }}</option>
                    @endforeach
                </select>
            </div>
            <button type="submit" class="button button--secondary">Search</button>
            @if ($search !== '' || $statusFilter !== null)
                <a href="{{ route('admin.enquiries.index') }}">Clear</a>
            @endif
        </form>

        @if ($submissions->isEmpty())
            <p>{{ $search === '' && $statusFilter === null ? 'No enquiries yet.' : 'No enquiries match your search.' }}</p>
        @else
            <table class="table">
                <caption class="visually-hidden">Enquiries, newest first</caption>
                <thead>
                    <tr>
                        <th scope="col">From</th>
                        <th scope="col">Subject</th>
                        <th scope="col">Status</th>
                        <th scope="col">Assigned to</th>
                        <th scope="col">Received</th>
                        <th scope="col"><span class="visually-hidden">Actions</span></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($submissions as $submission)
                        <tr>
                            <td data-label="From"><div>
                                <strong>{{ $submission->name }}</strong>
                                @if ($submission->organization)<br><span class="muted small">{{ $submission->organization }}</span>@endif
                            </div></td>
                            <td data-label="Subject"><div>{{ $submission->subject }}</div></td>
                            <td data-label="Status"><div>{{ str($submission->status->value)->headline() }}</div></td>
                            <td data-label="Assigned to"><div>{{ $submission->assignee?->name ?? 'Unassigned' }}</div></td>
                            <td data-label="Received"><div>
                                <time datetime="{{ $submission->created_at->toIso8601String() }}">{{ $submission->created_at->format('j M Y') }}</time>
                            </div></td>
                            <td data-label="Actions"><div class="actions">
                                <a href="{{ route('admin.enquiries.show', $submission) }}">View<span class="visually-hidden"> enquiry from {{ $submission->name }}</span></a>
                                @can('delete', $submission)
                                    <a href="{{ route('admin.enquiries.delete', $submission) }}">Delete<span class="visually-hidden"> enquiry from {{ $submission->name }}</span></a>
                                @endcan
                            </div></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>

            {{ $submissions->links('admin.partials.pagination') }}
        @endif
    </section>
@endsection
