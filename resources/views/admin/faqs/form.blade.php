@extends('admin.layouts.app')

@php
    $editing = $faq->exists;
    $canChangeStatus = auth()->user()->can('changeStatus', $faq);
@endphp

@section('title', $editing ? 'Edit '.$faq->question : 'Add an FAQ')

@section('content')
    <div class="page-head">
        <h1>{{ $editing ? 'Edit FAQ' : 'Add an FAQ' }}</h1>
        <a href="{{ route('admin.faqs.index') }}">Back to FAQs</a>
    </div>

    <div class="panel">
        @include('admin.partials.errors', ['fields' => [
            'question' => 'field-question',
            'answer' => 'field-answer',
            'is_active' => 'field-is_active',
        ]])

        <form class="form" method="POST"
              action="{{ $editing ? route('admin.faqs.update', $faq) : route('admin.faqs.store') }}">
            @csrf
            @if ($editing)
                @method('PUT')
            @endif

            @include('admin.partials.field', ['name' => 'question', 'label' => 'Question', 'type' => 'text', 'required' => true,
                'value' => old('question', $faq->question), 'maxlength' => 500])
            @include('admin.partials.field', ['name' => 'answer', 'label' => 'Answer', 'type' => 'textarea', 'required' => true,
                'value' => old('answer', $faq->answer), 'maxlength' => 5000])

            @if ($canChangeStatus)
                <div class="field checkbox">
                    <input type="hidden" name="is_active" value="0">
                    <input id="field-is_active" type="checkbox" name="is_active" value="1"
                           @checked(old('is_active', $faq->is_active ?? true))>
                    <label for="field-is_active">Show this FAQ on the website</label>
                </div>
            @else
                <p class="muted small">Status: {{ $faq->is_active ? 'shown on the website' : 'hidden' }}. Only administrators can change this.</p>
            @endif

            <div class="actions">
                <button type="submit" class="button">{{ $editing ? 'Save changes' : 'Create FAQ' }}</button>
                @if ($editing)
                    @can('delete', $faq)
                        <a href="{{ route('admin.faqs.delete', $faq) }}">Delete this FAQ</a>
                    @endcan
                @endif
            </div>
        </form>
    </div>
@endsection
