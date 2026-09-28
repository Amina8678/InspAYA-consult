{{-- Contact page and enquiry form (FR-CONT-01 to 03). $page is optional. --}}
@extends('layouts.public')

@php
    $contact = data_get($settings, 'contact', []);
    $privacyUrl = collect($footer['legal'] ?? [])->firstWhere('url', route('privacy-policy'))['url'] ?? null;
    $fieldOrder = ['name', 'email', 'phone', 'organization', 'subject', 'message', 'consent', 'website'];
@endphp

@section('content')
    <div class="container page-header">
        <h1>{{ $page['title'] ?? 'Contact us' }}</h1>
        <p class="lead">Have a question or ready to discuss your organization's needs? Send us a message.</p>
    </div>

    @include('public.partials.sections', ['sections' => $page['sections'] ?? []])

    <div class="container section">
        <div class="layout-split">
            <section aria-labelledby="details-heading">
                <h2 id="details-heading">Contact details</h2>
                @if (! empty($contact['email']) || ! empty($contact['phone']) || ! empty($contact['address']))
                    <dl class="contact-details">
                        @if (! empty($contact['email']))
                            <dt>Email</dt>
                            <dd><a href="mailto:{{ $contact['email'] }}">{{ $contact['email'] }}</a></dd>
                        @endif
                        @if (! empty($contact['phone']))
                            <dt>Phone</dt>
                            <dd><a href="tel:{{ preg_replace('/[^0-9+]/', '', $contact['phone']) }}">{{ $contact['phone'] }}</a></dd>
                        @endif
                        @if (! empty($contact['address']))
                            <dt>Address</dt>
                            <dd class="prose">{{ $contact['address'] }}</dd>
                        @endif
                    </dl>
                @else
                    <p>Please use the form to get in touch.</p>
                @endif
            </section>

            <section aria-labelledby="form-heading">
                <h2 id="form-heading">Send us a message</h2>

                @if (session('status'))
                    <div class="alert alert--success" role="status">
                        <p>{{ session('status') }}</p>
                    </div>
                @endif

                @if ($errors->any())
                    <div class="alert alert--error" role="alert" aria-labelledby="error-summary-heading" tabindex="-1" id="error-summary">
                        <h2 id="error-summary-heading">There is a problem with your message</h2>
                        <ul>
                            @foreach ($fieldOrder as $field)
                                @if ($errors->has($field))
                                    <li>
                                        @if ($field === 'website')
                                            {{ $errors->first($field) }}
                                        @else
                                            <a href="#field-{{ $field }}">{{ $errors->first($field) }}</a>
                                        @endif
                                    </li>
                                @endif
                            @endforeach
                        </ul>
                    </div>
                @endif

                <form class="form" method="POST" action="{{ route('contact.store') }}">
                    @csrf

                    @include('public.partials.field', ['name' => 'name', 'label' => 'Your name', 'type' => 'text', 'required' => true, 'autocomplete' => 'name', 'maxlength' => 255])
                    @include('public.partials.field', ['name' => 'email', 'label' => 'Email address', 'type' => 'email', 'required' => true, 'autocomplete' => 'email', 'maxlength' => 255])
                    @include('public.partials.field', ['name' => 'phone', 'label' => 'Phone number', 'type' => 'tel', 'required' => false, 'autocomplete' => 'tel', 'maxlength' => 50])
                    @include('public.partials.field', ['name' => 'organization', 'label' => 'Organization', 'type' => 'text', 'required' => false, 'autocomplete' => 'organization', 'maxlength' => 255])
                    @include('public.partials.field', ['name' => 'subject', 'label' => 'Subject', 'type' => 'text', 'required' => true, 'maxlength' => 255])
                    @include('public.partials.field', ['name' => 'message', 'label' => 'Message', 'type' => 'textarea', 'required' => true, 'maxlength' => 5000, 'hint' => 'Up to 5,000 characters.'])

                    <div class="field {{ $errors->has('consent') ? 'field--error' : '' }}">
                        @if ($errors->has('consent'))
                            <span class="field__error" id="field-consent-error">
                                <span class="visually-hidden">Error:</span> {{ $errors->first('consent') }}
                            </span>
                        @endif
                        <div class="checkbox">
                            <input id="field-consent" name="consent" type="checkbox" value="1" required
                                   @checked(old('consent'))
                                   @if ($errors->has('consent')) aria-invalid="true" aria-describedby="field-consent-error" @endif>
                            <label for="field-consent">
                                I agree that {{ data_get($settings, 'branding.site_name') ?: config('app.name') }} may store
                                the details in this form to respond to my enquiry, as described in the
                                @if ($privacyUrl)
                                    <a href="{{ $privacyUrl }}">privacy policy</a>.
                                @else
                                    privacy policy.
                                @endif
                                (required)
                            </label>
                        </div>
                    </div>

                    {{-- Anti-bot honeypot (FR-CONT-03): hidden from people and assistive tech; must stay empty. --}}
                    <div class="form__trap" aria-hidden="true">
                        <label for="field-website">Leave this field empty</label>
                        <input id="field-website" name="website" type="text" value="" tabindex="-1" autocomplete="off">
                    </div>

                    <button type="submit" class="button">Send message</button>
                </form>
            </section>
        </div>
    </div>
@endsection
