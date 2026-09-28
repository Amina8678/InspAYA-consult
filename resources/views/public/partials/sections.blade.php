{{--
    CMS page sections (contract §3 "CMS page"). Known types are rendered; any
    other type is skipped. Section headings are <h2> (the page has the <h1>).
    $skip: section types handled by the page itself (e.g. the home hero).
--}}
@foreach ($sections as $section)
    @continue(in_array($section['type'], $skip ?? [], true))
    @php($data = $section['data'])

    @switch($section['type'])
        @case('hero')
        @case('feature')
            <section class="section">
                <div class="container hero__inner {{ ! empty($data['background_media']) ? 'hero__inner--with-image' : '' }}">
                    <div>
                        @if (! empty($data['heading']))
                            <h2>{{ $data['heading'] }}</h2>
                        @endif
                        @if (! empty($data['body']))
                            <p class="prose lead">{{ $data['body'] }}</p>
                        @endif
                        @include('public.partials.ctas', ['data' => $data])
                    </div>
                    @if (! empty($data['background_media']))
                        <div class="hero__media">
                            @include('public.partials.image', ['image' => $data['background_media']])
                        </div>
                    @endif
                </div>
            </section>
            @break

        @case('intro')
        @case('text')
            <section class="section">
                <div class="container">
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
