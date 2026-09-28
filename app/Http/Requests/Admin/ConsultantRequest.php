<?php

namespace App\Http\Requests\Admin;

use App\Models\Consultant;
use App\Models\Service;
use App\Rules\SafeLink;
use App\Services\Assignments\ServiceConsultantAssignments;
use App\Support\MediaPicker;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * Create or edit a consultant (FR-TEAM-01/02, FR-ADM-07). Editors may update
 * content; creating, the active flag and service assignments need
 * consultants.manage (plan §6, enforced by ConsultantPolicy in the controller).
 *
 * Expertise and qualifications are textareas with one item per line.
 * Service assignments arrive as services[<id>] = none|supporting|lead.
 */
class ConsultantRequest extends FormRequest
{
    /** Profile links shown as icons on the site (FR-TEAM-01, "where approved"). */
    public const LINKS = [
        'linkedin' => 'LinkedIn profile',
        'x' => 'X (Twitter) profile',
        'facebook' => 'Facebook profile',
        'instagram' => 'Instagram profile',
        'website' => 'Personal website',
    ];

    public function authorize(): bool
    {
        $consultant = $this->route('consultant');

        return $consultant instanceof Consultant
            ? $this->user()->can('update', $consultant)
            : $this->user()->can('create', Consultant::class);
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        $rules = [
            'name' => ['required', 'string', 'max:255'],
            'title' => ['nullable', 'string', 'max:255'],
            'bio' => ['nullable', 'string', 'max:5000'],
            'photo_id' => ['nullable', 'integer', MediaPicker::rule()],
            'expertise' => ['nullable', 'string', 'max:10000'],
            'qualifications' => ['nullable', 'string', 'max:10000'],
            'email' => ['nullable', 'string', 'email:rfc', 'max:255'],
            'is_active' => ['sometimes', 'boolean'],
            'services' => ['sometimes', 'array'],
            'services.*' => ['string', 'in:'.implode(',', ServiceConsultantAssignments::ROLES)],
        ];

        foreach (array_keys(self::LINKS) as $network) {
            $rules['links_'.$network] = ['nullable', 'string', 'max:500', new SafeLink(webOnly: true)];
        }

        return $rules;
    }

    /**
     * @return list<callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator) {
                foreach (['expertise', 'qualifications'] as $field) {
                    $items = ServiceRequest::lines($this->input($field));

                    if (count($items) > ServiceRequest::MAX_LIST_ITEMS) {
                        $validator->errors()->add($field, 'Enter at most '.ServiceRequest::MAX_LIST_ITEMS." {$field} lines.");
                    }
                    foreach ($items as $item) {
                        if (mb_strlen($item) > ServiceRequest::MAX_ITEM_LENGTH) {
                            $validator->errors()->add($field, 'Each line must be '.ServiceRequest::MAX_ITEM_LENGTH.' characters or fewer.');
                            break;
                        }
                    }
                }

                $ids = array_keys((array) $this->input('services', []));
                if ($ids !== [] && Service::whereKey($ids)->count() !== count(array_unique($ids))) {
                    $validator->errors()->add('services', 'One of the services no longer exists. Reload the page and try again.');
                }
            },
        ];
    }

    /**
     * The profile links as stored: network => URL, empty ones dropped.
     *
     * @return array<string, string>
     */
    public function links(): array
    {
        $links = [];
        foreach (array_keys(self::LINKS) as $network) {
            $url = trim((string) $this->validated('links_'.$network));
            if ($url !== '') {
                $links[$network] = $url;
            }
        }

        return $links;
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return collect(self::LINKS)->mapWithKeys(fn ($label, $network) => ['links_'.$network => strtolower($label)])->all()
            + ['photo_id' => 'photo'];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'photo_id.exists' => 'Choose an image from the media library.',
            'services.*.in' => 'Choose "Not assigned", "Supporting" or "Lead" for each service.',
        ];
    }
}
