{{-- Services listing (FR-SVC-01). --}}
@extends('layouts.public')

@section('content')
    <div class="container page-header">
        <h1>Services</h1>
        <p class="lead">Multidisciplinary advisory services across our core operational areas.</p>
    </div>

    <section class="section section--muted" aria-label="All services">
        <div class="container">
            @if ($services)
                <ul class="grid">
                    @foreach ($services as $service)
                        <li>@include('public.partials.service-card', ['service' => $service, 'headingTag' => 'h2'])</li>
                    @endforeach
                </ul>
            @else
                <p>Our services will be listed here soon.</p>
            @endif
        </div>
    </section>
@endsection
