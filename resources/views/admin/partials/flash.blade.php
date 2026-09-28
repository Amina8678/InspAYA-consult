{{-- One-off success message after a redirect. --}}
@if (session('status'))
    <div class="alert alert--success" role="status">
        <p>{{ session('status') }}</p>
    </div>
@endif
