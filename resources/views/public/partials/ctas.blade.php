{{--
    Primary/secondary call-to-action links from a section's data (FR-HOME-01).
    $onDark: the secondary button uses the original light outline style.
--}}
@php($primary = $data['primary_cta'] ?? null)
@php($secondary = $data['secondary_cta'] ?? null)
@if (! empty($primary['url']) || ! empty($secondary['url']))
    <div class="button-row">
        @if (! empty($primary['url']))
            <a class="button" href="{{ $primary['url'] }}">{{ $primary['label'] ?? 'Learn more' }}</a>
        @endif
        @if (! empty($secondary['url']))
            <a class="button {{ ($onDark ?? false) ? 'button--light' : '' }}" href="{{ $secondary['url'] }}">{{ $secondary['label'] ?? 'Learn more' }}</a>
        @endif
    </div>
@endif
