{{-- Service summary card. $headingTag keeps the outline correct per page. --}}
<article class="card">
    <{{ $headingTag }} class="card__title"><a href="{{ $service['url'] }}">{{ $service['title'] }}</a></{{ $headingTag }}>
    @if ($service['short_description'])
        <p>{{ $service['short_description'] }}</p>
    @endif
</article>
