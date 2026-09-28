{{--
    Service summary card (original: icon circle, fills with the brand colour on
    hover or keyboard focus). $headingTag keeps the outline correct per page.
--}}
<article class="card card--service">
    <span class="iconbox">@include('public.partials.icon', ['name' => 'briefcase'])</span>
    <{{ $headingTag }} class="card__title"><a href="{{ $service['url'] }}">{{ $service['title'] }}</a></{{ $headingTag }}>
    @if ($service['short_description'])
        <p>{{ $service['short_description'] }}</p>
    @endif
</article>
