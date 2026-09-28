{{--
    Renders a contract image array: ['url', 'alt', 'width', 'height', 'mime_type'].
    $lazy (default true): images below the fold load lazily; pass false for the
    first visible image so it isn't delayed.
    $decorative (default false): background images with text over them get an
    empty alt so screen readers skip them.
--}}
@php($lazy = $lazy ?? true)
<img src="{{ $image['url'] }}"
     alt="{{ ($decorative ?? false) ? '' : $image['alt'] }}"
     @if ($image['width']) width="{{ $image['width'] }}" @endif
     @if ($image['height']) height="{{ $image['height'] }}" @endif
     @if ($lazy) loading="lazy" @else fetchpriority="high" @endif
     decoding="async">
