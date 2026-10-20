<?php

namespace Tests\Feature;

use App\Models\Post;
use App\Rss\PostThumbnailFetcher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

final class PostThumbnailFetcherTest extends TestCase
{
    use RefreshDatabase;

    public function test_successful_fetch(): void
    {
        $imageData = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAACklEQVR4nGMAAQAABQABDQottAAAAABJRU5ErkJggg==');
        Http::fake([
            'example.com/*' => Http::response(<<<END
<html>
<head>
<meta property="og:image" content="https://exampleb.com/image.png">
</head>
</html>
END),
            'exampleb.com/*' => Http::response($imageData),
        ]);
        $post = Post::factory()->create(['url' => 'http://example.com/cats']);

        $fetcher = new PostThumbnailFetcher();
        $result = $fetcher->fetchAndStoreForPost($post);

        $this->assertTrue($result);
        $expectedPath = storage_path("app/public/thumbs/{$post->feed_id}/{$post->id}.png");
        $this->assertFileExists($expectedPath);

        $content = file_get_contents($expectedPath);
        $this->assertEquals($imageData, $content);
        $this->assertEquals($post->thumbnail, "thumbs/{$post->feed_id}/{$post->id}.png");

        unlink($expectedPath);
    }

    public function test_svg_image_is_not_stored(): void
    {
        Storage::fake('public');
        Http::fake([
            'example.com/*' => Http::response('<meta property="og:image" content="https://exampleb.com/image.svg">'),
            'exampleb.com/*' => Http::response('<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>'),
        ]);
        $post = Post::factory()->create(['url' => 'http://example.com/cats']);

        $result = (new PostThumbnailFetcher())->fetchAndStoreForPost($post);

        $this->assertFalse($result);
        $this->assertEquals('', $post->refresh()->thumbnail);
        $this->assertEmpty(Storage::disk('public')->allFiles());
    }

    public function test_post_url_on_private_address_is_not_requested(): void
    {
        Http::fake();
        $post = Post::factory()->create(['url' => 'http://192.168.1.10/cats']);

        $result = (new PostThumbnailFetcher())->fetchAndStoreForPost($post);

        $this->assertFalse($result);
        Http::assertNothingSent();
    }

    public function test_redirect_to_private_address_is_not_followed(): void
    {
        $imageData = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAACklEQVR4nGMAAQAABQABDQottAAAAABJRU5ErkJggg==');
        Http::fake([
            'example.com/*' => Http::response('<meta property="og:image" content="https://exampleb.com/image.png">'),
            'exampleb.com/*' => Http::response('', 302, ['Location' => 'http://10.0.0.5/image.png']),
            '10.0.0.5/*' => Http::response($imageData),
        ]);
        $post = Post::factory()->create(['url' => 'http://example.com/cats']);

        $result = (new PostThumbnailFetcher())->fetchAndStoreForPost($post);

        $this->assertFalse($result);
        Http::assertNotSent(fn ($request) => str_contains($request->url(), '10.0.0.5'));
    }

    public function test_redirect_to_public_address_is_followed(): void
    {
        $imageData = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAACklEQVR4nGMAAQAABQABDQottAAAAABJRU5ErkJggg==');
        Http::fake([
            'example.com/*' => Http::response('', 302, ['Location' => 'https://example.org/article']),
            'example.org/*' => Http::response('<meta property="og:image" content="https://exampleb.com/image.png">'),
            'exampleb.com/*' => Http::response($imageData),
        ]);
        $post = Post::factory()->create(['url' => 'http://example.com/cats']);

        $result = (new PostThumbnailFetcher())->fetchAndStoreForPost($post);

        $this->assertTrue($result);
        unlink(storage_path("app/public/thumbs/{$post->feed_id}/{$post->id}.png"));
    }

    public function test_html_encoding_in_opengraph_url_is_decoded(): void
    {
        $imageData = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAACklEQVR4nGMAAQAABQABDQottAAAAABJRU5ErkJggg==');
        Http::fake([
            'example.com/*' => Http::response(<<<END
<html>
<head>
<meta property="og:image" content="https://exampleb.com/image.png?a=b&amp;c=d">
</head>
</html>
END),
            'https://exampleb.com/image.png?a=b&c=d' => Http::response($imageData),
            'exampleb.com/*' => Http::response('', 404),
        ]);
        $post = Post::factory()->create(['url' => 'http://example.com/cats']);

        $fetcher = new PostThumbnailFetcher();
        $result = $fetcher->fetchAndStoreForPost($post);
        $this->assertTrue($result);

        $expectedPath = storage_path("app/public/thumbs/{$post->feed_id}/{$post->id}.png");
        unlink($expectedPath);
    }
}
