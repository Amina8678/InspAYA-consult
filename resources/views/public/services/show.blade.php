{{-- Service detail (FR-SVC-02, FR-TEAM-02). --}}
@extends('layouts.public')

@php
    $serviceSchema = array_filter([
        '@context' => 'https://schema.org',
        '@type' => 'Service',
        'name' => $service['title'],
        'description' => $service['description'] ?: $service['short_description'],
        'url' => $service['seo']['canonical_url'],
        'provider' => array_filter([
            '@type' => 'Organization',
            'name' => data_get($settings, 'branding.site_name') ?: config('app.name'),
            'url' => route('home'),
        ]),
    ]);
@endphp

@push('head')
    <script type="application/ld+json">@json($serviceSchema)</script>
@endpush

@section('content')
    @include('public.partials.page-hero', [
        'heroTitle' => $service['title'],
        'heroLead' => $service['short_description'],
        'heroBreadcrumbs' => [
            ['label' => 'Home', 'url' => route('home')],
            ['label' => 'Services', 'url' => route('services.index')],
            ['label' => $service['title'], 'url' => $service['url']],
        ],
    ])

    <div class="section">
        <div class="container text-block">
            @if ($service['description'])
                <section aria-labelledby="overview-heading">
                    <p class="eyebrow">Services</p>
                    <h2 id="overview-heading">Overview</h2>
                    <p class="prose">{{ $service['description'] }}</p>
                </section>
            @endif

            @if ($service['capabilities'])
                <section aria-labelledby="capabilities-heading">
                    <h2 id="capabilities-heading">Capabilities</h2>
                    <ul class="check-list">
                        @foreach ($service['capabilities'] as $capability)
                            <li>@include('public.partials.icon', ['name' => 'check'])<span>{{ $capability }}</span></li>
                        @endforeach
                    </ul>
                </section>
            @endif

            @if ($service['outcomes'])
                <section aria-labelledby="outcomes-heading">
                    <h2 id="outcomes-heading">Outcomes and deliverables</h2>
                    <ul class="check-list">
                        @foreach ($service['outcomes'] as $outcome)
                            <li>@include('public.partials.icon', ['name' => 'check'])<span>{{ $outcome }}</span></li>
                        @endforeach
                    </ul>
                </section>
            @endif
        </div>
    </div>

    @if ($service['consultants'])
        <section class="section section--muted" aria-labelledby="team-heading">
            <div class="container">
                @include('public.partials.section-header', ['sectionEyebrow' => 'Our team', 'sectionHeading' => 'Consultants for this service', 'sectionId' => 'team-heading'])
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
                <p class="eyebrow">Contact</p>
                <h2 id="service-cta-heading">Discuss {{ $service['title'] }} with us</h2>
                <p>Tell us about your organization and we will arrange a consultation.</p>
                <div class="button-row button-row--center">
                    <a class="button" href="{{ route('contact') }}">Contact us</a>
                </div>
            </div>
        </div>
    </section>
@endsection
