{{-- Services listing (FR-SVC-01). --}}
@extends('layouts.public')

@section('content')
    @include('public.partials.page-hero', [
        'heroTitle' => 'Services',
        'heroLead' => 'Multidisciplinary advisory services across our core operational areas.',
    ])

    <section class="section section--muted" aria-label="All services">
        <div class="container">
            @if ($services)
                <ul class="grid">
                    @foreach ($services as $service)
                        <li>@include('public.partials.service-card', ['service' => $service, 'headingTag' => 'h2'])</li>
                    @endforeach
                </ul>
            @else
                <p class="text-block text-block--center">Our services will be listed here soon.</p>
            @endif
        </div>
    </section>
@endsection
