{{--
    Error summary for a form. $bag: error bag (default 'default').
    $fields: [field => label anchor id] in form order, so each message links
    to its input.
--}}
@php($messages = $errors->getBag($bag ?? 'default'))
@if ($messages->any())
    <div class="alert alert--error" role="alert" tabindex="-1" id="error-summary" aria-labelledby="error-summary-heading">
        <h2 id="error-summary-heading">There is a problem</h2>
        <ul>
            @foreach ($fields as $field => $anchor)
                @foreach ($messages->get($field) as $message)
                    <li><a href="#{{ $anchor }}">{{ $message }}</a></li>
                @endforeach
            @endforeach
        </ul>
    </div>
@endif
