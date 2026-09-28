@extends('admin.layouts.app')

@section('title', 'Delete '.$media->file_name)

@section('content')
    <div class="page-head">
        <h1>Delete file</h1>
        <a href="{{ route('admin.media.index') }}">Back to media library</a>
    </div>

    <div class="panel">
        <h2>{{ $media->file_name }}</h2>

        @include('admin.partials.errors', ['fields' => ['confirm' => 'field-confirm']])

        @if ($usages)
            <div class="alert alert--warning">
                <p><strong>This file is used in {{ count($usages) }} {{ str('place')->plural(count($usages)) }}.</strong>
                    Deleting it removes the image from all of them:</p>
                <ul>
                    @foreach ($usages as $usage)
                        <li>{{ str($usage['type'])->headline() }}: {{ $usage['label'] }} <span>({{ $usage['field'] }})</span></li>
                    @endforeach
                </ul>
            </div>
        @else
            <p>This file isn't used anywhere.</p>
        @endif

        <p>Deleting is permanent: the file is removed from the server and can't be restored.</p>

        <form method="POST" action="{{ route('admin.media.destroy', $media) }}">
            @csrf
            @method('DELETE')
            <div class="field {{ $errors->has('confirm') ? 'field--error' : '' }}">
                @if ($errors->has('confirm'))
                    <span class="field__error" id="field-confirm-error"><span class="visually-hidden">Error:</span> {{ $errors->first('confirm') }}</span>
                @endif
                <div class="checkbox">
                    <input id="field-confirm" type="checkbox" name="confirm" value="1" required
                           @if ($errors->has('confirm')) aria-invalid="true" aria-describedby="field-confirm-error" @endif>
                    <label for="field-confirm">
                        I understand this permanently deletes the file{{ $usages ? ' and removes it from the places listed above' : '' }}.
                    </label>
                </div>
            </div>
            <div class="actions">
                <button type="submit" class="button button--danger">Delete file</button>
                <a href="{{ route('admin.media.edit', $media) }}">Cancel</a>
            </div>
        </form>
    </div>
@endsection
