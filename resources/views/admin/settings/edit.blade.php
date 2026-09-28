@extends('admin.layouts.app')

@section('title', 'Site settings')

@php
    $fields = App\Http\Requests\Admin\SiteSettingsRequest::fields();
    $name = fn (string $key) => App\Http\Requests\Admin\SiteSettingsRequest::inputName($key);
@endphp

@section('content')
    <div class="page-head">
        <h1>Site settings</h1>
    </div>

    @if ($missing)
        <div class="alert alert--warning">
            <p>These settings are not set up in this database, so they can't be edited here:
                {{ implode(', ', $missing) }}. Run the site settings seeder to add them.</p>
        </div>
    @endif

    @include('admin.partials.errors', ['fields' => collect($fields)->keys()
        ->mapWithKeys(fn ($key) => [$name($key) => 'field-'.$name($key)])->all()])

    <form method="POST" action="{{ route('admin.settings.update') }}">
        @csrf
        @method('PUT')

        @foreach ($groups as $definition)
            <fieldset class="panel">
                <legend><h2>{{ $definition['label'] }}</h2></legend>

                @foreach ($definition['fields'] as $key => $field)
                    @continue(! $settings->has($key))

                    @if ($field['type'] === 'media')
                        @include('admin.partials.media-picker', [
                            'name' => $name($key),
                            'label' => $field['label'],
                            'selected' => $settings[$key]->media_id,
                            'options' => $mediaOptions,
                            'hint' => $field['hint'] ?? null,
                        ])
                    @else
                        @include('admin.partials.field', [
                            'name' => $name($key),
                            'label' => $field['label'],
                            'type' => match ($field['type']) {
                                'text', 'description' => 'textarea',
                                'email' => 'email',
                                'url' => 'url',
                                default => 'text',
                            },
                            'value' => old($name($key), $settings[$key]->value),
                            'required' => $field['required'] ?? false,
                            'hint' => $field['hint'] ?? ($field['type'] === 'url' ? 'A full address starting with https://.' : null),
                            'maxlength' => match ($field['type']) {
                                'text' => 1000, 'description' => 300, 'phone' => 50, 'tracking' => 40, 'url' => 500, default => 255,
                            },
                        ])
                    @endif
                @endforeach
            </fieldset>
        @endforeach

        <button type="submit" class="button">Save settings</button>
    </form>
@endsection
