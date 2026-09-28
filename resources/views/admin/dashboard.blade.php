@extends('admin.layouts.app')

@section('title', 'Dashboard')

@section('content')
    <div class="page-head">
        <h1>Dashboard</h1>
        <p class="muted">Signed in as {{ auth()->user()->name }}</p>
    </div>

    @if ($alerts)
        <section class="alert alert--warning" aria-labelledby="alerts-heading">
            <h2 id="alerts-heading">Needs attention</h2>
            <ul>
                @foreach ($alerts as $alert)
                    <li>{{ $alert }}</li>
                @endforeach
            </ul>
        </section>
    @endif

    @if ($stats)
        <h2 class="visually-hidden">Content counts</h2>
        <ul class="stats">
            @foreach ($stats as $stat)
                <li class="stat">
                    <span class="stat__value">{{ number_format($stat['value']) }}</span>
                    <span class="stat__label">{{ $stat['label'] }}</span>
                </li>
            @endforeach
        </ul>
    @endif

    <div class="grid-2">
        @if ($recentPosts !== null)
            <section class="panel" aria-labelledby="posts-heading">
                <h2 id="posts-heading">{{ $ownPostsOnly ? 'Your recent posts' : 'Recent posts' }}</h2>
                @if ($recentPosts->isEmpty())
                    <p class="muted">No posts yet.</p>
                @else
                    <ul>
                        @foreach ($recentPosts as $post)
                            <li>
                                {{ $post->title }}
                                <span class="muted small">
                                    ({{ ucfirst($post->status->value) }}, {{ $post->author->name }},
                                    <time datetime="{{ $post->updated_at->toIso8601String() }}">{{ $post->updated_at->diffForHumans() }}</time>)
                                </span>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </section>
        @endif

        @if ($recentEnquiries !== null)
            <section class="panel" aria-labelledby="enquiries-heading">
                <h2 id="enquiries-heading">Recent enquiries</h2>
                @if ($recentEnquiries->isEmpty())
                    <p class="muted">No enquiries yet.</p>
                @else
                    <ul>
                        @foreach ($recentEnquiries as $enquiry)
                            <li>
                                {{ $enquiry->subject }}
                                <span class="muted small">
                                    from {{ $enquiry->name }} ({{ str($enquiry->status->value)->headline() }},
                                    <time datetime="{{ $enquiry->created_at->toIso8601String() }}">{{ $enquiry->created_at->diffForHumans() }}</time>)
                                </span>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </section>
        @endif
    </div>

    @if ($activity !== null)
        <section class="panel" aria-labelledby="activity-heading">
            <h2 id="activity-heading">Recent activity</h2>
            @if ($activity->isEmpty())
                <p class="muted">No activity recorded yet.</p>
            @else
                <table class="table">
                    <caption class="visually-hidden">Latest audit log entries, newest first</caption>
                    <thead>
                        <tr>
                            <th scope="col">When</th>
                            <th scope="col">Who</th>
                            <th scope="col">Action</th>
                            <th scope="col">Record</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($activity as $entry)
                            <tr>
                                <td data-label="When">
                                    <time datetime="{{ $entry->created_at->toIso8601String() }}">{{ $entry->created_at->format('j M Y, H:i') }}</time>
                                </td>
                                <td data-label="Who">{{ $entry->user?->name ?? ($entry->new_values['email'] ?? 'System') }}</td>
                                <td data-label="Action">{{ str($entry->action)->headline() }}</td>
                                <td data-label="Record">
                                    @if ($entry->entity_type)
                                        {{ str($entry->entity_type)->headline() }} #{{ $entry->entity_id }}
                                    @else
                                        <span class="muted">—</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </section>
    @endif
@endsection
