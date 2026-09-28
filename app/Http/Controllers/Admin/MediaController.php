<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Media;
use App\Services\Media\FileInspector;
use App\Services\Media\MediaLibrary;
use App\Services\Media\MediaUsage;
use App\Support\AuditLogger;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Media library (FR-ADM-09). Routes carry permission:/can: middleware; each
 * action also authorizes against MediaPolicy (Authors: own uploads only).
 */
class MediaController extends Controller
{
    public const PER_PAGE = 24;

    /** Type filter => MIME prefix or list. */
    private const FILTERS = [
        'image' => 'image/',
        'document' => 'application/pdf',
    ];

    public function __construct(
        private MediaLibrary $library,
        private MediaUsage $usage,
        private AuditLogger $audit,
    ) {}

    public function index(Request $request): View
    {
        $this->authorize('viewAny', Media::class);

        $type = array_key_exists((string) $request->query('type'), self::FILTERS) ? $request->query('type') : null;

        $media = Media::query()
            ->with('uploader:id,name')
            ->when($type === 'image', fn ($q) => $q->where('mime_type', 'like', 'image/%'))
            ->when($type === 'document', fn ($q) => $q->where('mime_type', self::FILTERS['document']))
            ->latest()
            ->latest('id')
            ->paginate(self::PER_PAGE)
            ->withQueryString();

        return view('admin.media.index', [
            'media' => $media,
            'type' => $type,
            'accept' => implode(',', array_keys(FileInspector::TYPES)),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', Media::class);

        $validated = $request->validate([
            'file' => ['required', 'file'],
            'alt_text' => ['nullable', 'string', 'max:255'],
            'caption' => ['nullable', 'string', 'max:1000'],
        ]);

        $media = $this->library->store($request->file('file'), $validated, $request->user());

        $this->audit->record('created', $request->user(), $media, new: $this->auditValues($media));

        return redirect()->route('admin.media.index')->with('status', 'Uploaded "'.$media->file_name.'".');
    }

    public function edit(Media $media): View
    {
        $this->authorize('update', $media);

        return view('admin.media.edit', [
            'media' => $media->load('uploader:id,name'),
            'usages' => $this->usage->find($media),
            'accept' => implode(',', array_keys(FileInspector::TYPES)),
        ]);
    }

    public function update(Request $request, Media $media): RedirectResponse
    {
        $this->authorize('update', $media);

        $validated = $request->validate([
            'file' => ['nullable', 'file'],
            'alt_text' => ['nullable', 'string', 'max:255'],
            'caption' => ['nullable', 'string', 'max:1000'],
        ]);

        $before = $this->auditValues($media);
        $this->library->update($media, $validated, $request->file('file'));

        $this->audit->record('updated', $request->user(), $media, $before, $this->auditValues($media));

        return redirect()->route('admin.media.edit', $media)->with('status', 'Saved changes to "'.$media->file_name.'".');
    }

    /**
     * Confirmation page listing every usage (plan §3.6).
     */
    public function confirmDelete(Media $media): View
    {
        $this->authorize('delete', $media);

        return view('admin.media.delete', [
            'media' => $media,
            'usages' => $this->usage->find($media),
        ]);
    }

    public function destroy(Request $request, Media $media): RedirectResponse
    {
        $this->authorize('delete', $media);

        $request->validate(['confirm' => ['accepted']], [
            'confirm.accepted' => 'Confirm that you want to delete this file and remove it from the places listed.',
        ]);

        $before = $this->auditValues($media);
        $usages = $this->library->delete($media);

        $this->audit->record('deleted', $request->user(), $media, $before + ['cleared_usages' => $usages]);

        return redirect()->route('admin.media.index')->with('status', sprintf(
            'Deleted "%s"%s.',
            $media->file_name,
            $usages === [] ? '' : ' and removed it from '.count($usages).' '.str('place')->plural(count($usages)),
        ));
    }

    /**
     * @return array<string, mixed>
     */
    private function auditValues(Media $media): array
    {
        return $media->only(['file_name', 'storage_path', 'mime_type', 'size', 'width', 'height', 'alt_text', 'caption']);
    }
}
