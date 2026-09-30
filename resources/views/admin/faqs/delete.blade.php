@extends('admin.layouts.app')

@section('title', 'Delete '.$faq->question)

@section('content')
    <div class="page-head">
        <h1>Delete FAQ</h1>
        <a href="{{ route('admin.faqs.index') }}">Back to FAQs</a>
    </div>

    <div class="panel">
        <h2>{{ $faq->question }}</h2>

        @include('admin.partials.errors', ['fields' => ['confirm' => 'field-confirm']])

        <p>Nothing else depends on an FAQ, so deleting it only removes it from the website.</p>
        <p>Deleting is permanent. To take it off the website temporarily, untick "Show this FAQ on the website" instead.</p>

        <form method="POST" action="{{ route('admin.faqs.destroy', $faq) }}">
            @csrf
            @method('DELETE')
            <div class="field {{ $errors->has('confirm') ? 'field--error' : '' }}">
                @if ($errors->has('confirm'))
                    <span class="field__error" id="field-confirm-error"><span class="visually-hidden">Error:</span> {{ $errors->first('confirm') }}</span>
                @endif
                <div class="checkbox">
                    <input id="field-confirm" type="checkbox" name="confirm" value="1" required
                           @if ($errors->has('confirm')) aria-invalid="true" aria-describedby="field-confirm-error" @endif>
                    <label for="field-confirm">I understand this permanently deletes this FAQ.</label>
                </div>
            </div>
            <div class="actions">
                <button type="submit" class="button button--danger">Delete FAQ</button>
                <a href="{{ route('admin.faqs.edit', $faq) }}">Cancel</a>
            </div>
        </form>
    </div>
@endsection
