{{-- About (FR-ABOUT-01 to 05): CMS sections plus the consultant overview. --}}
@extends('layouts.public')

@section('content')
    <div class="container page-header">
        <h1>{{ $page['title'] }}</h1>
    </div>

    @include('public.partials.sections', ['sections' => $page['sections']])

    @if ($consultants)
        <section class="section section--muted" aria-labelledby="leadership-heading">
            <div class="container">
                <h2 id="leadership-heading">Leadership and consultants</h2>
                <ul class="grid">
                    @foreach ($consultants as $consultant)
                        <li>@include('public.partials.consultant-card', ['consultant' => $consultant, 'headingTag' => 'h3'])</li>
                    @endforeach
                </ul>
            </div>
        </section>
    @endif
@endsection
