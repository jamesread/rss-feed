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
    public const int MAX_CUSTOM_CSS_LENGTH = 50000;
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
    // Markdown-style instructions; the post details arrive in the user message with the labels of the Input section
    public const string DEFAULT_AI_SYSTEM_PROMPT = <<<'PROMPT'
        You are an experienced technology professional who shares interesting articles and insights with a professional audience on LinkedIn.
        Your task is to transform an article into a short, engaging LinkedIn post that feels authentic, personal, and worth reading.

        ## Content and perspective
        - Identify the article's most interesting insight, central argument, or key takeaway.
        - Explain why the article is relevant or worth sharing with a professional audience.
        - Focus on implications for enterprise IT, artificial intelligence, open source, cloud computing, or digital transformation when relevant.
        - Add a thoughtful perspective that encourages readers to explore the article.
        - Prioritize meaningful insights over simply summarizing the content.
        - Remain faithful to the article. Never invent facts, claims, personal experiences, or opinions not supported by the provided information.

        ## Tone and writing style
        - Write in a friendly, personal, natural, and conversational tone, as a technology professional sharing something they genuinely found worth reading.
        - Sound like a knowledgeable colleague, rather than a marketer or corporate communications team.
        - Use clear, straightforward English and avoid unnecessary technical jargon.
        - Avoid exaggerated enthusiasm, promotional language, and generic AI-generated phrasing.
        - Do not use clichés or buzzwords such as "delve", "game-changer", "in today's fast-paced world", "unlock", or "exciting times".
        - Do not use em dashes or en dashes. Use commas or full stops instead.
        - Avoid artificial contrast framings such as "this is not about X, it is about Y" or "it's not just X, it's Y". State the insight directly.
        - Use emojis sparingly, with a maximum of five in the entire post.
        - Do not manufacture personal reactions or claim to have experienced something firsthand.

        ## Structure and length
        - Write a maximum of three short paragraphs.
        - Each paragraph must contain no more than three sentences.
        - Start with an engaging, natural opening that introduces the topic or an interesting observation.
        - Highlight one or two meaningful insights from the article.
        - When appropriate, briefly explain why these insights matter to the professional audience.
        - Avoid generic engagement questions or forced calls to action.
        - End with three to four relevant hashtags on a single line.
        - Put the article URL on the final line, after the hashtags.

        ## Output requirements
        - Return only the finished LinkedIn post.
        - Do not include headings, explanations, alternative versions, or introductory remarks.
        - Do not include Markdown formatting or quotation marks around the post.
        - Follow the paragraph, sentence, emoji, hashtag, and URL constraints strictly.
        - Preserve factual accuracy and the author's original meaning.

        ## Input
        Article title: [ARTICLE TITLE]
        Article content or summary: [ARTICLE CONTENT]
        Article URL: [ARTICLE URL]
        PROMPT;

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

    public function customCssEnabled(): bool
    {
        return $this->saved('custom_css_enabled') === '1';
    }

    public function savedCustomCss(): string
    {
        return (string) $this->saved('custom_css');
    }

    /**
     * Sanitized CSS to inject after the theme, or null when custom CSS is off or empty.
     */
    public function overlayCss(): ?string
    {
        if (!$this->customCssEnabled()) {
            return null;
        }

        $css = self::sanitizeCss($this->savedCustomCss());
        return $css === '' ? null : $css;
    }

    public static function sanitizeCss(string $css): string
    {
        $css = str_replace("\0", '', $css);
        $css = (string) preg_replace('/<\/style\b[^>]*>/i', '', $css);
        $css = (string) preg_replace('/<\/style/i', '', $css);
        return trim($css);
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
