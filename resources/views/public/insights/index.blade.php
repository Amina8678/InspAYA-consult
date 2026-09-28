{{-- Insights listing, 9 per page, newest first. --}}
@extends('layouts.public')

@section('content')
    <div class="container page-header">
        <h1>Insights</h1>
        <p class="lead">Perspectives from our consultants.</p>
    </div>

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
                <p>No articles have been published yet.</p>
            @endif
        </div>
    </section>
@endsection
