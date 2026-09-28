@extends('admin.layouts.app')

@section('title', 'Users')

@section('content')
    <div class="page-head">
        <h1>Users</h1>
        @can('create', App\Models\User::class)
            <a class="button" href="{{ route('admin.users.create') }}">Add a user</a>
        @endcan
    </div>

    <section class="panel" aria-labelledby="list-heading">
        <h2 id="list-heading" class="visually-hidden">All users</h2>

        @include('admin.partials.search', ['action' => route('admin.users.index'), 'value' => $search, 'label' => 'Search by name, email or username'])

        @if ($users->isEmpty())
            <p>{{ $search === '' ? 'No users yet.' : 'No users match your search.' }}</p>
        @else
            <table class="table">
                <caption class="visually-hidden">Users, alphabetical by name</caption>
                <thead>
                    <tr>
                        <th scope="col">Name</th>
                        <th scope="col">Email</th>
                        <th scope="col">Username</th>
                        <th scope="col">Role</th>
                        <th scope="col">Status</th>
                        <th scope="col">Last login</th>
                        <th scope="col"><span class="visually-hidden">Actions</span></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($users as $user)
                        <tr>
                            <td data-label="Name"><div>{{ $user->name }}</div></td>
                            <td data-label="Email"><div>{{ $user->email }}</div></td>
                            <td data-label="Username"><div>{{ $user->username }}</div></td>
                            <td data-label="Role"><div>{{ $user->role?->name ?? 'None' }}</div></td>
                            <td data-label="Status"><div>{{ $user->status->value === 'active' ? 'Active' : 'Inactive' }}</div></td>
                            <td data-label="Last login"><div>
                                @if ($user->last_login_at)
                                    <time datetime="{{ $user->last_login_at->toIso8601String() }}">{{ $user->last_login_at->format('j M Y, H:i') }}</time>
                                @else
                                    <span class="muted">Never</span>
                                @endif
                            </div></td>
                            <td data-label="Actions"><div class="actions">
                                @can('update', $user)
                                    <a href="{{ route('admin.users.edit', $user) }}">Edit<span class="visually-hidden"> {{ $user->name }}</span></a>
                                @endcan
                            </div></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>

            {{ $users->links('admin.partials.pagination') }}
        @endif
    </section>
@endsection
