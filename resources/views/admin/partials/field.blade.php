{{--
    Labelled input with hint and error, tied together by for/id and
    aria-describedby.
    $name, $label, $type (text, email, password, file, textarea),
    optional: $value, $required, $autocomplete, $hint, $bag, $maxlength, $accept, $id.
--}}
@php
    $id = $id ?? 'field-'.$name;
    $messages = $errors->getBag($bag ?? 'default');
    $error = $messages->first($name);
    $required = $required ?? false;
    $hint = $hint ?? null;
    $describedBy = trim(($hint ? $id.'-hint ' : '').($error ? $id.'-error' : ''));
@endphp
<div class="field {{ $error ? 'field--error' : '' }}">
    <label for="{{ $id }}">{{ $label }}@if (! $required) <span class="field__optional">(optional)</span>@endif</label>
    @if ($hint)
        <span class="field__hint" id="{{ $id }}-hint">{{ $hint }}</span>
    @endif
    @if ($error)
        <span class="field__error" id="{{ $id }}-error"><span class="visually-hidden">Error:</span> {{ $error }}</span>
    @endif
    @if ($type === 'textarea')
        <textarea id="{{ $id }}" name="{{ $name }}" rows="4"
                  @if ($required) required @endif
                  @if ($maxlength ?? false) maxlength="{{ $maxlength }}" @endif
                  @if ($describedBy) aria-describedby="{{ $describedBy }}" @endif
                  @if ($error) aria-invalid="true" @endif>{{ $value ?? '' }}</textarea>
    @else
        <input id="{{ $id }}" name="{{ $name }}" type="{{ $type }}"
               @if (! in_array($type, ['password', 'file'], true)) value="{{ $value ?? '' }}" @endif
               @if ($required) required @endif
               @if ($autocomplete ?? false) autocomplete="{{ $autocomplete }}" @endif
               @if ($maxlength ?? false) maxlength="{{ $maxlength }}" @endif
               @if ($accept ?? false) accept="{{ $accept }}" @endif
               @if ($describedBy) aria-describedby="{{ $describedBy }}" @endif
               @if ($error) aria-invalid="true" @endif>
    @endif
</div>
