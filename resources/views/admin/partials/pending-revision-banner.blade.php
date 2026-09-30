{{--
    Stage 16: shown on a Page/BlogPost edit screen when it has an
    outstanding pending revision — from this viewer or anyone else, so a
    second Editor opening the same record knows not to step on it.
    $revisable: the Page or BlogPost, with currentRevision.submitter eager
    loaded (accessing it lazily would trip preventLazyLoading outside
    production).
--}}
@php
    $revision = $revisable->currentRevision;
    $isMine = $revision && auth()->user()->is($revision->submitter);
    $who = $isMine ? 'Your' : ($revision?->submitter?->name ?? 'a removed user')."'s";
@endphp
@if ($revision?->status === App\Enums\PendingRevisionStatus::Pending)
    <div class="alert">
        <p>{{ $who }} change is awaiting approval.
            @can('view', $revision)
                <a href="{{ route('admin.content-approvals.show', $revision) }}">Review it</a>.
            @endcan
        </p>
    </div>
@elseif ($revision?->status === App\Enums\PendingRevisionStatus::Rejected)
    <div class="alert alert--warning">
        <p>{{ $who }} {{ $isMine ? 'last submitted change was' : 'change was' }} rejected: {{ $revision->rejection_reason }}</p>
    </div>
@endif
