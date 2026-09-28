{{--
    Circular social icons (original style). $links: list of ['network', 'url'].
    Optional: $owner (added to each accessible name, e.g. "LinkedIn profile of
    Jane Doe") and $email (adds an email icon first). Icons are decorative;
    the text is visually hidden.
--}}
@php
    $known = ['linkedin' => 'LinkedIn', 'x' => 'X', 'twitter' => 'X', 'facebook' => 'Facebook', 'instagram' => 'Instagram'];
    $owner = $owner ?? null;
    $email = $email ?? null;
@endphp
@if ($links || $email)
    <ul class="social-icons">
        @if ($email)
            <li>
                <a href="mailto:{{ $email }}">
                    @include('public.partials.icon', ['name' => 'mail'])
                    <span class="visually-hidden">Email {{ $owner ?? $email }}</span>
                </a>
            </li>
        @endif
        @foreach ($links as $link)
            @php($network = strtolower($link['network']))
            <li>
                <a href="{{ $link['url'] }}" rel="noopener">
                    @include('public.partials.icon', ['name' => $network === 'twitter' ? 'x' : $network])
                    <span class="visually-hidden">{{ $known[$network] ?? ucfirst($network) }}{{ $owner ? ' profile of '.$owner : '' }}</span>
                </a>
            </li>
        @endforeach
    </ul>
@endif
