@extends('admin.layouts.app')

@section('title', 'Tags')

@section('content')
    <div class="page-head">
        <h1>Tags</h1>
        @can('create', App\Models\Tag::class)
            <a class="button" href="{{ route('admin.tags.create') }}">Add a tag</a>
        @endcan
    </div>

    <section class="panel" aria-labelledby="list-heading">
        <h2 id="list-heading" class="visually-hidden">All tags</h2>

        @include('admin.partials.search', ['action' => route('admin.tags.index'), 'value' => $search, 'label' => 'Search by name'])

        @if ($tags->isEmpty())
            <p>{{ $search === '' ? 'No tags yet.' : 'No tags match your search.' }}</p>
        @else
            <table class="table">
                <caption class="visually-hidden">Tags, alphabetical</caption>
                <thead>
                    <tr>
                        <th scope="col">Tag</th>
                        <th scope="col">Posts</th>
                        <th scope="col"><span class="visually-hidden">Actions</span></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($tags as $tag)
                        <tr>
                            <td data-label="Tag"><div>
                                <strong>{{ $tag->name }}</strong><br>
                                <span class="muted small">{{ $tag->slug }}</span>
                            </div></td>
                            <td data-label="Posts"><div>{{ $tag->blog_posts_count }}</div></td>
                            <td data-label="Actions"><div class="actions">
                                @can('update', $tag)
                                    <a href="{{ route('admin.tags.edit', $tag) }}">Edit<span class="visually-hidden"> {{ $tag->name }}</span></a>
                                @endcan
                                @can('delete', $tag)
                                    <a href="{{ route('admin.tags.delete', $tag) }}">Delete<span class="visually-hidden"> {{ $tag->name }}</span></a>
                                @endcan
                            </div></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>

            {{ $tags->links('admin.partials.pagination') }}
        @endif
    </section>
@endsection
