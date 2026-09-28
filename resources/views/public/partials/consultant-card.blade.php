{{--
    Consultant card (original team card: centred, round photo, circular social
    icons). Optional keys: services (Consultants page), is_lead (service page).
    $headingTag keeps the outline correct; $full adds expertise,
    qualifications and services.
--}}
@php
    $full = $full ?? false;
    $links = collect($consultant['links'])->map(fn ($url, $network) => ['network' => $network, 'url' => $url])->values()->all();
@endphp
<article class="card person">
    @if ($consultant['photo'])
        <div class="person__photo">
            @include('public.partials.image', ['image' => $consultant['photo']])
        </div>
    @else
        {{-- Initial: first letter or digit of the name (skips brackets and other symbols). --}}
        <span class="person__placeholder" aria-hidden="true">{{ mb_substr((string) preg_replace('/[^\p{L}\p{N}]+/u', '', $consultant['name']), 0, 1) }}</span>
    @endif
    @if (! empty($consultant['is_lead']))
        <p class="badge">Lead consultant</p>
    @endif
    <{{ $headingTag }} class="person__name">{{ $consultant['name'] }}</{{ $headingTag }}>
    @if ($consultant['title'])
        <p class="meta">{{ $consultant['title'] }}</p>
    @endif
    @if ($consultant['bio'])
        <p class="prose">{{ $consultant['bio'] }}</p>
    @endif

    @if ($full)
        <div class="person__details">
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
        </div>
    @endif

    @include('public.partials.social-links', ['links' => $links, 'owner' => $consultant['name'], 'email' => $consultant['email']])
</article>
