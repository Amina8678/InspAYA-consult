{{--
    Site header and main navigation (original navy bar). As a public.* view it
    receives $settings and $navigation from the shared composer wherever it is
    included, so error pages get the full navigation too. On small screens,
    site.js collapses the menu behind the Menu button; without JS the links
    simply wrap.
--}}
@php
    $siteName = data_get($settings, 'branding.site_name') ?: config('app.name');
    $logo = data_get($settings, 'branding.logo');
@endphp
<header class="site-header">
    <div class="container site-header__inner">
        <a class="brand" href="{{ route('home') }}">
            @if ($logo)
                <img src="{{ $logo['url'] }}" alt="{{ $siteName }} home"
                     @if ($logo['width']) width="{{ $logo['width'] }}" @endif
                     @if ($logo['height']) height="{{ $logo['height'] }}" @endif>
            @else
                {{ $siteName }}
            @endif
        </a>

        @if (! empty($navigation))
            <button type="button" class="nav-toggle" aria-controls="main-nav-panel" aria-expanded="false">
                @include('public.partials.icon', ['name' => 'menu'])
                Menu
            </button>

            <div id="main-nav-panel" class="main-nav-panel">
                <nav class="main-nav" aria-label="Main">
                    <ul>
                        @foreach ($navigation as $item)
                            <li>
                                <a href="{{ $item['url'] }}"
                                   @if ($item['active']) aria-current="{{ $item['url'] === url()->current() ? 'page' : 'true' }}" @endif>{{ $item['label'] }}</a>
                            </li>
                        @endforeach
                    </ul>
                </nav>
            </div>
        @endif
    </div>
</header>
