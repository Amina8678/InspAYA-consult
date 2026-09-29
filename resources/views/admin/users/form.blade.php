@extends('admin.layouts.app')

@php
    $editing = $targetUser->exists;
    $actor = auth()->user();
    $isSelf = $editing && $actor->is($targetUser);
    // Mirrors UserPolicy::mayActOn: an Administrator may not act on a Super
    // Admin account unless they are one themselves.
    $mayActOnTarget = ! $editing || ! $targetUser->isSuperAdmin() || $actor->isSuperAdmin();
    $canChangeRole = $editing ? ($actor->hasPermission('users.assign-role') && ! $isSelf && $mayActOnTarget) : true;
    $canChangeStatus = $editing && $actor->hasPermission('users.deactivate') && ! $isSelf && $mayActOnTarget;
@endphp

@section('title', $editing ? 'Edit '.$targetUser->name : 'Add a user')

@section('content')
    <div class="page-head">
        <h1>{{ $editing ? 'Edit user' : 'Add a user' }}</h1>
        <a href="{{ route('admin.users.index') }}">Back to users</a>
    </div>

    @include('admin.partials.errors', ['fields' => [
        'name' => 'field-name', 'email' => 'field-email', 'username' => 'field-username',
        'role_id' => 'field-role_id', 'status' => 'field-status',
    ]])

    @if ($editing && ! $mayActOnTarget)
        <p class="muted">This account is a Super Admin. Only a Super Admin can change it.</p>
    @endif

    <form method="POST" action="{{ $editing ? route('admin.users.update', $targetUser) : route('admin.users.store') }}">
        @csrf
        @if ($editing)
            @method('PUT')
        @endif

        <fieldset class="panel form" @if ($editing && ! $mayActOnTarget) disabled @endif>
            <legend><h2>Profile</h2></legend>
            @include('admin.partials.field', ['name' => 'name', 'label' => 'Name', 'type' => 'text', 'required' => true,
                'value' => old('name', $targetUser->name), 'maxlength' => 255])
            @include('admin.partials.field', ['name' => 'email', 'label' => 'Email address', 'type' => 'email', 'required' => true,
                'value' => old('email', $targetUser->email), 'maxlength' => 255])
            @include('admin.partials.field', ['name' => 'username', 'label' => 'Username', 'type' => 'text', 'required' => true,
                'value' => old('username', $targetUser->username), 'maxlength' => 50,
                'hint' => 'Letters, numbers, dots, underscores and hyphens only.'])
        </fieldset>

        <fieldset class="panel form">
            <legend><h2>Role</h2></legend>
            @if ($canChangeRole)
                <div class="field">
                    <label for="field-role_id">Role</label>
                    <select id="field-role_id" name="role_id" required>
                        @foreach ($roles as $role)
                            <option value="{{ $role->id }}" @selected((string) old('role_id', $targetUser->role_id) === (string) $role->id)>{{ $role->name }}</option>
                        @endforeach
                    </select>
                </div>
            @else
                <p>Role: <strong>{{ $targetUser->role?->name ?? 'None' }}</strong>.
                    @if ($isSelf)
                        You cannot change your own role.
                    @elseif ($mayActOnTarget)
                        Only an administrator with role-assignment rights can change this.
                    @endif
                </p>
            @endif
        </fieldset>

        @if ($editing)
            <fieldset class="panel form">
                <legend><h2>Status</h2></legend>
                @if ($canChangeStatus)
                    <div class="field">
                        <label for="field-status">Status</label>
                        <select id="field-status" name="status">
                            <option value="active" @selected(old('status', $targetUser->status->value) === 'active')>Active</option>
                            <option value="inactive" @selected(old('status', $targetUser->status->value) === 'inactive')>Inactive</option>
                        </select>
                    </div>
                @else
                    <p>Status: <strong>{{ $targetUser->status->value === 'active' ? 'Active' : 'Inactive' }}</strong>.
                        @if ($isSelf)
                            You cannot deactivate your own account.
                        @elseif ($mayActOnTarget)
                            Only an administrator can change this.
                        @endif
                    </p>
                @endif
            </fieldset>
        @else
            <p class="muted small">A password setup link will be emailed to this address; there is no password field here.</p>
        @endif

        <div class="actions">
            <button type="submit" class="button">{{ $editing ? 'Save changes' : 'Create user' }}</button>
        </div>
    </form>

    @if ($editing)
        @can('resetPassword', $targetUser)
            <section class="panel form" aria-labelledby="reset-password-heading">
                <h2 id="reset-password-heading">Password</h2>
                <p class="muted small">Sends a password reset link to {{ $targetUser->email }}, the same link a user gets from "Forgot password".</p>
                <form method="POST" action="{{ route('admin.users.reset-password', $targetUser) }}">
                    @csrf
                    <div class="actions">
                        <button type="submit" class="button">Send password reset link</button>
                    </div>
                </form>
            </section>
        @endcan
    @endif
@endsection
