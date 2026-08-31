@extends('layouts.admin')
@section('title', $item->exists ? 'Edit Project' : 'Add Project')
@section('content')
    <h2 class="mb-4">{{ $item->exists ? 'Edit Project' : 'Add Project' }}</h2>
    <form method="POST" action="{{ $item->exists ? route('admin.projects.update', $item) : route('admin.projects.store') }}">
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
            <label class="form-label">Subtitle</label>
            <input type="text" name="subtitle" class="form-control" value="{{ old('subtitle', $item->subtitle) }}">
        </div>
        <div class="mb-3">
            <label class="form-label">Order</label>
            <input type="number" name="order" class="form-control" value="{{ old('order', $item->order ?? 0) }}">
        </div>
        <button class="btn btn-dark">Save</button>
        <a href="{{ route('admin.projects.index') }}" class="btn btn-link">Cancel</a>
    </form>
@endsection
