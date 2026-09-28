<?php

namespace Tests\Feature\Admin;

use App\Models\CoreValue;
use App\Models\Service;
use App\Services\Ordering\SortOrder;
use App\Support\Slug;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SortOrderTest extends TestCase
{
    use RefreshDatabase;

    private function positions(): array
    {
        return Service::query()->ordered()->pluck('sort_order', 'title')->all();
    }

    public function test_append_places_new_records_at_the_end(): void
    {
        Service::factory()->create(['sort_order' => 3]);
        Service::factory()->create(['sort_order' => 7]);

        $service = Service::factory()->make(['sort_order' => 0]);
        app(SortOrder::class)->append($service);

        $this->assertTrue($service->exists);
        $this->assertSame(8, $service->fresh()->sort_order);
    }

    public function test_append_on_an_empty_table_starts_at_one(): void
    {
        $value = CoreValue::factory()->make();
        app(SortOrder::class)->append($value);

        $this->assertSame(1, $value->fresh()->sort_order);
    }

    public function test_append_and_move_lock_the_rows_they_read(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            $this->markTestSkipped('Row locks (FOR UPDATE) are only emitted on MySQL; SQLite serialises writes itself.');
        }

        $queries = [];
        DB::listen(function ($q) use (&$queries) {
            $queries[] = strtolower($q->sql);
        });

        $service = Service::factory()->make();
        app(SortOrder::class)->append($service);
        app(SortOrder::class)->move($service, 'up');

        $this->assertCount(2, array_filter($queries, fn ($sql) => str_contains($sql, 'for update')));
    }

    public function test_move_swaps_neighbours_and_renumbers_without_gaps_or_duplicates(): void
    {
        foreach (['A' => 5, 'B' => 5, 'C' => 9, 'D' => 20] as $title => $order) {
            Service::factory()->create(['title' => $title, 'slug' => strtolower($title), 'sort_order' => $order]);
        }
        $c = Service::firstWhere('title', 'C');

        $result = app(SortOrder::class)->move($c, 'up');

        $this->assertSame(['from' => 3, 'to' => 2], $result);
        $this->assertSame(['A' => 1, 'C' => 2, 'B' => 3, 'D' => 4], $this->positions());

        app(SortOrder::class)->move(Service::firstWhere('title', 'D'), 'down'); // already last
        $this->assertSame(['A' => 1, 'C' => 2, 'B' => 3, 'D' => 4], $this->positions());

        app(SortOrder::class)->move(Service::firstWhere('title', 'A'), 'up'); // already first
        $this->assertSame([1, 2, 3, 4], array_values($this->positions()));
    }

    public function test_slug_helper_makes_unique_slugs(): void
    {
        Service::factory()->create(['slug' => 'energy-policy']);
        Service::factory()->create(['slug' => 'energy-policy-2']);

        $this->assertSame('energy-policy-3', Slug::unique(new Service, 'Energy Policy'));

        $existing = Service::firstWhere('slug', 'energy-policy');
        $this->assertSame('energy-policy', Slug::unique($existing, 'Energy policy'));
        $this->assertMatchesRegularExpression('/^service-[a-z0-9]{6}$/', Slug::unique(new Service, '!!!'));
    }
}
