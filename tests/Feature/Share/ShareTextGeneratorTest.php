<?php

namespace Tests\Feature\Share;

use App\Config\AppSettings;
use App\Models\SharedPost;
use App\Share\ShareTextException;
use App\Share\ShareTextGenerator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

final class ShareTextGeneratorTest extends TestCase
{
    use RefreshDatabase;

    protected function sharedPost(): SharedPost
    {
        return new SharedPost([
            'url' => 'https://example.com/article',
            'title' => 'An article',
            'description' => 'What the article is about.',
            'feed' => ['name' => 'Tech News'],
        ]);
    }

    protected function configure(array $values): void
    {
        app(AppSettings::class)->save(array_merge(['ai_system_prompt' => 'Be brief.'], $values));
    }

    protected function generate(): string
    {
        return app(ShareTextGenerator::class)->generate($this->sharedPost());
    }

    protected function assertUserMessage(string $message): void
    {
        $this->assertEquals("Title: An article\nSource: Tech News\nSummary: What the article is about.\nLink: https://example.com/article", $message);
    }

    public function test_openai_request_and_response(): void
    {
        $this->configure(['ai_provider' => 'openai', 'ai_model' => 'gpt-test', 'ai_api_key' => 'sk-1']);
        Http::fake(['https://api.openai.com/v1/chat/completions' => Http::response(['choices' => [['message' => ['content' => "  Hello LinkedIn \n"]]]])]);

        $this->assertEquals('Hello LinkedIn', $this->generate());

        Http::assertSent(function (Request $request) {
            $this->assertEquals('Bearer sk-1', $request->header('Authorization')[0]);
            $this->assertEquals('gpt-test', $request['model']);
            $this->assertEquals(['role' => 'system', 'content' => 'Be brief.'], $request['messages'][0]);
            $this->assertEquals('user', $request['messages'][1]['role']);
            $this->assertUserMessage($request['messages'][1]['content']);
            return true;
        });
    }

    public function test_openrouter_uses_its_endpoint(): void
    {
        $this->configure(['ai_provider' => 'openrouter', 'ai_model' => 'meta/llama', 'ai_api_key' => 'or-1']);
        Http::fake(['https://openrouter.ai/api/v1/chat/completions' => Http::response(['choices' => [['message' => ['content' => 'Routed']]]])]);

        $this->assertEquals('Routed', $this->generate());
        Http::assertSent(fn (Request $request) => $request->header('Authorization')[0] === 'Bearer or-1');
    }

    public function test_anthropic_request_and_response(): void
    {
        $this->configure(['ai_provider' => 'anthropic', 'ai_model' => 'claude-test', 'ai_api_key' => 'ant-1']);
        Http::fake(['https://api.anthropic.com/v1/messages' => Http::response(['content' => [
            ['type' => 'text', 'text' => 'Part one'],
            ['type' => 'thinking', 'thinking' => 'ignored'],
            ['type' => 'text', 'text' => 'Part two'],
        ]])]);

        $this->assertEquals("Part one\nPart two", $this->generate());

        Http::assertSent(function (Request $request) {
            $this->assertEquals('ant-1', $request->header('x-api-key')[0]);
            $this->assertEquals('2023-06-01', $request->header('anthropic-version')[0]);
            $this->assertFalse($request->hasHeader('Authorization'));
            $this->assertEquals('claude-test', $request['model']);
            $this->assertEquals('Be brief.', $request['system']);
            $this->assertGreaterThan(0, $request['max_tokens']);
            $this->assertUserMessage($request['messages'][0]['content']);
            return true;
        });
    }

    public function test_compatible_provider_without_key_sends_no_authorization(): void
    {
        $this->configure(['ai_provider' => 'openai_compatible', 'ai_endpoint' => 'http://localhost:11434/v1', 'ai_model' => 'llama3']);
        Http::fake(['http://localhost:11434/v1/chat/completions' => Http::response(['choices' => [['message' => ['content' => 'Local']]]])]);

        $this->assertEquals('Local', $this->generate());
        Http::assertSent(fn (Request $request) => !$request->hasHeader('Authorization'));
    }

    public function test_missing_key_asks_to_set_up_the_provider(): void
    {
        Http::fake();

        $this->expectExceptionObject(new ShareTextException('Set up an AI provider in Settings → Social Media.'));
        try {
            $this->generate();
        } finally {
            Http::assertNothingSent();
        }
    }

    public function test_rejected_key(): void
    {
        $this->configure(['ai_api_key' => 'bad']);
        Http::fake(['*' => Http::response(['error' => 'invalid key'], 401)]);

        $this->expectExceptionObject(new ShareTextException('The AI provider rejected the API key (401).'));
        $this->generate();
    }

    public function test_unreachable_provider(): void
    {
        $this->configure(['ai_api_key' => 'sk-1']);
        Http::fake(fn () => throw new ConnectionException('timeout'));

        $this->expectExceptionObject(new ShareTextException('The AI provider could not be reached.'));
        $this->generate();
    }

    public function test_empty_reply(): void
    {
        $this->configure(['ai_api_key' => 'sk-1']);
        Http::fake(['*' => Http::response(['choices' => [['message' => ['content' => '   ']]]])]);

        $this->expectExceptionObject(new ShareTextException('The AI provider returned no text.'));
        $this->generate();
    }
}
