{{--
    Home (FR-HOME-01 to 08), in the original single-page style: full-height hero
    with image and overlay, then centred sections on alternating backgrounds.
    $page is null while the CMS Home page is unpublished.
--}}
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
    <section class="hero {{ $heroImage ? 'hero--image' : '' }}" aria-labelledby="hero-heading">
        @if ($heroImage)
            {{-- Background photo under the overlay: decorative, the text carries the meaning. --}}
            <div class="hero__bg">@include('public.partials.image', ['image' => $heroImage, 'lazy' => false, 'decorative' => true])</div>
        @endif
        <div class="container hero__content">
            <h1 id="hero-heading" class="hero__title">{{ $hero['heading'] ?? $siteName }}</h1>
            @if (! empty($hero['body']))
                <p class="hero__text prose">{{ $hero['body'] }}</p>
            @elseif (! $page && data_get($settings, 'seo.default_description'))
                <p class="hero__text">{{ data_get($settings, 'seo.default_description') }}</p>
            @endif
            @include('public.partials.ctas', ['onDark' => true, 'data' => array_filter([
                'primary_cta' => $hero['primary_cta'] ?? null,
                'secondary_cta' => $hero['secondary_cta'] ?? null,
            ]) ?: $defaultCtas])
        </div>
    </section>

    @include('public.partials.sections', ['sections' => $sections, 'skip' => ['hero']])

    @if ($services)
        <section class="section" aria-labelledby="services-heading">
            <div class="container">
                @include('public.partials.section-header', ['sectionEyebrow' => 'Services', 'sectionHeading' => 'What we do', 'sectionId' => 'services-heading'])
                <ul class="grid">
                    @foreach ($services as $service)
                        <li>@include('public.partials.service-card', ['service' => $service, 'headingTag' => 'h3'])</li>
                    @endforeach
                </ul>
                <div class="button-row button-row--center">
                    <a class="button" href="{{ route('services.index') }}">All services</a>
                </div>
            </div>
        </section>
    @endif

    @if ($coreValues)
        <section class="section section--muted" aria-labelledby="values-heading">
            <div class="container">
                @include('public.partials.section-header', ['sectionEyebrow' => 'Core values', 'sectionHeading' => 'Our core values', 'sectionId' => 'values-heading'])
                <ul class="grid">
                    @foreach ($coreValues as $value)
                        <li>@include('public.partials.value-card', ['value' => $value, 'headingTag' => 'h3'])</li>
                    @endforeach
                </ul>
            </div>
        </section>
    @endif

    @if ($consultants)
        <section class="section" aria-labelledby="consultants-heading">
            <div class="container">
                @include('public.partials.section-header', ['sectionEyebrow' => 'Our team', 'sectionHeading' => 'Our consultants', 'sectionId' => 'consultants-heading'])
                <ul class="grid">
                    @foreach ($consultants as $consultant)
                        <li>@include('public.partials.consultant-card', ['consultant' => $consultant, 'headingTag' => 'h3'])</li>
                    @endforeach
                </ul>
                <div class="button-row button-row--center">
                    <a class="button" href="{{ route('consultants.index') }}">Meet all our consultants</a>
                </div>
            </div>
        </section>
    @endif

    @if ($insights)
        <section class="section section--muted" aria-labelledby="insights-heading">
            <div class="container">
                @include('public.partials.section-header', ['sectionEyebrow' => 'Insights', 'sectionHeading' => 'Latest insights', 'sectionId' => 'insights-heading'])
                <ul class="grid">
                    @foreach ($insights as $post)
                        <li>@include('public.partials.post-card', ['post' => $post, 'headingTag' => 'h3'])</li>
                    @endforeach
                </ul>
                <div class="button-row button-row--center">
                    <a class="button" href="{{ route('insights.index') }}">All insights</a>
                </div>
            </div>
        </section>
    @endif

    <section class="section" aria-labelledby="contact-cta-heading">
        <div class="container">
            <div class="callout">
                <p class="eyebrow">Contact</p>
                <h2 id="contact-cta-heading">Talk to us about your organization</h2>
                <p>Tell us what you need and the right consultant will get back to you.</p>
                <div class="button-row button-row--center">
                    <a class="button" href="{{ route('contact') }}">Contact us</a>
                </div>
            </div>
        </div>
    </section>
@endsection
