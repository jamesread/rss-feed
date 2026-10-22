<?php

namespace Tests\Feature\Jobs;

use App\Config\AppSettings;
use App\Jobs\GenerateShareTextJob;
use App\Models\SharedPost;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Tests\TestCase;

final class GenerateShareTextJobTest extends TestCase
{
    use RefreshDatabase;

    protected const string URL = 'https://example.com/article';

    protected function makeEntry(array $overrides = []): SharedPost
    {
        return SharedPost::query()->create(array_merge([
            'url' => self::URL,
            'title' => 'An article',
            'description' => 'About it',
            'feed' => ['name' => 'Tech News'],
            'feeds' => [],
            'status' => SharedPost::PENDING,
            'shared_at' => time(),
        ], $overrides));
    }

    protected function withKey(): void
    {
        app(AppSettings::class)->save(['ai_api_key' => 'sk-1']);
    }

    public function test_pending_entry_gets_the_generated_text(): void
    {
        $this->withKey();
        Http::fake(['*' => Http::response(['choices' => [['message' => ['content' => 'Generated post']]]])]);
        $this->makeEntry();

        GenerateShareTextJob::dispatch(self::URL);

        $entry = SharedPost::query()->first();
        $this->assertEquals('Generated post', $entry->text);
        $this->assertEquals(SharedPost::READY, $entry->status);
        $this->assertNull($entry->error);
    }

    public function test_ready_entry_is_not_generated_again(): void
    {
        $this->withKey();
        Http::fake();
        $this->makeEntry(['status' => SharedPost::READY, 'text' => 'Edited text']);

        GenerateShareTextJob::dispatch(self::URL);

        Http::assertNothingSent();
        $this->assertEquals('Edited text', SharedPost::query()->first()->text);
    }

    public function test_removed_entry_is_not_generated(): void
    {
        $this->withKey();
        Http::fake();
        $this->makeEntry();
        SharedPost::query()->delete();

        GenerateShareTextJob::dispatch(self::URL);

        Http::assertNothingSent();
        $this->assertEquals(0, SharedPost::query()->count());
    }

    public function test_rejected_key_marks_the_entry_failed(): void
    {
        $this->withKey();
        Http::fake(['*' => Http::response([], 401)]);
        $this->makeEntry();

        GenerateShareTextJob::dispatch(self::URL);

        $entry = SharedPost::query()->first();
        $this->assertEquals(SharedPost::FAILED, $entry->status);
        $this->assertStringContainsString('rejected the API key', $entry->error);
        $this->assertNull($entry->text);
    }

    public function test_missing_setup_marks_the_entry_failed(): void
    {
        $this->makeEntry();

        GenerateShareTextJob::dispatch(self::URL);

        $entry = SharedPost::query()->first();
        $this->assertEquals(SharedPost::FAILED, $entry->status);
        $this->assertEquals('Set up an AI provider in Settings → Social Media.', $entry->error);
    }

    public function test_failed_hook_marks_a_pending_entry_failed(): void
    {
        $this->makeEntry();

        (new GenerateShareTextJob(self::URL))->failed(new RuntimeException('Timed out'));

        $entry = SharedPost::query()->first();
        $this->assertEquals(SharedPost::FAILED, $entry->status);
        $this->assertEquals('The text could not be generated.', $entry->error);
    }
}
