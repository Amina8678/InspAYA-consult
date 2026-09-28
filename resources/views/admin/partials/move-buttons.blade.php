{{--
    Keyboard-friendly reordering (no drag and drop): Move up / Move down as
    two small POST forms. $action (move route), $label (item name for the
    accessible button text), $first, $last (hide the button that can't apply).
--}}
<div class="actions">
    @unless ($first)
        <form method="POST" action="{{ $action }}">
            @csrf
            <input type="hidden" name="direction" value="up">
            <button type="submit" class="button button--secondary">Move up<span class="visually-hidden"> {{ $label }}</span></button>
        </form>
    @endunless
    @unless ($last)
        <form method="POST" action="{{ $action }}">
            @csrf
            <input type="hidden" name="direction" value="down">
            <button type="submit" class="button button--secondary">Move down<span class="visually-hidden"> {{ $label }}</span></button>
        </form>
    @endunless
</div>
