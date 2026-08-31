@extends('layouts.admin')
@section('title', 'Blog / Insights')
@section('content')
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h2>Blog / Insights</h2>
        <a href="{{ route('admin.blog.create') }}" class="btn btn-dark">+ Add Post</a>
    </div>
    <table class="table bg-white shadow-sm">
        <thead><tr><th>Order</th><th>Title</th><th></th></tr></thead>
        <tbody>
        @foreach ($items as $item)
            <tr>
                <td>{{ $item->order }}</td>
                <td>{{ $item->title }}</td>
                <td class="text-end">
                    <a href="{{ route('admin.blog.edit', $item) }}" class="btn btn-sm btn-outline-secondary">Edit</a>
                    <form method="POST" action="{{ route('admin.blog.destroy', $item) }}" class="d-inline" onsubmit="return confirm('Delete?')">
                        @csrf @method('DELETE')
                        <button class="btn btn-sm btn-outline-danger">Delete</button>
                    </form>
                </td>
            </tr>
        @endforeach
        </tbody>
    </table>
@endsection
