@extends('admin.layouts.app')

@php
    $editing = $value->exists;
    $canChangeStatus = auth()->user()->can('changeStatus', $value);
@endphp

@section('title', $editing ? 'Edit '.$value->title : 'Add a core value')

@section('content')
    <div class="page-head">
        <h1>{{ $editing ? 'Edit core value' : 'Add a core value' }}</h1>
        <a href="{{ route('admin.core-values.index') }}">Back to core values</a>
    </div>

    <div class="panel">
        @include('admin.partials.errors', ['fields' => [
            'title' => 'field-title',
            'slug' => 'field-slug',
            'description' => 'field-description',
            'icon_id' => 'field-icon_id',
            'is_active' => 'field-is_active',
        ]])

        <form class="form" method="POST"
              action="{{ $editing ? route('admin.core-values.update', $value) : route('admin.core-values.store') }}">
            @csrf
            @if ($editing)
                @method('PUT')
            @endif

            @include('admin.partials.field', ['name' => 'title', 'label' => 'Title', 'type' => 'text', 'required' => true,
                'value' => old('title', $value->title), 'maxlength' => 255])
            @include('admin.partials.field', ['name' => 'slug', 'label' => 'Slug', 'type' => 'text',
                'value' => old('slug', $value->slug), 'maxlength' => 191,
                'hint' => 'Lowercase words joined by hyphens, e.g. "client-first". Leave empty to create it from the title.'])
            @include('admin.partials.field', ['name' => 'description', 'label' => 'Description', 'type' => 'textarea',
                'value' => old('description', $value->description), 'maxlength' => 2000])
            @include('admin.partials.media-picker', ['name' => 'icon_id', 'label' => 'Icon', 'selected' => $value->icon_id,
                'options' => $mediaOptions, 'hint' => 'A small square image works best.'])

            @if ($canChangeStatus)
                <div class="field checkbox">
                    <input type="hidden" name="is_active" value="0">
                    <input id="field-is_active" type="checkbox" name="is_active" value="1"
                           @checked(old('is_active', $value->is_active ?? true))>
                    <label for="field-is_active">Show this value on the website</label>
                </div>
            @else
                <p class="muted small">Status: {{ $value->is_active ? 'shown on the website' : 'hidden' }}. Only administrators can change this.</p>
            @endif

            <div class="actions">
                <button type="submit" class="button">{{ $editing ? 'Save changes' : 'Create core value' }}</button>
                @if ($editing)
                    @can('delete', $value)
                        <a href="{{ route('admin.core-values.delete', $value) }}">Delete this core value</a>
                    @endcan
                @endif
            </div>
        </form>
    </div>
@endsection
