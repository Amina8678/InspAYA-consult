@extends('admin.layouts.auth')

@section('title', 'Too many attempts')

@section('content')
    <h1>Too many attempts</h1>
    <p>
        For your security, this has been paused.
        @if ($retryAfter)
            Please try again in {{ $retryAfter }} {{ str('second')->plural($retryAfter) }}.
        @else
            Please wait a minute and try again.
        @endif
    </p>
    <p><a href="{{ route('admin.login') }}">Back to sign in</a></p>
@endsection
