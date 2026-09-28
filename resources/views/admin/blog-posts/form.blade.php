@extends('admin.layouts.app')

@php
    $editing = $post->exists;
    $isPublished = $editing && $post->status === App\Enums\PostStatus::Published;
    $statusLabel = fn ($status) => match ($status->value) {
        'draft' => 'Draft (not on the website)',
        'review' => 'In review',
        'published' => 'Published',
    };
    $canChangeStatus = count($statusOptions) > 1;
    $publicUrl = $isPublished ? route('insights.show', $post->slug) : null;
@endphp

@section('title', $editing ? 'Edit '.$post->title : 'Write a post')

@section('content')
    <div class="page-head">
        <h1>{{ $editing ? 'Edit post' : 'Write a post' }}</h1>
        <a href="{{ route('admin.posts.index') }}">Back to posts</a>
    </div>

    @include('admin.partials.errors', ['fields' => [
        'title' => 'field-title', 'slug' => 'field-slug', 'confirm_slug_change' => 'field-confirm_slug_change',
        'excerpt' => 'field-excerpt', 'content' => 'field-content', 'category_id' => 'field-category_id',
        'tags' => 'tags-heading', 'featured_image_id' => 'field-featured_image_id',
        'meta_title' => 'field-meta_title', 'meta_description' => 'field-meta_description', 'canonical_url' => 'field-canonical_url',
        'status' => 'field-status', 'published_at' => 'field-published_at',
    ]])

    <form method="POST" action="{{ $editing ? route('admin.posts.update', $post) : route('admin.posts.store') }}">
        @csrf
        @if ($editing)
            @method('PUT')
        @endif

        <div class="actions panel">
            <button type="submit" class="button">{{ $editing ? 'Save changes' : 'Create post' }}</button>
            @if ($publicUrl)
                <a href="{{ $publicUrl }}" target="_blank" rel="noopener">View on the website<span class="visually-hidden"> (opens in a new tab)</span></a>
            @endif
        </div>

        <fieldset class="panel form">
            <legend><h2>Post</h2></legend>
            @if ($editing)
                <p class="muted small">Written by {{ $post->author->name }}.</p>
            @endif
            @include('admin.partials.field', ['name' => 'title', 'label' => 'Title', 'type' => 'text', 'required' => true,
                'value' => old('title', $post->title), 'maxlength' => 255])
            @include('admin.partials.field', ['name' => 'slug', 'label' => 'Slug', 'type' => 'text',
                'value' => old('slug', $post->slug), 'maxlength' => 191,
                'hint' => $editing ? 'Lowercase words joined by hyphens. Leave empty to keep the current slug.'
                                   : 'Lowercase words joined by hyphens. Leave empty to create it from the title.'])
            @if ($isPublished)
                <div class="alert alert--warning">
                    <p><strong>This post is published.</strong> Changing its slug changes links to it.</p>
                    <div class="field checkbox">
                        <input type="hidden" name="confirm_slug_change" value="0">
                        <input id="field-confirm_slug_change" type="checkbox" name="confirm_slug_change" value="1" @checked(old('confirm_slug_change'))>
                        <label for="field-confirm_slug_change">I understand, change the slug if I edited it</label>
                    </div>
                </div>
            @endif
            @include('admin.partials.field', ['name' => 'excerpt', 'label' => 'Excerpt', 'type' => 'textarea',
                'value' => old('excerpt', $post->excerpt), 'maxlength' => 1000,
                'hint' => 'A short summary shown in listings. Leave empty to let the website show the start of the post.'])
            @include('admin.partials.field', ['name' => 'content', 'label' => 'Content', 'type' => 'textarea', 'required' => true,
                'value' => old('content', $post->content), 'maxlength' => 50000,
                'hint' => 'Plain text; line breaks are kept. HTML isn\'t allowed.'])
            @include('admin.partials.media-picker', ['name' => 'featured_image_id', 'label' => 'Featured image', 'selected' => $post->featured_image_id,
                'options' => $mediaOptions])
        </fieldset>

        <fieldset class="panel form">
            <legend><h2>Category and tags</h2></legend>
            <div class="field">
                <label for="field-category_id">Category <span class="field__optional">(optional)</span></label>
                <select id="field-category_id" name="category_id">
                    <option value="">None</option>
                    @foreach ($categories as $category)
                        <option value="{{ $category->id }}" @selected((string) old('category_id', $post->category_id) === (string) $category->id)>{{ $category->name }}</option>
                    @endforeach
                </select>
            </div>

            <div class="field" role="group" aria-labelledby="tags-heading">
                <span id="tags-heading">Tags <span class="field__optional">(optional)</span></span>
                @if ($tags->isEmpty())
                    <p class="muted small">No tags yet.</p>
                @else
                    @foreach ($tags as $tag)
                        <div class="checkbox">
                            <input id="tag-{{ $tag->id }}" type="checkbox" name="tags[]" value="{{ $tag->id }}"
                                   @checked(in_array($tag->id, $selectedTags))>
                            <label for="tag-{{ $tag->id }}">{{ $tag->name }}</label>
                        </div>
                    @endforeach
                @endif
            </div>
        </fieldset>

        <fieldset class="panel form">
            <legend><h2>Search engines and sharing</h2></legend>
            @include('admin.partials.field', ['name' => 'meta_title', 'label' => 'Page title for search results', 'type' => 'text',
                'value' => old('meta_title', $post->meta_title), 'maxlength' => 255,
                'hint' => 'Leave empty to use "'.($post->title ?: 'Title').' | site name". Around 60 characters works best.'])
            @include('admin.partials.field', ['name' => 'meta_description', 'label' => 'Description for search results', 'type' => 'textarea',
                'value' => old('meta_description', $post->meta_description), 'maxlength' => 500,
                'hint' => 'Around 155 characters works best.'])
            @include('admin.partials.field', ['name' => 'canonical_url', 'label' => 'Canonical URL', 'type' => 'url',
                'value' => old('canonical_url', $post->canonical_url), 'maxlength' => 500,
                'hint' => 'Only if the main copy of this article lives at another address. Leave empty to use this post\'s own address.'])
        </fieldset>

        <fieldset class="panel form">
            <legend><h2>Publishing</h2></legend>
            @if ($canChangeStatus)
                <div class="field">
                    <label for="field-status">Status</label>
                    <select id="field-status" name="status">
                        @foreach ($statusOptions as $option)
                            <option value="{{ $option->value }}" @selected(old('status', $post->status?->value) === $option->value)>{{ $statusLabel($option) }}</option>
                        @endforeach
                    </select>
                </div>
            @else
                <p>Status: <strong>{{ $statusLabel($post->status ?? App\Enums\PostStatus::Draft) }}</strong>.
                    @if ($editing && $post->status === App\Enums\PostStatus::Draft)
                        Save to keep it as a draft.
                    @elseif ($editing && $post->status === App\Enums\PostStatus::Review)
                        It is waiting for an editor or administrator.
                    @else
                        Publishing and unpublishing need an administrator.
                    @endif
                </p>
            @endif
            @if ($canPublish)
                @include('admin.partials.field', ['name' => 'published_at', 'label' => 'Publish date', 'type' => 'datetime-local',
                    'value' => old('published_at', $post->published_at?->format('Y-m-d\TH:i')),
                    'hint' => 'A future date keeps the post off the website until then. Leave empty to publish immediately.'])
            @elseif ($editing && $post->published_at)
                <p class="muted small">Publish date: {{ $post->published_at->format('j M Y, H:i') }}.</p>
            @endif
        </fieldset>

        <div class="actions">
            <button type="submit" class="button">{{ $editing ? 'Save changes' : 'Create post' }}</button>
            @if ($editing)
                @can('delete', $post)
                    <a href="{{ route('admin.posts.delete', $post) }}">Delete this post</a>
                @endcan
            @endif
        </div>
    </form>
@endsection
