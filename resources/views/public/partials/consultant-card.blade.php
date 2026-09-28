{{--
    Consultant card (contract §3). Optional keys: services (Consultants page),
    is_lead (service page). $headingTag keeps the outline correct; $full
    shows expertise, qualifications and links.
--}}
@php($full = $full ?? false)
<article class="card person">
    @if ($consultant['photo'])
        <div class="person__photo">
            @include('public.partials.image', ['image' => $consultant['photo']])
        </div>
    @endif
    @if (! empty($consultant['is_lead']))
        <p class="badge">Lead consultant</p>
    @endif
    <{{ $headingTag }}>{{ $consultant['name'] }}</{{ $headingTag }}>
    @if ($consultant['title'])
        <p class="meta">{{ $consultant['title'] }}</p>
    @endif
    @if ($consultant['bio'])
        <p class="prose">{{ $consultant['bio'] }}</p>
    @endif

    @if ($full)
        @if ($consultant['expertise'])
            <p><strong>Expertise:</strong> {{ implode(', ', $consultant['expertise']) }}</p>
        @endif
        @if ($consultant['qualifications'])
            <p><strong>Qualifications:</strong> {{ implode('; ', $consultant['qualifications']) }}</p>
        @endif
        @if (! empty($consultant['services']))
            <p><strong>Services:</strong></p>
            <ul>
                @foreach ($consultant['services'] as $service)
                    <li><a href="{{ $service['url'] }}">{{ $service['title'] }}</a></li>
                @endforeach
            </ul>
        @endif
        @if ($consultant['email'] || $consultant['links'])
            <ul>
                @if ($consultant['email'])
                    <li><a href="mailto:{{ $consultant['email'] }}">Email {{ $consultant['name'] }}</a></li>
                @endif
                @foreach ($consultant['links'] as $network => $url)
                    <li><a href="{{ $url }}" rel="noopener">{{ ucfirst($network) }}<span class="visually-hidden"> profile of {{ $consultant['name'] }}</span></a></li>
                @endforeach
            </ul>
        @endif
    @endif
</article>
