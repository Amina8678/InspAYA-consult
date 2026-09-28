{{-- Consultants (FR-TEAM-01/02): active profiles with their services. --}}
@extends('layouts.public')

@section('content')
    <div class="container page-header">
        <h1>Consultants</h1>
        <p class="lead">Experienced advisors across governance, finance, energy, engineering, digital systems and law.</p>
    </div>

    <section class="section section--muted" aria-label="All consultants">
        <div class="container">
            @if ($consultants)
                <ul class="grid">
                    @foreach ($consultants as $consultant)
                        <li>@include('public.partials.consultant-card', ['consultant' => $consultant, 'headingTag' => 'h2', 'full' => true])</li>
                    @endforeach
                </ul>
            @else
                <p>Consultant profiles will be published here soon.</p>
            @endif
        </div>
    </section>
@endsection
