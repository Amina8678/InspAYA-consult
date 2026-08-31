@extends('layouts.admin')
@section('title', $item->exists ? 'Edit Service' : 'Add Service')
@section('content')
    <h2 class="mb-4">{{ $item->exists ? 'Edit Service' : 'Add Service' }}</h2>
    <form method="POST" action="{{ $item->exists ? route('admin.services.update', $item) : route('admin.services.store') }}">
        @csrf
        @if ($item->exists) @method('PUT') @endif
        <div class="mb-3">
            <label class="form-label">Icon class (boxicons, e.g. bx bxs-cog)</label>
            <input type="text" name="icon" class="form-control" value="{{ old('icon', $item->icon) }}">
        </div>
        <div class="mb-3">
            <label class="form-label">Title</label>
            <input type="text" name="title" class="form-control" value="{{ old('title', $item->title) }}" required>
        </div>
        <div class="mb-3">
            <label class="form-label">Description</label>
            <textarea name="description" rows="3" class="form-control">{{ old('description', $item->description) }}</textarea>
        </div>
        <div class="mb-3">
            <label class="form-label">Order</label>
            <input type="number" name="order" class="form-control" value="{{ old('order', $item->order ?? 0) }}">
        </div>
        <button class="btn btn-dark">Save</button>
        <a href="{{ route('admin.services.index') }}" class="btn btn-link">Cancel</a>
    </form>
@endsection
