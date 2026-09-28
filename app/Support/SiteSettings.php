<?php

namespace App\Support;

use App\Enums\SettingType;
use App\Models\SiteSetting;
use App\View\Presenters\MediaPresenter;
use Illuminate\Support\Arr;

/**
 * All site_settings rows, loaded once per request (registered as a scoped
 * singleton) and exposed as a plain nested array: "contact.email" becomes
 * ['contact' => ['email' => …]]. Values are typed by SettingType; media
 * settings resolve to MediaPresenter arrays.
 *
 * Nothing is cached across requests, so no Collection or model ever ends up
 * in the cache store.
 */
class SiteSettings
{
    /** @var array<string, mixed>|null */
    private ?array $settings = null;

    /**
     * @return array<string, mixed>
     */
    public function all(): array
    {
        return $this->settings ??= $this->load();
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return Arr::get($this->all(), $key, $default);
    }

    /**
     * @return array<string, mixed>
     */
    private function load(): array
    {
        $flat = SiteSetting::query()
            ->with('media')
            ->orderBy('key')
            ->get()
            ->mapWithKeys(fn (SiteSetting $setting) => [$setting->key => $this->typed($setting)])
            ->all();

        return Arr::undot($flat);
    }

    private function typed(SiteSetting $setting): mixed
    {
        $value = $setting->value;

        return match ($setting->type) {
            SettingType::Boolean => $value === null ? null : filter_var($value, FILTER_VALIDATE_BOOLEAN),
            SettingType::Integer => $value === null ? null : (int) $value,
            SettingType::Json => $value === null ? null : json_decode($value, true),
            SettingType::Media => MediaPresenter::present($setting->media),
            SettingType::String, SettingType::Text => $value,
        };
    }
}
