<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class TestStorageIsolationTest extends TestCase
{
    public function test_fake_disks_are_namespaced_by_database_engine(): void
    {
        $engine = config('database.default');

        Storage::fake('public');

        $this->assertStringEndsWith(
            "framework/testing/disks/public_test_{$engine}",
            rtrim(Storage::disk('public')->path(''), '/'),
        );
    }
}
