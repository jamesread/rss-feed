<?php

namespace Tests\Feature;

use App\Jobs\RefreshFeedJob;
use App\Models\Feed;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\GeneratesTestData;
use Tests\TestCase;

final class FeedControllerTest extends TestCase
{
    use RefreshDatabase;
    use GeneratesTestData;

    protected function setUp(): void
    {
        parent::setUp();
        $this->generateStableTestData();
    }

    public function test_get_feed(): void
    {
        $resp = $this->get('/feed?url=' . urlencode('http://example.com/a.xml'));
        $resp->assertOk();
        $resp->assertJson([
            'name' => 'Feed A',
            'color' => '#F00',
            'tags' => ['#Tech', '#News'],
            'url' => 'http://example.com/a.xml'
        ]);
    }

    public function test_non_existing_feed(): void
    {
        $resp = $this->get('/feed?url=' . urlencode('http://example.com/abc.xml'));
        $resp->assertNotFound();
    }

    public function test_refresh_starts_reload_even_when_feed_is_fresh(): void
    {
        Feed::query()->update(['last_fetched_at' => time()]);
        config(['app.feed_update_frequency' => 60]);
        Queue::fake();

        $this->get('/')->assertOk();
        Queue::assertNotPushed(RefreshFeedJob::class);

        $this->postJson('/feed', ['url' => 'http://example.com/a.xml'])
            ->assertOk()
            ->assertJson([
                'url' => 'http://example.com/a.xml',
                'reloading' => true,
            ]);

        Queue::assertPushed(RefreshFeedJob::class);
    }

    public function test_refresh_of_unknown_feed_is_not_found(): void
    {
        Queue::fake();

        $this->postJson('/feed', ['url' => 'http://example.com/abc.xml'])->assertNotFound();
        Queue::assertNotPushed(RefreshFeedJob::class);
    }

    public function test_refresh_without_url_is_rejected(): void
    {
        $this->postJson('/feed', [])->assertStatus(422)->assertJsonValidationErrors('url');
    }

    public function test_refresh_without_csrf_token_is_rejected(): void
    {
        $this->app['env'] = 'production';

        $this->post('/feed', ['url' => 'http://example.com/a.xml'])->assertStatus(419);
    }
}
