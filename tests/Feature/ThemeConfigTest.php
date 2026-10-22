<?php

namespace Tests\Feature;

use App\Config\AppSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\GeneratesTestData;
use Tests\TestCase;

final class ThemeConfigTest extends TestCase
{
    use RefreshDatabase;
    use GeneratesTestData;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
        $this->generateStableTestData();
    }

    public static function themeProvider(): array
    {
        return [
            'dark' => ['dark', 'dark'],
            'light' => ['light', 'light'],
            'system' => ['system', 'system'],
            'unset' => [null, 'system'],
            'mixed case' => ['Dark', 'dark'],
            'whitespace' => [' light ', 'light'],
            'unknown' => ['blue', 'system'],
            'multiple values' => ['dark,light', 'system'],
            'empty' => ['', 'system'],
        ];
    }

    #[DataProvider('themeProvider')]
    public function test_theme_is_rendered_on_root_element(?string $configured, string $expected): void
    {
        config(['app.theme' => $configured]);

        $resp = $this->get('/');
        $resp->assertOk();
        $resp->assertSee('data-theme="' . $expected . '"', false);
    }

    public function test_saved_theme_overrides_env(): void
    {
        config(['app.theme' => 'dark']);
        app(AppSettings::class)->save(['theme' => 'light']);

        $this->get('/')->assertOk()->assertSee('data-theme="light"', false);
    }

    public function test_saved_system_theme_overrides_env(): void
    {
        config(['app.theme' => 'dark']);
        app(AppSettings::class)->save(['theme' => 'system']);

        $this->get('/')->assertOk()->assertSee('data-theme="system"', false);
    }
}
