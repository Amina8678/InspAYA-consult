<?php

namespace App\Http\Controllers\Admin;

use App\Enums\SettingType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SiteSettingsRequest;
use App\Models\SiteSetting;
use App\Support\AuditLogger;
use App\Support\MediaPicker;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;

/**
 * Site settings screen (FR-ADM-13). Values are stored as plain text or a
 * media id; nothing here is ever written into CSS or url().
 */
class SiteSettingsController extends Controller
{
    public function __construct(private AuditLogger $audit, private MediaPicker $mediaPicker) {}

    public function edit(): View
    {
        $this->authorize('viewAny', SiteSetting::class);

        $settings = SiteSetting::query()
            ->whereIn('key', array_keys(SiteSettingsRequest::fields()))
            ->get()
            ->keyBy('key');

        return view('admin.settings.edit', [
            'groups' => SiteSettingsRequest::GROUPS,
            'settings' => $settings,
            'missing' => array_values(array_diff(array_keys(SiteSettingsRequest::fields()), $settings->keys()->all())),
            'mediaOptions' => $this->mediaPicker->options($settings->pluck('media_id')->filter()->all()),
        ]);
    }

    public function update(SiteSettingsRequest $request): RedirectResponse
    {
        $old = $new = [];

        DB::transaction(function () use ($request, &$old, &$new) {
            $rows = SiteSetting::query()
                ->whereIn('key', array_keys(SiteSettingsRequest::fields()))
                ->lockForUpdate()
                ->get();

            foreach ($rows as $setting) {
                $input = SiteSettingsRequest::inputName($setting->key);

                // Only fields actually submitted change; an omitted field is
                // never wiped.
                if (! $request->exists($input)) {
                    continue;
                }

                $this->authorize('update', $setting);

                $raw = $request->validated($input);
                $isMedia = $setting->type === SettingType::Media;
                $column = $isMedia ? 'media_id' : 'value';
                $value = $isMedia
                    ? ($raw === null || $raw === '' ? null : (int) $raw)
                    : (($trimmed = trim((string) $raw)) === '' ? null : $trimmed);

                if ($setting->{$column} !== $value) {
                    $old[$setting->key] = $setting->{$column};
                    $new[$setting->key] = $value;
                    $setting->{$column} = $value;
                    $setting->save();
                }
            }
        });

        if ($new === []) {
            return redirect()->route('admin.settings.edit')->with('status', 'No changes to save.');
        }

        $this->audit->record('settings_updated', $request->user(), null, $old, $new);

        return redirect()->route('admin.settings.edit')->with('status', 'Settings saved ('.count($new).' changed).');
    }
}
