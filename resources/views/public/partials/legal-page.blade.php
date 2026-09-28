{{-- Shared body for the Privacy Policy and Terms of Service pages (FR-LEGAL-01). --}}
<div class="container page-header">
    <h1>{{ $page['title'] }}</h1>
    @if ($page['published_at'])
        <p class="meta">Last updated <time datetime="{{ $page['published_at'] }}">{{ $page['published_on'] }}</time></p>
    @endif
</div>

@include('public.partials.sections', ['sections' => $page['sections']])
