@extends('admin.layouts.app')

@section('title', 'Content approvals')

@section('content')
    <div class="page-head">
        <h1>Content approvals</h1>
    </div>

    <section class="panel" aria-labelledby="list-heading">
        <h2 id="list-heading" class="visually-hidden">Pending changes</h2>

        @if ($revisions->isEmpty())
            <p>Nothing is waiting for approval.</p>
        @else
            <table class="table">
                <caption class="visually-hidden">Pending changes, oldest first</caption>
                <thead>
                    <tr>
                        <th scope="col">Type</th>
                        <th scope="col">Title</th>
                        <th scope="col">Submitted by</th>
                        <th scope="col">Submitted</th>
                        <th scope="col"><span class="visually-hidden">Actions</span></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($revisions as $revision)
                        <tr>
                            <td data-label="Type"><div>{{ $typeLabel($revision) }}</div></td>
                            <td data-label="Title"><div>{{ $revision->revisable->title }}</div></td>
                            <td data-label="Submitted by"><div>{{ $revision->submitter?->name ?? 'A removed user' }}</div></td>
                            <td data-label="Submitted"><div>
                                <time datetime="{{ $revision->created_at->toIso8601String() }}">{{ $revision->created_at->format('j M Y, H:i') }}</time>
                            </div></td>
                            <td data-label="Actions"><div class="actions">
                                <a href="{{ route('admin.content-approvals.show', $revision) }}">Review<span class="visually-hidden"> change to {{ $revision->revisable->title }}</span></a>
                            </div></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>

            {{ $revisions->links('admin.partials.pagination') }}
        @endif
    </section>
@endsection
