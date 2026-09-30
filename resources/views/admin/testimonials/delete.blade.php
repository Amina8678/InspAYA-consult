@extends('admin.layouts.app')

@section('title', 'Delete testimonial from '.$testimonial->author_name)

@section('content')
    <div class="page-head">
        <h1>Delete testimonial</h1>
        <a href="{{ route('admin.testimonials.index') }}">Back to testimonials</a>
    </div>

    <div class="panel">
        <h2>Testimonial from {{ $testimonial->author_name }}</h2>

        @include('admin.partials.errors', ['fields' => ['confirm' => 'field-confirm']])

        <p>Nothing else depends on a testimonial, so deleting it only removes it from the website.
            @if ($testimonial->photo)
                Its photo stays in the media library.
            @endif
        </p>
        <p>Deleting is permanent. To take it off the website temporarily, untick "Show this testimonial on the website" instead.</p>

        <form method="POST" action="{{ route('admin.testimonials.destroy', $testimonial) }}">
            @csrf
            @method('DELETE')
            <div class="field {{ $errors->has('confirm') ? 'field--error' : '' }}">
                @if ($errors->has('confirm'))
                    <span class="field__error" id="field-confirm-error"><span class="visually-hidden">Error:</span> {{ $errors->first('confirm') }}</span>
                @endif
                <div class="checkbox">
                    <input id="field-confirm" type="checkbox" name="confirm" value="1" required
                           @if ($errors->has('confirm')) aria-invalid="true" aria-describedby="field-confirm-error" @endif>
                    <label for="field-confirm">I understand this permanently deletes this testimonial.</label>
                </div>
            </div>
            <div class="actions">
                <button type="submit" class="button button--danger">Delete testimonial</button>
                <a href="{{ route('admin.testimonials.edit', $testimonial) }}">Cancel</a>
            </div>
        </form>
    </div>
@endsection
