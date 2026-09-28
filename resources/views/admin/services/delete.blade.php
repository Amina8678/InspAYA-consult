@extends('admin.layouts.app')

@section('title', 'Delete '.$service->title)

@section('content')
    <div class="page-head">
        <h1>Delete service</h1>
        <a href="{{ route('admin.services.index') }}">Back to services</a>
    </div>

    <div class="panel">
        <h2>{{ $service->title }}</h2>

        @include('admin.partials.errors', ['fields' => ['confirm' => 'field-confirm']])

        @if ($assigned->isNotEmpty())
            <div class="alert alert--warning">
                <p><strong>{{ $assigned->count() }} consultant {{ str('assignment')->plural($assigned->count()) }} will be removed with this service.</strong>
                    The consultants themselves are kept.</p>
                <ul>
                    @foreach ($assigned as $consultant)
                        <li>{{ $consultant->name }} <span>({{ $consultant->pivot->is_lead ? 'Lead' : 'Supporting' }})</span></li>
                    @endforeach
                </ul>
            </div>
        @else
            <p>No consultants are assigned to this service.</p>
        @endif

        @if ($service->is_active)
            <p>The page /services/{{ $service->slug }} will stop working, and links to it will lead to a "Page not found" page.</p>
        @endif
        <p>Deleting is permanent. To take it off the website temporarily, untick "Show this service on the website" instead.</p>

        <form method="POST" action="{{ route('admin.services.destroy', $service) }}">
            @csrf
            @method('DELETE')
            <div class="field {{ $errors->has('confirm') ? 'field--error' : '' }}">
                @if ($errors->has('confirm'))
                    <span class="field__error" id="field-confirm-error"><span class="visually-hidden">Error:</span> {{ $errors->first('confirm') }}</span>
                @endif
                <div class="checkbox">
                    <input id="field-confirm" type="checkbox" name="confirm" value="1" required
                           @if ($errors->has('confirm')) aria-invalid="true" aria-describedby="field-confirm-error" @endif>
                    <label for="field-confirm">I understand this permanently deletes "{{ $service->title }}"{{ $assigned->isNotEmpty() ? ' and its consultant assignments' : '' }}.</label>
                </div>
            </div>
            <div class="actions">
                <button type="submit" class="button button--danger">Delete service</button>
                <a href="{{ route('admin.services.edit', $service) }}">Cancel</a>
            </div>
        </form>
    </div>
@endsection
