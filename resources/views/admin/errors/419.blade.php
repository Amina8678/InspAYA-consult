@extends('admin.layouts.auth')

@section('title', 'Page expired')

@section('content')
    <h1>This page has expired</h1>
    <p>
        Your session timed out before this form was sent, so nothing was saved.
        Sign in again, then reopen the page and repeat the change.
    </p>
    <p><a href="{{ route('admin.login') }}">Go to sign in</a></p>
@endsection
