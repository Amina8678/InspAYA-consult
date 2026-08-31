@extends('layouts.admin')
@section('title', 'Pricing Plans')
@section('content')
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h2>Pricing Plans</h2>
        <a href="{{ route('admin.pricing.create') }}" class="btn btn-dark">+ Add Plan</a>
    </div>
    <table class="table bg-white shadow-sm">
        <thead><tr><th>Order</th><th>Name</th><th>Price</th><th></th></tr></thead>
        <tbody>
        @foreach ($items as $item)
            <tr>
                <td>{{ $item->order }}</td>
                <td>{{ $item->name }}</td>
                <td>{{ $item->price }}</td>
                <td class="text-end">
                    <a href="{{ route('admin.pricing.edit', $item) }}" class="btn btn-sm btn-outline-secondary">Edit</a>
                    <form method="POST" action="{{ route('admin.pricing.destroy', $item) }}" class="d-inline" onsubmit="return confirm('Delete?')">
                        @csrf @method('DELETE')
                        <button class="btn btn-sm btn-outline-danger">Delete</button>
                    </form>
                </td>
            </tr>
        @endforeach
        </tbody>
    </table>
@endsection
