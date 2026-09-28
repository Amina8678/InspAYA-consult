{{-- Core values (FR-VAL-01/02), in display order. --}}
@extends('layouts.public')

@section('content')
    @include('public.partials.page-hero', [
        'heroTitle' => 'Core values',
        'heroLead' => 'The principles that guide every engagement.',
    ])

    <section class="section section--muted" aria-label="Our values">
        <div class="container">
            @if ($coreValues)
                <ul class="grid">
                    @foreach ($coreValues as $value)
                        <li>@include('public.partials.value-card', ['value' => $value, 'headingTag' => 'h2'])</li>
                    @endforeach
                </ul>
            @else
                <p class="text-block text-block--center">Our core values will be published here soon.</p>
            @endif
        </div>
    </section>
@endsection
