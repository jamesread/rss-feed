<?php

namespace Tests\Feature;

use App\Models\Feed;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\GeneratesTestData;
use Tests\TestCase;

final class PostViewControllerTest extends TestCase
{
    use RefreshDatabase;
    use GeneratesTestData;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
        $this->generateStableTestData();
    }

    public function test_home(): void
    {
        // Standard main page
        $resp = $this->get('/');
        $resp->assertInertia(function (Assert $page) {
            $page->component('Posts');
            $page->has('feeds', 3);
            $page->has('posts', 100);
            $page->where('page', 1);
            $page->where('search', '');
            $page->where('feeds.0.name', 'Feed A');
            $page->where('feeds.0.color', '#F00');
            $page->where('feeds.0.tags.0', '#Tech');
        });

        // Pagination test
        $resp = $this->get('/?page=2');
        $resp->assertInertia(function (Assert $page) {
            $page->component('Posts');
            $page->has('feeds', 3);
            $page->has('posts', 50);
            $page->where('page', 2);
        });

        // Search test
        $resp = $this->get('/?query=Special+title+for');
        $resp->assertInertia(function (Assert $page) {
            $page->component('Posts');
            $page->has('feeds', 3);
            $page->has('posts', 3);
            $page->where('search', 'Special title for');
        });
    }

    public function test_tag(): void
    {
        $resp = $this->get('/t/News');
        $resp->assertInertia(function (Assert $page) {
            $page->component('Posts');
            $page->has('feeds', 2);
            $page->has('posts', 100);
            $page->where('page', 1);
            $page->where('search', '');
            $page->where('tag', 'News');
        });
    }

    public function test_feed(): void
    {
        $resp = $this->get('/f/' . urlencode(urlencode('http://example.com/b.xml')));
        $resp->assertInertia(function (Assert $page) {
            $page->component('Posts');
            $page->has('feeds', 1);
            $page->has('posts', 50);
            $page->where('page', 1);
            $page->where('search', '');
            $page->where('tag', '');
            $page->where('feed', 'http://example.com/b.xml');
        });
    }

    public function test_feed_favicon_is_included(): void
    {
        Feed::query()->where('url', 'http://example.com/b.xml')->update(['favicon' => 'favicons/2.png', 'favicon_checked_at' => 1792483618]);

        $resp = $this->get('/');
        $resp->assertInertia(function (Assert $page) {
            $page->where('feeds.0.favicon', '');
            $page->where('feeds.1.favicon', 'favicons/2.png?v=1792483618');
            $page->where('posts.0.feed.favicon', fn ($favicon) => is_string($favicon));
        });
    }

    public function test_feed_fetch_status_is_included(): void
    {
        Feed::query()->where('url', 'http://example.com/b.xml')->update([
            'last_fetch_error' => 'HTTP 404',
            'last_fetch_succeeded_at' => 1792483618,
        ]);

        $resp = $this->get('/');
        $resp->assertInertia(function (Assert $page) {
            $page->where('feeds.0.fetch_error', '');
            $page->where('feeds.1.fetch_error', 'HTTP 404');
            $page->where('feeds.1.fetch_succeeded_at', 1792483618);
        });
    }
}
