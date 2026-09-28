@extends('admin.layouts.app')

@section('title', 'Delete '.$tag->name)

@section('content')
    <div class="page-head">
        <h1>Delete tag</h1>
        <a href="{{ route('admin.tags.index') }}">Back to tags</a>
    </div>

    <div class="panel">
        <h2>{{ $tag->name }}</h2>

        @include('admin.partials.errors', ['fields' => ['confirm' => 'field-confirm']])

        @if ($postCount > 0)
            <div class="alert alert--warning">
                <p><strong>{{ $postCount }} {{ str('post')->plural($postCount) }} {{ $postCount === 1 ? 'has' : 'have' }} this tag.</strong>
                    The tag is removed from {{ $postCount === 1 ? 'it' : 'them' }}; the posts themselves are kept.</p>
            </div>
        @else
            <p>No posts have this tag.</p>
        @endif
        <p>Deleting is permanent.</p>

        <form method="POST" action="{{ route('admin.tags.destroy', $tag) }}">
            @csrf
            @method('DELETE')
            <div class="field {{ $errors->has('confirm') ? 'field--error' : '' }}">
                @if ($errors->has('confirm'))
                    <span class="field__error" id="field-confirm-error"><span class="visually-hidden">Error:</span> {{ $errors->first('confirm') }}</span>
                @endif
                <div class="checkbox">
                    <input id="field-confirm" type="checkbox" name="confirm" value="1" required
                           @if ($errors->has('confirm')) aria-invalid="true" aria-describedby="field-confirm-error" @endif>
                    <label for="field-confirm">I understand this permanently deletes "{{ $tag->name }}".</label>
                </div>
            </div>
            <div class="actions">
                <button type="submit" class="button button--danger">Delete tag</button>
                <a href="{{ route('admin.tags.edit', $tag) }}">Cancel</a>
            </div>
        </form>
    </div>
@endsection
