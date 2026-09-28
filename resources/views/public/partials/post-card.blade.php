{{-- Article summary card (contract §3). $headingTag keeps the outline correct. --}}
<article class="card">
    @if ($post['featured_image'])
        <div class="card__media">
            @include('public.partials.image', ['image' => $post['featured_image']])
        </div>
    @endif
    <{{ $headingTag }} class="card__title"><a href="{{ $post['url'] }}">{{ $post['title'] }}</a></{{ $headingTag }}>
    <p class="meta">
        @if ($post['published_at'])
            <time datetime="{{ $post['published_at'] }}">{{ $post['published_on'] }}</time>
        @endif
        @if ($post['category'])
            <span aria-hidden="true">&middot;</span> {{ $post['category']['name'] }}
        @endif
    </p>
    @if ($post['excerpt'])
        <p>{{ $post['excerpt'] }}</p>
    @endif
</article>
