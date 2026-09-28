{{--
    Admin shell: skip link, header with user menu, permission-filtered
    sidebar, main content with flash messages. The sidebar only hides links;
    the routes' permission:/can: middleware is the real enforcement.
--}}
@php
    $user = auth()->user();
    $navItems = array_values(array_filter([
        ['label' => 'Dashboard', 'route' => 'admin.dashboard', 'match' => 'admin.dashboard', 'show' => true],
        ['label' => 'Media library', 'route' => 'admin.media.index', 'match' => 'admin.media.*', 'show' => $user->can('media.view')],
        ['label' => 'Services', 'route' => 'admin.services.index', 'match' => 'admin.services.*', 'show' => $user->can('viewAny', App\Models\Service::class)],
        ['label' => 'Core values', 'route' => 'admin.core-values.index', 'match' => 'admin.core-values.*', 'show' => $user->can('viewAny', App\Models\CoreValue::class)],
        ['label' => 'Site settings', 'route' => 'admin.settings.edit', 'match' => 'admin.settings.*', 'show' => $user->can('settings.manage')],
    ], fn (array $item) => $item['show']));
@endphp
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
        <a class="admin-brand" href="{{ route('admin.dashboard') }}">{{ config('app.name') }} CMS</a>

        <div class="admin-header__end">
            <button type="button" class="button button--secondary nav-toggle" aria-controls="admin-nav" aria-expanded="false">Menu</button>

            <details class="user-menu">
                <summary>
                    <span class="visually-hidden">Account menu for </span>{{ $user->name }}
                    <span class="muted small">({{ $user->role?->name }})</span>
                </summary>
                <div class="user-menu__panel">
                    <ul>
                        <li><a href="{{ route('admin.account.password.edit') }}">Change password</a></li>
                        <li><a href="{{ route('home') }}">View website</a></li>
                        <li>
                            <form method="POST" action="{{ route('admin.logout') }}">
                                @csrf
                                <button type="submit" class="button button--link">Sign out</button>
                            </form>
                        </li>
                    </ul>
                </div>
            </details>
        </div>
    </header>

    <div class="admin-body">
        <nav id="admin-nav" class="admin-nav" aria-label="Admin">
            <ul>
                @foreach ($navItems as $item)
                    <li>
                        <a href="{{ route($item['route']) }}"
                           @if (request()->routeIs($item['match'])) aria-current="page" @endif>{{ $item['label'] }}</a>
                    </li>
                @endforeach
            </ul>
        </nav>

        <main id="main" class="admin-main" tabindex="-1">
            @include('admin.partials.flash')
            @yield('content')
        </main>
    </div>

    <script src="{{ asset('admin-assets/admin.js') }}" defer></script>
</body>
</html>
