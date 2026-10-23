{{--
    Page expired (CSRF token or session timed out). Any other form than the
    contact form ends up here; the contact form is handled before this view
    by a redirect that keeps the visitor's message (see AppServiceProvider).
--}}
@extends('layouts.public')

@section('robots', 'noindex')

@php($seo = ['title' => 'Page expired | '.config('app.name'), 'description' => null, 'canonical_url' => url()->current(), 'og_image' => null])

@section('content')
    <div class="container section">
        <h1>This page has expired</h1>
        <p class="lead">The page was open for too long, so for your security it was not submitted. Please go back, reload the page and try again.</p>
        <div class="button-row">
            <a class="button" href="{{ route('home') }}">Go to the home page</a>
            <a class="button button--secondary" href="{{ route('contact') }}">Contact us</a>
        </div>
    </div>
@endsection
