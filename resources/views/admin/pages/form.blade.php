@extends('admin.layouts.app')

@php
    $editing = $page->exists;
    $canPublish = auth()->user()->can('publish', $page);
    $status = old('status', $page->status?->value ?? 'draft');
    $isPublished = $editing && $page->status?->value === 'published';
    $slugLocked = $editing && App\Support\CorePages::slugLocked($page->slug);
    $publicUrl = $editing ? App\Support\CorePages::url($page->slug) : null;

    // Error summary: page fields, then every section error linked to its field.
    $errorFields = [
        'title' => 'field-title', 'slug' => 'field-slug', 'confirm_slug_change' => 'field-confirm_slug_change',
        'sections' => 'sections-heading',
    ];
    foreach ($errors->keys() as $key) {
        if (preg_match('/^sections\.(\d+)\.(\w+)$/', $key, $m)) {
            $errorFields[$key] = in_array($m[2], App\Support\PageSections::formKeys($sections[$m[1]]['type'] ?? 'text'), true) && $m[2] !== 'type'
                ? "section-{$m[1]}-{$m[2]}" : "section-{$m[1]}";
        }
    }
    $errorFields += ['meta_title' => 'field-meta_title', 'meta_description' => 'field-meta_description',
        'canonical_url' => 'field-canonical_url', 'og_image_id' => 'field-og_image_id', 'status' => 'field-status'];
@endphp

@section('title', $editing ? 'Edit '.$page->title : 'Add a page')

@section('content')
    <div class="page-head">
        <h1>{{ $editing ? 'Edit page' : 'Add a page' }}</h1>
        <a href="{{ route('admin.pages.index') }}">Back to pages</a>
    </div>

    @include('admin.partials.errors', ['fields' => $errorFields])

    <form method="POST" action="{{ $editing ? route('admin.pages.update', $page) : route('admin.pages.store') }}">
        @csrf
        @if ($editing)
            @method('PUT')
        @endif

        {{-- First submit button in the form, so pressing Enter in a field saves
             (rather than triggering a section's Move or Remove button). --}}
        <div class="actions panel">
            <button type="submit" class="button">{{ $editing ? 'Save changes' : 'Create page' }}</button>
            @if ($publicUrl && $isPublished)
                <a href="{{ $publicUrl }}" target="_blank" rel="noopener">View on the website<span class="visually-hidden"> (opens in a new tab)</span></a>
            @endif
        </div>

        <fieldset class="panel form">
            <legend><h2>Page</h2></legend>
            @include('admin.partials.field', ['name' => 'title', 'label' => 'Title', 'type' => 'text', 'required' => true,
                'value' => old('title', $page->title), 'maxlength' => 255])

            @if ($slugLocked)
                <input type="hidden" name="slug" value="{{ $page->slug }}">
                <p><strong>Address:</strong> {{ parse_url($publicUrl, PHP_URL_PATH) ?: '/' }}
                    <span class="muted small">(fixed: this page is part of the site's structure)</span></p>
            @else
                @include('admin.partials.field', ['name' => 'slug', 'label' => 'Slug', 'type' => 'text',
                    'value' => old('slug', $page->slug), 'maxlength' => 191,
                    'hint' => $editing ? 'Lowercase words joined by hyphens. Leave empty to keep the current slug.'
                                       : 'Lowercase words joined by hyphens. Leave empty to create it from the title.'])
                @if ($isPublished)
                    <div class="alert alert--warning">
                        <p><strong>This page is published.</strong> Changing its slug changes links to it.</p>
                        <div class="field checkbox">
                            <input type="hidden" name="confirm_slug_change" value="0">
                            <input id="field-confirm_slug_change" type="checkbox" name="confirm_slug_change" value="1" @checked(old('confirm_slug_change'))>
                            <label for="field-confirm_slug_change">I understand, change the slug if I edited it</label>
                        </div>
                    </div>
                @endif
                @unless ($publicUrl)
                    <p class="muted small">Only the home, about, contact, privacy and terms pages have a public address at the moment.</p>
                @endunless
            @endif
        </fieldset>

        <section class="panel" aria-labelledby="sections-heading">
            <h2 id="sections-heading">Sections</h2>
            <p class="muted small">Sections appear on the page in this order. Moving, adding or removing a section keeps your
                unsaved changes; nothing is saved until you press Save.</p>

            @forelse ($sections as $i => $row)
                @include('admin.pages.section', ['row' => $row, 'i' => $i, 'count' => count($sections), 'mediaOptions' => $mediaOptions])
            @empty
                <p>This page has no sections yet.</p>
            @endforelse

            @if (count($sections) < App\Support\PageSections::MAX_SECTIONS)
                <div class="filters">
                    <div class="field">
                        <label for="new-section-type">Add a section</label>
                        <select id="new-section-type" name="new_section_type">
                            @foreach (App\Support\PageSections::TYPES as $type => $definition)
                                <option value="{{ $type }}">{{ $definition['label'] }}</option>
                            @endforeach
                        </select>
                    </div>
                    <button type="submit" class="button button--secondary" name="section_action" value="add">Add section</button>
                </div>
            @endif
        </section>

        <fieldset class="panel form">
            <legend><h2>Search engines and sharing</h2></legend>
            @include('admin.partials.field', ['name' => 'meta_title', 'label' => 'Page title for search results', 'type' => 'text',
                'value' => old('meta_title', $page->meta_title), 'maxlength' => 255,
                'hint' => 'Leave empty to use "'.($page->title ?: 'Title').' | site name". Around 60 characters works best.'])
            @include('admin.partials.field', ['name' => 'meta_description', 'label' => 'Description for search results', 'type' => 'textarea',
                'value' => old('meta_description', $page->meta_description), 'maxlength' => 500,
                'hint' => 'Around 155 characters works best.'])
            @include('admin.partials.field', ['name' => 'canonical_url', 'label' => 'Canonical URL', 'type' => 'url',
                'value' => old('canonical_url', $page->canonical_url), 'maxlength' => 500,
                'hint' => 'Only if the main copy of this content lives at another address. Leave empty to use this page\'s own address.'])
            @include('admin.partials.media-picker', ['name' => 'og_image_id', 'label' => 'Sharing image', 'selected' => $page->og_image_id,
                'options' => $mediaOptions, 'hint' => 'Shown when the page is shared. Leave empty to use the first section image or the site default.'])
        </fieldset>

        <fieldset class="panel form">
            <legend><h2>Publishing</h2></legend>
            @if ($canPublish)
                <div class="field">
                    <label for="field-status">Status</label>
                    <select id="field-status" name="status">
                        <option value="draft" @selected($status === 'draft')>Draft (not on the website)</option>
                        <option value="published" @selected($status === 'published')>Published</option>
                    </select>
                </div>
            @else
                <p>Status: <strong>{{ $isPublished ? 'Published' : 'Draft' }}</strong>.
                    {{ $isPublished ? 'Your changes go live when you save.' : 'Saving keeps it as a draft.' }}
                    Publishing and unpublishing need an administrator.</p>
            @endif
            @if ($editing && $page->published_at)
                <p class="muted small">Last published {{ $page->published_at->format('j M Y, H:i') }}.</p>
            @endif
        </fieldset>

        <div class="actions">
            <button type="submit" class="button">{{ $editing ? 'Save changes' : 'Create page' }}</button>
            @if ($editing && App\Support\CorePages::deletable($page->slug))
                @can('delete', $page)
                    <a href="{{ route('admin.pages.delete', $page) }}">Delete this page</a>
                @endcan
            @endif
        </div>
    </form>
@endsection
