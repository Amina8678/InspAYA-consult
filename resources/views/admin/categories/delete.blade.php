@extends('admin.layouts.app')

@section('title', 'Delete '.$category->name)

@section('content')
    <div class="page-head">
        <h1>Delete category</h1>
        <a href="{{ route('admin.categories.index') }}">Back to categories</a>
    </div>

    <div class="panel">
        <h2>{{ $category->name }}</h2>

        @include('admin.partials.errors', ['fields' => ['confirm' => 'field-confirm']])

        @if ($postCount > 0)
            <div class="alert alert--warning">
                <p><strong>{{ $postCount }} {{ str('post')->plural($postCount) }} {{ $postCount === 1 ? 'is' : 'are' }} in this category.</strong>
                    {{ $postCount === 1 ? 'It becomes' : 'They become' }} uncategorised; the posts themselves are kept.</p>
            </div>
        @else
            <p>No posts are in this category.</p>
        @endif
        <p>Deleting is permanent.</p>

        <form method="POST" action="{{ route('admin.categories.destroy', $category) }}">
            @csrf
            @method('DELETE')
            <div class="field {{ $errors->has('confirm') ? 'field--error' : '' }}">
                @if ($errors->has('confirm'))
                    <span class="field__error" id="field-confirm-error"><span class="visually-hidden">Error:</span> {{ $errors->first('confirm') }}</span>
                @endif
                <div class="checkbox">
                    <input id="field-confirm" type="checkbox" name="confirm" value="1" required
                           @if ($errors->has('confirm')) aria-invalid="true" aria-describedby="field-confirm-error" @endif>
                    <label for="field-confirm">I understand this permanently deletes "{{ $category->name }}".</label>
                </div>
            </div>
            <div class="actions">
                <button type="submit" class="button button--danger">Delete category</button>
                <a href="{{ route('admin.categories.edit', $category) }}">Cancel</a>
            </div>
        </form>
    </div>
@endsection
