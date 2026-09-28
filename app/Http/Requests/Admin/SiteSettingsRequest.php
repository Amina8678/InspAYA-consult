<?php

namespace App\Http\Requests\Admin;

use App\Models\SiteSetting;
use App\Rules\SafeLink;
use App\Support\MediaPicker;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Site settings (FR-ADM-13). Every editable key is declared here with its
 * group, label and type; the type decides the strict validation. Keys are
 * defined in code (SiteSettingsSeeder) and are never created from the form.
 *
 * Form field names replace "." with "__" (e.g. contact__email), because
 * Laravel reads dots in input names as nesting.
 */
class SiteSettingsRequest extends FormRequest
{
    /**
     * @var array<string, array{label: string, fields: array<string, array{label: string, type: string, hint?: string, required?: bool}>}>
     */
    public const GROUPS = [
        'branding' => ['label' => 'Branding', 'fields' => [
            'branding.site_name' => ['label' => 'Site name', 'type' => 'string', 'required' => true],
            'branding.logo' => ['label' => 'Header logo', 'type' => 'media'],
            'branding.footer_logo' => ['label' => 'Footer logo', 'type' => 'media'],
            'branding.footer_image' => ['label' => 'Footer image', 'type' => 'media'],
        ]],
        'contact' => ['label' => 'Contact details', 'fields' => [
            'contact.email' => ['label' => 'Public email address', 'type' => 'email'],
            'contact.phone' => ['label' => 'Public phone number', 'type' => 'phone', 'hint' => 'Digits, spaces and + ( ) - . only.'],
            'contact.address' => ['label' => 'Office address', 'type' => 'text'],
        ]],
        'social' => ['label' => 'Social links', 'fields' => [
            'social.linkedin' => ['label' => 'LinkedIn page', 'type' => 'url'],
            'social.x' => ['label' => 'X (Twitter) profile', 'type' => 'url'],
            'social.facebook' => ['label' => 'Facebook page', 'type' => 'url'],
            'social.instagram' => ['label' => 'Instagram profile', 'type' => 'url'],
        ]],
        'seo' => ['label' => 'Search and sharing defaults', 'fields' => [
            'seo.default_title' => ['label' => 'Default page title', 'type' => 'string', 'hint' => 'Used when a page has no title of its own.'],
            'seo.default_description' => ['label' => 'Default meta description', 'type' => 'description', 'hint' => 'Up to 300 characters. Shown by search engines when a page has no description of its own.'],
            'seo.default_og_image' => ['label' => 'Default sharing image', 'type' => 'media', 'hint' => 'Used when a shared page has no image of its own.'],
        ]],
        'analytics' => ['label' => 'Analytics', 'fields' => [
            'analytics.tracking_id' => ['label' => 'Analytics tracking ID', 'type' => 'tracking', 'hint' => 'Letters, digits and hyphens only (e.g. G-ABC123). Not output on the site until cookie consent is in place (FR-LEGAL-02).'],
        ]],
        'email' => ['label' => 'Email', 'fields' => [
            'email.from_address' => ['label' => 'Send emails from', 'type' => 'email', 'hint' => 'Mail server credentials stay in the server configuration, not here.'],
            'email.enquiry_recipient' => ['label' => 'Send new enquiries to', 'type' => 'email'],
        ]],
    ];

    public function authorize(): bool
    {
        return $this->user()->can('viewAny', SiteSetting::class);
    }

    /**
     * @return array<string, array{label: string, type: string, hint?: string, required?: bool, group: string}>
     */
    public static function fields(): array
    {
        $fields = [];
        foreach (self::GROUPS as $group => $definition) {
            foreach ($definition['fields'] as $key => $field) {
                $fields[$key] = $field + ['group' => $group];
            }
        }

        return $fields;
    }

    public static function inputName(string $key): string
    {
        return str_replace('.', '__', $key);
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        $rules = [];
        $present = SiteSetting::query()->whereIn('key', array_keys(self::fields()))->pluck('key')->all();

        foreach (self::fields() as $key => $field) {
            if (! in_array($key, $present, true)) {
                continue; // not set up in this database: shown as missing, never created
            }

            // "sometimes": a field left out of the submission isn't validated
            // (and the controller leaves that setting untouched).
            $base = ['sometimes', ($field['required'] ?? false) ? 'required' : 'nullable'];
            $rules[self::inputName($key)] = array_merge($base, match ($field['type']) {
                'string' => ['string', 'max:255'],
                'text' => ['string', 'max:1000'],
                'description' => ['string', 'max:300'],
                'email' => ['string', 'email:rfc', 'max:255'],
                'phone' => ['string', 'max:50', 'regex:/^[0-9+()\-.\s]+$/D'],
                'url' => ['string', 'max:500', new SafeLink(webOnly: true)],
                'tracking' => ['string', 'max:40', 'regex:/^[A-Za-z0-9-]+$/D'],
                'media' => ['integer', MediaPicker::rule()],
            });
        }

        return $rules;
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return collect(self::fields())
            ->mapWithKeys(fn ($field, $key) => [self::inputName($key) => strtolower($field['label'])])
            ->all();
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            '*.regex' => 'The :attribute contains characters that are not allowed.',
            '*.exists' => 'Choose an image from the media library.',
        ];
    }
}
