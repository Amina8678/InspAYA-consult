<?php

namespace Database\Seeders\Demo;

use App\Models\Consultant;
use App\Models\Service;
use Database\Seeders\ServiceSeeder;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Demo content (non-production only). Clearly fictional placeholder profiles:
 * real names, biographies and assignments come from the client (SRS
 * Appendix B). No photos, emails or links.
 */
class ConsultantSeeder extends Seeder
{
    public function run(): void
    {
        // Each placeholder consultant leads one or two services.
        $consultants = [
            'Governance & HR' => [ServiceSeeder::SERVICES[0], ServiceSeeder::SERVICES[1]],
            'Finance & Audit' => [ServiceSeeder::SERVICES[2]],
            'Energy & Engineering' => [ServiceSeeder::SERVICES[3], ServiceSeeder::SERVICES[4]],
            'Digital & Legal' => [ServiceSeeder::SERVICES[5], ServiceSeeder::SERVICES[6]],
        ];

        $sort = 0;

        foreach ($consultants as $area => $serviceTitles) {
            $sort++;

            $consultant = Consultant::firstOrCreate(
                ['name' => "[PLACEHOLDER] {$area} Consultant"],
                [
                    'title' => "Lead Consultant, {$area}",
                    'bio' => '[PLACEHOLDER] Biography awaiting client-supplied content.',
                    'expertise' => array_map('trim', explode('&', $area)),
                    'qualifications' => ['[PLACEHOLDER] Qualification'],
                    'is_active' => true,
                    'sort_order' => $sort,
                ],
            );

            $serviceIds = Service::whereIn('slug', array_map(fn ($t) => Str::slug($t), $serviceTitles))->pluck('id');

            $consultant->services()->syncWithoutDetaching(
                $serviceIds->mapWithKeys(fn ($id) => [$id => ['is_lead' => true, 'sort_order' => 1]])->all()
            );
        }
    }
}
