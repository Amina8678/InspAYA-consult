{{--
    Base layout for the public site. Receives $settings and $seo from the page
    view; error pages pass none, so both have fallbacks here. The header and
    footer partials get their own shared data (they are public.* views).
--}}
@php
    $settings = $settings ?? [];
    $siteName = data_get($settings, 'branding.site_name') ?: config('app.name');
    $seo = $seo ?? [
        'title' => $siteName,
        'description' => data_get($settings, 'seo.default_description'),
        'canonical_url' => url()->current(),
        'og_image' => null,
    ];
    $logo = data_get($settings, 'branding.logo');
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $seo['title'] }}</title>
    @if ($seo['description'])
        <meta name="description" content="{{ $seo['description'] }}">
    @endif
    @hasSection('robots')
        <meta name="robots" content="@yield('robots')">
    @else
        <link rel="canonical" href="{{ $seo['canonical_url'] }}">
    @endif

    <meta property="og:site_name" content="{{ $siteName }}">
    <meta property="og:type" content="@yield('og_type', 'website')">
    <meta property="og:title" content="{{ $seo['title'] }}">
    @if ($seo['description'])
        <meta property="og:description" content="{{ $seo['description'] }}">
    @endif
    <meta property="og:url" content="{{ $seo['canonical_url'] }}">
    @if ($seo['og_image'])
        <meta property="og:image" content="{{ $seo['og_image']['url'] }}">
        @if ($seo['og_image']['alt'] !== '')
            <meta property="og:image:alt" content="{{ $seo['og_image']['alt'] }}">
        @endif
        @if ($seo['og_image']['width'] && $seo['og_image']['height'])
            <meta property="og:image:width" content="{{ $seo['og_image']['width'] }}">
            <meta property="og:image:height" content="{{ $seo['og_image']['height'] }}">
        @endif
    @endif
    <meta name="twitter:card" content="{{ $seo['og_image'] ? 'summary_large_image' : 'summary' }}">

    <link rel="stylesheet" href="{{ asset('css/theme.css') }}">
    <link rel="stylesheet" href="{{ asset('css/site.css') }}">

    {{-- Structured data (NFR-SEO-04). @json escapes <, >, & and quotes. --}}
    @php
        $organizationSchema = array_filter([
            '@context' => 'https://schema.org',
            '@type' => 'Organization',
            'name' => $siteName,
            'url' => route('home'),
            'logo' => $logo['url'] ?? null,
            'email' => data_get($settings, 'contact.email'),
            'telephone' => data_get($settings, 'contact.phone'),
        ]);
    @endphp
    <script type="application/ld+json" nonce="{{ $cspNonce ?? '' }}">@json($organizationSchema)</script>
    @stack('head')
</head>
<body>
    <a class="skip-link" href="#main">Skip to main content</a>

    @include('public.partials.header')

    <main id="main" tabindex="-1">
        @yield('content')
    </main>

    @include('public.partials.footer')

    <script src="{{ asset('js/site.js') }}" defer></script>
</body>
</html>
