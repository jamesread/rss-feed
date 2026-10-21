<?php

namespace Tests\Feature;

use App\Models\Feed;
use App\Models\Post;
use App\Models\StarredPost;
use App\Rss\PostPruner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

final class StarredPostControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function snapshot(int $n, array $overrides = []): array
    {
        return array_merge([
            'url' => "https://example.com/{$n}",
            'title' => "Post {$n}",
            'description' => str_repeat('x', 800),
            'published_at' => 1000 + $n,
            'thumbnail' => '',
            'source' => 'Example News',
            'feed' => ['name' => 'Feed A', 'color' => '#f00', 'url' => 'https://example.com/feed.xml', 'tags' => ['#a'], 'favicon' => 'favicons/1.png', 'extra' => 'dropped'],
            'feeds' => [['name' => 'Feed A', 'color' => '#f00', 'url' => 'https://example.com/feed.xml', 'tags' => ['#a']]],
        ], $overrides);
    }

    public function test_star_stores_snapshot(): void
    {
        $this->postJson('/starred', $this->snapshot(1))->assertNoContent();

        $starred = StarredPost::query()->sole();
        $this->assertEquals('https://example.com/1', $starred->url);
        $this->assertEquals(500, mb_strlen($starred->description));
        $this->assertEqualsWithDelta(time(), $starred->starred_at, 5);
        $this->assertEquals(['name' => 'Feed A', 'color' => '#f00', 'url' => 'https://example.com/feed.xml', 'tags' => ['#a'], 'favicon' => 'favicons/1.png'], $starred->feed);
        $this->assertEquals([['name' => 'Feed A', 'color' => '#f00', 'url' => 'https://example.com/feed.xml']], $starred->feeds);

        $json = $starred->toArray();
        $this->assertArrayNotHasKey('id', $json);
        foreach (['url', 'title', 'description', 'published_at', 'thumbnail', 'source', 'starred_at', 'feed', 'feeds'] as $key) {
            $this->assertArrayHasKey($key, $json);
        }
    }

    public function test_star_twice_keeps_first_copy(): void
    {
        $this->postJson('/starred', $this->snapshot(1))->assertNoContent();
        StarredPost::query()->update(['starred_at' => 50]);

        $this->postJson('/starred', $this->snapshot(1, ['title' => 'Changed']))->assertNoContent();

        $starred = StarredPost::query()->sole();
        $this->assertEquals(50, $starred->starred_at);
        $this->assertEquals('Post 1', $starred->title);
    }

    public function test_unstar(): void
    {
        $this->postJson('/starred', $this->snapshot(1))->assertNoContent();
        $this->deleteJson('/starred', ['url' => 'https://example.com/1'])->assertNoContent();
        $this->assertEquals(0, StarredPost::query()->count());

        $this->deleteJson('/starred', ['url' => 'https://example.com/missing'])->assertNoContent();
    }

    public function test_unsafe_url_is_rejected(): void
    {
        $this->postJson('/starred', $this->snapshot(1, ['url' => 'javascript:alert(1)']))->assertStatus(422);
        $this->postJson('/starred/import', ['posts' => [$this->snapshot(1, ['url' => 'javascript:alert(1)'])]])->assertStatus(422);
        $this->assertEquals(0, StarredPost::query()->count());
    }

    public function test_import_adds_missing_posts_and_keeps_star_times(): void
    {
        $this->postJson('/starred', $this->snapshot(2))->assertNoContent();
        StarredPost::query()->update(['starred_at' => 50]);

        $this->postJson('/starred/import', ['posts' => [
            $this->snapshot(1, ['starred_at' => 10]),
            $this->snapshot(2, ['starred_at' => 20, 'title' => 'Changed']),
            $this->snapshot(3, ['starred_at' => 30]),
        ]])->assertNoContent();

        $stars = StarredPost::query()->orderBy('url')->get();
        $this->assertCount(3, $stars);
        $this->assertEquals([10, 50, 30], $stars->pluck('starred_at')->all());
        $this->assertEquals('Post 2', $stars[1]->title);
    }

    public function test_request_without_csrf_token_is_rejected(): void
    {
        // The CSRF check is skipped for unit tests, so run this request as production
        $this->app['env'] = 'production';

        $this->post('/starred', $this->snapshot(1))->assertStatus(419);
        $this->assertEquals(0, StarredPost::query()->count());
    }

    public function test_star_keeps_thumbnail_copy_until_unstarred(): void
    {
        $storage = Storage::fake('public');
        $storage->put('thumbs/1/2.jpeg', 'img');
        $copy = 'starred/' . sha1('https://example.com/1') . '.jpeg';

        $this->postJson('/starred', $this->snapshot(1, ['thumbnail' => 'thumbs/1/2.jpeg']))->assertNoContent();
        $this->assertEquals($copy, StarredPost::query()->sole()->thumbnail);
        $storage->assertExists($copy);

        $this->deleteJson('/starred', ['url' => 'https://example.com/1'])->assertNoContent();
        $storage->assertMissing($copy);
        $storage->assertExists('thumbs/1/2.jpeg');
    }

    public function test_thumbnail_copy_survives_pruning(): void
    {
        $storage = Storage::fake('public');
        $feed = Feed::query()->forceCreate(['url' => 'https://example.com/feed.xml', 'last_fetched_at' => 0, 'last_accessed_at' => 0]);
        $post = $feed->posts()->forceCreate(['url' => 'https://example.com/1', 'title' => 'Post 1', 'description' => '', 'published_at' => 100, 'guid' => '1']);
        $post->thumbnail = "thumbs/{$feed->id}/{$post->id}.jpeg";
        $post->save();
        $storage->put($post->thumbnail, 'img');

        $this->postJson('/starred', $this->snapshot(1, ['thumbnail' => $post->thumbnail]))->assertNoContent();
        app(PostPruner::class)->prune(1);

        $this->assertEquals(0, Post::query()->count());
        $storage->assertMissing($post->thumbnail);
        $storage->assertExists(StarredPost::query()->sole()->thumbnail);
    }

    public function test_missing_or_unsafe_thumbnail_is_not_kept(): void
    {
        $storage = Storage::fake('public');
        $storage->put('secret.txt', 'secret');

        $this->postJson('/starred', $this->snapshot(1, ['thumbnail' => 'thumbs/1/missing.jpeg']))->assertNoContent();
        $this->postJson('/starred', $this->snapshot(2, ['thumbnail' => '../secret.txt']))->assertNoContent();
        $this->postJson('/starred', $this->snapshot(3, ['thumbnail' => 'thumbs/../secret.txt']))->assertNoContent();

        $this->assertEquals(['', '', ''], StarredPost::query()->pluck('thumbnail')->all());
        $this->assertEmpty($storage->files('starred'));
    }
}
