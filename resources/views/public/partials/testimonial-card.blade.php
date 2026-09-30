{{--
    Testimonial card (FR-HOME-07 trust indicator): quote, then author. $headingTag
    keeps the outline correct on whichever page includes this.
--}}
<article class="card card--quote">
    <blockquote class="prose">
        <p>&ldquo;{{ $testimonial['quote'] }}&rdquo;</p>
    </blockquote>
    <footer>
        @if ($testimonial['photo'])
            <div class="person__photo person__photo--small">
                @include('public.partials.image', ['image' => $testimonial['photo']])
            </div>
        @endif
        <{{ $headingTag }} class="person__name">{{ $testimonial['author_name'] }}</{{ $headingTag }}>
        @if ($testimonial['author_title'] || $testimonial['organization'])
            <p class="meta">{{ trim(implode(', ', array_filter([$testimonial['author_title'], $testimonial['organization']]))) }}</p>
        @endif
    </footer>
</article>
