<?php

namespace Tests\Feature;

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
}
