{{--
    Navy header band for inner pages (same family as the home hero). Holds the
    page's single <h1>. Parameters are prefixed because @include inherits the
    parent's variables: $heroTitle, optional $heroLead and $heroBreadcrumbs
    (list of ['label', 'url']; the last is the current page).
--}}
<div class="page-hero">
    <div class="container">
        @if (! empty($heroBreadcrumbs))
            <nav class="breadcrumb" aria-label="Breadcrumb">
                <ol>
                    @foreach ($heroBreadcrumbs as $crumb)
                        <li><a href="{{ $crumb['url'] }}" @if ($loop->last) aria-current="page" @endif>{{ $crumb['label'] }}</a></li>
                    @endforeach
                </ol>
            </nav>
        @endif
        <h1>{{ $heroTitle }}</h1>
        @if (! empty($heroLead))
            <p class="lead">{{ $heroLead }}</p>
        @endif
    </div>
</div>
