@extends('admin.layouts.app')

@php($editing = $category->exists)

@section('title', $editing ? 'Edit '.$category->name : 'Add a category')

@section('content')
    <div class="page-head">
        <h1>{{ $editing ? 'Edit category' : 'Add a category' }}</h1>
        <a href="{{ route('admin.categories.index') }}">Back to categories</a>
    </div>

    @include('admin.partials.errors', ['fields' => ['name' => 'field-name', 'slug' => 'field-slug', 'description' => 'field-description']])

    <form method="POST" action="{{ $editing ? route('admin.categories.update', $category) : route('admin.categories.store') }}">
        @csrf
        @if ($editing)
            @method('PUT')
        @endif

        <fieldset class="panel form">
            <legend><h2>Category</h2></legend>
            @include('admin.partials.field', ['name' => 'name', 'label' => 'Name', 'type' => 'text', 'required' => true,
                'value' => old('name', $category->name), 'maxlength' => 100])
            @include('admin.partials.field', ['name' => 'slug', 'label' => 'Slug', 'type' => 'text',
                'value' => old('slug', $category->slug), 'maxlength' => 191,
                'hint' => $editing ? 'Lowercase words joined by hyphens. Leave empty to keep the current slug.'
                                   : 'Lowercase words joined by hyphens. Leave empty to create it from the name.'])
            @include('admin.partials.field', ['name' => 'description', 'label' => 'Description', 'type' => 'textarea',
                'value' => old('description', $category->description), 'maxlength' => 500])
        </fieldset>

        <div class="actions">
            <button type="submit" class="button">{{ $editing ? 'Save changes' : 'Create category' }}</button>
            @if ($editing)
                @can('delete', $category)
                    <a href="{{ route('admin.categories.delete', $category) }}">Delete this category</a>
                @endcan
            @endif
        </div>
    </form>
@endsection
