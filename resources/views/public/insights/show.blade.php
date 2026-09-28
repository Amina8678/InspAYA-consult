{{-- Article (FR-BLOG-03 to 05). Content is plain text; newlines kept via .prose. --}}
@extends('layouts.public')

@section('og_type', 'article')

@php
    $articleSchema = array_filter([
        '@context' => 'https://schema.org',
        '@type' => 'Article',
        'headline' => $post['title'],
        'datePublished' => $post['published_at'],
        'author' => ['@type' => 'Person', 'name' => $post['author']['name']],
        'image' => $post['featured_image']['url'] ?? null,
        'mainEntityOfPage' => $post['seo']['canonical_url'],
    ]);
    $shareUrl = $post['seo']['canonical_url'];
    $shareLinks = [
        'LinkedIn' => 'https://www.linkedin.com/sharing/share-offsite/?url='.rawurlencode($shareUrl),
        'X' => 'https://twitter.com/intent/tweet?url='.rawurlencode($shareUrl).'&text='.rawurlencode($post['title']),
        'Facebook' => 'https://www.facebook.com/sharer/sharer.php?u='.rawurlencode($shareUrl),
        'Email' => 'mailto:?subject='.rawurlencode($post['title']).'&body='.rawurlencode($shareUrl),
    ];
@endphp

@push('head')
    @if ($post['published_at'])
        <meta property="article:published_time" content="{{ $post['published_at'] }}">
    @endif
    <script type="application/ld+json">@json($articleSchema)</script>
@endpush

@section('content')
    <div class="container">
        <nav class="breadcrumb" aria-label="Breadcrumb">
            <ol>
                <li><a href="{{ route('home') }}">Home</a></li>
                <li><a href="{{ route('insights.index') }}">Insights</a></li>
                <li><a href="{{ $post['url'] }}" aria-current="page">{{ $post['title'] }}</a></li>
            </ol>
        </nav>

        <article class="article section">
            <header>
                <h1>{{ $post['title'] }}</h1>
                <p class="meta">
                    By {{ $post['author']['name'] }}
                    @if ($post['published_at'])
                        <span aria-hidden="true">&middot;</span>
                        <time datetime="{{ $post['published_at'] }}">{{ $post['published_on'] }}</time>
                    @endif
                    @if ($post['category'])
                        <span aria-hidden="true">&middot;</span> {{ $post['category']['name'] }}
                    @endif
                </p>
            </header>

            @if ($post['featured_image'])
                <div class="article__image">
                    @include('public.partials.image', ['image' => $post['featured_image'], 'lazy' => false])
                </div>
            @endif

            <div class="article__body prose">{{ $post['content'] }}</div>

            @if ($post['tags'])
                <h2 class="visually-hidden">Tags</h2>
                <ul class="tag-list">
                    @foreach ($post['tags'] as $tag)
                        <li>{{ $tag['name'] }}</li>
                    @endforeach
                </ul>
            @endif

            <section class="share" aria-labelledby="share-heading">
                <h2 id="share-heading">Share this article</h2>
                <ul>
                    @foreach ($shareLinks as $network => $href)
                        <li>
                            <a href="{{ $href }}" @if ($network !== 'Email') target="_blank" rel="noopener noreferrer" @endif>
                                {{ $network }}@if ($network !== 'Email')<span class="visually-hidden"> (opens in a new tab)</span>@endif
                            </a>
                        </li>
                    @endforeach
                    <li>
                        {{-- Shown by site.js when the Clipboard API is available. --}}
                        <button type="button" class="button button--secondary" data-copy-url="{{ $shareUrl }}"
                                aria-describedby="copy-status" hidden>Copy link</button>
                    </li>
                </ul>
                <p id="copy-status" class="meta" role="status"></p>
            </section>
        </article>
    </div>

    @if ($post['related'])
        <section class="section section--muted" aria-labelledby="related-heading">
            <div class="container">
                <h2 id="related-heading">Related insights</h2>
                <ul class="grid">
                    @foreach ($post['related'] as $related)
                        <li>@include('public.partials.post-card', ['post' => $related, 'headingTag' => 'h3'])</li>
                    @endforeach
                </ul>
            </div>
        </section>
    @endif
@endsection
