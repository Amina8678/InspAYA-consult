@extends('admin.layouts.app')

@section('title', 'Delete note')

@section('content')
    <div class="page-head">
        <h1>Delete note</h1>
        <a href="{{ route('admin.enquiries.show', $submission) }}">Back to enquiry</a>
    </div>

    <div class="panel">
        <p class="pre-line">{{ $note->body }}</p>

        @include('admin.partials.errors', ['fields' => ['confirm' => 'field-confirm']])

        <p>Deleting is permanent.</p>

        <form method="POST" action="{{ route('admin.enquiries.notes.destroy', [$submission, $note]) }}">
            @csrf
            @method('DELETE')
            <div class="field {{ $errors->has('confirm') ? 'field--error' : '' }}">
                @if ($errors->has('confirm'))
                    <span class="field__error" id="field-confirm-error"><span class="visually-hidden">Error:</span> {{ $errors->first('confirm') }}</span>
                @endif
                <div class="checkbox">
                    <input id="field-confirm" type="checkbox" name="confirm" value="1" required
                           @if ($errors->has('confirm')) aria-invalid="true" aria-describedby="field-confirm-error" @endif>
                    <label for="field-confirm">I understand this permanently deletes this note.</label>
                </div>
            </div>
            <div class="actions">
                <button type="submit" class="button button--danger">Delete note</button>
                <a href="{{ route('admin.enquiries.show', $submission) }}">Cancel</a>
            </div>
        </form>
    </div>
@endsection
