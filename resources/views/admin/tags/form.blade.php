@extends('admin.layouts.app')

@php($editing = $tag->exists)

@section('title', $editing ? 'Edit '.$tag->name : 'Add a tag')

@section('content')
    <div class="page-head">
        <h1>{{ $editing ? 'Edit tag' : 'Add a tag' }}</h1>
        <a href="{{ route('admin.tags.index') }}">Back to tags</a>
    </div>

    @include('admin.partials.errors', ['fields' => ['name' => 'field-name', 'slug' => 'field-slug']])

    <form method="POST" action="{{ $editing ? route('admin.tags.update', $tag) : route('admin.tags.store') }}">
        @csrf
        @if ($editing)
            @method('PUT')
        @endif

        <fieldset class="panel form">
            <legend><h2>Tag</h2></legend>
            @include('admin.partials.field', ['name' => 'name', 'label' => 'Name', 'type' => 'text', 'required' => true,
                'value' => old('name', $tag->name), 'maxlength' => 100])
            @include('admin.partials.field', ['name' => 'slug', 'label' => 'Slug', 'type' => 'text',
                'value' => old('slug', $tag->slug), 'maxlength' => 191,
                'hint' => $editing ? 'Lowercase words joined by hyphens. Leave empty to keep the current slug.'
                                   : 'Lowercase words joined by hyphens. Leave empty to create it from the name.'])
        </fieldset>

        <div class="actions">
            <button type="submit" class="button">{{ $editing ? 'Save changes' : 'Create tag' }}</button>
            @if ($editing)
                @can('delete', $tag)
                    <a href="{{ route('admin.tags.delete', $tag) }}">Delete this tag</a>
                @endcan
            @endif
        </div>
    </form>
@endsection
