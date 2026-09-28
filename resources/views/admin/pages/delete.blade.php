@extends('admin.layouts.app')

@section('title', 'Delete '.$page->title)

@php($publicUrl = App\Support\CorePages::url($page->slug))

@section('content')
    <div class="page-head">
        <h1>Delete page</h1>
        <a href="{{ route('admin.pages.index') }}">Back to pages</a>
    </div>

    <div class="panel">
        <h2>{{ $page->title }}</h2>

        @include('admin.partials.errors', ['fields' => ['confirm' => 'field-confirm']])

        @if ($publicUrl)
            <p>This page provides the content for {{ parse_url($publicUrl, PHP_URL_PATH) }}. That address keeps working
                after deletion, but shows only its built-in content.</p>
        @endif
        <p>Nothing else depends on this page. Images used in its sections stay in the media library.</p>
        <p>Deleting is permanent. To take it off the website temporarily, set it back to Draft instead.</p>

        <form method="POST" action="{{ route('admin.pages.destroy', $page) }}">
            @csrf
            @method('DELETE')
            <div class="field {{ $errors->has('confirm') ? 'field--error' : '' }}">
                @if ($errors->has('confirm'))
                    <span class="field__error" id="field-confirm-error"><span class="visually-hidden">Error:</span> {{ $errors->first('confirm') }}</span>
                @endif
                <div class="checkbox">
                    <input id="field-confirm" type="checkbox" name="confirm" value="1" required
                           @if ($errors->has('confirm')) aria-invalid="true" aria-describedby="field-confirm-error" @endif>
                    <label for="field-confirm">I understand this permanently deletes "{{ $page->title }}".</label>
                </div>
            </div>
            <div class="actions">
                <button type="submit" class="button button--danger">Delete page</button>
                <a href="{{ route('admin.pages.edit', $page) }}">Cancel</a>
            </div>
        </form>
    </div>
@endsection
