{{-- Not found. Receives none of the shared variables (see contract §2). --}}
@extends('layouts.public')

@section('robots', 'noindex')

@php($seo = ['title' => 'Page not found | '.config('app.name'), 'description' => null, 'canonical_url' => url()->current(), 'og_image' => null])

@section('content')
    <div class="container section">
        <h1>Page not found</h1>
        <p class="lead">The page you are looking for doesn't exist, has moved, or isn't published yet.</p>
        <div class="button-row">
            <a class="button" href="{{ route('home') }}">Go to the home page</a>
            <a class="button button--secondary" href="{{ route('contact') }}">Contact us</a>
        </div>
    </div>
@endsection
