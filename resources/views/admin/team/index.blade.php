@extends('layouts.admin')
@section('title', 'Team')
@section('content')
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h2>Team Members</h2>
        <a href="{{ route('admin.team.create') }}" class="btn btn-dark">+ Add Member</a>
    </div>
    <table class="table bg-white shadow-sm">
        <thead><tr><th>Order</th><th>Name</th><th>Role</th><th></th></tr></thead>
        <tbody>
        @foreach ($items as $item)
            <tr>
                <td>{{ $item->order }}</td>
                <td>{{ $item->name }}</td>
                <td>{{ $item->role }}</td>
                <td class="text-end">
                    <a href="{{ route('admin.team.edit', $item) }}" class="btn btn-sm btn-outline-secondary">Edit</a>
                    <form method="POST" action="{{ route('admin.team.destroy', $item) }}" class="d-inline" onsubmit="return confirm('Delete?')">
                        @csrf @method('DELETE')
                        <button class="btn btn-sm btn-outline-danger">Delete</button>
                    </form>
                </td>
            </tr>
        @endforeach
        </tbody>
    </table>
@endsection
