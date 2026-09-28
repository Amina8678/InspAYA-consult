@extends('admin.layouts.app')

@section('title', 'Services')

@php
    $canReorder = auth()->user()->can('reorder', App\Models\Service::class) && $search === '';
@endphp

@section('content')
    <div class="page-head">
        <h1>Services</h1>
        @can('create', App\Models\Service::class)
            <a class="button" href="{{ route('admin.services.create') }}">Add a service</a>
        @endcan
    </div>

    <section class="panel" aria-labelledby="list-heading">
        <h2 id="list-heading" class="visually-hidden">All services</h2>

        @include('admin.partials.search', ['action' => route('admin.services.index'), 'value' => $search, 'label' => 'Search services'])

        @if ($services->isEmpty())
            <p>{{ $search === '' ? 'No services yet.' : 'No services match your search.' }}</p>
        @else
            @if ($search !== '' && auth()->user()->can('reorder', App\Models\Service::class))
                <p class="muted small">Clear the search to change the display order.</p>
            @endif
            <table class="table">
                <caption class="visually-hidden">Services in display order</caption>
                <thead>
                    <tr>
                        <th scope="col">Position</th>
                        <th scope="col">Service</th>
                        <th scope="col">Status</th>
                        <th scope="col">Consultants</th>
                        <th scope="col"><span class="visually-hidden">Actions</span></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($services as $service)
                        @php($position = $services->firstItem() + $loop->index)
                        <tr>
                            <td data-label="Position"><div>{{ $position }}</div></td>
                            <td data-label="Service"><div>
                                <strong>{{ $service->title }}</strong><br>
                                <span class="muted small">/services/{{ $service->slug }}</span>
                            </div></td>
                            <td data-label="Status"><div>{{ $service->is_active ? 'Live' : 'Hidden' }}</div></td>
                            <td data-label="Consultants"><div>
                                {{ $service->consultants_count }}
                                @if ($service->lead_consultants_count)
                                    <span class="muted small">({{ $service->lead_consultants_count }} lead)</span>
                                @endif
                            </div></td>
                            <td data-label="Actions"><div class="actions">
                                @can('update', $service)
                                    <a href="{{ route('admin.services.edit', $service) }}">Edit<span class="visually-hidden"> {{ $service->title }}</span></a>
                                @endcan
                                @if ($service->is_active)
                                    <a href="{{ route('services.show', $service->slug) }}" target="_blank" rel="noopener">View<span class="visually-hidden"> {{ $service->title }} on the website (opens in a new tab)</span></a>
                                @endif
                                @if ($canReorder)
                                    @include('admin.partials.move-buttons', [
                                        'action' => route('admin.services.move', $service),
                                        'label' => $service->title,
                                        'first' => $position === 1,
                                        'last' => $position === $services->total(),
                                    ])
                                @endif
                                @can('delete', $service)
                                    <a href="{{ route('admin.services.delete', $service) }}">Delete<span class="visually-hidden"> {{ $service->title }}</span></a>
                                @endcan
                            </div></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>

            {{ $services->links('admin.partials.pagination') }}
        @endif
    </section>
@endsection
