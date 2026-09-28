{{--
    One section in the page editor. $row (form values), $i (index), $count,
    $mediaOptions. Only the fields of this section's type are rendered.
    Move/remove are ordinary submit buttons (no JavaScript needed).
--}}
@php
    $type = $row['type'] ?? 'text';
    $definition = App\Support\PageSections::TYPES[$type] ?? null;
    $keys = App\Support\PageSections::formKeys($type);
    $number = $i + 1;
    $label = ($definition['label'] ?? 'Unknown').' section';
    $field = fn (string $key) => [
        'name' => "sections[$i][$key]",
        'errorKey' => "sections.$i.$key",
        'id' => "section-$i-$key",
        'value' => $row[$key] ?? null,
    ];
@endphp
<fieldset class="panel section-editor" id="section-{{ $i }}">
    <legend><h3>Section {{ $number }}: {{ $definition['label'] ?? 'Unknown type' }}</h3></legend>
    <input type="hidden" name="sections[{{ $i }}][type]" value="{{ $type }}">

    @if ($errors->has("sections.$i.type"))
        <p class="field__error" id="section-{{ $i }}-type"><span class="visually-hidden">Error:</span> {{ $errors->first("sections.$i.type") }}</p>
    @endif
    @foreach ($errors->get("sections.$i.*") as $key => $messages)
        @continue(in_array(\Illuminate\Support\Str::afterLast($key, '.'), $keys, true))
        <p class="field__error"><span class="visually-hidden">Error:</span> {{ $messages[0] }}</p>
    @endforeach

    @if ($definition)
        <p class="muted small">{{ $definition['hint'] }}</p>

        @include('admin.partials.field', $field('heading') + ['label' => 'Heading', 'type' => 'text', 'maxlength' => App\Support\PageSections::HEADING_MAX])
        @include('admin.partials.field', $field('body') + ['label' => 'Text', 'type' => 'textarea', 'maxlength' => $definition['body_max'],
            'hint' => 'Plain text, up to '.number_format($definition['body_max']).' characters. Line breaks are kept.'])

        @if (in_array('background_media_id', $keys, true))
            @include('admin.partials.media-picker', $field('background_media_id') + [
                'label' => $type === 'hero' ? 'Background image' : 'Image',
                'selected' => $row['background_media_id'] ?? null,
                'options' => $mediaOptions,
                'hint' => $type === 'hero' ? 'Shown behind the heading under a dark overlay.' : 'Shown beside the text.',
            ])
        @endif

        @foreach (['primary_cta' => 'First button', 'secondary_cta' => 'Second button'] as $button => $buttonLabel)
            @continue(! in_array($button.'_url', $keys, true))
            <fieldset class="field">
                <legend>{{ $buttonLabel }} <span class="field__optional">(optional)</span></legend>
                @include('admin.partials.field', $field($button.'_label') + ['label' => 'Label', 'type' => 'text',
                    'maxlength' => App\Support\PageSections::BUTTON_LABEL_MAX])
                @include('admin.partials.field', $field($button.'_url') + ['label' => 'Link', 'type' => 'text',
                    'maxlength' => App\Support\PageSections::URL_MAX,
                    'hint' => 'A page on this site (/contact), a web address (https://…), mailto: or tel:.'])
            </fieldset>
        @endforeach
    @endif

    <div class="actions">
        @if ($i > 0)
            <button type="submit" class="button button--secondary" name="section_action" value="up:{{ $i }}">Move up<span class="visually-hidden"> section {{ $number }}</span></button>
        @endif
        @if ($i < $count - 1)
            <button type="submit" class="button button--secondary" name="section_action" value="down:{{ $i }}">Move down<span class="visually-hidden"> section {{ $number }}</span></button>
        @endif
        <button type="submit" class="button button--secondary" name="section_action" value="remove:{{ $i }}">Remove<span class="visually-hidden"> section {{ $number }} ({{ $label }})</span></button>
    </div>
</fieldset>
