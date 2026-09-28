@extends('admin.layouts.app')

@section('title', 'Consultants')

@php
    $canReorder = auth()->user()->can('reorder', App\Models\Consultant::class) && $search === '';
@endphp

@section('content')
    <div class="page-head">
        <h1>Consultants</h1>
        @can('create', App\Models\Consultant::class)
            <a class="button" href="{{ route('admin.consultants.create') }}">Add a consultant</a>
        @endcan
    </div>

    <section class="panel" aria-labelledby="list-heading">
        <h2 id="list-heading" class="visually-hidden">All consultants</h2>

        @include('admin.partials.search', ['action' => route('admin.consultants.index'), 'value' => $search, 'label' => 'Search by name or job title'])

        @if ($consultants->isEmpty())
            <p>{{ $search === '' ? 'No consultants yet.' : 'No consultants match your search.' }}</p>
        @else
            @if ($search !== '' && auth()->user()->can('reorder', App\Models\Consultant::class))
                <p class="muted small">Clear the search to change the display order.</p>
            @endif
            <table class="table">
                <caption class="visually-hidden">Consultants in display order</caption>
                <thead>
                    <tr>
                        <th scope="col">Position</th>
                        <th scope="col">Photo</th>
                        <th scope="col">Consultant</th>
                        <th scope="col">Status</th>
                        <th scope="col">Services</th>
                        <th scope="col"><span class="visually-hidden">Actions</span></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($consultants as $consultant)
                        @php($position = $consultants->firstItem() + $loop->index)
                        <tr>
                            <td data-label="Position"><div>{{ $position }}</div></td>
                            <td data-label="Photo"><div>
                                @if ($consultant->photo)
                                    <img class="thumb" src="{{ Storage::disk($consultant->photo->disk)->url($consultant->photo->storage_path) }}"
                                         alt="" width="80" height="80" loading="lazy">
                                @else
                                    <span class="muted">None</span>
                                @endif
                            </div></td>
                            <td data-label="Consultant"><div>
                                <strong>{{ $consultant->name }}</strong>
                                @if ($consultant->title)<br><span class="muted small">{{ $consultant->title }}</span>@endif
                            </div></td>
                            <td data-label="Status"><div>{{ $consultant->is_active ? 'Shown' : 'Hidden' }}</div></td>
                            <td data-label="Services"><div>{{ $consultant->services_count }}</div></td>
                            <td data-label="Actions"><div class="actions">
                                @can('update', $consultant)
                                    <a href="{{ route('admin.consultants.edit', $consultant) }}">Edit<span class="visually-hidden"> {{ $consultant->name }}</span></a>
                                @endcan
                                @if ($canReorder)
                                    @include('admin.partials.move-buttons', [
                                        'action' => route('admin.consultants.move', $consultant),
                                        'label' => $consultant->name,
                                        'first' => $position === 1,
                                        'last' => $position === $consultants->total(),
                                    ])
                                @endif
                                @can('delete', $consultant)
                                    <a href="{{ route('admin.consultants.delete', $consultant) }}">Delete<span class="visually-hidden"> {{ $consultant->name }}</span></a>
                                @endcan
                            </div></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>

            {{ $consultants->links('admin.partials.pagination') }}
        @endif
    </section>
@endsection
