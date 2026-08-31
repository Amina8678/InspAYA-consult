@extends('layouts.admin')
@section('title', 'Dashboard')
@section('content')
    <h2 class="mb-4">Dashboard</h2>
    <div class="row g-3">
        @foreach ([
            ['label' => 'Services', 'count' => $counts['services'], 'route' => 'admin.services.index'],
            ['label' => 'Team Members', 'count' => $counts['team'], 'route' => 'admin.team.index'],
            ['label' => 'Projects', 'count' => $counts['projects'], 'route' => 'admin.projects.index'],
            ['label' => 'Blog Posts', 'count' => $counts['blog'], 'route' => 'admin.blog.index'],
            ['label' => 'Pricing Plans', 'count' => $counts['pricing'], 'route' => 'admin.pricing.index'],
        ] as $card)
            <div class="col-md-4 col-lg-2">
                <a href="{{ route($card['route']) }}" class="card text-decoration-none text-dark shadow-sm h-100">
                    <div class="card-body">
                        <div class="fs-3 fw-bold">{{ $card['count'] }}</div>
                        <div class="text-muted">{{ $card['label'] }}</div>
                    </div>
                </a>
            </div>
        @endforeach
    </div>
    <a href="{{ route('admin.settings.edit') }}" class="btn btn-dark mt-4">Edit Theme &amp; Site Content</a>
@endsection
