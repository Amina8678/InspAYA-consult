{{--
    Pick an image from the media library: a plain <select> (fully keyboard
    and screen-reader accessible, no JS needed) with a preview of the current
    choice. admin.js only refreshes the preview when the choice changes.
    $name, $label, $selected (media id or null), $options (MediaPicker::options),
    optional: $hint, $id, $required, $errorKey (dot-notation key for array
    fields, e.g. sections.2.background_media_id).
--}}
@php
    $id = $id ?? 'field-'.$name;
    $error = $errors->first($errorKey ?? $name);
    $selected = old($errorKey ?? $name, $selected);
    $current = $options[$selected] ?? null;
    $hintId = $id.'-hint';
    $describedBy = trim($hintId.' '.($error ? $id.'-error' : ''));
@endphp
<div class="field media-picker {{ $error ? 'field--error' : '' }}">
    <label for="{{ $id }}">{{ $label }}@if (! ($required ?? false)) <span class="field__optional">(optional)</span>@endif</label>
    <span class="field__hint" id="{{ $hintId }}">
        {{ $hint ?? 'Choose an image from the media library.' }}
        <a href="{{ route('admin.media.index') }}" target="_blank" rel="noopener">Upload images in the media library<span class="visually-hidden"> (opens in a new tab)</span></a>, then reload this page.
    </span>
    @if ($error)
        <span class="field__error" id="{{ $id }}-error"><span class="visually-hidden">Error:</span> {{ $error }}</span>
    @endif
    <select id="{{ $id }}" name="{{ $name }}" data-media-preview="{{ $id }}-preview"
            aria-describedby="{{ $describedBy }}" @if ($error) aria-invalid="true" @endif>
        <option value="">No image</option>
        @foreach ($options as $mediaId => $option)
            <option value="{{ $mediaId }}" data-url="{{ $option['url'] }}" data-alt="{{ $option['alt'] }}" @selected((string) $selected === (string) $mediaId)>{{ $option['label'] }}</option>
        @endforeach
    </select>
    <div class="media-picker__preview" id="{{ $id }}-preview" aria-live="polite">
        @if ($current)
            <img src="{{ $current['url'] }}" alt="Preview: {{ $current['alt'] ?: 'selected image' }}" width="160" height="90" loading="lazy">
        @else
            <p class="muted small">No image selected.</p>
        @endif
    </div>
</div>
