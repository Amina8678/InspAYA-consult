{{-- Insights listing, 9 per page, newest first. --}}
@extends('layouts.public')

@section('content')
    @include('public.partials.page-hero', [
        'heroTitle' => 'Insights',
        'heroLead' => 'Perspectives from our consultants.',
    ])

    <section class="section section--muted" aria-label="Articles">
        <div class="container">
            @if ($posts->isNotEmpty())
                <ul class="grid">
                    @foreach ($posts as $post)
                        <li>@include('public.partials.post-card', ['post' => $post, 'headingTag' => 'h2'])</li>
                    @endforeach
                </ul>
                {{ $posts->links('public.partials.pagination') }}
            @else
                <p class="text-block text-block--center">No articles have been published yet.</p>
            @endif
        </div>
    </section>
@endsection
