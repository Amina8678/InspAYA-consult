@extends('admin.layouts.app')

@php
    $editing = $testimonial->exists;
    $canChangeStatus = auth()->user()->can('changeStatus', $testimonial);
@endphp

@section('title', $editing ? 'Edit testimonial from '.$testimonial->author_name : 'Add a testimonial')

@section('content')
    <div class="page-head">
        <h1>{{ $editing ? 'Edit testimonial' : 'Add a testimonial' }}</h1>
        <a href="{{ route('admin.testimonials.index') }}">Back to testimonials</a>
    </div>

    <div class="panel">
        @include('admin.partials.errors', ['fields' => [
            'quote' => 'field-quote',
            'author_name' => 'field-author_name',
            'author_title' => 'field-author_title',
            'organization' => 'field-organization',
            'photo_id' => 'field-photo_id',
            'is_active' => 'field-is_active',
        ]])

        <form class="form" method="POST"
              action="{{ $editing ? route('admin.testimonials.update', $testimonial) : route('admin.testimonials.store') }}">
            @csrf
            @if ($editing)
                @method('PUT')
            @endif

            @include('admin.partials.field', ['name' => 'quote', 'label' => 'Quote', 'type' => 'textarea', 'required' => true,
                'value' => old('quote', $testimonial->quote), 'maxlength' => 2000])
            @include('admin.partials.field', ['name' => 'author_name', 'label' => 'Author name', 'type' => 'text', 'required' => true,
                'value' => old('author_name', $testimonial->author_name), 'maxlength' => 255])
            @include('admin.partials.field', ['name' => 'author_title', 'label' => 'Author title', 'type' => 'text',
                'value' => old('author_title', $testimonial->author_title), 'maxlength' => 255])
            @include('admin.partials.field', ['name' => 'organization', 'label' => 'Organization', 'type' => 'text',
                'value' => old('organization', $testimonial->organization), 'maxlength' => 255])
            @include('admin.partials.media-picker', ['name' => 'photo_id', 'label' => 'Photo', 'selected' => $testimonial->photo_id,
                'options' => $mediaOptions, 'hint' => 'A square headshot works best.'])

            @if ($canChangeStatus)
                <div class="field checkbox">
                    <input type="hidden" name="is_active" value="0">
                    <input id="field-is_active" type="checkbox" name="is_active" value="1"
                           @checked(old('is_active', $testimonial->is_active ?? true))>
                    <label for="field-is_active">Show this testimonial on the website</label>
                </div>
            @else
                <p class="muted small">Status: {{ $testimonial->is_active ? 'shown on the website' : 'hidden' }}. Only administrators can change this.</p>
            @endif

            <div class="actions">
                <button type="submit" class="button">{{ $editing ? 'Save changes' : 'Create testimonial' }}</button>
                @if ($editing)
                    @can('delete', $testimonial)
                        <a href="{{ route('admin.testimonials.delete', $testimonial) }}">Delete this testimonial</a>
                    @endcan
                @endif
            </div>
        </form>
    </div>
@endsection
