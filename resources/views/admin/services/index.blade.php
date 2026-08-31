@extends('layouts.admin')
@section('title', 'Services')
@section('content')
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h2>Services</h2>
        <a href="{{ route('admin.services.create') }}" class="btn btn-dark">+ Add Service</a>
    </div>
    <table class="table bg-white shadow-sm">
        <thead><tr><th>Order</th><th>Icon</th><th>Title</th><th></th></tr></thead>
        <tbody>
        @foreach ($items as $item)
            <tr>
                <td>{{ $item->order }}</td>
                <td><i class="{{ $item->icon }}"></i> {{ $item->icon }}</td>
                <td>{{ $item->title }}</td>
                <td class="text-end">
                    <a href="{{ route('admin.services.edit', $item) }}" class="btn btn-sm btn-outline-secondary">Edit</a>
                    <form method="POST" action="{{ route('admin.services.destroy', $item) }}" class="d-inline" onsubmit="return confirm('Delete this service?')">
                        @csrf @method('DELETE')
                        <button class="btn btn-sm btn-outline-danger">Delete</button>
                    </form>
                </td>
            </tr>
        @endforeach
        </tbody>
    </table>
@endsection
