{{-- Accessible pagination for admin lists. --}}
@if ($paginator->hasPages())
    <nav aria-label="Pagination">
        <ul class="actions">
            @if (! $paginator->onFirstPage())
                <li><a href="{{ $paginator->previousPageUrl() }}" rel="prev">Previous<span class="visually-hidden"> page</span></a></li>
            @endif
            <li><span class="muted">Page {{ $paginator->currentPage() }} of {{ $paginator->lastPage() }}</span></li>
            @if ($paginator->hasMorePages())
                <li><a href="{{ $paginator->nextPageUrl() }}" rel="next">Next<span class="visually-hidden"> page</span></a></li>
            @endif
        </ul>
    </nav>
@endif
