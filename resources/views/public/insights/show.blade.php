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
        ['network' => 'linkedin', 'label' => 'LinkedIn', 'url' => 'https://www.linkedin.com/sharing/share-offsite/?url='.rawurlencode($shareUrl)],
        ['network' => 'x', 'label' => 'X', 'url' => 'https://twitter.com/intent/tweet?url='.rawurlencode($shareUrl).'&text='.rawurlencode($post['title'])],
        ['network' => 'facebook', 'label' => 'Facebook', 'url' => 'https://www.facebook.com/sharer/sharer.php?u='.rawurlencode($shareUrl)],
        ['network' => 'mail', 'label' => 'email', 'url' => 'mailto:?subject='.rawurlencode($post['title']).'&body='.rawurlencode($shareUrl)],
    ];
@endphp

@push('head')
    @if ($post['published_at'])
        <meta property="article:published_time" content="{{ $post['published_at'] }}">
    @endif
    <script type="application/ld+json">@json($articleSchema)</script>
@endpush

@section('content')
    <div class="page-hero">
        <div class="container">
            <nav class="breadcrumb" aria-label="Breadcrumb">
                <ol>
                    <li><a href="{{ route('home') }}">Home</a></li>
                    <li><a href="{{ route('insights.index') }}">Insights</a></li>
                    <li><a href="{{ $post['url'] }}" aria-current="page">{{ $post['title'] }}</a></li>
                </ol>
            </nav>
            @if ($post['category'])
                <p class="eyebrow">{{ $post['category']['name'] }}</p>
            @endif
            <h1>{{ $post['title'] }}</h1>
            <p class="meta">
                By {{ $post['author']['name'] }}
                @if ($post['published_at'])
                    <span aria-hidden="true">&middot;</span>
                    <time datetime="{{ $post['published_at'] }}">{{ $post['published_on'] }}</time>
                @endif
            </p>
        </div>
    </div>

    <div class="section">
        <article class="container article">
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
                    <li>
                        <ul class="social-icons">
                            @foreach ($shareLinks as $link)
                                <li>
                                    <a href="{{ $link['url'] }}" @if ($link['network'] !== 'mail') target="_blank" rel="noopener noreferrer" @endif>
                                        @include('public.partials.icon', ['name' => $link['network']])
                                        <span class="visually-hidden">Share on {{ $link['label'] }}@if ($link['network'] !== 'mail') (opens in a new tab)@endif</span>
                                    </a>
                                </li>
                            @endforeach
                        </ul>
                    </li>
                    <li>
                        {{-- Shown by site.js when the Clipboard API is available. --}}
                        <button type="button" class="button copy-link" data-copy-url="{{ $shareUrl }}"
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
                @include('public.partials.section-header', ['sectionEyebrow' => 'Insights', 'sectionHeading' => 'Related insights', 'sectionId' => 'related-heading'])
                <ul class="grid">
                    @foreach ($post['related'] as $related)
                        <li>@include('public.partials.post-card', ['post' => $related, 'headingTag' => 'h3'])</li>
                    @endforeach
                </ul>
            </div>
        </section>
    @endif
@endsection
