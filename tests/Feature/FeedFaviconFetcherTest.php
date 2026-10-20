<?php

namespace Tests\Feature;

use App\Models\Feed;
use App\Models\Post;
use App\Rss\FeedFaviconFetcher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

final class FeedFaviconFetcherTest extends TestCase
{
    use RefreshDatabase;

    protected string $png;
    protected string $ico;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        $this->png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAACklEQVR4nGMAAQAABQABDQottAAAAABJRU5ErkJggg==');
        $this->ico = base64_decode('AAABAAEAAQEAAAEAIABDAAAAFgAAAIlQTkcNChoKAAAADUlIRFIAAAABAAAAAQgGAAAAHxXEiQAAAApJREFUeJxjAAEAAAUAAQ0KLbQAAAAASUVORK5CYII=');
    }

    protected function fetch(Feed $feed, string $site = ''): bool
    {
        return app(FeedFaviconFetcher::class)->fetchAndStoreForFeed($feed, $site);
    }

    public function test_largest_declared_icon_is_stored(): void
    {
        Http::fake([
            'https://example.com/' => Http::response(<<<END
<html><head>
<link rel="icon" sizes="32x32" href="/icon-32.png">
<link rel="apple-touch-icon" href="/apple-touch-icon.png">
</head></html>
END),
            'https://example.com/apple-touch-icon.png' => Http::response($this->png),
            '*' => Http::response('', 404),
        ]);
        $feed = Feed::factory()->create(['url' => 'https://example.com/feed.xml']);

        $this->assertTrue($this->fetch($feed));

        $this->assertEquals("favicons/{$feed->id}.png", $feed->refresh()->favicon);
        Storage::disk('public')->assertExists("favicons/{$feed->id}.png");
        Http::assertNotSent(fn (Request $request) => str_ends_with($request->url(), 'icon-32.png'));
    }

    public function test_unquoted_link_attributes_are_parsed(): void
    {
        Http::fake([
            'https://example.com/' => Http::response(<<<END
<link rel=icon type=image/png sizes=64x64 href=/icons/favicon-64.png>
<link rel=icon type=image/png sizes=16x16 href=/icons/favicon-16.png>
END),
            'https://example.com/icons/favicon-64.png' => Http::response($this->png),
            '*' => Http::response('', 404),
        ]);
        $feed = Feed::factory()->create(['url' => 'https://example.com/feed.xml']);

        $this->assertTrue($this->fetch($feed));

        $this->assertEquals("favicons/{$feed->id}.png", $feed->refresh()->favicon);
    }

    public function test_falls_back_to_favicon_ico(): void
    {
        Http::fake([
            'https://example.com/' => Http::response('<html><head><title>No icons</title></head></html>'),
            'https://example.com/favicon.ico' => Http::response($this->ico),
            '*' => Http::response('', 404),
        ]);
        $feed = Feed::factory()->create(['url' => 'https://example.com/feed.xml']);

        $this->assertTrue($this->fetch($feed));

        $this->assertEquals("favicons/{$feed->id}.ico", $feed->refresh()->favicon);
        Storage::disk('public')->assertExists("favicons/{$feed->id}.ico");
    }

    public function test_svg_icon_is_rejected_and_favicon_ico_tried(): void
    {
        Http::fake([
            'https://example.com/' => Http::response('<link rel="icon" type="image/svg+xml" href="/icon.svg">'),
            'https://example.com/icon.svg' => Http::response('<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>'),
            'https://example.com/favicon.ico' => Http::response($this->ico),
            '*' => Http::response('', 404),
        ]);
        $feed = Feed::factory()->create(['url' => 'https://example.com/feed.xml']);

        $this->assertTrue($this->fetch($feed));

        $this->assertEquals("favicons/{$feed->id}.ico", $feed->refresh()->favicon);
        $this->assertEquals(["favicons/{$feed->id}.ico"], Storage::disk('public')->allFiles());
    }

    public function test_lookup_uses_the_newest_post_url_origin(): void
    {
        Http::fake([
            'https://example.com/' => Http::response('<link rel="icon" href="https://example.com/icon.png">'),
            'https://example.com/icon.png' => Http::response($this->png),
            '*' => Http::response('', 404),
        ]);
        $feed = Feed::factory()->create(['url' => 'https://example.org/feeds/site.xml']);
        Post::factory()->create(['feed_id' => $feed->id, 'url' => 'https://example.net/old', 'published_at' => time() - 5000]);
        Post::factory()->create(['feed_id' => $feed->id, 'url' => 'https://example.com/new-article', 'published_at' => time()]);

        $this->assertTrue($this->fetch($feed));

        Http::assertSent(fn (Request $request) => $request->url() === 'https://example.com/');
        Http::assertNotSent(fn (Request $request) => str_contains($request->url(), 'example.org'));
        Http::assertNotSent(fn (Request $request) => str_contains($request->url(), 'example.net'));
    }

    public function test_lookup_uses_feed_origin_without_posts(): void
    {
        Http::fake([
            'https://example.com/' => Http::response(''),
            'https://example.com/favicon.ico' => Http::response($this->ico),
            '*' => Http::response('', 404),
        ]);
        $feed = Feed::factory()->create(['url' => 'https://example.com/blog/feed.xml']);

        $this->assertTrue($this->fetch($feed));

        Http::assertSent(fn (Request $request) => $request->url() === 'https://example.com/');
    }

    public function test_checked_at_is_set_on_success_and_failure(): void
    {
        Http::fake([
            'https://example.com/' => Http::response(''),
            'https://example.com/favicon.ico' => Http::response($this->ico),
            '*' => Http::response('', 404),
        ]);
        $found = Feed::factory()->create(['url' => 'https://example.com/feed.xml']);
        $missing = Feed::factory()->create(['url' => 'https://example.org/feed.xml']);

        $this->assertTrue($this->fetch($found));
        $this->assertFalse($this->fetch($missing));

        $this->assertGreaterThan(time() - 10, $found->refresh()->favicon_checked_at);
        $this->assertGreaterThan(time() - 10, $missing->refresh()->favicon_checked_at);
        $this->assertEquals('', $missing->favicon);
    }

    public function test_private_site_is_not_requested(): void
    {
        Http::fake();
        $feed = Feed::factory()->create(['url' => 'http://192.168.1.10/feed.xml']);

        $this->assertFalse($this->fetch($feed));

        Http::assertNothingSent();
        $this->assertGreaterThan(time() - 10, $feed->refresh()->favicon_checked_at);
    }

    public function test_configured_site_origin_is_used(): void
    {
        Http::fake([
            'https://example.com/' => Http::response(''),
            'https://example.com/favicon.ico' => Http::response($this->ico),
            '*' => Http::response('', 404),
        ]);
        $feed = Feed::factory()->create(['url' => 'https://news.google.com/rss/search?q=Example']);
        Post::factory()->create(['feed_id' => $feed->id, 'url' => 'https://news.google.com/rss/articles/abc', 'published_at' => time()]);

        $this->assertTrue($this->fetch($feed, 'https://example.com/insights/'));

        $feed->refresh();
        $this->assertEquals("favicons/{$feed->id}.ico", $feed->favicon);
        $this->assertEquals('https://example.com/insights/', $feed->favicon_site);
        Http::assertSent(fn (Request $request) => $request->url() === 'https://example.com/');
        Http::assertNotSent(fn (Request $request) => str_contains($request->url(), 'insights'));
        Http::assertNotSent(fn (Request $request) => str_contains($request->url(), 'google'));
    }

    public function test_google_news_without_site_is_not_requested(): void
    {
        Http::fake();
        $feed = Feed::factory()->create(['url' => 'https://news.google.com/rss/search?q=Example']);
        Post::factory()->create(['feed_id' => $feed->id, 'url' => 'https://news.google.com/rss/articles/abc', 'published_at' => time()]);

        $this->assertFalse($this->fetch($feed));

        Http::assertNothingSent();
        $feed->refresh();
        $this->assertEquals('', $feed->favicon);
        $this->assertEquals('', $feed->favicon_site);
        $this->assertGreaterThan(time() - 10, $feed->favicon_checked_at);
    }

    public function test_stored_google_favicon_is_removed(): void
    {
        Http::fake();
        $feed = Feed::factory()->create(['url' => 'https://news.google.com/rss/search?q=Example']);
        Storage::disk('public')->put("favicons/{$feed->id}.ico", $this->ico);
        $feed->favicon = "favicons/{$feed->id}.ico";
        $feed->save();

        $this->assertFalse($this->fetch($feed));

        $this->assertEquals('', $feed->refresh()->favicon);
        Storage::disk('public')->assertMissing("favicons/{$feed->id}.ico");
        Http::assertNothingSent();
    }
}
