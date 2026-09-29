@extends('admin.layouts.app')

@php
    $entity = App\Support\AuditLogPresenter::entity($log);
    $keys = array_values(array_unique(array_merge(array_keys($log->old_values ?? []), array_keys($log->new_values ?? []))));
    $render = function (mixed $value) {
        if ($value === null) {
            return '—';
        }
        if (is_bool($value)) {
            return $value ? 'Yes' : 'No';
        }
        if (is_array($value)) {
            return json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        }

        return (string) $value;
    };
@endphp

@section('title', 'Audit log entry')

@section('content')
    <div class="page-head">
        <h1>{{ str($log->action)->headline() }}</h1>
        <a href="{{ route('admin.audit-logs.index') }}">Back to audit log</a>
    </div>

    <section class="panel" aria-labelledby="summary-heading">
        <h2 id="summary-heading">Summary</h2>
        <dl>
            <dt>When</dt>
            <dd><time datetime="{{ $log->created_at->toIso8601String() }}">{{ $log->created_at->format('j M Y, H:i:s') }}</time></dd>
            <dt>Actor</dt>
            <dd>{{ App\Support\AuditLogPresenter::actor($log) }}</dd>
            <dt>Action</dt>
            <dd>{{ str($log->action)->headline() }}</dd>
            <dt>Entity</dt>
            <dd>
                @if ($entity['url'])
                    <a href="{{ $entity['url'] }}">{{ $entity['label'] }}</a>
                @else
                    {{ $entity['label'] }}
                @endif
            </dd>
            @if ($log->ip_address)
                <dt>IP address</dt>
                <dd>{{ $log->ip_address }}</dd>
            @endif
            @if ($log->user_agent)
                <dt>Browser</dt>
                <dd>{{ $log->user_agent }}</dd>
            @endif
        </dl>
    </section>

    <section class="panel" aria-labelledby="details-heading">
        <h2 id="details-heading">Details</h2>

        @if ($keys === [])
            <p class="muted">No additional details were recorded for this entry.</p>
        @else
            <table class="table">
                <caption class="visually-hidden">Stored values for this entry, before and after where both are recorded</caption>
                <thead>
                    <tr>
                        <th scope="col">Field</th>
                        <th scope="col">Before</th>
                        <th scope="col">After</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($keys as $key)
                        @php
                            $hasOld = $log->old_values && array_key_exists($key, $log->old_values);
                            $hasNew = $log->new_values && array_key_exists($key, $log->new_values);
                        @endphp
                        <tr>
                            <td data-label="Field"><div>{{ str($key)->headline() }}</div></td>
                            <td data-label="Before"><div class="pre-line">{{ $hasOld ? $render($log->old_values[$key]) : '—' }}</div></td>
                            <td data-label="After"><div class="pre-line">{{ $hasNew ? $render($log->new_values[$key]) : '—' }}</div></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </section>
@endsection
