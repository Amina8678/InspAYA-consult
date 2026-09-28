@extends('admin.layouts.app')

@section('title', 'Categories')

@section('content')
    <div class="page-head">
        <h1>Categories</h1>
        @can('create', App\Models\Category::class)
            <a class="button" href="{{ route('admin.categories.create') }}">Add a category</a>
        @endcan
    </div>

    <section class="panel" aria-labelledby="list-heading">
        <h2 id="list-heading" class="visually-hidden">All categories</h2>

        @include('admin.partials.search', ['action' => route('admin.categories.index'), 'value' => $search, 'label' => 'Search by name'])

        @if ($categories->isEmpty())
            <p>{{ $search === '' ? 'No categories yet.' : 'No categories match your search.' }}</p>
        @else
            <table class="table">
                <caption class="visually-hidden">Categories, alphabetical</caption>
                <thead>
                    <tr>
                        <th scope="col">Category</th>
                        <th scope="col">Posts</th>
                        <th scope="col"><span class="visually-hidden">Actions</span></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($categories as $category)
                        <tr>
                            <td data-label="Category"><div>
                                <strong>{{ $category->name }}</strong><br>
                                <span class="muted small">{{ $category->slug }}</span>
                            </div></td>
                            <td data-label="Posts"><div>{{ $category->blog_posts_count }}</div></td>
                            <td data-label="Actions"><div class="actions">
                                @can('update', $category)
                                    <a href="{{ route('admin.categories.edit', $category) }}">Edit<span class="visually-hidden"> {{ $category->name }}</span></a>
                                @endcan
                                @can('delete', $category)
                                    <a href="{{ route('admin.categories.delete', $category) }}">Delete<span class="visually-hidden"> {{ $category->name }}</span></a>
                                @endcan
                            </div></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>

            {{ $categories->links('admin.partials.pagination') }}
        @endif
    </section>
@endsection
