<?php

namespace Tests\Feature\Site;

use App\Models\Consultant;
use App\Models\CoreValue;
use App\Models\Service;

class ConsultantAndValueRoutesTest extends SiteTestCase
{
    public function test_consultants_lists_active_profiles_with_their_active_services(): void
    {
        $active = Service::factory()->create(['title' => 'Visible service']);
        $inactive = Service::factory()->inactive()->create(['title' => 'Hidden service']);
        $consultant = Consultant::factory()->create(['name' => 'Shown', 'sort_order' => 1]);
        $consultant->services()->attach([$active->id, $inactive->id]);
        Consultant::factory()->inactive()->create(['name' => 'Hidden']);

        $response = $this->get(route('consultants.index'));

        $response->assertOk()
            ->assertViewIs('public.consultants.index')
            ->assertViewHas('consultants', function (array $consultants) {
                $this->assertSame(['Shown'], array_column($consultants, 'name'));
                $this->assertSame(['Visible service'], array_column($consultants[0]['services'], 'title'));
                $this->assertArrayHasKey('photo', $consultants[0]);

                return true;
            });
        $this->assertSeo($response);
    }

    public function test_core_values_lists_active_values_in_display_order(): void
    {
        CoreValue::factory()->create(['title' => 'Integrity', 'sort_order' => 2]);
        CoreValue::factory()->withIcon()->create(['title' => 'Excellence', 'sort_order' => 1]);
        CoreValue::factory()->inactive()->create(['title' => 'Hidden']);

        $response = $this->get(route('core-values.index'));

        $response->assertOk()
            ->assertViewIs('public.core-values.index')
            ->assertViewHas('coreValues', function (array $values) {
                $this->assertSame(['Excellence', 'Integrity'], array_column($values, 'title'));
                $this->assertNotNull($values[0]['icon']['url']);
                $this->assertNull($values[1]['icon']);

                return true;
            });
        $this->assertSeo($response);
    }
}
