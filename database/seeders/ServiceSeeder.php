<?php

namespace Database\Seeders;

use App\Models\Service;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * The seven core operational areas (SRS Appendix A; FR-SVC-01 mislabels it
 * Appendix B). Descriptions are placeholders: Appendix B lists the approved
 * copy as still outstanding from the client.
 *
 * Idempotent and non-destructive: matched by slug, never overwritten.
 */
class ServiceSeeder extends Seeder
{
    public const PLACEHOLDER = '[PLACEHOLDER: awaiting client-approved copy, SRS Appendix B]';

    /**
     * @var list<string>
     */
    public const SERVICES = [
        'Organizational Governance and Strategy',
        'Human Resource Management and Development',
        'Financial Analytics and Forensic Audits',
        'Energy Policy Evaluations and Formulation',
        'Engineering Design and Planning',
        'Digital Systems Design and Management',
        'Corporate Legal Consultations',
    ];

    public function run(): void
    {
        foreach (self::SERVICES as $index => $title) {
            Service::firstOrCreate(
                ['slug' => Str::slug($title)],
                [
                    'title' => $title,
                    'short_description' => self::PLACEHOLDER.' Summary of '.$title.'.',
                    'description' => self::PLACEHOLDER.' Full description of '.$title.'.',
                    'capabilities' => [self::PLACEHOLDER.' Capability 1', self::PLACEHOLDER.' Capability 2'],
                    'outcomes' => [self::PLACEHOLDER.' Outcome 1', self::PLACEHOLDER.' Outcome 2'],
                    'is_active' => true,
                    'sort_order' => $index + 1,
                    'meta_title' => $title.' | InspAya Consult',
                    'meta_description' => self::PLACEHOLDER,
                ],
            );
        }
    }
}
