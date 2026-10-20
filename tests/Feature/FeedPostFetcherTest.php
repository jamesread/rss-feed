<?php

namespace Tests\Feature;

use App\Models\Feed;
use App\Rss\FeedPostFetcher;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

final class FeedPostFetcherTest extends TestCase
{
    protected function fetch(): \App\Rss\FeedFetchResult
    {
        $feed = (new Feed())->forceFill(['url' => 'https://example.com/feed.xml']);
        return app(FeedPostFetcher::class)->fetchForFeed($feed);
    }

    public function test_http_error_is_reported(): void
    {
        Http::fake(['example.com/*' => Http::response('', 404)]);

        $result = $this->fetch();

        $this->assertTrue($result->failed());
        $this->assertEquals('HTTP 404', $result->error);
        $this->assertEquals([], $result->posts);
    }

    public function test_connection_failure_is_reported(): void
    {
        Http::fake(['example.com/*' => Http::failedConnection('Could not resolve host: example.com')]);

        $result = $this->fetch();

        $this->assertEquals('Could not resolve host: example.com', $result->error);
    }

    public function test_html_body_is_invalid_feed_content(): void
    {
        Http::fake(['example.com/*' => Http::response('<html><head><title>Not a feed</title></head><body><p>Hi</p></body></html>')]);

        $this->assertEquals('Invalid feed content', $this->fetch()->error);
    }

    public function test_malformed_xml_is_invalid_feed_content(): void
    {
        Http::fake(['example.com/*' => Http::response('<!DOCTYPE html><html><body><p>Broken<br></body></html>')]);

        $this->assertEquals('Invalid feed content', $this->fetch()->error);
    }

    public function test_empty_valid_feed_is_a_success(): void
    {
        Http::fake(['example.com/*' => Http::response('<rss version="2.0"><channel></channel></rss>')]);

        $result = $this->fetch();

        $this->assertFalse($result->failed());
        $this->assertEquals([], $result->posts);
    }

    public function test_atom_feed_is_a_success(): void
    {
        Http::fake(['example.com/*' => Http::response('<feed xmlns="http://www.w3.org/2005/Atom"><title>A</title></feed>')]);

        $this->assertFalse($this->fetch()->failed());
    }
}
