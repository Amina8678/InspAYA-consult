@extends('admin.layouts.app')

@section('title', 'FAQs')

@php
    $canReorder = auth()->user()->can('reorder', App\Models\Faq::class) && $search === '';
@endphp

@section('content')
    <div class="page-head">
        <h1>FAQs</h1>
        @can('create', App\Models\Faq::class)
            <a class="button" href="{{ route('admin.faqs.create') }}">Add an FAQ</a>
        @endcan
    </div>

    <section class="panel" aria-labelledby="list-heading">
        <h2 id="list-heading" class="visually-hidden">All FAQs</h2>

        @include('admin.partials.search', ['action' => route('admin.faqs.index'), 'value' => $search, 'label' => 'Search by question'])

        @if ($faqs->isEmpty())
            <p>{{ $search === '' ? 'No FAQs yet.' : 'No FAQs match your search.' }}</p>
        @else
            @if (! $canReorder && $search !== '')
                <p class="muted small">Clear the search to change the display order.</p>
            @endif
            <table class="table">
                <caption class="visually-hidden">FAQs in display order</caption>
                <thead>
                    <tr>
                        <th scope="col">Position</th>
                        <th scope="col">Question</th>
                        <th scope="col">Status</th>
                        <th scope="col"><span class="visually-hidden">Actions</span></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($faqs as $faq)
                        @php($position = $faqs->firstItem() + $loop->index)
                        <tr>
                            <td data-label="Position"><div>{{ $position }}</div></td>
                            <td data-label="Question"><div><strong>{{ $faq->question }}</strong></div></td>
                            <td data-label="Status"><div>{{ $faq->is_active ? 'Active' : 'Hidden' }}</div></td>
                            <td data-label="Actions"><div class="actions">
                                @can('update', $faq)
                                    <a href="{{ route('admin.faqs.edit', $faq) }}">Edit<span class="visually-hidden"> "{{ $faq->question }}"</span></a>
                                @endcan
                                @if ($canReorder)
                                    @include('admin.partials.move-buttons', [
                                        'action' => route('admin.faqs.move', $faq),
                                        'label' => '"'.$faq->question.'"',
                                        'first' => $position === 1,
                                        'last' => $position === $faqs->total(),
                                    ])
                                @endif
                                @can('delete', $faq)
                                    <a href="{{ route('admin.faqs.delete', $faq) }}">Delete<span class="visually-hidden"> "{{ $faq->question }}"</span></a>
                                @endcan
                            </div></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>

            {{ $faqs->links('admin.partials.pagination') }}
        @endif
    </section>
@endsection
