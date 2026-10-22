<?php

namespace App\Share;

use App\Config\AppSettings;
use App\Models\SharedPost;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Asks the configured AI provider for a LinkedIn post about a shared post.
 * Anthropic gets its messages API, every other provider the OpenAI chat completions API.
 */
class ShareTextGenerator
{
    protected const int TIMEOUT_SECONDS = 60;
    protected const int MAX_TOKENS = 1024;

    public function __construct(
        protected AppSettings $settings,
    ) {
    }

    /**
     * @throws ShareTextException
     */
    public function generate(SharedPost $post): string
    {
        $provider = $this->settings->aiProvider();
        $endpoint = rtrim($this->settings->aiEndpoint(), '/');
        $model = $this->settings->aiModel();
        $key = $this->settings->aiApiKey();

        // A local OpenAI compatible server may not need a key, the hosted providers always do
        if ($endpoint === '' || $model === '' || ($key === '' && $provider !== 'openai_compatible')) {
            throw new ShareTextException('Set up an AI provider in Settings → Social Media.');
        }

        $text = $provider === 'anthropic'
            ? $this->anthropic($endpoint, $model, $key, $post)
            : $this->openAi($endpoint, $model, $key, $post);

        $text = trim($text);
        if ($text === '') {
            throw new ShareTextException('The AI provider returned no text.');
        }

        return $text;
    }

    protected function openAi(string $endpoint, string $model, string $key, SharedPost $post): string
    {
        $request = $this->request();
        if ($key !== '') {
            $request = $request->withToken($key);
        }

        $response = $this->send(fn () => $request->post($endpoint . '/chat/completions', [
            'model' => $model,
            'messages' => [
                ['role' => 'system', 'content' => $this->settings->aiSystemPrompt()],
                ['role' => 'user', 'content' => $this->userMessage($post)],
            ],
        ]));

        return (string) $response->json('choices.0.message.content', '');
    }

    protected function anthropic(string $endpoint, string $model, string $key, SharedPost $post): string
    {
        $response = $this->send(fn () => $this->request()
            ->withHeaders(['x-api-key' => $key, 'anthropic-version' => '2023-06-01'])
            ->post($endpoint . '/messages', [
                'model' => $model,
                'max_tokens' => self::MAX_TOKENS,
                'system' => $this->settings->aiSystemPrompt(),
                'messages' => [
                    ['role' => 'user', 'content' => $this->userMessage($post)],
                ],
            ]));

        $parts = array_filter((array) $response->json('content', []), fn ($part) => is_array($part) && ($part['type'] ?? '') === 'text');

        return implode("\n", array_map(fn (array $part) => (string) ($part['text'] ?? ''), $parts));
    }

    protected function request(): PendingRequest
    {
        return Http::acceptJson()->asJson()->timeout(self::TIMEOUT_SECONDS);
    }

    /**
     * Send the request and turn failures into short messages. The response body goes to the log only.
     * @param callable(): Response $send
     */
    protected function send(callable $send): Response
    {
        try {
            $response = $send();
        } catch (ConnectionException) {
            throw new ShareTextException('The AI provider could not be reached.');
        }

        if ($response->successful()) {
            return $response;
        }

        Log::warning('AI provider request failed', ['status' => $response->status(), 'body' => mb_substr($response->body(), 0, 2000)]);

        throw new ShareTextException(match (true) {
            in_array($response->status(), [401, 403], true) => "The AI provider rejected the API key ({$response->status()}).",
            $response->status() === 404 => 'The AI provider does not know this endpoint or model (404).',
            $response->status() === 429 => 'The AI provider is rate limiting requests (429). Try again later.',
            default => "The AI provider returned an error ({$response->status()}).",
        });
    }

    protected function userMessage(SharedPost $post): string
    {
        return implode("\n", [
            'Article title: ' . $post->title,
            'Article source: ' . ($post->feed['name'] ?? ''),
            'Article content or summary: ' . $post->description,
            'Article URL: ' . $post->url,
        ]);
    }
}
