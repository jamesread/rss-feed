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

    public function test_ai_settings_default_to_openai_without_key(): void
    {
        $this->assertEquals('openai', $this->settings()->aiProvider());
        $this->assertEquals('https://api.openai.com/v1', $this->settings()->aiEndpoint());
        $this->assertEquals('gpt-5-mini', $this->settings()->aiModel());
        $this->assertFalse($this->settings()->hasAiApiKey());
        $this->assertEquals(AppSettings::DEFAULT_AI_SYSTEM_PROMPT, $this->settings()->aiSystemPrompt());
    }

    public function test_ai_endpoint_is_fixed_except_for_compatible_providers(): void
    {
        $this->settings()->save(['ai_provider' => 'anthropic', 'ai_endpoint' => 'http://localhost:11434/v1']);
        $this->assertEquals('https://api.anthropic.com/v1', $this->settings()->aiEndpoint());
        $this->assertEquals('claude-sonnet-5-5', $this->settings()->aiModel());

        $this->settings()->save(['ai_provider' => 'openai_compatible']);
        $this->assertEquals('http://localhost:11434/v1', $this->settings()->aiEndpoint());
        $this->assertEquals('', $this->settings()->aiModel());

        $this->settings()->save(['ai_provider' => 'unknown']);
        $this->assertEquals('openai', $this->settings()->aiProvider());
    }

    public function test_saved_ai_values_win_over_defaults(): void
    {
        $this->settings()->save(['ai_model' => 'gpt-test', 'ai_api_key' => 'sk-1', 'ai_system_prompt' => 'Be brief.']);

        $this->assertEquals('gpt-test', $this->settings()->aiModel());
        $this->assertEquals('sk-1', $this->settings()->aiApiKey());
        $this->assertTrue($this->settings()->hasAiApiKey());
        $this->assertEquals('Be brief.', $this->settings()->aiSystemPrompt());
    }

    public function test_custom_css_is_off_by_default(): void
    {
        $this->assertFalse($this->settings()->customCssEnabled());
        $this->assertEquals('', $this->settings()->savedCustomCss());
        $this->assertNull($this->settings()->overlayCss());
    }

    public function test_overlay_css_is_only_returned_when_enabled(): void
    {
        $this->settings()->save(['custom_css' => 'body { color: red; }']);
        $this->assertEquals('body { color: red; }', $this->settings()->savedCustomCss());
        $this->assertFalse($this->settings()->customCssEnabled());
        $this->assertNull($this->settings()->overlayCss());

        $this->settings()->save(['custom_css_enabled' => '1']);
        $this->assertTrue($this->settings()->customCssEnabled());
        $this->assertEquals('body { color: red; }', $this->settings()->overlayCss());

        $this->settings()->save(['custom_css_enabled' => '0']);
        $this->assertNull($this->settings()->overlayCss());
        $this->assertEquals('body { color: red; }', $this->settings()->savedCustomCss());
    }

    public function test_overlay_css_strips_style_breakout(): void
    {
        $this->settings()->save([
            'custom_css_enabled' => '1',
            'custom_css' => "body{}\0</style><script>alert(1)</script></STYLE>",
        ]);

        $this->assertEquals('body{}<script>alert(1)</script>', $this->settings()->overlayCss());
    }

    public function test_empty_enabled_css_is_not_an_overlay(): void
    {
        $this->settings()->save(['custom_css_enabled' => '1', 'custom_css' => '   ']);
        $this->assertNull($this->settings()->overlayCss());
    }

    public function test_missing_table_falls_back_to_env(): void
    {
        Schema::drop('settings');
        config(['app.theme' => 'dark', 'app.dark_background' => '#111']);

        $this->assertEquals('dark', $this->settings()->theme());
        $this->assertEquals('#111', $this->settings()->darkBackground());
        $this->assertNull($this->settings()->autoplaySeconds());
        $this->assertFalse($this->settings()->customCssEnabled());
        $this->assertNull($this->settings()->overlayCss());
    }
}
