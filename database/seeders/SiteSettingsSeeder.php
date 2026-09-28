<?php

namespace Database\Seeders;

use App\Enums\SettingType;
use App\Models\SiteSetting;
use Illuminate\Database\Seeder;

/**
 * Setting keys from schema plan §3.17 with placeholder values only: no real
 * contact details. The client supplies the real values (SRS Appendix B).
 *
 * Idempotent and non-destructive: existing keys are never overwritten, so
 * values edited in the CMS survive a reseed.
 */
class SiteSettingsSeeder extends Seeder
{
    /**
     * @var list<array{key: string, group: string, type: SettingType, value: ?string}>
     */
    public const SETTINGS = [
        ['key' => 'branding.site_name', 'group' => 'branding', 'type' => SettingType::String, 'value' => 'InspAya Consult'],
        // Image settings: the file is attached later via media_id (none seeded).
        ['key' => 'branding.logo', 'group' => 'branding', 'type' => SettingType::Media, 'value' => null],
        ['key' => 'branding.footer_logo', 'group' => 'branding', 'type' => SettingType::Media, 'value' => null],
        ['key' => 'branding.footer_image', 'group' => 'branding', 'type' => SettingType::Media, 'value' => null],

        ['key' => 'contact.email', 'group' => 'contact', 'type' => SettingType::String, 'value' => 'hello@example.com'],
        ['key' => 'contact.phone', 'group' => 'contact', 'type' => SettingType::String, 'value' => '+1 555 0100'],
        ['key' => 'contact.address', 'group' => 'contact', 'type' => SettingType::Text, 'value' => '[PLACEHOLDER] Office address awaiting client confirmation'],

        ['key' => 'social.linkedin', 'group' => 'social', 'type' => SettingType::String, 'value' => null],
        ['key' => 'social.x', 'group' => 'social', 'type' => SettingType::String, 'value' => null],
        ['key' => 'social.facebook', 'group' => 'social', 'type' => SettingType::String, 'value' => null],
        ['key' => 'social.instagram', 'group' => 'social', 'type' => SettingType::String, 'value' => null],

        ['key' => 'seo.default_title', 'group' => 'seo', 'type' => SettingType::String, 'value' => 'InspAya Consult'],
        ['key' => 'seo.default_description', 'group' => 'seo', 'type' => SettingType::Text, 'value' => '[PLACEHOLDER] Multidisciplinary corporate and advisory consulting.'],
        // Fallback Open Graph image for pages without their own; set via media_id.
        ['key' => 'seo.default_og_image', 'group' => 'seo', 'type' => SettingType::Media, 'value' => null],

        ['key' => 'analytics.tracking_id', 'group' => 'analytics', 'type' => SettingType::String, 'value' => null],

        // Non-secret email config only; SMTP credentials stay in .env (D8).
        ['key' => 'email.from_address', 'group' => 'email', 'type' => SettingType::String, 'value' => 'no-reply@example.com'],
        ['key' => 'email.enquiry_recipient', 'group' => 'email', 'type' => SettingType::String, 'value' => 'enquiries@example.com'],
    ];

    public function run(): void
    {
        foreach (self::SETTINGS as $setting) {
            SiteSetting::firstOrCreate(
                ['key' => $setting['key']],
                ['group' => $setting['group'], 'type' => $setting['type'], 'value' => $setting['value']],
            );
        }
    }
}
