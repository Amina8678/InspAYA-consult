@extends('admin.layouts.app')

@php
    $editing = $consultant->exists;
    $user = auth()->user();
    $canChangeStatus = $user->can('changeStatus', $consultant);
    $canAssign = $user->can('assignServices', $consultant);
    $lines = fn (?array $items) => implode("\n", $items ?? []);
    $linkFields = App\Http\Requests\Admin\ConsultantRequest::LINKS;
@endphp

@section('title', $editing ? 'Edit '.$consultant->name : 'Add a consultant')

@section('content')
    <div class="page-head">
        <h1>{{ $editing ? 'Edit consultant' : 'Add a consultant' }}</h1>
        <a href="{{ route('admin.consultants.index') }}">Back to consultants</a>
    </div>

    @include('admin.partials.errors', ['fields' => [
        'name' => 'field-name',
        'title' => 'field-title',
        'bio' => 'field-bio',
        'photo_id' => 'field-photo_id',
        'expertise' => 'field-expertise',
        'qualifications' => 'field-qualifications',
        'email' => 'field-email',
    ] + collect($linkFields)->keys()->mapWithKeys(fn ($n) => ['links_'.$n => 'field-links_'.$n])->all()
      + ['services' => 'services-heading']])

    <form method="POST" action="{{ $editing ? route('admin.consultants.update', $consultant) : route('admin.consultants.store') }}">
        @csrf
        @if ($editing)
            @method('PUT')
        @endif

        <fieldset class="panel form">
            <legend><h2>Profile</h2></legend>
            @include('admin.partials.field', ['name' => 'name', 'label' => 'Name', 'type' => 'text', 'required' => true,
                'value' => old('name', $consultant->name), 'maxlength' => 255])
            @include('admin.partials.field', ['name' => 'title', 'label' => 'Job title', 'type' => 'text',
                'value' => old('title', $consultant->title), 'maxlength' => 255])
            @include('admin.partials.field', ['name' => 'bio', 'label' => 'Biography', 'type' => 'textarea',
                'value' => old('bio', $consultant->bio), 'maxlength' => 5000])
            @include('admin.partials.media-picker', ['name' => 'photo_id', 'label' => 'Photo', 'selected' => $consultant->photo_id,
                'options' => $mediaOptions, 'hint' => 'A square portrait works best; it is shown in a circle.'])
            @include('admin.partials.field', ['name' => 'expertise', 'label' => 'Areas of expertise', 'type' => 'textarea',
                'value' => old('expertise', $lines($consultant->expertise)), 'hint' => 'One area per line (up to 30 lines, 255 characters each).'])
            @include('admin.partials.field', ['name' => 'qualifications', 'label' => 'Qualifications', 'type' => 'textarea',
                'value' => old('qualifications', $lines($consultant->qualifications)), 'hint' => 'One qualification per line (up to 30 lines, 255 characters each).'])
        </fieldset>

        <fieldset class="panel form">
            <legend><h2>Contact and links</h2></legend>
            <p class="muted small">Only publish contact details and profiles the consultant has approved (FR-TEAM-01).</p>
            @include('admin.partials.field', ['name' => 'email', 'label' => 'Public email address', 'type' => 'email',
                'value' => old('email', $consultant->email), 'maxlength' => 255])
            @foreach ($linkFields as $network => $label)
                @include('admin.partials.field', ['name' => 'links_'.$network, 'label' => $label, 'type' => 'url',
                    'value' => old('links_'.$network, $consultant->links[$network] ?? null), 'maxlength' => 500,
                    'hint' => 'A full address starting with https://.'])
            @endforeach
        </fieldset>

        <fieldset class="panel form">
            <legend><h2>Visibility</h2></legend>
            @if ($canChangeStatus)
                <div class="field checkbox">
                    <input type="hidden" name="is_active" value="0">
                    <input id="field-is_active" type="checkbox" name="is_active" value="1"
                           @checked(old('is_active', $consultant->is_active ?? true))>
                    <label for="field-is_active">Show this consultant on the website</label>
                </div>
            @else
                <p class="muted">Status: {{ $consultant->is_active ? 'shown on the website' : 'hidden' }}. Only administrators can change this.</p>
            @endif
        </fieldset>

        <section class="panel" aria-labelledby="services-heading">
            <h2 id="services-heading">Services</h2>

            @if ($errors->has('services'))
                <p class="field__error"><span class="visually-hidden">Error:</span> {{ $errors->first('services') }}</p>
            @endif

            @if ($services->isEmpty())
                <p class="muted">No services yet.</p>
            @elseif ($canAssign)
                <p class="muted small">The same assignments appear on each service's edit screen; changing them here changes them there.</p>
                <table class="table">
                    <caption class="visually-hidden">Service assignments</caption>
                    <thead>
                        <tr>
                            <th scope="col">Service</th>
                            <th scope="col">Role</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($services as $service)
                            @php($role = old('services.'.$service->id, $assignments[$service->id] ?? 'none'))
                            <tr>
                                <td data-label="Service"><div>
                                    {{ $service->title }}
                                    @unless ($service->is_active)<br><span class="muted small">Hidden on the website</span>@endunless
                                </div></td>
                                <td data-label="Role"><div class="field">
                                    <label class="visually-hidden" for="service-{{ $service->id }}">Role on {{ $service->title }}</label>
                                    <select id="service-{{ $service->id }}" name="services[{{ $service->id }}]">
                                        <option value="none" @selected($role === 'none')>Not assigned</option>
                                        <option value="supporting" @selected($role === 'supporting')>Supporting</option>
                                        <option value="lead" @selected($role === 'lead')>Lead</option>
                                    </select>
                                </div></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @else
                @php($assigned = $services->filter(fn ($s) => isset($assignments[$s->id])))
                @if ($assigned->isEmpty())
                    <p class="muted">Not assigned to any service.</p>
                @else
                    <ul>
                        @foreach ($assigned as $service)
                            <li>{{ $service->title }} <span class="muted">({{ ucfirst($assignments[$service->id]) }})</span></li>
                        @endforeach
                    </ul>
                @endif
                <p class="muted small">Only administrators can change service assignments.</p>
            @endif
        </section>

        <div class="actions">
            <button type="submit" class="button">{{ $editing ? 'Save changes' : 'Create consultant' }}</button>
            @if ($editing)
                @can('delete', $consultant)
                    <a href="{{ route('admin.consultants.delete', $consultant) }}">Delete this consultant</a>
                @endcan
            @endif
        </div>
    </form>
@endsection
