<?php

namespace Tests\Feature\Jobs;

use App\Jobs\FetchFeedFaviconJob;
use App\Jobs\RefreshFeedJob;
use App\Models\Feed;
use App\Models\Post;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

final class RefreshFeedJobTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_stores_posts_for_the_given_feed(): void
    {
        $feed = Feed::factory()->create([
            'url' => 'https://example.com/feed.xml',
            'last_fetched_at' => time() - 50000,
        ]);
        $job = new RefreshFeedJob($feed);
        Http::fake([
            'example.com/*' => Http::response(<<<END
<?xml version="1.0" encoding="utf-8" standalone="yes"?>
<rss version="2.0">
  <channel>
    <item>
      <title>BookStack Release v22.06</title>
      <link>https://www.bookstackapp.com/blog/bookstack-release-v22-06/</link>
      <pubDate>Fri, 24 Jun 2022 11:00:00 +0000</pubDate>

      <guid>https://www.bookstackapp.com/blog/bookstack-release-v22-06/</guid>
      <description>A little description</description>
    </item>
  </channel>
</rss>
END),
            // Keep thumbnail and favicon lookups for the post offline
            '*' => Http::response('', 404),
        ]);

        $this->assertEquals(0, $feed->posts()->count());

        dispatch_sync($job);

        /** @var Post[] $posts */
        $posts = $feed->posts()->get();
        $this->assertCount(1, $posts);
        $this->assertGreaterThan(time() - 10, $feed->refresh()->last_fetched_at);

        $this->assertDatabaseHas('posts', [
            'feed_id' => $feed->id,
            'title' => 'BookStack Release v22.06',
            'url' => 'https://www.bookstackapp.com/blog/bookstack-release-v22-06/',
            'description' => 'A little description',
            'published_at' => 1656068400,
        ]);
    }

    public function test_unreachable_feed_completes_and_updates_last_fetched_at(): void
    {
        $feed = Feed::factory()->create([
            'url' => 'https://example.com/feed.xml',
            'last_fetched_at' => time() - 50000,
        ]);
        Http::fake(['example.com/*' => Http::failedConnection('Could not resolve host: example.com')]);

        dispatch_sync(new RefreshFeedJob($feed));

        $this->assertEquals(0, $feed->posts()->count());
        $this->assertGreaterThan(time() - 10, $feed->refresh()->last_fetched_at);
    }

    public function test_failed_fetch_records_error_and_keeps_last_success(): void
    {
        $lastSuccess = time() - 86400;
        $feed = Feed::factory()->create([
            'url' => 'https://example.com/feed.xml',
            'last_fetch_succeeded_at' => $lastSuccess,
        ]);
        Http::fake(['example.com/*' => Http::response('', 404)]);

        dispatch_sync(new RefreshFeedJob($feed));

        $feed->refresh();
        $this->assertEquals('HTTP 404', $feed->last_fetch_error);
        $this->assertEquals($lastSuccess, $feed->last_fetch_succeeded_at);
    }

    public function test_successful_fetch_clears_error(): void
    {
        $feed = Feed::factory()->create([
            'url' => 'https://example.com/feed.xml',
            'last_fetch_error' => 'HTTP 500',
            'last_fetch_succeeded_at' => 0,
        ]);
        Http::fake(['example.com/*' => Http::response('<rss version="2.0"><channel></channel></rss>')]);

        dispatch_sync(new RefreshFeedJob($feed));

        $feed->refresh();
        $this->assertEquals('', $feed->last_fetch_error);
        $this->assertGreaterThan(time() - 10, $feed->last_fetch_succeeded_at);
    }

    public function test_non_successful_response_is_logged(): void
    {
        $feed = Feed::factory()->create(['url' => 'https://example.com/feed.xml']);
        Http::fake(['example.com/*' => Http::response('', 404)]);
        Log::spy();

        dispatch_sync(new RefreshFeedJob($feed));

        Log::shouldHaveReceived('warning')->once()->with('Feed fetch failed', [
            'url' => 'https://example.com/feed.xml',
            'reason' => 'HTTP 404',
        ]);
    }

    public function test_connection_error_is_logged(): void
    {
        $feed = Feed::factory()->create(['url' => 'https://example.com/feed.xml']);
        Http::fake(['example.com/*' => Http::failedConnection('Could not resolve host: example.com')]);
        Log::spy();

        dispatch_sync(new RefreshFeedJob($feed));

        Log::shouldHaveReceived('warning')->once()->with('Feed fetch failed', [
            'url' => 'https://example.com/feed.xml',
            'reason' => 'Could not resolve host: example.com',
        ]);
    }

    public function test_successful_fetch_is_not_logged(): void
    {
        $feed = Feed::factory()->create(['url' => 'https://example.com/feed.xml']);
        Http::fake(['example.com/*' => Http::response('<rss version="2.0"><channel></channel></rss>')]);
        Log::spy();

        dispatch_sync(new RefreshFeedJob($feed));

        Log::shouldNotHaveReceived('warning');
    }

    public function test_favicon_job_is_dispatched_when_due(): void
    {
        $feed = Feed::factory()->create(['url' => 'https://example.com/feed.xml', 'favicon_checked_at' => 0]);
        Http::fake(['example.com/*' => Http::response('<rss version="2.0"><channel></channel></rss>')]);
        Queue::fake([FetchFeedFaviconJob::class]);

        dispatch_sync(new RefreshFeedJob($feed));

        Queue::assertPushed(FetchFeedFaviconJob::class, 1);
    }

    public function test_favicon_job_is_not_dispatched_when_recently_checked(): void
    {
        $feed = Feed::factory()->create([
            'url' => 'https://example.com/feed.xml',
            'favicon_checked_at' => time() - (2 * 86400),
        ]);
        Http::fake(['example.com/*' => Http::response('<rss version="2.0"><channel></channel></rss>')]);
        Queue::fake([FetchFeedFaviconJob::class]);

        dispatch_sync(new RefreshFeedJob($feed));

        Queue::assertNotPushed(FetchFeedFaviconJob::class);
    }

    public function test_favicon_is_looked_up_again_when_site_changes(): void
    {
        $feed = Feed::factory()->create([
            'url' => 'https://example.com/feed.xml',
            'favicon_checked_at' => time() - 86400,
        ]);
        Http::fake(['example.com/*' => Http::response('<rss version="2.0"><channel></channel></rss>')]);

        dispatch_sync(new RefreshFeedJob($feed, 'https://example.com/about'));

        $feed->refresh();
        $this->assertEquals('https://example.com/about', $feed->favicon_site);
        $this->assertGreaterThan(time() - 10, $feed->favicon_checked_at);
    }

    public function test_favicon_job_is_not_dispatched_when_site_unchanged(): void
    {
        $feed = Feed::factory()->create([
            'url' => 'https://example.com/feed.xml',
            'favicon_checked_at' => time() - (2 * 86400),
            'favicon_site' => 'https://example.com',
        ]);
        Http::fake(['example.com/*' => Http::response('<rss version="2.0"><channel></channel></rss>')]);
        Queue::fake([FetchFeedFaviconJob::class]);

        dispatch_sync(new RefreshFeedJob($feed, 'https://example.com'));

        Queue::assertNotPushed(FetchFeedFaviconJob::class);
    }

    public function test_favicon_job_is_not_dispatched_when_thumbnails_disabled(): void
    {
        config(['app.load_post_thumbnails' => false]);
        $feed = Feed::factory()->create(['url' => 'https://example.com/feed.xml', 'favicon_checked_at' => 0]);
        Http::fake(['example.com/*' => Http::response('<rss version="2.0"><channel></channel></rss>')]);
        Queue::fake([FetchFeedFaviconJob::class]);

        dispatch_sync(new RefreshFeedJob($feed));

        Queue::assertNotPushed(FetchFeedFaviconJob::class);
    }

    public function test_job_is_unique_per_feed(): void
    {
        $feedA = Feed::factory()->create(['url' => 'https://example.com/feed.xml']);
        $feedB = Feed::factory()->create(['url' => 'https://example-b.com/feed.xml']);

        Queue::fake();

        dispatch(new RefreshFeedJob($feedA));
        dispatch(new RefreshFeedJob($feedA));
        dispatch(new RefreshFeedJob($feedA));
        dispatch(new RefreshFeedJob($feedB));
        dispatch(new RefreshFeedJob($feedB));

        Queue::assertPushed(RefreshFeedJob::class, 2);
    }
}
