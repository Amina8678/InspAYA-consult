{{-- Accessible pagination for $posts->links('public.partials.pagination'). --}}
@if ($paginator->hasPages())
    <nav class="pagination" aria-label="Pagination">
        <ul>
            @if (! $paginator->onFirstPage())
                <li><a href="{{ $paginator->previousPageUrl() }}" rel="prev">Previous<span class="visually-hidden"> page</span></a></li>
            @endif

            @foreach ($elements as $element)
                @if (is_string($element))
                    <li><span aria-hidden="true">{{ $element }}</span></li>
                @endif

                @if (is_array($element))
                    @foreach ($element as $page => $url)
                        <li>
                            @if ($page == $paginator->currentPage())
                                <span aria-current="page"><span class="visually-hidden">Page </span>{{ $page }}</span>
                            @else
                                <a href="{{ $url }}"><span class="visually-hidden">Page </span>{{ $page }}</a>
                            @endif
                        </li>
                    @endforeach
                @endif
            @endforeach

            @if ($paginator->hasMorePages())
                <li><a href="{{ $paginator->nextPageUrl() }}" rel="next">Next<span class="visually-hidden"> page</span></a></li>
            @endif
        </ul>
    </nav>
@endif
