@extends('layouts.admin')
@section('title', $item->exists ? 'Edit Plan' : 'Add Plan')
@section('content')
    <h2 class="mb-4">{{ $item->exists ? 'Edit Plan' : 'Add Plan' }}</h2>
    <form method="POST" action="{{ $item->exists ? route('admin.pricing.update', $item) : route('admin.pricing.store') }}">
        @csrf
        @if ($item->exists) @method('PUT') @endif
        <div class="row g-3">
            <div class="col-md-6">
                <label class="form-label">Plan name</label>
                <input type="text" name="name" class="form-control" value="{{ old('name', $item->name) }}" required>
            </div>
            <div class="col-md-3">
                <label class="form-label">Price (e.g. $999)</label>
                <input type="text" name="price" class="form-control" value="{{ old('price', $item->price) }}" required>
            </div>
            <div class="col-md-3">
                <label class="form-label">Order</label>
                <input type="number" name="order" class="form-control" value="{{ old('order', $item->order ?? 0) }}">
            </div>
            <div class="col-md-12">
                <label class="form-label">Features (one per line)</label>
                <textarea name="features" rows="6" class="form-control">{{ old('features', is_array($item->features) ? implode("\n", $item->features) : '') }}</textarea>
            </div>
        </div>
        <button class="btn btn-dark mt-4">Save</button>
        <a href="{{ route('admin.pricing.index') }}" class="btn btn-link">Cancel</a>
    </form>
@endsection
