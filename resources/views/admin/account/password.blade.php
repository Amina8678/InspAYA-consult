@extends('admin.layouts.app')

@section('title', 'Change password')

@section('content')
    <div class="page-head">
        <h1>Change password</h1>
    </div>

    <div class="panel">
        @include('admin.partials.errors', ['bag' => 'updatePassword', 'fields' => [
            'current_password' => 'field-current_password',
            'password' => 'field-password',
        ]])

        <form class="form" method="POST" action="{{ route('admin.account.password.update') }}">
            @csrf
            @method('PUT')

            @include('admin.partials.field', ['name' => 'current_password', 'label' => 'Current password', 'type' => 'password', 'required' => true, 'autocomplete' => 'current-password', 'bag' => 'updatePassword'])
            @include('admin.partials.field', ['name' => 'password', 'label' => 'New password', 'type' => 'password', 'required' => true, 'autocomplete' => 'new-password', 'bag' => 'updatePassword',
                'hint' => 'At least 12 characters, with upper- and lower-case letters, a number and a symbol.'])
            @include('admin.partials.field', ['name' => 'password_confirmation', 'label' => 'Confirm new password', 'type' => 'password', 'required' => true, 'autocomplete' => 'new-password', 'bag' => 'updatePassword'])

            <p class="muted small">Changing your password signs you out on your other devices.</p>
            <button type="submit" class="button">Change password</button>
        </form>
    </div>
@endsection
