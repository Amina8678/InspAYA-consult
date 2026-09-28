{{-- Service detail (FR-SVC-02, FR-TEAM-02). --}}
@extends('layouts.public')

@section('content')
    <div class="container">
        <nav class="breadcrumb" aria-label="Breadcrumb">
            <ol>
                <li><a href="{{ route('home') }}">Home</a></li>
                <li><a href="{{ route('services.index') }}">Services</a></li>
                <li><a href="{{ $service['url'] }}" aria-current="page">{{ $service['title'] }}</a></li>
            </ol>
        </nav>
    </div>

    <div class="container page-header">
        <h1>{{ $service['title'] }}</h1>
        @if ($service['short_description'])
            <p class="lead">{{ $service['short_description'] }}</p>
        @endif
    </div>

    <div class="container">
        @if ($service['description'])
            <section class="section" aria-labelledby="overview-heading">
                <h2 id="overview-heading">Overview</h2>
                <p class="prose">{{ $service['description'] }}</p>
            </section>
        @endif

        @if ($service['capabilities'])
            <section class="section" aria-labelledby="capabilities-heading">
                <h2 id="capabilities-heading">Capabilities</h2>
                <ul class="check-list">
                    @foreach ($service['capabilities'] as $capability)
                        <li>{{ $capability }}</li>
                    @endforeach
                </ul>
            </section>
        @endif

        @if ($service['outcomes'])
            <section class="section" aria-labelledby="outcomes-heading">
                <h2 id="outcomes-heading">Outcomes and deliverables</h2>
                <ul class="check-list">
                    @foreach ($service['outcomes'] as $outcome)
                        <li>{{ $outcome }}</li>
                    @endforeach
                </ul>
            </section>
        @endif
    </div>

    @if ($service['consultants'])
        <section class="section section--muted" aria-labelledby="team-heading">
            <div class="container">
                <h2 id="team-heading">Consultants for this service</h2>
                <ul class="grid">
                    @foreach ($service['consultants'] as $consultant)
                        <li>@include('public.partials.consultant-card', ['consultant' => $consultant, 'headingTag' => 'h3'])</li>
                    @endforeach
                </ul>
            </div>
        </section>
    @endif

    <section class="section" aria-labelledby="service-cta-heading">
        <div class="container">
            <div class="callout">
                <h2 id="service-cta-heading">Discuss {{ $service['title'] }} with us</h2>
                <p>Tell us about your organization and we will arrange a consultation.</p>
                <a class="button" href="{{ route('contact') }}">Contact us</a>
            </div>
        </div>
    </section>
@endsection
