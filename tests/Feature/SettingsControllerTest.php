<?php

namespace Tests\Feature;

use App\Config\AppSettings;
use App\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

final class SettingsControllerTest extends TestCase
{
    use RefreshDatabase;

    protected string $feedsFile;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
        $this->feedsFile = tempnam(sys_get_temp_dir(), 'feeds');
        config(['app.config_file' => $this->feedsFile]);
    }

    protected function tearDown(): void
    {
        @chmod($this->feedsFile, 0644);
        @unlink($this->feedsFile);
        parent::tearDown();
    }

    protected function row(array $overrides = []): array
    {
        return array_merge([
            'title' => 'Tech News',
            'tags' => 'tech',
            'url' => 'https://example.com/feed.xml',
            'site' => '',
            'color' => '',
            'hidden' => false,
        ], $overrides);
    }

    protected function general(array $overrides = []): array
    {
        return array_merge([
            'theme' => 'light',
            'dark_background' => '#000000',
            'autoplay_enabled' => true,
            'autoplay_seconds' => 45,
            'custom_css_enabled' => false,
            'custom_css' => '',
        ], $overrides);
    }

    protected function ai(array $overrides = []): array
    {
        return array_merge([
            'provider' => 'anthropic',
            'endpoint' => null,
            'model' => 'claude-test',
            'api_key' => 'sk-secret-123',
            'forget_api_key' => false,
            'system_prompt' => 'Be brief.',
        ], $overrides);
    }

    protected function savedSettings(): array
    {
        return Setting::query()->pluck('value', 'key')->all();
    }

    public function test_general_section_shows_env_values_when_nothing_is_saved(): void
    {
        config(['app.theme' => 'dark', 'app.dark_background' => '#123']);

        $this->get('/settings')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Settings')
            ->where('section', 'general')
            ->where('general.theme', 'dark')
            ->where('general.dark_background', null)
            ->where('general.default_dark_background', '#123')
            ->where('general.autoplay_enabled', false)
            ->where('general.autoplay_seconds', 30)
            ->where('general.custom_css_enabled', false)
            ->where('general.custom_css', '')
            ->where('autoplayIntervals', [10, 15, 30, 45, 60, 120]));
    }

    public function test_sections_are_addressable(): void
    {
        $this->get('/settings/feeds')->assertOk()->assertInertia(fn (Assert $page) => $page->where('section', 'feeds'));
        $this->get('/settings/ai')->assertOk()->assertInertia(fn (Assert $page) => $page->where('section', 'ai'));
        $this->get('/settings/general')->assertOk()->assertInertia(fn (Assert $page) => $page->where('section', 'general'));
        $this->get('/settings/other')->assertNotFound();
    }

    public function test_save_general_settings(): void
    {
        $this->putJson('/settings', $this->general(['dark_background' => '#ABC']))->assertNoContent();

        $this->assertEquals([
            'theme' => 'light',
            'dark_background' => '#abc',
            'autoplay_enabled' => '1',
            'autoplay_seconds' => '45',
            'custom_css_enabled' => '0',
        ], $this->savedSettings());

        $this->get('/settings')->assertInertia(fn (Assert $page) => $page
            ->where('general.theme', 'light')
            ->where('general.dark_background', '#abc')
            ->where('general.autoplay_enabled', true)
            ->where('general.autoplay_seconds', 45)
            ->where('general.custom_css_enabled', false)
            ->where('general.custom_css', ''));
    }

    public function test_save_custom_css(): void
    {
        $this->putJson('/settings', $this->general([
            'custom_css_enabled' => true,
            'custom_css' => 'body { color: red; }',
        ]))->assertNoContent();

        $this->assertEquals('1', $this->savedSettings()['custom_css_enabled']);
        $this->assertEquals('body { color: red; }', $this->savedSettings()['custom_css']);

        $this->get('/settings')->assertInertia(fn (Assert $page) => $page
            ->where('general.custom_css_enabled', true)
            ->where('general.custom_css', 'body { color: red; }'));
    }

    public function test_disabled_custom_css_keeps_the_saved_rules(): void
    {
        $this->putJson('/settings', $this->general([
            'custom_css_enabled' => true,
            'custom_css' => 'body { color: red; }',
        ]))->assertNoContent();
        $this->putJson('/settings', $this->general([
            'custom_css_enabled' => false,
            'custom_css' => 'body { color: red; }',
        ]))->assertNoContent();

        $this->assertEquals('0', $this->savedSettings()['custom_css_enabled']);
        $this->assertEquals('body { color: red; }', $this->savedSettings()['custom_css']);
        $this->get('/settings')->assertInertia(fn (Assert $page) => $page
            ->where('general.custom_css_enabled', false)
            ->where('general.custom_css', 'body { color: red; }'));
    }

    public function test_invalid_values_are_rejected(): void
    {
        $this->putJson('/settings', $this->general())->assertNoContent();

        $this->putJson('/settings', $this->general(['autoplay_seconds' => 7, 'theme' => 'light']))
            ->assertStatus(422)->assertJsonValidationErrors('autoplay_seconds');
        $this->putJson('/settings', $this->general(['theme' => 'blue', 'autoplay_seconds' => 10]))
            ->assertStatus(422)->assertJsonValidationErrors('theme');
        $this->putJson('/settings', $this->general(['dark_background' => 'red', 'autoplay_seconds' => 10]))
            ->assertStatus(422)->assertJsonValidationErrors('dark_background');
        $this->putJson('/settings', $this->general(['custom_css' => str_repeat('a', AppSettings::MAX_CUSTOM_CSS_LENGTH + 1)]))
            ->assertStatus(422)->assertJsonValidationErrors('custom_css');

        $this->assertEquals('45', $this->savedSettings()['autoplay_seconds']);
    }

    public function test_null_background_removes_the_saved_colour(): void
    {
        config(['app.dark_background' => null]);
        $this->putJson('/settings', $this->general())->assertNoContent();
        $this->putJson('/settings', $this->general(['dark_background' => null]))->assertNoContent();

        $this->assertArrayNotHasKey('dark_background', $this->savedSettings());
        $this->get('/settings')->assertInertia(fn (Assert $page) => $page
            ->where('general.dark_background', null)
            ->where('general.default_dark_background', '#101828'));
    }

    public function test_request_without_csrf_token_is_rejected(): void
    {
        // The CSRF check is skipped for unit tests, so run this request as production
        $this->app['env'] = 'production';

        $this->put('/settings', $this->general())->assertStatus(419);
        $this->assertEquals([], $this->savedSettings());
    }

    public function test_feed_rows_are_read_from_the_feeds_file(): void
    {
        file_put_contents($this->feedsFile, implode("\n", [
            '# My feeds',
            '',
            'https://news.google.com/rss/search?q=Acme Acme_News site=https://www.example.com #acme #news',
            '-https://example.com/feed-d.xml Cat_Facts[#0078b9] #cats',
        ]));

        $this->get('/settings/feeds')->assertInertia(fn (Assert $page) => $page
            ->where('rows', [
                ['title' => 'Acme News', 'tags' => 'acme, news', 'url' => 'https://news.google.com/rss/search?q=Acme', 'site' => 'https://www.example.com', 'color' => '', 'hidden' => false],
                ['title' => 'Cat Facts', 'tags' => 'cats', 'url' => 'https://example.com/feed-d.xml', 'site' => '', 'color' => '#0078b9', 'hidden' => true],
            ]));
    }

    public function test_empty_or_missing_feeds_file_gives_no_rows(): void
    {
        $this->get('/settings/feeds')->assertInertia(fn (Assert $page) => $page->where('rows', []));

        unlink($this->feedsFile);
        $this->get('/settings/feeds')->assertInertia(fn (Assert $page) => $page->where('rows', []));
    }

    public function test_tags_are_accepted_with_and_without_spaces(): void
    {
        foreach (['tag_1,tag_2', 'tag_1, tag_2', ' #tag_1 , tag_2, '] as $tags) {
            $this->putJson('/settings/feeds', ['rows' => [$this->row(['tags' => $tags])]])->assertNoContent();
            $this->assertEquals("https://example.com/feed.xml Tech_News #tag_1 #tag_2\n", file_get_contents($this->feedsFile), $tags);
        }
    }

    public function test_saved_rows_replace_the_file_and_keep_colour_and_hidden_flag(): void
    {
        file_put_contents($this->feedsFile, "# comment\n-https://example.com/feed-d.xml Cat_Facts[#0078b9] #cats\n");

        $this->putJson('/settings/feeds', ['rows' => [
            $this->row(['title' => 'Cat Facts', 'tags' => 'cats, facts', 'url' => 'https://example.com/feed-d.xml', 'color' => '#0078b9', 'hidden' => true]),
            $this->row(['site' => 'https://www.example.com']),
        ]])->assertNoContent();

        $this->assertEquals(
            "-https://example.com/feed-d.xml Cat_Facts[#0078b9] #cats #facts\n"
            . "https://example.com/feed.xml Tech_News site=https://www.example.com #tech\n",
            file_get_contents($this->feedsFile)
        );
    }

    public function test_saved_feed_appears_in_the_posts_view(): void
    {
        $this->putJson('/settings/feeds', ['rows' => [$this->row()]])->assertNoContent();

        $this->get('/')->assertInertia(fn (Assert $page) => $page
            ->has('feeds', 1)
            ->where('feeds.0.name', 'Tech News')
            ->where('feeds.0.tags', ['#tech']));
    }

    public function test_removing_all_rows_empties_the_file(): void
    {
        file_put_contents($this->feedsFile, "https://example.com/feed.xml Tech_News #tech\n");
        $this->putJson('/settings/feeds', ['rows' => []])->assertNoContent();
        $this->assertEquals('', file_get_contents($this->feedsFile));
    }

    public function test_invalid_rows_are_rejected_and_nothing_is_written(): void
    {
        $original = "https://example.com/keep.xml Keep #keep\n";
        file_put_contents($this->feedsFile, $original);

        $this->putJson('/settings/feeds', ['rows' => [
            $this->row(['title' => '']),
            $this->row(['tags' => '', 'url' => 'https://example.com/1']),
            $this->row(['url' => 'example.com/feed']),
            $this->row(['tags' => 'tag one', 'url' => 'https://example.com/3']),
            $this->row(['title' => 'Bad [title]', 'url' => 'https://example.com/4']),
            $this->row(['url' => 'https://example.com/1']),
            $this->row(['tags' => ', #,', 'url' => 'https://example.com/6']),
            $this->row(['site' => 'www.example.com', 'url' => 'https://example.com/7']),
        ]])->assertStatus(422)->assertJsonValidationErrors([
            'rows.0.title', 'rows.1.tags', 'rows.2.url', 'rows.3.tags', 'rows.4.title', 'rows.5.url', 'rows.6.tags', 'rows.7.site',
        ])->assertJsonMissingValidationErrors(['rows.1.url', 'rows.0.tags']);

        $this->assertEquals($original, file_get_contents($this->feedsFile));
    }

    public function test_read_only_feeds_file_gives_an_error(): void
    {
        $original = "https://example.com/keep.xml Keep #keep\n";
        file_put_contents($this->feedsFile, $original);
        chmod($this->feedsFile, 0444);

        $this->putJson('/settings/feeds', ['rows' => [$this->row()]])
            ->assertStatus(500)
            ->assertJson(['message' => 'The feeds file could not be written. Check that the application can write to it.']);
        $this->assertEquals($original, file_get_contents($this->feedsFile));
    }

    public function test_feeds_request_without_csrf_token_is_rejected(): void
    {
        $this->app['env'] = 'production';

        $this->put('/settings/feeds', ['rows' => [$this->row()]])->assertStatus(419);
        $this->assertEquals('', file_get_contents($this->feedsFile));
    }

    public function test_ai_section_never_contains_the_api_key(): void
    {
        $this->putJson('/settings/ai', $this->ai())->assertNoContent();

        $response = $this->get('/settings/ai')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('ai.provider', 'anthropic')
            ->where('ai.model', 'claude-test')
            ->where('ai.has_api_key', true)
            ->where('ai.system_prompt', 'Be brief.')
            ->where('ai.default_system_prompt', AppSettings::DEFAULT_AI_SYSTEM_PROMPT)
            ->where('ai.fixed_endpoints.anthropic', 'https://api.anthropic.com/v1')
            ->missing('ai.api_key'));
        $this->assertStringNotContainsString('sk-secret-123', $response->getContent());
    }

    public function test_ai_section_shows_defaults_when_nothing_is_saved(): void
    {
        $this->get('/settings/ai')->assertInertia(fn (Assert $page) => $page
            ->where('ai.provider', 'openai')
            ->where('ai.model', 'gpt-5-mini')
            ->where('ai.has_api_key', false)
            ->where('ai.system_prompt', AppSettings::DEFAULT_AI_SYSTEM_PROMPT));
    }

    public function test_save_ai_settings(): void
    {
        $this->putJson('/settings/ai', $this->ai([
            'provider' => 'openai_compatible',
            'endpoint' => 'http://localhost:11434/v1/',
        ]))->assertNoContent();

        $this->assertEquals([
            'ai_provider' => 'openai_compatible',
            'ai_endpoint' => 'http://localhost:11434/v1',
            'ai_model' => 'claude-test',
            'ai_api_key' => 'sk-secret-123',
            'ai_system_prompt' => 'Be brief.',
        ], $this->savedSettings());
    }

    public function test_empty_api_key_keeps_the_saved_key_and_forget_removes_it(): void
    {
        $this->putJson('/settings/ai', $this->ai())->assertNoContent();

        $this->putJson('/settings/ai', $this->ai(['api_key' => '', 'model' => 'other']))->assertNoContent();
        $this->assertEquals('sk-secret-123', $this->savedSettings()['ai_api_key']);
        $this->assertEquals('other', $this->savedSettings()['ai_model']);

        $this->putJson('/settings/ai', $this->ai(['api_key' => null, 'forget_api_key' => true]))->assertNoContent();
        $this->assertArrayNotHasKey('ai_api_key', $this->savedSettings());
    }

    public function test_default_prompt_is_not_saved(): void
    {
        $this->putJson('/settings/ai', $this->ai())->assertNoContent();
        $this->putJson('/settings/ai', $this->ai(['system_prompt' => AppSettings::DEFAULT_AI_SYSTEM_PROMPT]))->assertNoContent();

        $this->assertArrayNotHasKey('ai_system_prompt', $this->savedSettings());
    }

    public function test_invalid_ai_settings_are_rejected(): void
    {
        $this->putJson('/settings/ai', $this->ai(['provider' => 'gemini']))->assertUnprocessable()->assertJsonValidationErrors('provider');
        $this->putJson('/settings/ai', $this->ai(['model' => '']))->assertUnprocessable()->assertJsonValidationErrors('model');
        $this->putJson('/settings/ai', $this->ai(['provider' => 'openai_compatible', 'endpoint' => '']))->assertUnprocessable()->assertJsonValidationErrors('endpoint');
        $this->putJson('/settings/ai', $this->ai(['provider' => 'openai_compatible', 'endpoint' => 'ftp://x']))->assertUnprocessable()->assertJsonValidationErrors('endpoint');

        $this->assertEquals([], $this->savedSettings());
    }

    public function test_ai_request_without_csrf_token_is_rejected(): void
    {
        $this->app['env'] = 'production';

        $this->put('/settings/ai', $this->ai())->assertStatus(419);
        $this->assertEquals([], $this->savedSettings());
    }
}
