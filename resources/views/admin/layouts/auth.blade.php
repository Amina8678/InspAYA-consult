{{-- Signed-out admin screens (sign in, forgot/reset password, 429). --}}
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>@yield('title') | {{ config('app.name') }} CMS</title>
    <link rel="stylesheet" href="{{ asset('admin-assets/admin.css') }}">
</head>
<body>
    <a class="skip-link" href="#main">Skip to main content</a>

    <header class="admin-header">
        <a class="admin-brand" href="{{ route('home') }}">{{ config('app.name') }}</a>
    </header>

    <main id="main" class="auth" tabindex="-1">
        <div class="auth__card">
            @yield('content')
        </div>
    </main>

    <script src="{{ asset('admin-assets/admin.js') }}" defer></script>
</body>
</html>
