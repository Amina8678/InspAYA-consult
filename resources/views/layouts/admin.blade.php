<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Admin') · InspAYA CMS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
</head>
<body class="bg-light">
    <div class="d-flex" style="min-height:100vh;">
        <nav class="p-3 text-white" style="width:230px; background:#0A1E38; flex-shrink:0;">
            <h5 class="mb-4">InspAYA CMS</h5>
            <div class="d-flex flex-column gap-2">
                <a class="text-white text-decoration-none" href="{{ route('admin.dashboard') }}">Dashboard</a>
                <a class="text-white text-decoration-none" href="{{ route('admin.settings.edit') }}">Theme &amp; Content</a>
                <a class="text-white text-decoration-none" href="{{ route('admin.services.index') }}">Services</a>
                <a class="text-white text-decoration-none" href="{{ route('admin.team.index') }}">Team</a>
                <a class="text-white text-decoration-none" href="{{ route('admin.projects.index') }}">Projects</a>
                <a class="text-white text-decoration-none" href="{{ route('admin.blog.index') }}">Blog / Insights</a>
                <a class="text-white text-decoration-none" href="{{ route('admin.pricing.index') }}">Pricing Plans</a>
                <hr class="border-secondary">
                <a class="text-white text-decoration-none" href="{{ route('home') }}" target="_blank">View Site &#8599;</a>
                <form method="POST" action="{{ route('admin.logout') }}">
                    @csrf
                    <button class="btn btn-sm btn-outline-light mt-2 w-100">Logout</button>
                </form>
            </div>
        </nav>
        <main class="flex-grow-1 p-4">
            @if (session('status'))
                <div class="alert alert-success">{{ session('status') }}</div>
            @endif
            @yield('content')
        </main>
    </div>
</body>
</html>
