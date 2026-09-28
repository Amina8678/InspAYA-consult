@extends('admin.layouts.app')

@section('title', 'Edit note')

@section('content')
    <div class="page-head">
        <h1>Edit note</h1>
        <a href="{{ route('admin.enquiries.show', $submission) }}">Back to enquiry</a>
    </div>

    @include('admin.partials.errors', ['fields' => ['body' => 'field-body']])

    <form method="POST" action="{{ route('admin.enquiries.notes.update', [$submission, $note]) }}">
        @csrf
        @method('PUT')

        <fieldset class="panel form">
            <legend><h2>Note on the enquiry from {{ $submission->name }}</h2></legend>
            @include('admin.partials.field', ['name' => 'body', 'label' => 'Note', 'type' => 'textarea', 'required' => true,
                'value' => old('body', $note->body), 'maxlength' => 5000])
        </fieldset>

        <div class="actions">
            <button type="submit" class="button">Save changes</button>
            @can('delete', $note)
                <a href="{{ route('admin.enquiries.notes.delete', [$submission, $note]) }}">Delete this note</a>
            @endcan
        </div>
    </form>
@endsection
