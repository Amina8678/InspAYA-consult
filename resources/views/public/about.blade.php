{{-- About (FR-ABOUT-01 to 05): CMS sections plus the consultant overview. --}}
@extends('layouts.public')

@section('content')
    @include('public.partials.page-hero', ['heroTitle' => $page['title']])

    @include('public.partials.sections', ['sections' => $page['sections']])

    @if ($consultants)
        <section class="section section--muted" aria-labelledby="leadership-heading">
            <div class="container">
                @include('public.partials.section-header', ['sectionEyebrow' => 'Our team', 'sectionHeading' => 'Leadership and consultants', 'sectionId' => 'leadership-heading'])
                <ul class="grid">
                    @foreach ($consultants as $consultant)
                        <li>@include('public.partials.consultant-card', ['consultant' => $consultant, 'headingTag' => 'h3'])</li>
                    @endforeach
                </ul>
            </div>
        </section>
    @endif
@endsection
