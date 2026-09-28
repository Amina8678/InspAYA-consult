@extends('admin.layouts.app')

@section('title', 'Delete enquiry from '.$submission->name)

@section('content')
    <div class="page-head">
        <h1>Delete enquiry</h1>
        <a href="{{ route('admin.enquiries.show', $submission) }}">Back to enquiry</a>
    </div>

    <div class="panel">
        <h2>{{ $submission->subject }}</h2>
        <p class="muted small">From {{ $submission->name }}.</p>

        @include('admin.partials.errors', ['fields' => ['confirm' => 'field-confirm']])

        @if ($submission->notes_count > 0)
            <div class="alert alert--warning">
                <p><strong>{{ $submission->notes_count }} internal {{ str('note')->plural($submission->notes_count) }} will be deleted with it.</strong></p>
            </div>
        @endif
        <p>Deleting is permanent, including the visitor's submitted details.</p>

        <form method="POST" action="{{ route('admin.enquiries.destroy', $submission) }}">
            @csrf
            @method('DELETE')
            <div class="field {{ $errors->has('confirm') ? 'field--error' : '' }}">
                @if ($errors->has('confirm'))
                    <span class="field__error" id="field-confirm-error"><span class="visually-hidden">Error:</span> {{ $errors->first('confirm') }}</span>
                @endif
                <div class="checkbox">
                    <input id="field-confirm" type="checkbox" name="confirm" value="1" required
                           @if ($errors->has('confirm')) aria-invalid="true" aria-describedby="field-confirm-error" @endif>
                    <label for="field-confirm">I understand this permanently deletes this enquiry.</label>
                </div>
            </div>
            <div class="actions">
                <button type="submit" class="button button--danger">Delete enquiry</button>
                <a href="{{ route('admin.enquiries.show', $submission) }}">Cancel</a>
            </div>
        </form>
    </div>
@endsection
