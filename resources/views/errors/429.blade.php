{{-- Too many requests (contact form and sign-in rate limits). No shared variables. --}}
@extends('layouts.public')

@section('robots', 'noindex')

@php($seo = ['title' => 'Too many requests | '.config('app.name'), 'description' => null, 'canonical_url' => url()->current(), 'og_image' => null])

@section('content')
    <div class="container section">
        <h1>Too many requests</h1>
        <p class="lead">You've tried this too many times in a short period. Please wait a minute and try again.</p>
        <div class="button-row">
            <a class="button" href="{{ route('home') }}">Go to the home page</a>
        </div>
    </div>
@endsection
