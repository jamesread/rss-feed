<?php

namespace Tests\Feature;

use App\Models\StarredPost;
use App\Rss\RssParser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use SimpleXMLElement;
use Tests\TestCase;

final class StarredFeedTest extends TestCase
{
    use RefreshDatabase;

    protected function star(string $url, int $starredAt, array $overrides = []): StarredPost
    {
        return StarredPost::query()->create(array_merge([
            'url' => $url,
            'title' => 'Title of ' . $url,
            'description' => 'About ' . $url,
            'published_at' => 10,
            'feed' => ['name' => 'Tech News', 'color' => '#F00', 'url' => 'https://example.com/feed.xml', 'tags' => [], 'favicon' => ''],
            'feeds' => [],
            'starred_at' => $starredAt,
        ], $overrides));
    }

    protected function fetchFeed(array $headers = []): SimpleXMLElement
    {
        $response = $this->get('/favorites', $headers);
        $response->assertOk();
        $this->assertStringStartsWith('application/rss+xml', $response->headers->get('Content-Type'));

        $xml = simplexml_load_string($response->getContent());
        $this->assertNotFalse($xml, 'Feed is not well-formed XML');

        return $xml;
    }

    public function test_feed_lists_starred_posts_newest_star_first(): void
    {
        $this->star('https://example.com/old', 100);
        $this->star('https://example.com/rust', strtotime('2026-10-21 08:00:00 UTC'), ['title' => 'Rust 2.0 released']);

        $xml = $this->fetchFeed();
        $this->assertEquals('2.0', (string) $xml['version']);
        $this->assertCount(2, $xml->channel->item);

        $item = $xml->channel->item[0];
        $this->assertEquals('Rust 2.0 released', (string) $item->title);
        $this->assertEquals('https://example.com/rust', (string) $item->link);
        $this->assertEquals('https://example.com/rust', (string) $item->guid);
        $this->assertEquals('true', (string) $item->guid['isPermaLink']);
        $this->assertEquals('About https://example.com/rust', (string) $item->description);
        $this->assertEquals('Tech News', (string) $item->category);
        $this->assertEquals('Wed, 21 Oct 2026 08:00:00 +0000', (string) $item->pubDate);

        $this->assertEquals('https://example.com/old', (string) $xml->channel->item[1]->link);
    }

    public function test_empty_feed_is_valid(): void
    {
        $xml = $this->fetchFeed();
        $this->assertEquals(config('app.name') . ' – Starred', (string) $xml->channel->title);
        $this->assertCount(0, $xml->channel->item);
    }

    public function test_special_characters_are_escaped(): void
    {
        $this->star('https://example.com/a?x=1&y=2', 100, ['title' => 'AT&T <beta> "test"', 'description' => 'Fish & <chips>']);

        $item = $this->fetchFeed()->channel->item[0];
        $this->assertEquals('AT&T <beta> "test"', (string) $item->title);
        $this->assertEquals('Fish & <chips>', (string) $item->description);
        $this->assertEquals('https://example.com/a?x=1&y=2', (string) $item->link);
    }

    public function test_feed_follows_star_and_unstar(): void
    {
        $this->star('https://example.com/a', 100);
        $this->assertCount(1, $this->fetchFeed()->channel->item);

        $this->postJson('/starred', ['url' => 'https://example.com/c', 'title' => 'C'])->assertNoContent();
        $this->deleteJson('/starred', ['url' => 'https://example.com/a'])->assertNoContent();

        $items = $this->fetchFeed()->channel->item;
        $this->assertCount(1, $items);
        $this->assertEquals('https://example.com/c', (string) $items[0]->link);
    }

    public function test_links_follow_the_proxy_prefix(): void
    {
        $xml = $this->fetchFeed([
            'X-Forwarded-Host' => 'example.org',
            'X-Forwarded-Proto' => 'https',
            'X-Forwarded-Port' => '443',
            'X-Forwarded-Prefix' => '/rss',
        ]);

        $this->assertEquals('https://example.org/rss/starred', (string) $xml->channel->link);
        $self = $xml->channel->children('http://www.w3.org/2005/Atom')->link;
        $this->assertEquals('https://example.org/rss/favorites', (string) $self->attributes()['href']);
    }

    public function test_items_include_kept_thumbnails(): void
    {
        $storage = Storage::fake('public');
        $storage->put('starred/ab12.jpeg', str_repeat('x', 20480));
        $this->star('https://example.com/with', 300, ['thumbnail' => 'starred/ab12.jpeg']);
        $this->star('https://example.com/missing', 200, ['thumbnail' => 'starred/gone.jpeg']);
        $this->star('https://example.com/without', 100);

        $items = $this->fetchFeed([
            'X-Forwarded-Host' => 'news.example.com',
            'X-Forwarded-Proto' => 'https',
            'X-Forwarded-Port' => '443',
        ])->channel->item;

        $enclosure = $items[0]->enclosure;
        $this->assertEquals('https://news.example.com/storage/starred/ab12.jpeg', (string) $enclosure['url']);
        $this->assertEquals('image/jpeg', (string) $enclosure['type']);
        $this->assertEquals('20480', (string) $enclosure['length']);
        $media = $items[0]->children('http://search.yahoo.com/mrss/')->content;
        $this->assertEquals('https://news.example.com/storage/starred/ab12.jpeg', (string) $media->attributes()['url']);
        $this->assertEquals('image', (string) $media->attributes()['medium']);

        foreach ([1, 2] as $i) {
            $this->assertCount(0, $items[$i]->enclosure);
            $this->assertCount(0, $items[$i]->children('http://search.yahoo.com/mrss/'));
        }
    }

    public function test_feed_can_be_read_by_the_app_parser(): void
    {
        $this->star('https://example.com/a', 1_790_000_000, ['title' => 'First & best']);
        $this->star('https://example.com/b', 1_790_000_100, ['title' => 'Second']);

        $content = $this->get('/favorites')->getContent();
        $posts = (new RssParser())->rssDataToPosts($content);

        $this->assertCount(2, $posts);
        $this->assertEquals(['Second', 'First & best'], array_column($posts, 'title'));
        $this->assertEquals(['https://example.com/b', 'https://example.com/a'], array_column($posts, 'url'));
    }
}
