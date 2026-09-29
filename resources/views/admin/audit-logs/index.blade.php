@extends('admin.layouts.app')

@section('title', 'Audit log')

@section('content')
    <div class="page-head">
        <h1>Audit log</h1>
    </div>

    <section class="panel" aria-labelledby="list-heading">
        <h2 id="list-heading" class="visually-hidden">All audit log entries</h2>

        <form class="filters" method="GET" action="{{ route('admin.audit-logs.index') }}" role="search">
            <div class="field">
                <label for="filter-entity_type">Entity type</label>
                <select id="filter-entity_type" name="entity_type">
                    <option value="">All types</option>
                    @foreach (App\Support\AuditLogPresenter::ENTITY_TYPES as $value => $label)
                        <option value="{{ $value }}" @selected($filters['entityType'] === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="field">
                <label for="filter-action">Action</label>
                <select id="filter-action" name="action">
                    <option value="">All actions</option>
                    @foreach (App\Support\AuditLogPresenter::ACTIONS as $value)
                        <option value="{{ $value }}" @selected($filters['action'] === $value)>{{ str($value)->headline() }}</option>
                    @endforeach
                </select>
            </div>
            <div class="field">
                <label for="filter-actor">Actor</label>
                <select id="filter-actor" name="actor">
                    <option value="">Everyone</option>
                    <option value="guest" @selected($filters['actor'] === 'guest')>Guest / System</option>
                    @foreach ($actors as $actor)
                        <option value="{{ $actor->id }}" @selected($filters['actor'] === (string) $actor->id)>{{ $actor->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="field">
                <label for="filter-from">From</label>
                <input id="filter-from" type="date" name="from" value="{{ $filters['from']?->format('Y-m-d') }}">
            </div>
            <div class="field">
                <label for="filter-to">To</label>
                <input id="filter-to" type="date" name="to" value="{{ $filters['to']?->format('Y-m-d') }}">
            </div>
            <button type="submit" class="button button--secondary">Filter</button>
            @if (array_filter($filters))
                <a href="{{ route('admin.audit-logs.index') }}">Clear</a>
            @endif
        </form>

        @if ($logs->isEmpty())
            <p>No audit log entries match these filters.</p>
        @else
            <table class="table">
                <caption class="visually-hidden">Audit log entries, newest first</caption>
                <thead>
                    <tr>
                        <th scope="col">When</th>
                        <th scope="col">Actor</th>
                        <th scope="col">Action</th>
                        <th scope="col">Entity</th>
                        <th scope="col"><span class="visually-hidden">Actions</span></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($logs as $log)
                        @php($entity = App\Support\AuditLogPresenter::entity($log))
                        <tr>
                            <td data-label="When"><div>
                                <time datetime="{{ $log->created_at->toIso8601String() }}">{{ $log->created_at->format('j M Y, H:i') }}</time>
                            </div></td>
                            <td data-label="Actor"><div>{{ App\Support\AuditLogPresenter::actor($log) }}</div></td>
                            <td data-label="Action"><div>{{ str($log->action)->headline() }}</div></td>
                            <td data-label="Entity"><div>
                                @if ($entity['url'])
                                    <a href="{{ $entity['url'] }}">{{ $entity['label'] }}</a>
                                @else
                                    {{ $entity['label'] }}
                                @endif
                            </div></td>
                            <td data-label="Actions"><div class="actions">
                                <a href="{{ route('admin.audit-logs.show', $log) }}">Details<span class="visually-hidden"> for this entry</span></a>
                            </div></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>

            {{ $logs->links('admin.partials.pagination') }}
        @endif
    </section>
@endsection
