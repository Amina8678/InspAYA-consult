@extends('admin.layouts.app')

@section('title', 'Media library')

@section('content')
    <div class="page-head">
        <h1>Media library</h1>
        <p class="muted">{{ $media->total() }} {{ str('file')->plural($media->total()) }}</p>
    </div>

    @can('create', App\Models\Media::class)
        <section class="panel" aria-labelledby="upload-heading">
            <h2 id="upload-heading">Upload a file</h2>

            @include('admin.partials.errors', ['fields' => [
                'file' => 'field-file',
                'alt_text' => 'field-alt_text',
                'caption' => 'field-caption',
            ]])

            <form class="form" method="POST" action="{{ route('admin.media.store') }}" enctype="multipart/form-data">
                @csrf
                @include('admin.partials.field', ['name' => 'file', 'label' => 'File', 'type' => 'file', 'required' => true, 'accept' => $accept,
                    'hint' => 'JPEG, PNG, WebP or GIF images up to 5 MB (max 10,000 px per side), or PDF documents up to 10 MB.'])
                @include('admin.partials.field', ['name' => 'alt_text', 'label' => 'Alt text', 'type' => 'text', 'value' => old('alt_text'), 'maxlength' => 255,
                    'hint' => 'Required for images: describe what the image shows for people who can\'t see it.'])
                @include('admin.partials.field', ['name' => 'caption', 'label' => 'Caption', 'type' => 'textarea', 'value' => old('caption'), 'maxlength' => 1000])
                <button type="submit" class="button">Upload</button>
            </form>
        </section>
    @endcan

    <section class="panel" aria-labelledby="library-heading">
        <h2 id="library-heading">Files</h2>

        <form class="filters" method="GET" action="{{ route('admin.media.index') }}">
            <div class="field">
                <label for="filter-type">Type</label>
                <select id="filter-type" name="type">
                    <option value="">All types</option>
                    <option value="image" @selected($type === 'image')>Images</option>
                    <option value="document" @selected($type === 'document')>Documents (PDF)</option>
                </select>
            </div>
            <button type="submit" class="button button--secondary">Filter</button>
        </form>

        @if ($media->isEmpty())
            <p>No files {{ $type ? 'of this type ' : '' }}yet.</p>
        @else
            <table class="table">
                <caption class="visually-hidden">Media files, newest first</caption>
                <thead>
                    <tr>
                        <th scope="col">Preview</th>
                        <th scope="col">File</th>
                        <th scope="col">Details</th>
                        <th scope="col">Uploaded</th>
                        <th scope="col"><span class="visually-hidden">Actions</span></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($media as $item)
                        <tr>
                            <td data-label="Preview"><div>
                                @if (str_starts_with($item->mime_type, 'image/'))
                                    <img class="thumb" src="{{ Storage::disk($item->disk)->url($item->storage_path) }}"
                                         alt="{{ $item->alt_text }}" width="80" height="80" loading="lazy">
                                @else
                                    <span class="file-badge" aria-hidden="true">PDF</span>
                                @endif
                                </div>
                            </td>
                            <td data-label="File"><div>
                                <strong>{{ $item->file_name }}</strong>
                                @if ($item->alt_text)
                                    <br><span class="muted small">Alt: {{ $item->alt_text }}</span>
                                @elseif (str_starts_with($item->mime_type, 'image/'))
                                    <br><span class="muted small">No alt text</span>
                                @endif
                                </div>
                            </td>
                            <td data-label="Details" class="small"><div>
                                {{ $item->mime_type }}<br>
                                {{ Illuminate\Support\Number::fileSize($item->size, 1) }}
                                @if ($item->width && $item->height)
                                    <br>{{ $item->width }} × {{ $item->height }} px
                                @endif
                                </div>
                            </td>
                            <td data-label="Uploaded" class="small"><div>
                                <time datetime="{{ $item->created_at->toIso8601String() }}">{{ $item->created_at->format('j M Y') }}</time>
                                <br>{{ $item->uploader?->name ?? 'Unknown' }}
                                </div>
                            </td>
                            <td data-label="Actions"><div>
                                <div class="actions">
                                    @can('update', $item)
                                        <a href="{{ route('admin.media.edit', $item) }}">Edit<span class="visually-hidden"> {{ $item->file_name }}</span></a>
                                    @endcan
                                    @can('delete', $item)
                                        <a href="{{ route('admin.media.delete', $item) }}">Delete<span class="visually-hidden"> {{ $item->file_name }}</span></a>
                                    @endcan
                                </div>
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>

            {{ $media->links('admin.partials.pagination') }}
        @endif
    </section>
@endsection
