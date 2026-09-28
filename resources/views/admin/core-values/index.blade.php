@extends('admin.layouts.app')

@section('title', 'Core values')

@php
    $canReorder = auth()->user()->can('reorder', App\Models\CoreValue::class) && $search === '';
@endphp

@section('content')
    <div class="page-head">
        <h1>Core values</h1>
        @can('create', App\Models\CoreValue::class)
            <a class="button" href="{{ route('admin.core-values.create') }}">Add a core value</a>
        @endcan
    </div>

    <section class="panel" aria-labelledby="list-heading">
        <h2 id="list-heading" class="visually-hidden">All core values</h2>

        @include('admin.partials.search', ['action' => route('admin.core-values.index'), 'value' => $search, 'label' => 'Search core values'])

        @if ($values->isEmpty())
            <p>{{ $search === '' ? 'No core values yet.' : 'No core values match your search.' }}</p>
        @else
            @if (! $canReorder && $search !== '')
                <p class="muted small">Clear the search to change the display order.</p>
            @endif
            <table class="table">
                <caption class="visually-hidden">Core values in display order</caption>
                <thead>
                    <tr>
                        <th scope="col">Position</th>
                        <th scope="col">Icon</th>
                        <th scope="col">Title</th>
                        <th scope="col">Status</th>
                        <th scope="col"><span class="visually-hidden">Actions</span></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($values as $value)
                        @php($position = $values->firstItem() + $loop->index)
                        <tr>
                            <td data-label="Position"><div>{{ $position }}</div></td>
                            <td data-label="Icon"><div>
                                @if ($value->icon)
                                    <img class="thumb" src="{{ Storage::disk($value->icon->disk)->url($value->icon->storage_path) }}"
                                         alt="" width="80" height="80" loading="lazy">
                                @else
                                    <span class="muted">None</span>
                                @endif
                            </div></td>
                            <td data-label="Title"><div><strong>{{ $value->title }}</strong><br><span class="muted small">{{ $value->slug }}</span></div></td>
                            <td data-label="Status"><div>{{ $value->is_active ? 'Active' : 'Hidden' }}</div></td>
                            <td data-label="Actions"><div class="actions">
                                @can('update', $value)
                                    <a href="{{ route('admin.core-values.edit', $value) }}">Edit<span class="visually-hidden"> {{ $value->title }}</span></a>
                                @endcan
                                @if ($canReorder)
                                    @include('admin.partials.move-buttons', [
                                        'action' => route('admin.core-values.move', $value),
                                        'label' => $value->title,
                                        'first' => $position === 1,
                                        'last' => $position === $values->total(),
                                    ])
                                @endif
                                @can('delete', $value)
                                    <a href="{{ route('admin.core-values.delete', $value) }}">Delete<span class="visually-hidden"> {{ $value->title }}</span></a>
                                @endcan
                            </div></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>

            {{ $values->links('admin.partials.pagination') }}
        @endif
    </section>
@endsection
