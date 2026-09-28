@extends('admin.layouts.auth')

@section('title', 'Reset your password')

@section('content')
    <h1>Reset your password</h1>

    @if (session('status'))
        {{-- Same message whether or not the email has an account. --}}
        <div class="alert alert--success" role="status"><p>{{ session('status') }}</p></div>
    @endif

    <p>Enter the email address for your account and we'll send you a link to choose a new password.</p>

    @include('admin.partials.errors', ['fields' => ['email' => 'field-email']])

    <form method="POST" action="{{ route('admin.password.email') }}">
        @csrf
        @include('admin.partials.field', ['name' => 'email', 'label' => 'Email address', 'type' => 'email', 'required' => true,
            'value' => old('email'), 'autocomplete' => 'username'])
        <button type="submit" class="button">Send reset link</button>
    </form>

    <p class="small"><a href="{{ route('admin.login') }}">Back to sign in</a></p>
@endsection
