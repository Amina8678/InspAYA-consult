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
                            @php($hasChildren = ! empty($item['children']))
                            <li @if ($hasChildren) class="has-children" @endif>
                                <a href="{{ $item['url'] }}"
                                   @if ($item['active']) aria-current="{{ $item['url'] === url()->current() ? 'page' : 'true' }}" @endif>{{ $item['label'] }}</a>
                                @if ($hasChildren)
                                    @php($submenuId = 'nav-submenu-'.str($item['label'])->slug())
                                    {{--
                                        Without JS the list below is always visible (a plain
                                        nested list, same "works without JS" rule as the rest of
                                        the header). With JS this button toggles it — same
                                        disclosure pattern as .nav-toggle above: a real <button>,
                                        Escape closes it and returns focus, nothing relies on
                                        hover (which mobile has none of), and hidden items are
                                        skipped by Tab automatically since they're display:none.
                                    --}}
                                    <button type="button" class="nav-submenu-toggle" aria-expanded="false" aria-controls="{{ $submenuId }}">
                                        <span class="visually-hidden">Show {{ $item['label'] }} submenu</span>
                                        @include('public.partials.icon', ['name' => 'chevron-down', 'class' => 'icon icon--small'])
                                    </button>
                                    <ul id="{{ $submenuId }}" class="nav-submenu">
                                        @foreach ($item['children'] as $child)
                                            <li>
                                                <a href="{{ $child['url'] }}" @if ($child['active']) aria-current="true" @endif>{{ $child['label'] }}</a>
                                            </li>
                                        @endforeach
                                    </ul>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                </nav>
            </div>
        @endif
    </div>
</header>
