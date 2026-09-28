@extends('admin.layouts.app')

@section('title', 'Delete '.$post->title)

@section('content')
    <div class="page-head">
        <h1>Delete post</h1>
        <a href="{{ route('admin.posts.index') }}">Back to posts</a>
    </div>

    <div class="panel">
        <h2>{{ $post->title }}</h2>

        @include('admin.partials.errors', ['fields' => ['confirm' => 'field-confirm']])

        <p>By {{ $post->author->name }}.
            @if ($post->category) Category: {{ $post->category->name }}. @endif
            @if ($post->tags->isNotEmpty()) Tags: {{ $post->tags->pluck('name')->join(', ') }}. @endif
        </p>
        @if ($post->status->value === 'published')
            <p>The page /insights/{{ $post->slug }} will stop working, and links to it will lead to a "Page not found" page.</p>
        @endif
        <p>Deleting is permanent. The category and tags themselves are kept.</p>

        <form method="POST" action="{{ route('admin.posts.destroy', $post) }}">
            @csrf
            @method('DELETE')
            <div class="field {{ $errors->has('confirm') ? 'field--error' : '' }}">
                @if ($errors->has('confirm'))
                    <span class="field__error" id="field-confirm-error"><span class="visually-hidden">Error:</span> {{ $errors->first('confirm') }}</span>
                @endif
                <div class="checkbox">
                    <input id="field-confirm" type="checkbox" name="confirm" value="1" required
                           @if ($errors->has('confirm')) aria-invalid="true" aria-describedby="field-confirm-error" @endif>
                    <label for="field-confirm">I understand this permanently deletes "{{ $post->title }}".</label>
                </div>
            </div>
            <div class="actions">
                <button type="submit" class="button button--danger">Delete post</button>
                <a href="{{ route('admin.posts.edit', $post) }}">Cancel</a>
            </div>
        </form>
    </div>
@endsection
