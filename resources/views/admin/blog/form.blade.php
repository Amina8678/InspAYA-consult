@extends('layouts.admin')
@section('title', $item->exists ? 'Edit Post' : 'Add Post')
@section('content')
    <h2 class="mb-4">{{ $item->exists ? 'Edit Post' : 'Add Post' }}</h2>
    <form method="POST" action="{{ $item->exists ? route('admin.blog.update', $item) : route('admin.blog.store') }}">
        @csrf
        @if ($item->exists) @method('PUT') @endif
        <div class="mb-3">
            <label class="form-label">Image path</label>
            <input type="text" name="image" class="form-control" value="{{ old('image', $item->image) }}">
        </div>
        <div class="mb-3">
            <label class="form-label">Title</label>
            <input type="text" name="title" class="form-control" value="{{ old('title', $item->title) }}" required>
        </div>
        <div class="mb-3">
            <label class="form-label">Excerpt</label>
            <textarea name="excerpt" rows="3" class="form-control">{{ old('excerpt', $item->excerpt) }}</textarea>
        </div>
        <div class="mb-3">
            <label class="form-label">Order</label>
            <input type="number" name="order" class="form-control" value="{{ old('order', $item->order ?? 0) }}">
        </div>
        <button class="btn btn-dark">Save</button>
        <a href="{{ route('admin.blog.index') }}" class="btn btn-link">Cancel</a>
    </form>
@endsection
