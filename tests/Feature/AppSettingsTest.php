<?php

namespace Tests\Feature;

use App\Config\AppSettings;
use App\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

final class AppSettingsTest extends TestCase
{
    use RefreshDatabase;

    protected function settings(): AppSettings
    {
        return app(AppSettings::class);
    }

    public function test_theme_resolves_saved_then_env_then_default(): void
    {
        config(['app.theme' => null]);
        $this->assertEquals('system', $this->settings()->theme());

        config(['app.theme' => ' Dark ']);
        $this->assertEquals('dark', $this->settings()->theme());

        $this->settings()->save(['theme' => 'light']);
        $this->assertEquals('light', $this->settings()->theme());

        Setting::query()->whereKey('theme')->update(['value' => 'blue']);
        $this->assertEquals('dark', $this->settings()->theme());
    }

    public function test_dark_background_resolves_saved_then_env_then_default(): void
    {
        config(['app.dark_background' => null]);
        $this->assertNull($this->settings()->darkBackground());

        config(['app.dark_background' => '123ABC']);
        $this->assertEquals('#123abc', $this->settings()->darkBackground());
        $this->assertNull($this->settings()->savedDarkBackground());

        $this->settings()->save(['dark_background' => '#000']);
        $this->assertEquals('#000', $this->settings()->darkBackground());
        $this->assertEquals('#000', $this->settings()->savedDarkBackground());

        Setting::query()->whereKey('dark_background')->update(['value' => 'red']);
        $this->assertEquals('#123abc', $this->settings()->darkBackground());

        $this->settings()->save(['dark_background' => null]);
        $this->assertEquals(0, Setting::query()->count());
        $this->assertEquals('#123abc', $this->settings()->darkBackground());
    }

    public function test_autoplay_defaults_to_off_with_thirty_seconds(): void
    {
        $this->assertFalse($this->settings()->autoplayEnabled());
        $this->assertEquals(30, $this->settings()->autoplayInterval());
        $this->assertNull($this->settings()->autoplaySeconds());

        $this->settings()->save(['autoplay_enabled' => '1', 'autoplay_seconds' => '15']);
        $this->assertTrue($this->settings()->autoplayEnabled());
        $this->assertEquals(15, $this->settings()->autoplaySeconds());

        Setting::query()->whereKey('autoplay_seconds')->update(['value' => '7']);
        $this->assertEquals(30, $this->settings()->autoplaySeconds());

        $this->settings()->save(['autoplay_enabled' => '0']);
        $this->assertNull($this->settings()->autoplaySeconds());
        $this->assertEquals(30, $this->settings()->autoplayInterval());
    }

    public function test_missing_table_falls_back_to_env(): void
    {
        Schema::drop('settings');
        config(['app.theme' => 'dark', 'app.dark_background' => '#111']);

        $this->assertEquals('dark', $this->settings()->theme());
        $this->assertEquals('#111', $this->settings()->darkBackground());
        $this->assertNull($this->settings()->autoplaySeconds());
    }
}
