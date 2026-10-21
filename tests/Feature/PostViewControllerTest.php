<?php

namespace Tests\Feature;

use App\Models\Feed;
use App\Models\Post;
use App\Models\StarredPost;
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

    protected function makeRecentPosts(): void
    {
        Post::query()->update(['published_at' => time() - (40 * 86400)]);
        $feedA = Feed::query()->where('url', 'http://example.com/a.xml')->first();
        Post::factory()->create(['feed_id' => $feedA->id, 'title' => 'Fresh partnership news', 'published_at' => time() - 3600]);
        Post::factory()->create(['feed_id' => $feedA->id, 'title' => 'Fresh weather news', 'published_at' => time() - 7200]);
        Post::factory()->create(['feed_id' => $feedA->id, 'title' => 'Partnership from last week', 'published_at' => time() - (3 * 86400)]);
    }

    public function test_since_24h_lists_only_recent_posts(): void
    {
        $this->makeRecentPosts();

        $resp = $this->get('/?since=24h');
        $resp->assertInertia(function (Assert $page) {
            $page->where('since', '24h');
            $page->has('posts', 2);
        });

        $this->get('/?since=7d')->assertInertia(fn (Assert $page) => $page->has('posts', 3));
        $this->get('/?since=30d')->assertInertia(fn (Assert $page) => $page->has('posts', 3));
    }

    public function test_unknown_since_lists_all_posts(): void
    {
        $this->makeRecentPosts();

        $resp = $this->get('/?since=1y');
        $resp->assertInertia(function (Assert $page) {
            $page->where('since', '');
            $page->has('posts', 100);
        });
    }

    public function test_since_combines_with_search_on_tag_view(): void
    {
        $this->makeRecentPosts();

        $resp = $this->get('/t/Tech?since=7d&query=partnership');
        $resp->assertInertia(function (Assert $page) {
            $page->where('since', '7d');
            $page->where('search', 'partnership');
            $page->has('posts', 2);
        });

        $this->get('/t/Animals?since=7d&query=partnership')->assertInertia(fn (Assert $page) => $page->has('posts', 0));
    }

    public function test_has_more_and_collapsed_feeds_in_page_data(): void
    {
        $feedA = Feed::query()->where('url', 'http://example.com/a.xml')->first();
        $feedB = Feed::query()->where('url', 'http://example.com/b.xml')->first();
        Post::factory()->create(['feed_id' => $feedA->id, 'title' => 'Shared story in feeds A and B', 'url' => 'https://news.test/shared', 'published_at' => time() + 20]);
        Post::factory()->create(['feed_id' => $feedB->id, 'title' => 'Shared story in feeds A and B', 'url' => 'https://news.test/shared', 'published_at' => time() + 10]);

        $this->get('/t/News')->assertInertia(function (Assert $page) {
            $page->where('hasMore', true);
            // 100 posts loaded, the two copies of the shared story collapse into one
            $page->has('posts', 99);
            $page->where('posts.0.title', 'Shared story in feeds A and B');
            $page->where('posts.0.feeds.0.name', 'Feed A');
            $page->where('posts.0.feeds.1.name', 'Feed B');
            $page->where('posts.1.title', fn ($title) => $title !== 'Shared story in feeds A and B');
        });

        $this->get('/t/News?page=2')->assertInertia(fn (Assert $page) => $page->where('hasMore', false));
    }

    public function test_starred_view_renders_without_starred_posts(): void
    {
        $this->get('/starred')->assertInertia(function (Assert $page) {
            $page->component('Posts');
            $page->where('starred', true);
            $page->has('posts', 0);
            $page->has('feeds', 3);
            $page->where('hasMore', false);
            $page->where('starredUrls', []);
        });
    }

    public function test_starred_view_lists_stored_posts_newest_star_first(): void
    {
        $this->starPost('https://example.com/old', 100);
        $this->starPost('https://example.com/new', 300);
        // Not a post in the database, as if it was pruned
        $this->starPost('https://example.com/pruned', 200);

        $this->get('/starred')->assertInertia(function (Assert $page) {
            $page->has('posts', 3);
            $page->where('posts.0.url', 'https://example.com/new');
            $page->where('posts.1.url', 'https://example.com/pruned');
            $page->where('posts.2.url', 'https://example.com/old');
            $page->where('posts.0.feed.name', 'Feed A');
            $page->has('starredUrls', 3);
        });
    }

    public function test_posts_views_pass_starred_urls(): void
    {
        $this->starPost('https://example.com/a', 100);

        $this->get('/')->assertInertia(fn (Assert $page) => $page->where('starredUrls', ['https://example.com/a']));
        $this->get('/t/Tech')->assertInertia(fn (Assert $page) => $page->where('starredUrls', ['https://example.com/a']));
    }

    protected function starPost(string $url, int $starredAt): void
    {
        StarredPost::query()->create([
            'url' => $url,
            'title' => 'Starred ' . $url,
            'description' => '',
            'published_at' => 10,
            'feed' => ['name' => 'Feed A', 'color' => '#F00', 'url' => 'https://example.com/a.xml', 'tags' => [], 'favicon' => ''],
            'feeds' => [],
            'starred_at' => $starredAt,
        ]);
    }
}
