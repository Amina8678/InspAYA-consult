{{--
    Server error. Receives none of the shared variables (see contract §2).
    Content is fixed and generic on purpose: never interpolate the
    exception, its message or any debug data here, regardless of APP_DEBUG.
--}}
@extends('layouts.public')

@section('robots', 'noindex')

@php($seo = ['title' => 'Something went wrong | '.config('app.name'), 'description' => null, 'canonical_url' => url()->current(), 'og_image' => null])

@section('content')
    <div class="container section">
        <h1>Something went wrong</h1>
        <p class="lead">Sorry, something went wrong on our end. Please try again in a few minutes.</p>
        <div class="button-row">
            <a class="button" href="{{ route('home') }}">Go to the home page</a>
            <a class="button button--secondary" href="{{ route('contact') }}">Contact us</a>
        </div>
    </div>
@endsection
