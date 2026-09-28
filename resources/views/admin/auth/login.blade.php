@extends('admin.layouts.auth')

@section('title', 'Sign in')

@section('content')
    <h1>Sign in</h1>

    @if (session('status'))
        <div class="alert alert--success" role="status"><p>{{ session('status') }}</p></div>
    @endif

    {{-- One generic message for every failure: it never says whether the email exists. --}}
    @include('admin.partials.errors', ['fields' => ['email' => 'field-email', 'password' => 'field-password']])

    <form method="POST" action="{{ route('admin.login.submit') }}">
        @csrf
        @include('admin.partials.field', ['name' => 'email', 'label' => 'Email address', 'type' => 'email', 'required' => true,
            'value' => old('email'), 'autocomplete' => 'username'])
        @include('admin.partials.field', ['name' => 'password', 'label' => 'Password', 'type' => 'password', 'required' => true,
            'autocomplete' => 'current-password'])
        <div class="field checkbox">
            <input id="field-remember" type="checkbox" name="remember" value="1" @checked(old('remember'))>
            <label for="field-remember">Keep me signed in on this device</label>
        </div>
        <button type="submit" class="button">Sign in</button>
    </form>

    <p class="small"><a href="{{ route('admin.password.request') }}">Forgot your password?</a></p>
@endsection
