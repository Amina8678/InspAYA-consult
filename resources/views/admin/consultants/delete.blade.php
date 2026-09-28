@extends('admin.layouts.app')

@section('title', 'Delete '.$consultant->name)

@section('content')
    <div class="page-head">
        <h1>Delete consultant</h1>
        <a href="{{ route('admin.consultants.index') }}">Back to consultants</a>
    </div>

    <div class="panel">
        <h2>{{ $consultant->name }}</h2>

        @include('admin.partials.errors', ['fields' => ['confirm' => 'field-confirm']])

        @if ($assigned->isNotEmpty())
            <div class="alert alert--warning">
                <p><strong>{{ $assigned->count() }} service {{ str('assignment')->plural($assigned->count()) }} will be removed with this consultant.</strong>
                    The services themselves are kept.</p>
                <ul>
                    @foreach ($assigned as $service)
                        <li>{{ $service->title }} <span>({{ $service->pivot->is_lead ? 'Lead' : 'Supporting' }})</span></li>
                    @endforeach
                </ul>
            </div>
        @else
            <p>This consultant isn't assigned to any service.</p>
        @endif

        @if ($consultant->photo)
            <p>Their photo stays in the media library.</p>
        @endif
        <p>Deleting is permanent. To take them off the website temporarily, untick "Show this consultant on the website" instead.</p>

        <form method="POST" action="{{ route('admin.consultants.destroy', $consultant) }}">
            @csrf
            @method('DELETE')
            <div class="field {{ $errors->has('confirm') ? 'field--error' : '' }}">
                @if ($errors->has('confirm'))
                    <span class="field__error" id="field-confirm-error"><span class="visually-hidden">Error:</span> {{ $errors->first('confirm') }}</span>
                @endif
                <div class="checkbox">
                    <input id="field-confirm" type="checkbox" name="confirm" value="1" required
                           @if ($errors->has('confirm')) aria-invalid="true" aria-describedby="field-confirm-error" @endif>
                    <label for="field-confirm">I understand this permanently deletes "{{ $consultant->name }}"{{ $assigned->isNotEmpty() ? ' and their service assignments' : '' }}.</label>
                </div>
            </div>
            <div class="actions">
                <button type="submit" class="button button--danger">Delete consultant</button>
                <a href="{{ route('admin.consultants.edit', $consultant) }}">Cancel</a>
            </div>
        </form>
    </div>
@endsection
