<?php

namespace Tests\Feature;

use App\Config\ConfiguredFeedList;
use App\Config\ConfiguredFeedProvider;
use App\Models\Feed;
use App\Models\Post;
use App\Rss\PostProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class PostProviderTest extends TestCase
{
    use RefreshDatabase;

    protected ConfiguredFeedList $feeds;

    /** @var array<string, Feed> */
    protected array $feedModels = [];

    protected function setUp(): void
    {
        parent::setUp();
        $provider = new ConfiguredFeedProvider();
        $provider->loadFromString(implode("\n", [
            'https://acme.test/feed Acme-News[#00f] #eco',
            'https://globex.test/feed Globex-News #eco',
            'https://initech.test/feed Initech-News #eco',
        ]));
        $this->feeds = $provider->getAll();
        foreach (Feed::all() as $feed) {
            $this->feedModels[$feed->url] = $feed;
        }
    }

    protected function makePost(string $feedUrl, string $title, string $url, int $age): Post
    {
        return Post::factory()->create([
            'feed_id' => $this->feedModels[$feedUrl]->id,
            'title' => $title,
            'url' => $url,
            'published_at' => time() - $age,
        ]);
    }

    protected function latest(int $count = 100, int $page = 1): array
    {
        return app(PostProvider::class)->getLatest($this->feeds, $count, $page);
    }

    public function test_same_url_in_two_feeds_is_shown_once(): void
    {
        $this->makePost('https://globex.test/feed', 'Story A', 'https://news.test/a', 100);
        $this->makePost('https://acme.test/feed', 'Story A', 'https://news.test/a', 50);

        ['posts' => $posts] = $this->latest();

        $this->assertCount(1, $posts);
        $this->assertEquals('Acme-News', $posts[0]->feed->name);
        $this->assertEquals(['Acme-News', 'Globex-News'], array_column($posts[0]->feeds, 'name'));
        $this->assertEquals('#00f', $posts[0]->feeds[0]['color']);
        $this->assertEquals('https://globex.test/feed', $posts[0]->feeds[1]['url']);
    }

    public function test_titles_differing_only_in_punctuation_are_merged(): void
    {
        $this->makePost('https://acme.test/feed', 'Acme, Globex sign AI partnership', 'https://one.test/x', 10);
        $this->makePost('https://globex.test/feed', 'Acme & Globex sign AI partnership!', 'https://two.test/y', 20);

        $this->assertCount(1, $this->latest()['posts']);
    }

    public function test_short_identical_titles_are_not_merged(): void
    {
        $this->makePost('https://acme.test/feed', 'Weekly update', 'https://one.test/x', 10);
        $this->makePost('https://globex.test/feed', 'Weekly update', 'https://two.test/y', 20);

        $this->assertCount(2, $this->latest()['posts']);
    }

    public function test_story_in_three_feeds_keeps_newest_position_and_feed_order(): void
    {
        $this->makePost('https://initech.test/feed', 'Other story about something else', 'https://news.test/o', 5);
        $this->makePost('https://acme.test/feed', 'Shared story in three feeds today', 'https://news.test/s1', 10);
        $this->makePost('https://globex.test/feed', 'Unrelated news item for testing', 'https://news.test/u', 15);
        $this->makePost('https://globex.test/feed', 'Shared story in three feeds today', 'https://news.test/s2', 20);
        $this->makePost('https://initech.test/feed', 'Shared story in three feeds today', 'https://news.test/s3', 30);

        ['posts' => $posts] = $this->latest();

        $this->assertEquals([
            'Other story about something else',
            'Shared story in three feeds today',
            'Unrelated news item for testing',
        ], $posts->pluck('title')->all());
        $this->assertEquals(['Acme-News', 'Globex-News', 'Initech-News'], array_column($posts[1]->feeds, 'name'));
        $this->assertEquals(['Initech-News'], array_column($posts[0]->feeds, 'name'));
    }

    public function test_has_more_reflects_posts_beyond_the_page(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->makePost('https://acme.test/feed', "Story number {$i}", "https://news.test/{$i}", $i * 10);
        }

        $this->assertTrue($this->latest(4)['hasMore']);
        $this->assertFalse($this->latest(5)['hasMore']);
        $this->assertFalse($this->latest(4, 2)['hasMore']);
        $this->assertCount(1, $this->latest(4, 2)['posts']);
    }
}
