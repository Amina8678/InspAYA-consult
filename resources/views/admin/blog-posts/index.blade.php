@extends('admin.layouts.app')

@section('title', 'Blog posts')

@php
    $statusLabel = fn ($status) => match ($status->value) {
        'draft' => 'Draft',
        'review' => 'In review',
        'published' => 'Published',
    };
@endphp

@section('content')
    <div class="page-head">
        <h1>Blog posts</h1>
        @can('create', App\Models\BlogPost::class)
            <a class="button" href="{{ route('admin.posts.create') }}">Write a post</a>
        @endcan
    </div>

    <section class="panel" aria-labelledby="list-heading">
        <h2 id="list-heading" class="visually-hidden">{{ $ownOnly ? 'Your posts' : 'All posts' }}</h2>

        @if ($ownOnly)
            <p class="muted small">Showing your own posts only.</p>
        @endif

        @include('admin.partials.search', ['action' => route('admin.posts.index'), 'value' => $search, 'label' => 'Search by title or slug'])

        @if ($posts->isEmpty())
            <p>{{ $search === '' ? 'No posts yet.' : 'No posts match your search.' }}</p>
        @else
            <table class="table">
                <caption class="visually-hidden">Blog posts, most recently changed first</caption>
                <thead>
                    <tr>
                        <th scope="col">Post</th>
                        <th scope="col">Status</th>
                        <th scope="col">Author</th>
                        <th scope="col">Category</th>
                        <th scope="col">Last changed</th>
                        <th scope="col"><span class="visually-hidden">Actions</span></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($posts as $post)
                        <tr>
                            <td data-label="Post"><div><strong>{{ $post->title }}</strong></div></td>
                            <td data-label="Status"><div>{{ $statusLabel($post->status) }}</div></td>
                            <td data-label="Author"><div>{{ $post->author->name }}</div></td>
                            <td data-label="Category"><div>{{ $post->category?->name ?? 'None' }}</div></td>
                            <td data-label="Last changed"><div>
                                <time datetime="{{ $post->updated_at->toIso8601String() }}">{{ $post->updated_at->format('j M Y') }}</time>
                            </div></td>
                            <td data-label="Actions"><div class="actions">
                                @can('update', $post)
                                    <a href="{{ route('admin.posts.edit', $post) }}">Edit<span class="visually-hidden"> {{ $post->title }}</span></a>
                                @endcan
                                @if ($post->status->value === 'published')
                                    <a href="{{ route('insights.show', $post->slug) }}" target="_blank" rel="noopener">View<span class="visually-hidden"> {{ $post->title }} on the website (opens in a new tab)</span></a>
                                @endif
                                @can('delete', $post)
                                    <a href="{{ route('admin.posts.delete', $post) }}">Delete<span class="visually-hidden"> {{ $post->title }}</span></a>
                                @endcan
                            </div></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>

            {{ $posts->links('admin.partials.pagination') }}
        @endif
    </section>
@endsection
