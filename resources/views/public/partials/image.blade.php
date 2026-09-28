{{--
    Renders a contract image array: ['url', 'alt', 'width', 'height', 'mime_type'].
    $lazy (default true): images below the fold load lazily; pass false for the
    first visible image so it isn't delayed.
--}}
@php($lazy = $lazy ?? true)
<img src="{{ $image['url'] }}"
     alt="{{ $image['alt'] }}"
     @if ($image['width']) width="{{ $image['width'] }}" @endif
     @if ($image['height']) height="{{ $image['height'] }}" @endif
     @if ($lazy) loading="lazy" @else fetchpriority="high" @endif
     decoding="async">
