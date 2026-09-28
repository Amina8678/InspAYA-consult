{{--
    Site footer (FR-HOME-08). As a public.* view it receives $settings and
    $footer from the shared composer wherever it is included, including on
    error pages.
--}}
@php($siteName = data_get($settings, 'branding.site_name') ?: config('app.name'))
<footer class="site-footer">
    <div class="container">
        @if ($footer)
            <div class="site-footer__grid">
                <div>
                    @if ($footer['logo'])
                        @include('public.partials.image', ['image' => $footer['logo'], 'lazy' => true])
                    @endif
                    <h2>{{ $footer['site_name'] ?: $siteName }}</h2>
                    @php($contact = $footer['contact'])
                    @if ($contact['email'] || $contact['phone'] || $contact['address'])
                        <ul>
                            @if ($contact['email'])
                                <li><a href="mailto:{{ $contact['email'] }}">{{ $contact['email'] }}</a></li>
                            @endif
                            @if ($contact['phone'])
                                <li><a href="tel:{{ preg_replace('/[^0-9+]/', '', $contact['phone']) }}">{{ $contact['phone'] }}</a></li>
                            @endif
                            @if ($contact['address'])
                                <li class="prose">{{ $contact['address'] }}</li>
                            @endif
                        </ul>
                    @endif
                </div>

                @if ($footer['services'])
                    <div>
                        <h2>Services</h2>
                        <ul>
                            @foreach ($footer['services'] as $service)
                                <li><a href="{{ $service['url'] }}">{{ $service['label'] }}</a></li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                <div>
                    <h2>Explore</h2>
                    <ul>
                        <li><a href="{{ route('consultants.index') }}">Consultants</a></li>
                        <li><a href="{{ route('core-values.index') }}">Core values</a></li>
                        <li><a href="{{ route('insights.index') }}">Insights</a></li>
                        <li><a href="{{ route('contact') }}">Contact us</a></li>
                    </ul>
                </div>

                @if ($footer['social'])
                    <div>
                        <h2>Follow us</h2>
                        <ul>
                            @foreach ($footer['social'] as $link)
                                <li><a href="{{ $link['url'] }}" rel="noopener">{{ ucfirst($link['network']) }}</a></li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                @if ($footer['image'])
                    <div>
                        @include('public.partials.image', ['image' => $footer['image'], 'lazy' => true])
                    </div>
                @endif
            </div>
        @endif

        <div class="site-footer__bottom">
            <p>&copy; {{ $footer['year'] ?? now()->year }} {{ $siteName }}</p>
            @if ($footer && $footer['legal'])
                <ul>
                    @foreach ($footer['legal'] as $link)
                        <li><a href="{{ $link['url'] }}">{{ $link['label'] }}</a></li>
                    @endforeach
                </ul>
            @endif
        </div>
    </div>
</footer>
