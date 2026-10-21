<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\GeneratesTestData;
use Tests\TestCase;

final class DarkBackgroundConfigTest extends TestCase
{
    use RefreshDatabase;
    use GeneratesTestData;

    private const DARK_BG_CLASS = 'class="h-full dark:bg-dark-page"';

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
        $this->generateStableTestData();
    }

    public static function validProvider(): array
    {
        return [
            'long form' => ['#000000', '#000000'],
            'no hash' => ['000000', '#000000'],
            'short form uppercase' => ['#FFF', '#fff'],
            'whitespace and uppercase' => [' 123ABC ', '#123abc'],
        ];
    }

    public static function invalidProvider(): array
    {
        return [
            'unset' => [null],
            'empty' => [''],
            'named colour' => ['blue'],
            'five digits' => ['#12345'],
            'non hex digits' => ['#gggggg'],
            'rgb function' => ['rgb(0,0,0)'],
            'css injection' => ['#000;color:red'],
            'eight digits' => ['#000000ff'],
            'double hash' => ['##000'],
        ];
    }

    #[DataProvider('validProvider')]
    public function test_valid_dark_background_is_rendered_on_root_element(string $configured, string $expected): void
    {
        config(['app.dark_background' => $configured]);

        $resp = $this->get('/');
        $resp->assertOk();
        $resp->assertSee('data-dark-bg style="--app-dark-bg: ' . $expected . '"', false);
        $resp->assertSee(self::DARK_BG_CLASS, false);
    }

    #[DataProvider('invalidProvider')]
    public function test_invalid_dark_background_falls_back_to_default(?string $configured): void
    {
        config(['app.dark_background' => $configured]);

        $resp = $this->get('/');
        $resp->assertOk();
        $resp->assertDontSee('data-dark-bg', false);
        $resp->assertDontSee('--app-dark-bg:', false);
        $resp->assertSee(self::DARK_BG_CLASS, false);
    }
}
