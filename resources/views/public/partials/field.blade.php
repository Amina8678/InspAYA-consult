{{--
    One labelled form field with its error message.
    $name, $label, $type ('text', 'email', 'tel' or 'textarea'),
    $required (bool), $autocomplete, $maxlength, $hint (optional).
--}}
@php
    $id = 'field-'.$name;
    $hasError = $errors->has($name);
    $describedBy = trim(($hint ?? false ? $id.'-hint ' : '').($hasError ? $id.'-error' : ''));
@endphp
<div class="field {{ $hasError ? 'field--error' : '' }}">
    <label for="{{ $id }}">
        {{ $label }}
        <span class="field__hint">{{ $required ? '(required)' : '(optional)' }}</span>
    </label>
    @if ($hint ?? false)
        <span class="field__hint" id="{{ $id }}-hint">{{ $hint }}</span>
    @endif
    @if ($hasError)
        <span class="field__error" id="{{ $id }}-error">
            <span class="visually-hidden">Error:</span> {{ $errors->first($name) }}
        </span>
    @endif
    @if ($type === 'textarea')
        <textarea id="{{ $id }}" name="{{ $name }}" rows="8"
                  @if ($required) required @endif
                  @if ($maxlength ?? false) maxlength="{{ $maxlength }}" @endif
                  @if ($describedBy) aria-describedby="{{ $describedBy }}" @endif
                  @if ($hasError) aria-invalid="true" @endif>{{ old($name) }}</textarea>
    @else
        <input id="{{ $id }}" name="{{ $name }}" type="{{ $type }}" value="{{ old($name) }}"
               @if ($required) required @endif
               @if ($autocomplete ?? false) autocomplete="{{ $autocomplete }}" @endif
               @if ($maxlength ?? false) maxlength="{{ $maxlength }}" @endif
               @if ($describedBy) aria-describedby="{{ $describedBy }}" @endif
               @if ($hasError) aria-invalid="true" @endif>
    @endif
</div>
