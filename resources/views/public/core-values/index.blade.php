{{-- Core values (FR-VAL-01/02), in display order. --}}
@extends('layouts.public')

@section('content')
    <div class="container page-header">
        <h1>Core values</h1>
        <p class="lead">The principles that guide every engagement.</p>
    </div>

    <section class="section section--muted" aria-label="Our values">
        <div class="container">
            @if ($coreValues)
                <ul class="grid">
                    @foreach ($coreValues as $value)
                        <li>
                            <article class="card">
                                @if ($value['icon'])
                                    <div class="icon">@include('public.partials.image', ['image' => $value['icon']])</div>
                                @endif
                                <h2>{{ $value['title'] }}</h2>
                                @if ($value['description'])
                                    <p class="prose">{{ $value['description'] }}</p>
                                @endif
                            </article>
                        </li>
                    @endforeach
                </ul>
            @else
                <p>Our core values will be published here soon.</p>
            @endif
        </div>
    </section>
@endsection
