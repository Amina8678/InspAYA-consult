{{-- Labelled search box for admin lists. $action, $value, $label (e.g. "Search core values"). --}}
<form class="filters" method="GET" action="{{ $action }}" role="search">
    <div class="field">
        <label for="list-search">{{ $label }}</label>
        <input id="list-search" type="text" name="q" value="{{ $value }}" maxlength="100">
    </div>
    <button type="submit" class="button button--secondary">Search</button>
    @if ($value !== '')
        <a href="{{ $action }}">Clear search</a>
    @endif
</form>
