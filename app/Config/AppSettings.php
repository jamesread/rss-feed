<?php

namespace App\Config;

use App\Models\Setting;
use Illuminate\Database\QueryException;

/**
 * Settings that can be changed on the settings page.
 * Each value resolves as: saved setting, then the environment config, then the built-in default.
 */
class AppSettings
{
    public const array THEMES = ['system', 'light', 'dark'];
    public const array AUTOPLAY_INTERVALS = [10, 15, 30, 45, 60, 120];
    public const int DEFAULT_AUTOPLAY_SECONDS = 30;
    // The built-in dark page background (Tailwind gray-900), shown in the colour picker when none is set
    public const string DEFAULT_DARK_BACKGROUND = '#101828';

    public const array AI_PROVIDERS = ['openai', 'anthropic', 'openrouter', 'openai_compatible'];
    public const string DEFAULT_AI_PROVIDER = 'openai';
    // Providers with a fixed endpoint; "openai_compatible" uses the saved endpoint instead
    public const array AI_ENDPOINTS = [
        'openai' => 'https://api.openai.com/v1',
        'anthropic' => 'https://api.anthropic.com/v1',
        'openrouter' => 'https://openrouter.ai/api/v1',
    ];
    public const array AI_DEFAULT_MODELS = [
        'openai' => 'gpt-5-mini',
        'anthropic' => 'claude-sonnet-5-5',
    ];
    public const string DEFAULT_AI_SYSTEM_PROMPT = 'You write LinkedIn posts that share an article with a professional audience. '
        . 'Write in a friendly, personal and natural tone, as a person sharing something they found worth reading. '
        . 'Use at most three short paragraphs of at most three sentences each. '
        . 'Use emojis sparingly, at most two in the whole post. '
        . 'Avoid typical AI phrases and buzzwords such as "delve", "game-changer", "in today\'s fast-paced world", "unlock" or "exciting times". '
        . 'Do not use em dashes or en dashes; use commas or full stops instead. '
        . 'Avoid contrast framings such as "this is not about X, it is about Y" or "it\'s not just X, it\'s Y"; say directly what the article offers. '
        . 'Do not invent facts that are not in the article details. '
        . 'End with 3 to 4 relevant hashtags on one line, followed by the article link on the last line. '
        . 'Reply with the post text only.';

    public function theme(): string
    {
        return self::normalizeTheme($this->saved('theme'))
            ?? self::normalizeTheme(config('app.theme'))
            ?? 'system';
    }

    /**
     * The dark page background as "#rgb" or "#rrggbb", or null for the built-in default.
     */
    public function darkBackground(): ?string
    {
        return self::normalizeColor($this->saved('dark_background'))
            ?? self::normalizeColor(config('app.dark_background'));
    }

    public function savedDarkBackground(): ?string
    {
        return self::normalizeColor($this->saved('dark_background'));
    }

    public function autoplayEnabled(): bool
    {
        return $this->saved('autoplay_enabled') === '1';
    }

    public function autoplayInterval(): int
    {
        $seconds = intval($this->saved('autoplay_seconds'));
        return in_array($seconds, self::AUTOPLAY_INTERVALS, true) ? $seconds : self::DEFAULT_AUTOPLAY_SECONDS;
    }

    /**
     * The auto-advance interval in seconds when auto-advance is on, otherwise null.
     */
    public function autoplaySeconds(): ?int
    {
        return $this->autoplayEnabled() ? $this->autoplayInterval() : null;
    }

    public function aiProvider(): string
    {
        $provider = $this->saved('ai_provider');
        return in_array($provider, self::AI_PROVIDERS, true) ? $provider : self::DEFAULT_AI_PROVIDER;
    }

    /**
     * The API base URL: fixed for the known providers, the saved endpoint for "openai_compatible".
     */
    public function aiEndpoint(): string
    {
        return self::AI_ENDPOINTS[$this->aiProvider()] ?? (string) $this->saved('ai_endpoint');
    }

    public function savedAiEndpoint(): string
    {
        return (string) $this->saved('ai_endpoint');
    }

    public function aiModel(): string
    {
        return $this->saved('ai_model') ?? (self::AI_DEFAULT_MODELS[$this->aiProvider()] ?? '');
    }

    public function aiApiKey(): string
    {
        return (string) $this->saved('ai_api_key');
    }

    public function hasAiApiKey(): bool
    {
        return $this->aiApiKey() !== '';
    }

    public function aiSystemPrompt(): string
    {
        return $this->saved('ai_system_prompt') ?? self::DEFAULT_AI_SYSTEM_PROMPT;
    }

    /**
     * Save the given values. A null value removes the saved setting, so it falls back to the environment.
     * @param array<string, string|null> $values
     */
    public function save(array $values): void
    {
        foreach ($values as $key => $value) {
            if ($value === null) {
                Setting::query()->whereKey($key)->delete();
            } else {
                Setting::query()->updateOrCreate(['key' => $key], ['value' => $value]);
            }
        }
    }

    public static function normalizeTheme(mixed $theme): ?string
    {
        $theme = strtolower(trim((string) $theme));
        return in_array($theme, self::THEMES, true) ? $theme : null;
    }

    public static function normalizeColor(mixed $color): ?string
    {
        $color = strtolower(trim((string) $color));
        return preg_match('/^#?([0-9a-f]{3}(?:[0-9a-f]{3})?)$/', $color, $match) ? '#' . $match[1] : null;
    }

    protected function saved(string $key): ?string
    {
        try {
            return Setting::query()->whereKey($key)->value('value');
        } catch (QueryException) {
            // No settings table yet, e.g. before the migrations ran
            return null;
        }
    }
}
