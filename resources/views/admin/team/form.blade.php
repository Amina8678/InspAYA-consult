@extends('layouts.admin')
@section('title', $item->exists ? 'Edit Member' : 'Add Member')
@section('content')
    <h2 class="mb-4">{{ $item->exists ? 'Edit Member' : 'Add Member' }}</h2>
    <form method="POST" action="{{ $item->exists ? route('admin.team.update', $item) : route('admin.team.store') }}">
        @csrf
        @if ($item->exists) @method('PUT') @endif
        <div class="row g-3">
            <div class="col-md-6">
                <label class="form-label">Name</label>
                <input type="text" name="name" class="form-control" value="{{ old('name', $item->name) }}" required>
            </div>
            <div class="col-md-6">
                <label class="form-label">Role</label>
                <input type="text" name="role" class="form-control" value="{{ old('role', $item->role) }}">
            </div>
            <div class="col-md-6">
                <label class="form-label">Image path (e.g. asset/images/mama.jpg)</label>
                <input type="text" name="image" class="form-control" value="{{ old('image', $item->image) }}">
            </div>
            <div class="col-md-2">
                <label class="form-label">Facebook URL</label>
                <input type="text" name="facebook" class="form-control" value="{{ old('facebook', $item->facebook) }}">
            </div>
            <div class="col-md-2">
                <label class="form-label">Twitter URL</label>
                <input type="text" name="twitter" class="form-control" value="{{ old('twitter', $item->twitter) }}">
            </div>
            <div class="col-md-2">
                <label class="form-label">Instagram URL</label>
                <input type="text" name="instagram" class="form-control" value="{{ old('instagram', $item->instagram) }}">
            </div>
            <div class="col-md-2">
                <label class="form-label">Order</label>
                <input type="number" name="order" class="form-control" value="{{ old('order', $item->order ?? 0) }}">
            </div>
        </div>
        <button class="btn btn-dark mt-4">Save</button>
        <a href="{{ route('admin.team.index') }}" class="btn btn-link">Cancel</a>
    </form>
@endsection
