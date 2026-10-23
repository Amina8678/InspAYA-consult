{{--
    Maintenance / unavailable. Deliberately standalone (no layout): it must
    render with the database down, so it reads no settings and runs no queries.
    Styles come from the site's own stylesheets (the CSP allows no inline CSS).
--}}
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>Back soon | {{ config('app.name') }}</title>
    <link rel="icon" href="{{ \App\Support\Assets::url('favicon.svg') }}" type="image/svg+xml">
    <link rel="icon" href="{{ \App\Support\Assets::url('favicon.ico') }}" sizes="32x32">
    <link rel="stylesheet" href="{{ \App\Support\Assets::url('css/theme.css') }}">
    <link rel="stylesheet" href="{{ \App\Support\Assets::url('css/site.css') }}">
</head>
<body>
    <main id="main" tabindex="-1">
        <div class="container section">
            <p class="eyebrow">{{ config('app.name') }}</p>
            <h1>We'll be back shortly</h1>
            <p class="lead">The site is briefly unavailable, either for maintenance or because of a temporary problem. Please try again in a few minutes.</p>
        </div>
    </main>
</body>
</html>
