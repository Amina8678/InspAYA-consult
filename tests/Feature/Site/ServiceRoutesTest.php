<?php

namespace Tests\Feature\Site;

use App\Models\Consultant;
use App\Models\Service;

class ServiceRoutesTest extends SiteTestCase
{
    public function test_index_lists_active_services_in_display_order(): void
    {
        Service::factory()->create(['title' => 'Second', 'sort_order' => 2]);
        Service::factory()->create(['title' => 'First', 'sort_order' => 1]);
        Service::factory()->inactive()->create(['title' => 'Hidden']);

        $response = $this->get(route('services.index'));

        $response->assertOk()
            ->assertViewIs('public.services.index')
            ->assertViewHas('services', function (array $services) {
                $this->assertSame(['First', 'Second'], array_column($services, 'title'));
                $this->assertSame(['title', 'slug', 'url', 'short_description'], array_keys($services[0]));

                return true;
            });
        $this->assertSeo($response);
    }

    public function test_show_renders_an_active_service_with_its_active_consultants(): void
    {
        $service = Service::factory()->create(['meta_title' => null, 'title' => 'Energy Policy']);
        $lead = Consultant::factory()->withPhoto()->create(['name' => 'Lead']);
        $support = Consultant::factory()->create(['name' => 'Support']);
        $inactive = Consultant::factory()->inactive()->create(['name' => 'Inactive']);
        $service->consultants()->attach([
            $support->id => ['is_lead' => false, 'sort_order' => 2],
            $lead->id => ['is_lead' => true, 'sort_order' => 1],
            $inactive->id => ['is_lead' => true, 'sort_order' => 0],
        ]);

        $response = $this->get(route('services.show', $service->slug));

        $response->assertOk()
            ->assertViewIs('public.services.show')
            ->assertViewHas('service', function (array $data) use ($service) {
                $this->assertSame($service->title, $data['title']);
                $this->assertSame($service->capabilities, $data['capabilities']);
                $this->assertSame(['Lead', 'Support'], array_column($data['consultants'], 'name'));
                $this->assertSame(['Lead'], array_column($data['lead_consultants'], 'name'));
                $this->assertNotNull($data['consultants'][0]['photo']['url']);
                $this->assertSame('Energy Policy | '.config('app.name'), $data['seo']['title']);
                $this->assertSame(route('services.show', $service->slug), $data['seo']['canonical_url']);

                return true;
            });
        $this->assertSeo($response);
    }

    public function test_inactive_service_404s(): void
    {
        $service = Service::factory()->inactive()->create();

        $this->get(route('services.show', $service->slug))->assertNotFound();
    }

    public function test_unknown_slug_404s(): void
    {
        $this->get(route('services.show', 'no-such-service'))->assertNotFound();
    }

    public function test_malformed_slug_404s_without_reaching_the_controller(): void
    {
        $this->get('/services/Not_A_Slug')->assertNotFound();
    }
}
