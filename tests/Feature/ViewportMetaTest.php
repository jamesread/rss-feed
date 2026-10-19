<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\GeneratesTestData;
use Tests\TestCase;

final class ViewportMetaTest extends TestCase
{
    use RefreshDatabase;
    use GeneratesTestData;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
        $this->generateStableTestData();
    }

    public function test_viewport_extends_into_safe_areas(): void
    {
        $resp = $this->get('/');
        $resp->assertOk();
        $resp->assertSee('<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">', false);
    }
}
