@extends('admin.layouts.auth')

@section('title', 'Choose a new password')

@section('content')
    <h1>Choose a new password</h1>

    @include('admin.partials.errors', ['fields' => ['email' => 'field-email', 'password' => 'field-password']])

    <form method="POST" action="{{ route('admin.password.store') }}">
        @csrf
        <input type="hidden" name="token" value="{{ $token }}">
        @include('admin.partials.field', ['name' => 'email', 'label' => 'Email address', 'type' => 'email', 'required' => true,
            'value' => old('email', $email), 'autocomplete' => 'username'])
        @include('admin.partials.field', ['name' => 'password', 'label' => 'New password', 'type' => 'password', 'required' => true,
            'autocomplete' => 'new-password', 'hint' => 'At least 12 characters, with upper- and lower-case letters, a number and a symbol.'])
        @include('admin.partials.field', ['name' => 'password_confirmation', 'label' => 'Confirm new password', 'type' => 'password',
            'required' => true, 'autocomplete' => 'new-password'])
        <button type="submit" class="button">Reset password</button>
    </form>

    <p class="small"><a href="{{ route('admin.login') }}">Back to sign in</a></p>
@endsection
