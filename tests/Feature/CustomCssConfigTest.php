<?php

namespace Tests\Feature;

use App\Config\AppSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\GeneratesTestData;
use Tests\TestCase;

final class CustomCssConfigTest extends TestCase
{
    use RefreshDatabase;
    use GeneratesTestData;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
        $this->generateStableTestData();
    }

    public function test_disabled_custom_css_is_not_injected(): void
    {
        app(AppSettings::class)->save([
            'custom_css_enabled' => '0',
            'custom_css' => 'body { color: red; }',
        ]);

        $this->get('/')->assertOk()->assertDontSee('id="custom-css"', false);
    }

    public function test_enabled_custom_css_is_injected_after_page_content(): void
    {
        app(AppSettings::class)->save([
            'custom_css_enabled' => '1',
            'custom_css' => 'body { color: red; }',
        ]);

        $html = $this->get('/')->assertOk()->getContent();

        $this->assertStringContainsString('<style id="custom-css">body { color: red; }</style>', $html);
        $this->assertMatchesRegularExpression('/<style id="custom-css">body \{ color: red; \}<\/style>\s*<\/body>/', $html);
    }

    public function test_style_breakout_cannot_inject_script_tags(): void
    {
        app(AppSettings::class)->save([
            'custom_css_enabled' => '1',
            'custom_css' => 'body{}</style><script>alert(1)</script>',
        ]);

        $html = $this->get('/')->assertOk()->getContent();

        $this->assertStringNotContainsString('</style><script>alert(1)</script>', $html);
        $this->assertStringContainsString('<style id="custom-css">body{}<script>alert(1)</script></style>', $html);
    }

    public function test_empty_enabled_css_is_not_injected(): void
    {
        app(AppSettings::class)->save(['custom_css_enabled' => '1']);

        $this->get('/')->assertOk()->assertDontSee('id="custom-css"', false);
    }
}
