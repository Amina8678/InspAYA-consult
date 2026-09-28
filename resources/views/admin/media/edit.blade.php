@extends('admin.layouts.app')

@section('title', 'Edit '.$media->file_name)

@php($isImage = str_starts_with($media->mime_type, 'image/'))

@section('content')
    <div class="page-head">
        <h1>Edit file</h1>
        <a href="{{ route('admin.media.index') }}">Back to media library</a>
    </div>

    <div class="grid-2">
        <section class="panel" aria-labelledby="details-heading">
            <h2 id="details-heading">{{ $media->file_name }}</h2>
            @if ($isImage)
                <img src="{{ Storage::disk($media->disk)->url($media->storage_path) }}" alt="{{ $media->alt_text }}"
                     @if ($media->width) width="{{ $media->width }}" @endif
                     @if ($media->height) height="{{ $media->height }}" @endif>
            @endif
            <dl class="small">
                <dt>Type</dt><dd>{{ $media->mime_type }}</dd>
                <dt>Size</dt><dd>{{ Illuminate\Support\Number::fileSize($media->size, 1) }}</dd>
                @if ($media->width && $media->height)
                    <dt>Dimensions</dt><dd>{{ $media->width }} × {{ $media->height }} px</dd>
                @endif
                <dt>Uploaded</dt><dd>{{ $media->created_at->format('j M Y, H:i') }} by {{ $media->uploader?->name ?? 'Unknown' }}</dd>
            </dl>

            <h3>Used in</h3>
            @if ($usages)
                <ul>
                    @foreach ($usages as $usage)
                        <li>{{ str($usage['type'])->headline() }}: {{ $usage['label'] }} <span class="muted">({{ $usage['field'] }})</span></li>
                    @endforeach
                </ul>
            @else
                <p class="muted">Not used anywhere yet.</p>
            @endif
        </section>

        <section class="panel" aria-labelledby="edit-heading">
            <h2 id="edit-heading">Details</h2>

            @include('admin.partials.errors', ['fields' => [
                'alt_text' => 'field-alt_text',
                'caption' => 'field-caption',
                'file' => 'field-file',
            ]])

            <form class="form" method="POST" action="{{ route('admin.media.update', $media) }}" enctype="multipart/form-data">
                @csrf
                @method('PUT')
                @include('admin.partials.field', ['name' => 'alt_text', 'label' => 'Alt text', 'type' => 'text', 'required' => $isImage,
                    'value' => old('alt_text', $media->alt_text), 'maxlength' => 255,
                    'hint' => 'Describe what the image shows for people who can\'t see it.'])
                @include('admin.partials.field', ['name' => 'caption', 'label' => 'Caption', 'type' => 'textarea',
                    'value' => old('caption', $media->caption), 'maxlength' => 1000])
                @include('admin.partials.field', ['name' => 'file', 'label' => 'Replace file', 'type' => 'file', 'accept' => $accept,
                    'hint' => 'Keeps this item, and everywhere it is used, but swaps the file. Same type and size limits as uploads.'])
                <div class="actions">
                    <button type="submit" class="button">Save changes</button>
                    @can('delete', $media)
                        <a href="{{ route('admin.media.delete', $media) }}">Delete this file</a>
                    @endcan
                </div>
            </form>
        </section>
    </div>
@endsection
