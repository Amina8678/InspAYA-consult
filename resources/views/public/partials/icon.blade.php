{{--
    Inline SVG icons (replace the original Boxicons CDN). Decorative: always
    aria-hidden, so every use needs visible or visually-hidden text.
    $name: briefcase, check, menu, linkedin, x, facebook, instagram, mail,
    link (default), mark. $class (optional).
--}}
@php($class = $class ?? 'icon')
@switch($name)
    @case('briefcase')
        <svg class="{{ $class }}" viewBox="0 0 24 24" aria-hidden="true" focusable="false" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="7" width="18" height="13" rx="2"/><path d="M9 7V5a2 2 0 0 1 2-2h2a2 2 0 0 1 2 2v2M3 13h18"/></svg>
        @break
    @case('check')
        <svg class="{{ $class }}" viewBox="0 0 24 24" aria-hidden="true" focusable="false" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="m8 12.5 2.5 2.5L16 9.5"/></svg>
        @break
    @case('menu')
        <svg class="{{ $class }}" viewBox="0 0 24 24" aria-hidden="true" focusable="false" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M4 7h16M4 12h16M4 17h16"/></svg>
        @break
    @case('linkedin')
        <svg class="{{ $class }}" viewBox="0 0 24 24" aria-hidden="true" focusable="false" fill="currentColor"><path d="M4 9h3.2v11H4zM5.6 3.6a1.9 1.9 0 1 1 0 3.8 1.9 1.9 0 0 1 0-3.8zM9.3 9h3.1v1.5c.5-.9 1.7-1.8 3.4-1.8 3.3 0 4.2 2.1 4.2 5V20h-3.2v-5.4c0-1.4-.3-2.8-1.9-2.8-1.7 0-2.4 1.2-2.4 2.9V20H9.3z"/></svg>
        @break
    @case('x')
        <svg class="{{ $class }}" viewBox="0 0 24 24" aria-hidden="true" focusable="false" fill="currentColor"><path d="M4 3.5h4.6l4 5.6 4.8-5.6h2.3l-6 7 7 9.9h-4.6l-4.4-6.1-5.3 6.1H4.1l6.6-7.6zm3.4 1.6 9.6 13.7h1.3L8.7 5.1z"/></svg>
        @break
    @case('facebook')
        <svg class="{{ $class }}" viewBox="0 0 24 24" aria-hidden="true" focusable="false" fill="currentColor"><path d="M13.5 21v-7.5H16l.5-3h-3V8.6c0-.9.3-1.6 1.6-1.6h1.6V4.3c-.3 0-1.3-.1-2.4-.1-2.4 0-3.9 1.4-3.9 4v2.3H7.8v3h2.6V21z"/></svg>
        @break
    @case('instagram')
        <svg class="{{ $class }}" viewBox="0 0 24 24" aria-hidden="true" focusable="false" fill="none" stroke="currentColor" stroke-width="2"><rect x="3.5" y="3.5" width="17" height="17" rx="5"/><circle cx="12" cy="12" r="4"/><circle cx="17.3" cy="6.7" r="0.6" fill="currentColor"/></svg>
        @break
    @case('mail')
        <svg class="{{ $class }}" viewBox="0 0 24 24" aria-hidden="true" focusable="false" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="5" width="18" height="14" rx="2"/><path d="m3 7 9 6 9-6"/></svg>
        @break
    @case('mark')
        <svg class="{{ $class }}" viewBox="0 0 48 48" aria-hidden="true" focusable="false" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="24" cy="24" r="21"/><path d="M15 31V17m9 14V17l9 14V17"/></svg>
        @break
    @default
        <svg class="{{ $class }}" viewBox="0 0 24 24" aria-hidden="true" focusable="false" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 4h6v6M20 4 10 14M18 14v5a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1V7a1 1 0 0 1 1-1h5"/></svg>
@endswitch
