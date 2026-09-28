@extends('admin.layouts.app')

@section('title', 'Delete '.$value->title)

@section('content')
    <div class="page-head">
        <h1>Delete core value</h1>
        <a href="{{ route('admin.core-values.index') }}">Back to core values</a>
    </div>

    <div class="panel">
        <h2>{{ $value->title }}</h2>

        @include('admin.partials.errors', ['fields' => ['confirm' => 'field-confirm']])

        <p>Nothing else depends on a core value, so deleting it only removes it from the Core values page and the home page.
            @if ($value->icon)
                Its icon stays in the media library.
            @endif
        </p>
        <p>Deleting is permanent. To take it off the website temporarily, untick "Show this value on the website" instead.</p>

        <form method="POST" action="{{ route('admin.core-values.destroy', $value) }}">
            @csrf
            @method('DELETE')
            <div class="field {{ $errors->has('confirm') ? 'field--error' : '' }}">
                @if ($errors->has('confirm'))
                    <span class="field__error" id="field-confirm-error"><span class="visually-hidden">Error:</span> {{ $errors->first('confirm') }}</span>
                @endif
                <div class="checkbox">
                    <input id="field-confirm" type="checkbox" name="confirm" value="1" required
                           @if ($errors->has('confirm')) aria-invalid="true" aria-describedby="field-confirm-error" @endif>
                    <label for="field-confirm">I understand this permanently deletes "{{ $value->title }}".</label>
                </div>
            </div>
            <div class="actions">
                <button type="submit" class="button button--danger">Delete core value</button>
                <a href="{{ route('admin.core-values.edit', $value) }}">Cancel</a>
            </div>
        </form>
    </div>
@endsection
