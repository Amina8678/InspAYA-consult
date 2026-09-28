@extends('admin.layouts.app')

@php
    $editing = $service->exists;
    $user = auth()->user();
    $canChangeStatus = $user->can('changeStatus', $service);
    $canAssign = $user->can('assignConsultants', $service);
    $isLive = $editing && $service->is_active;
    $lines = fn (?array $items) => implode("\n", $items ?? []);
@endphp

@section('title', $editing ? 'Edit '.$service->title : 'Add a service')

@section('content')
    <div class="page-head">
        <h1>{{ $editing ? 'Edit service' : 'Add a service' }}</h1>
        <a href="{{ route('admin.services.index') }}">Back to services</a>
    </div>

    @include('admin.partials.errors', ['fields' => [
        'title' => 'field-title',
        'slug' => 'field-slug',
        'confirm_slug_change' => 'field-confirm_slug_change',
        'short_description' => 'field-short_description',
        'description' => 'field-description',
        'capabilities' => 'field-capabilities',
        'outcomes' => 'field-outcomes',
        'meta_title' => 'field-meta_title',
        'meta_description' => 'field-meta_description',
        'consultants' => 'consultants-heading',
    ]])

    <form method="POST" action="{{ $editing ? route('admin.services.update', $service) : route('admin.services.store') }}">
        @csrf
        @if ($editing)
            @method('PUT')
        @endif

        <fieldset class="panel form">
            <legend><h2>Content</h2></legend>

            @include('admin.partials.field', ['name' => 'title', 'label' => 'Title', 'type' => 'text', 'required' => true,
                'value' => old('title', $service->title), 'maxlength' => 255])

            @include('admin.partials.field', ['name' => 'slug', 'label' => 'Slug (web address)', 'type' => 'text',
                'value' => old('slug', $service->slug), 'maxlength' => 191,
                'hint' => $editing
                    ? 'Lowercase words joined by hyphens. The page is at /services/'.$service->slug.'. Leave empty to keep it.'
                    : 'Lowercase words joined by hyphens, e.g. "energy-policy". Leave empty to create it from the title.'])

            @if ($isLive)
                <div class="alert alert--warning">
                    <p><strong>This service is live.</strong> Changing the slug changes its web address, so existing links and
                        bookmarks to /services/{{ $service->slug }} will stop working.</p>
                    <div class="field checkbox {{ $errors->has('confirm_slug_change') ? 'field--error' : '' }}">
                        <input type="hidden" name="confirm_slug_change" value="0">
                        <input id="field-confirm_slug_change" type="checkbox" name="confirm_slug_change" value="1"
                               @checked(old('confirm_slug_change'))
                               @if ($errors->has('confirm_slug_change')) aria-invalid="true" aria-describedby="field-confirm_slug_change-error" @endif>
                        <label for="field-confirm_slug_change">I understand, change the web address if I edited the slug</label>
                    </div>
                    @if ($errors->has('confirm_slug_change'))
                        <p class="field__error" id="field-confirm_slug_change-error"><span class="visually-hidden">Error:</span> {{ $errors->first('confirm_slug_change') }}</p>
                    @endif
                </div>
            @endif

            @include('admin.partials.field', ['name' => 'short_description', 'label' => 'Summary', 'type' => 'textarea',
                'value' => old('short_description', $service->short_description), 'maxlength' => 500,
                'hint' => 'Shown on service cards and under the page title. Up to 500 characters.'])
            @include('admin.partials.field', ['name' => 'description', 'label' => 'Full description', 'type' => 'textarea',
                'value' => old('description', $service->description), 'maxlength' => 20000])
            @include('admin.partials.field', ['name' => 'capabilities', 'label' => 'Capabilities', 'type' => 'textarea',
                'value' => old('capabilities', $lines($service->capabilities)),
                'hint' => 'One capability per line (up to 30 lines, 255 characters each).'])
            @include('admin.partials.field', ['name' => 'outcomes', 'label' => 'Outcomes and deliverables', 'type' => 'textarea',
                'value' => old('outcomes', $lines($service->outcomes)),
                'hint' => 'One outcome per line (up to 30 lines, 255 characters each).'])
        </fieldset>

        <fieldset class="panel form">
            <legend><h2>Search engines</h2></legend>
            @include('admin.partials.field', ['name' => 'meta_title', 'label' => 'Page title for search results', 'type' => 'text',
                'value' => old('meta_title', $service->meta_title), 'maxlength' => 255,
                'hint' => 'Leave empty to use "'.($service->title ?: 'Title').' | site name". Around 60 characters works best.'])
            @include('admin.partials.field', ['name' => 'meta_description', 'label' => 'Description for search results', 'type' => 'textarea',
                'value' => old('meta_description', $service->meta_description), 'maxlength' => 500,
                'hint' => 'Leave empty to use the summary. Around 155 characters works best.'])
        </fieldset>

        <fieldset class="panel form">
            <legend><h2>Visibility</h2></legend>
            @if ($canChangeStatus)
                <div class="field checkbox">
                    <input type="hidden" name="is_active" value="0">
                    <input id="field-is_active" type="checkbox" name="is_active" value="1"
                           @checked(old('is_active', $service->is_active ?? true))>
                    <label for="field-is_active">Show this service on the website</label>
                </div>
            @else
                <p class="muted">Status: {{ $service->is_active ? 'live on the website' : 'hidden' }}. Only administrators can change this.</p>
            @endif
        </fieldset>

        <section class="panel" aria-labelledby="consultants-heading">
            <h2 id="consultants-heading">Consultants</h2>

            @if ($errors->has('consultants'))
                <p class="field__error"><span class="visually-hidden">Error:</span> {{ $errors->first('consultants') }}</p>
            @endif

            @if ($consultants->isEmpty())
                <p class="muted">No consultants yet.</p>
            @elseif ($canAssign)
                <p class="muted small">Choose who works on this service. Lead consultants are highlighted on the service page (FR-TEAM-02).</p>
                <table class="table">
                    <caption class="visually-hidden">Consultant assignments</caption>
                    <thead>
                        <tr>
                            <th scope="col">Consultant</th>
                            <th scope="col">Role on this service</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($consultants as $consultant)
                            @php($role = old('consultants.'.$consultant->id, $assignments[$consultant->id] ?? 'none'))
                            <tr>
                                <td data-label="Consultant"><div>
                                    {{ $consultant->name }}
                                    @if ($consultant->title)<br><span class="muted small">{{ $consultant->title }}</span>@endif
                                    @unless ($consultant->is_active)<br><span class="muted small">Hidden on the website</span>@endunless
                                </div></td>
                                <td data-label="Role"><div class="field">
                                    <label class="visually-hidden" for="consultant-{{ $consultant->id }}">Role of {{ $consultant->name }}</label>
                                    <select id="consultant-{{ $consultant->id }}" name="consultants[{{ $consultant->id }}]">
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
                @php($assigned = $consultants->filter(fn ($c) => isset($assignments[$c->id])))
                @if ($assigned->isEmpty())
                    <p class="muted">No consultants assigned.</p>
                @else
                    <ul>
                        @foreach ($assigned as $consultant)
                            <li>{{ $consultant->name }} <span class="muted">({{ ucfirst($assignments[$consultant->id]) }})</span></li>
                        @endforeach
                    </ul>
                @endif
                <p class="muted small">Only administrators can change consultant assignments.</p>
            @endif
        </section>

        <div class="actions">
            <button type="submit" class="button">{{ $editing ? 'Save changes' : 'Create service' }}</button>
            @if ($editing)
                @can('delete', $service)
                    <a href="{{ route('admin.services.delete', $service) }}">Delete this service</a>
                @endcan
            @endif
        </div>
    </form>
@endsection
