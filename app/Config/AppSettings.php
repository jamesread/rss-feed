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
