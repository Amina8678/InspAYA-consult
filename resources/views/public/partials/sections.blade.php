{{--
    CMS page sections (contract §3 "CMS page"). Known types are rendered; any
    other type is skipped. Section headings are <h2> (the page has the <h1>).
    $skip: section types handled by the page itself (e.g. the home hero).
    Layouts follow the original design: a dark banner, the "Why choose us"
    image/text split, and centred intro text on alternating backgrounds.
--}}
@foreach ($sections as $section)
    @continue(in_array($section['type'], $skip ?? [], true))
    @php($data = $section['data'])

    @switch($section['type'])
        @case('hero')
            <section class="banner {{ ! empty($data['background_media']) ? 'hero--image' : '' }}">
                @if (! empty($data['background_media']))
                    <div class="hero__bg">@include('public.partials.image', ['image' => $data['background_media'], 'decorative' => true])</div>
                @endif
                <div class="container">
                    @if (! empty($data['heading']))
                        <h2>{{ $data['heading'] }}</h2>
                    @endif
                    @if (! empty($data['body']))
                        <p class="prose lead">{{ $data['body'] }}</p>
                    @endif
                    @include('public.partials.ctas', ['data' => $data, 'onDark' => true])
                </div>
            </section>
            @break

        @case('feature')
            {{-- Original "Why choose us?" split: image column + text. Without an
                 image the column shows the brand mark on a navy gradient. --}}
            <section class="feature">
                <div class="feature__media {{ ! empty($data['background_media']) ? 'feature__media--image' : '' }}">
                    @if (! empty($data['background_media']))
                        @include('public.partials.image', ['image' => $data['background_media']])
                    @else
                        @include('public.partials.icon', ['name' => 'mark', 'class' => 'feature__mark'])
                    @endif
                </div>
                <div class="feature__body">
                    <p class="eyebrow">Why choose us</p>
                    @if (! empty($data['heading']))
                        <h2>{{ $data['heading'] }}</h2>
                    @endif
                    @if (! empty($data['body']))
                        <p class="prose lead">{{ $data['body'] }}</p>
                    @endif
                    @include('public.partials.ctas', ['data' => $data])
                </div>
            </section>
            @break

        @case('intro')
            <section class="section">
                <div class="container text-block text-block--center">
                    @if (! empty($data['heading']))
                        <h2>{{ $data['heading'] }}</h2>
                    @endif
                    @if (! empty($data['body']))
                        <p class="prose lead">{{ $data['body'] }}</p>
                    @endif
                </div>
            </section>
            @break

        @case('text')
            <section class="section">
                <div class="container text-block">
                    @if (! empty($data['heading']))
                        <h2>{{ $data['heading'] }}</h2>
                    @endif
                    @if (! empty($data['body']))
                        <p class="prose">{{ $data['body'] }}</p>
                    @endif
                </div>
            </section>
            @break
    @endswitch
@endforeach
