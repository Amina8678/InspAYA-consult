@extends('admin.layouts.app')

@section('title', 'Pages')

@section('content')
    <div class="page-head">
        <h1>Pages</h1>
        @can('create', App\Models\Page::class)
            <a class="button" href="{{ route('admin.pages.create') }}">Add a page</a>
        @endcan
    </div>

    <section class="panel" aria-labelledby="list-heading">
        <h2 id="list-heading" class="visually-hidden">All pages</h2>

        @include('admin.partials.search', ['action' => route('admin.pages.index'), 'value' => $search, 'label' => 'Search by title or slug'])

        @if ($pages->isEmpty())
            <p>{{ $search === '' ? 'No pages yet.' : 'No pages match your search.' }}</p>
        @else
            <table class="table">
                <caption class="visually-hidden">Pages, alphabetical</caption>
                <thead>
                    <tr>
                        <th scope="col">Page</th>
                        <th scope="col">Status</th>
                        <th scope="col">Last changed</th>
                        <th scope="col"><span class="visually-hidden">Actions</span></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($pages as $page)
                        @php($url = App\Support\CorePages::url($page->slug))
                        <tr>
                            <td data-label="Page"><div>
                                <strong>{{ $page->title }}</strong><br>
                                <span class="muted small">{{ $url ? parse_url($url, PHP_URL_PATH) ?: '/' : 'No public address yet' }}</span>
                            </div></td>
                            <td data-label="Status"><div>{{ $page->status->value === 'published' ? 'Published' : 'Draft' }}</div></td>
                            <td data-label="Last changed"><div>
                                <time datetime="{{ $page->updated_at->toIso8601String() }}">{{ $page->updated_at->format('j M Y') }}</time>
                            </div></td>
                            <td data-label="Actions"><div class="actions">
                                @can('update', $page)
                                    <a href="{{ route('admin.pages.edit', $page) }}">Edit<span class="visually-hidden"> {{ $page->title }}</span></a>
                                @endcan
                                @if ($url && $page->status->value === 'published')
                                    <a href="{{ $url }}" target="_blank" rel="noopener">View<span class="visually-hidden"> {{ $page->title }} on the website (opens in a new tab)</span></a>
                                @endif
                                @if (App\Support\CorePages::deletable($page->slug))
                                    @can('delete', $page)
                                        <a href="{{ route('admin.pages.delete', $page) }}">Delete<span class="visually-hidden"> {{ $page->title }}</span></a>
                                    @endcan
                                @endif
                            </div></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>

            {{ $pages->links('admin.partials.pagination') }}
        @endif
    </section>
@endsection
