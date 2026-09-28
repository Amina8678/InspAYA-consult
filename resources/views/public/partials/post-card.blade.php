{{-- Article summary card (original blog card: image on top, title, excerpt). --}}
<article class="card">
    @if ($post['featured_image'])
        <div class="card__media">
            @include('public.partials.image', ['image' => $post['featured_image']])
        </div>
    @endif
    @if ($post['category'])
        <p class="eyebrow">{{ $post['category']['name'] }}</p>
    @endif
    <{{ $headingTag }} class="card__title"><a href="{{ $post['url'] }}">{{ $post['title'] }}</a></{{ $headingTag }}>
    @if ($post['published_at'])
        <p class="meta"><time datetime="{{ $post['published_at'] }}">{{ $post['published_on'] }}</time></p>
    @endif
    @if ($post['excerpt'])
        <p>{{ $post['excerpt'] }}</p>
    @endif
</article>
