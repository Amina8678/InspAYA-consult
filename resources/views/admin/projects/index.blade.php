@extends('layouts.admin')
@section('title', 'Projects')
@section('content')
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h2>Projects</h2>
        <a href="{{ route('admin.projects.create') }}" class="btn btn-dark">+ Add Project</a>
    </div>
    <table class="table bg-white shadow-sm">
        <thead><tr><th>Order</th><th>Title</th><th>Subtitle</th><th></th></tr></thead>
        <tbody>
        @foreach ($items as $item)
            <tr>
                <td>{{ $item->order }}</td>
                <td>{{ $item->title }}</td>
                <td>{{ $item->subtitle }}</td>
                <td class="text-end">
                    <a href="{{ route('admin.projects.edit', $item) }}" class="btn btn-sm btn-outline-secondary">Edit</a>
                    <form method="POST" action="{{ route('admin.projects.destroy', $item) }}" class="d-inline" onsubmit="return confirm('Delete?')">
                        @csrf @method('DELETE')
                        <button class="btn btn-sm btn-outline-danger">Delete</button>
                    </form>
                </td>
            </tr>
        @endforeach
        </tbody>
    </table>
@endsection
