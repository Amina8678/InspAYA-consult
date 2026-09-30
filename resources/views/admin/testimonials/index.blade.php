@extends('admin.layouts.app')

@section('title', 'Testimonials')

@php
    $canReorder = auth()->user()->can('reorder', App\Models\Testimonial::class) && $search === '';
@endphp

@section('content')
    <div class="page-head">
        <h1>Testimonials</h1>
        @can('create', App\Models\Testimonial::class)
            <a class="button" href="{{ route('admin.testimonials.create') }}">Add a testimonial</a>
        @endcan
    </div>

    <section class="panel" aria-labelledby="list-heading">
        <h2 id="list-heading" class="visually-hidden">All testimonials</h2>

        @include('admin.partials.search', ['action' => route('admin.testimonials.index'), 'value' => $search, 'label' => 'Search by author name'])

        @if ($testimonials->isEmpty())
            <p>{{ $search === '' ? 'No testimonials yet.' : 'No testimonials match your search.' }}</p>
        @else
            @if (! $canReorder && $search !== '')
                <p class="muted small">Clear the search to change the display order.</p>
            @endif
            <table class="table">
                <caption class="visually-hidden">Testimonials in display order</caption>
                <thead>
                    <tr>
                        <th scope="col">Position</th>
                        <th scope="col">Photo</th>
                        <th scope="col">Author</th>
                        <th scope="col">Quote</th>
                        <th scope="col">Status</th>
                        <th scope="col"><span class="visually-hidden">Actions</span></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($testimonials as $testimonial)
                        @php($position = $testimonials->firstItem() + $loop->index)
                        <tr>
                            <td data-label="Position"><div>{{ $position }}</div></td>
                            <td data-label="Photo"><div>
                                @if ($testimonial->photo)
                                    <img class="thumb" src="{{ Storage::disk($testimonial->photo->disk)->url($testimonial->photo->storage_path) }}"
                                         alt="" width="80" height="80" loading="lazy">
                                @else
                                    <span class="muted">None</span>
                                @endif
                            </div></td>
                            <td data-label="Author"><div>
                                <strong>{{ $testimonial->author_name }}</strong>
                                @if ($testimonial->author_title || $testimonial->organization)
                                    <br><span class="muted small">{{ trim(implode(', ', array_filter([$testimonial->author_title, $testimonial->organization]))) }}</span>
                                @endif
                            </div></td>
                            <td data-label="Quote"><div>{{ \Illuminate\Support\Str::limit($testimonial->quote, 80) }}</div></td>
                            <td data-label="Status"><div>{{ $testimonial->is_active ? 'Active' : 'Hidden' }}</div></td>
                            <td data-label="Actions"><div class="actions">
                                @can('update', $testimonial)
                                    <a href="{{ route('admin.testimonials.edit', $testimonial) }}">Edit<span class="visually-hidden"> testimonial from {{ $testimonial->author_name }}</span></a>
                                @endcan
                                @if ($canReorder)
                                    @include('admin.partials.move-buttons', [
                                        'action' => route('admin.testimonials.move', $testimonial),
                                        'label' => 'testimonial from '.$testimonial->author_name,
                                        'first' => $position === 1,
                                        'last' => $position === $testimonials->total(),
                                    ])
                                @endif
                                @can('delete', $testimonial)
                                    <a href="{{ route('admin.testimonials.delete', $testimonial) }}">Delete<span class="visually-hidden"> testimonial from {{ $testimonial->author_name }}</span></a>
                                @endcan
                            </div></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>

            {{ $testimonials->links('admin.partials.pagination') }}
        @endif
    </section>
@endsection
