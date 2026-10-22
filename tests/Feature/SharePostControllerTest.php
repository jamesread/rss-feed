<?php

namespace Tests\Feature;

use App\Jobs\GenerateShareTextJob;
use App\Models\SharedPost;
use App\Models\StarredPost;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

final class SharePostControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
        Storage::fake('public');
    }

    protected function star(int $n, array $overrides = []): StarredPost
    {
        return StarredPost::query()->create(array_merge([
            'url' => "https://example.com/{$n}",
            'title' => "Post {$n}",
            'description' => 'About it',
            'published_at' => 1000 + $n,
            'thumbnail' => '',
            'source' => '',
            'feed' => ['name' => 'Feed A', 'color' => '#f00', 'url' => 'https://example.com/feed.xml', 'tags' => ['#a'], 'favicon' => ''],
            'feeds' => [],
            'starred_at' => time(),
        ], $overrides));
    }

    protected function share(int $n, array $overrides = []): SharedPost
    {
        return SharedPost::query()->create(array_merge([
            'url' => "https://example.com/{$n}",
            'title' => "Post {$n}",
            'description' => 'About it',
            'feed' => ['name' => 'Feed A'],
            'feeds' => [],
            'status' => SharedPost::READY,
            'text' => 'Generated',
            'shared_at' => 1000 + $n,
        ], $overrides));
    }

    public function test_adding_a_starred_post_copies_it_and_queues_generation(): void
    {
        Storage::disk('public')->put('starred/abc.jpg', 'image');
        $this->star(1, ['thumbnail' => 'starred/abc.jpg']);

        $this->postJson('/share', ['url' => 'https://example.com/1'])->assertNoContent();

        $entry = SharedPost::query()->sole();
        $this->assertEquals('Post 1', $entry->title);
        $this->assertEquals('Feed A', $entry->feed['name']);
        $this->assertEquals(SharedPost::PENDING, $entry->status);
        $this->assertNull($entry->text);
        $this->assertEquals('shared/' . sha1('https://example.com/1') . '.jpg', $entry->thumbnail);
        Storage::disk('public')->assertExists($entry->thumbnail);
        Queue::assertPushed(GenerateShareTextJob::class, 1);
    }

    public function test_only_starred_posts_can_be_added(): void
    {
        $this->postJson('/share', ['url' => 'https://example.com/9'])->assertUnprocessable()->assertJsonValidationErrors('url');

        $this->assertEquals(0, SharedPost::query()->count());
        Queue::assertNothingPushed();
    }

    public function test_adding_twice_keeps_one_entry_and_generates_once(): void
    {
        $this->star(1);

        $this->postJson('/share', ['url' => 'https://example.com/1'])->assertNoContent();
        $this->postJson('/share', ['url' => 'https://example.com/1'])->assertNoContent();

        $this->assertEquals(1, SharedPost::query()->count());
        Queue::assertPushed(GenerateShareTextJob::class, 1);
    }

    public function test_unstarring_keeps_the_entry_and_its_thumbnail(): void
    {
        Storage::disk('public')->put('starred/abc.jpg', 'image');
        $this->star(1, ['thumbnail' => 'starred/abc.jpg']);
        $this->postJson('/share', ['url' => 'https://example.com/1'])->assertNoContent();

        $this->deleteJson('/starred', ['url' => 'https://example.com/1'])->assertNoContent();

        $entry = SharedPost::query()->sole();
        Storage::disk('public')->assertMissing('starred/abc.jpg');
        Storage::disk('public')->assertExists($entry->thumbnail);
    }

    public function test_edit_saves_the_text_of_a_generated_entry(): void
    {
        $this->share(1);

        $this->putJson('/share', ['url' => 'https://example.com/1', 'text' => 'My version'])->assertNoContent();

        $this->assertEquals('My version', SharedPost::query()->sole()->text);
    }

    public function test_edit_is_rejected_while_pending_or_empty(): void
    {
        $this->share(1, ['status' => SharedPost::PENDING, 'text' => null]);
        $this->share(2);

        $this->putJson('/share', ['url' => 'https://example.com/1', 'text' => 'Too early'])->assertUnprocessable()->assertJsonValidationErrors('text');
        $this->putJson('/share', ['url' => 'https://example.com/2', 'text' => ''])->assertUnprocessable()->assertJsonValidationErrors('text');
        $this->putJson('/share', ['url' => 'https://example.com/3', 'text' => 'Unknown'])->assertUnprocessable()->assertJsonValidationErrors('url');

        $this->assertNull(SharedPost::query()->where('url', 'https://example.com/1')->value('text'));
        $this->assertEquals('Generated', SharedPost::query()->where('url', 'https://example.com/2')->value('text'));
    }

    public function test_retry_is_only_allowed_after_a_failure(): void
    {
        $this->share(1);
        $this->share(2, ['status' => SharedPost::FAILED, 'text' => null, 'error' => 'No key']);

        $this->postJson('/share/retry', ['url' => 'https://example.com/1'])->assertUnprocessable();
        Queue::assertNothingPushed();

        $this->postJson('/share/retry', ['url' => 'https://example.com/2'])->assertNoContent();
        $entry = SharedPost::query()->where('url', 'https://example.com/2')->sole();
        $this->assertEquals(SharedPost::PENDING, $entry->status);
        $this->assertNull($entry->error);
        Queue::assertPushed(GenerateShareTextJob::class, 1);
    }

    public function test_remove_deletes_the_entry_and_keeps_the_star(): void
    {
        $this->star(1);
        Storage::disk('public')->put('shared/x.jpg', 'image');
        $this->share(1, ['thumbnail' => 'shared/x.jpg']);

        $this->deleteJson('/share', ['url' => 'https://example.com/1'])->assertNoContent();

        $this->assertEquals(0, SharedPost::query()->count());
        Storage::disk('public')->assertMissing('shared/x.jpg');
        $this->assertEquals(1, StarredPost::query()->count());
    }

    public function test_share_view_lists_newest_first(): void
    {
        $this->share(1);
        $this->share(2, ['status' => SharedPost::FAILED, 'text' => null, 'error' => 'No key']);

        $this->get('/share')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Share')
            ->has('posts', 2)
            ->where('posts.0.url', 'https://example.com/2')
            ->where('posts.0.status', 'failed')
            ->where('posts.0.error', 'No key')
            ->where('posts.1.text', 'Generated'));
    }

    public function test_request_without_csrf_token_is_rejected(): void
    {
        $this->star(1);
        $this->app['env'] = 'production';

        $this->post('/share', ['url' => 'https://example.com/1'])->assertStatus(419);
        $this->assertEquals(0, SharedPost::query()->count());
    }
}
