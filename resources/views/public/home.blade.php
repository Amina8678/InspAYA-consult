{{-- Home (FR-HOME-01 to 08). $page is null while the CMS Home page is unpublished. --}}
@extends('layouts.public')

@php
    $sections = $page['sections'] ?? [];
    $hero = collect($sections)->firstWhere('type', 'hero')['data'] ?? [];
    $siteName = data_get($settings, 'branding.site_name') ?: config('app.name');
    $heroImage = $hero['background_media'] ?? null;
    $defaultCtas = [
        'primary_cta' => ['label' => 'Contact us', 'url' => route('contact')],
        'secondary_cta' => ['label' => 'Our services', 'url' => route('services.index')],
    ];
@endphp

@section('content')
    <section class="hero" aria-labelledby="hero-heading">
        <div class="container hero__inner {{ $heroImage ? 'hero__inner--with-image' : '' }}">
            <div>
                <h1 id="hero-heading">{{ $hero['heading'] ?? $siteName }}</h1>
                @if (! empty($hero['body']))
                    <p class="lead prose">{{ $hero['body'] }}</p>
                @elseif (! $page && data_get($settings, 'seo.default_description'))
                    <p class="lead">{{ data_get($settings, 'seo.default_description') }}</p>
                @endif
                @include('public.partials.ctas', ['data' => array_filter([
                    'primary_cta' => $hero['primary_cta'] ?? null,
                    'secondary_cta' => $hero['secondary_cta'] ?? null,
                ]) ?: $defaultCtas])
            </div>
            @if ($heroImage)
                <div class="hero__media">
                    @include('public.partials.image', ['image' => $heroImage, 'lazy' => false])
                </div>
            @endif
        </div>
    </section>

    @include('public.partials.sections', ['sections' => $sections, 'skip' => ['hero']])

    @if ($services)
        <section class="section section--muted" aria-labelledby="services-heading">
            <div class="container">
                <div class="section__header">
                    <h2 id="services-heading">What we do</h2>
                </div>
                <ul class="grid">
                    @foreach ($services as $service)
                        <li>@include('public.partials.service-card', ['service' => $service, 'headingTag' => 'h3'])</li>
                    @endforeach
                </ul>
                <p><a href="{{ route('services.index') }}">All services</a></p>
            </div>
        </section>
    @endif

    @if ($coreValues)
        <section class="section" aria-labelledby="values-heading">
            <div class="container">
                <div class="section__header">
                    <h2 id="values-heading">Our core values</h2>
                </div>
                <ul class="grid">
                    @foreach ($coreValues as $value)
                        <li>
                            <article class="card">
                                @if ($value['icon'])
                                    <div class="icon">@include('public.partials.image', ['image' => $value['icon']])</div>
                                @endif
                                <h3>{{ $value['title'] }}</h3>
                                @if ($value['description'])
                                    <p class="prose">{{ $value['description'] }}</p>
                                @endif
                            </article>
                        </li>
                    @endforeach
                </ul>
            </div>
        </section>
    @endif

    @if ($consultants)
        <section class="section section--muted" aria-labelledby="consultants-heading">
            <div class="container">
                <div class="section__header">
                    <h2 id="consultants-heading">Our consultants</h2>
                </div>
                <ul class="grid">
                    @foreach ($consultants as $consultant)
                        <li>@include('public.partials.consultant-card', ['consultant' => $consultant, 'headingTag' => 'h3'])</li>
                    @endforeach
                </ul>
                <p><a href="{{ route('consultants.index') }}">Meet all our consultants</a></p>
            </div>
        </section>
    @endif

    @if ($insights)
        <section class="section" aria-labelledby="insights-heading">
            <div class="container">
                <div class="section__header">
                    <h2 id="insights-heading">Latest insights</h2>
                </div>
                <ul class="grid">
                    @foreach ($insights as $post)
                        <li>@include('public.partials.post-card', ['post' => $post, 'headingTag' => 'h3'])</li>
                    @endforeach
                </ul>
                <p><a href="{{ route('insights.index') }}">All insights</a></p>
            </div>
        </section>
    @endif

    <section class="section" aria-labelledby="contact-cta-heading">
        <div class="container">
            <div class="callout">
                <h2 id="contact-cta-heading">Talk to us about your organization</h2>
                <p>Tell us what you need and the right consultant will get back to you.</p>
                <a class="button" href="{{ route('contact') }}">Contact us</a>
            </div>
        </div>
    </section>
@endsection
