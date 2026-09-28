{{-- Consultants (FR-TEAM-01/02): active profiles with their services. --}}
@extends('layouts.public')

@section('content')
    @include('public.partials.page-hero', [
        'heroTitle' => 'Consultants',
        'heroLead' => 'Experienced advisors across governance, finance, energy, engineering, digital systems and law.',
    ])

    <section class="section section--muted" aria-label="All consultants">
        <div class="container">
            @if ($consultants)
                <ul class="grid">
                    @foreach ($consultants as $consultant)
                        <li>@include('public.partials.consultant-card', ['consultant' => $consultant, 'headingTag' => 'h2', 'full' => true])</li>
                    @endforeach
                </ul>
            @else
                <p class="text-block text-block--center">Consultant profiles will be published here soon.</p>
            @endif
        </div>
    </section>
@endsection
