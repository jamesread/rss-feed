<?php

namespace Tests\Unit;

use App\Config\RssConfig;
use PHPUnit\Framework\TestCase;

final class RssConfigTest extends TestCase
{
    public function test_get_feed_urls(): void
    {
        $config = new RssConfig();
        $config->addFeed('https://example.com', 'example a');
        $config->addFeed('https://example-b.com/cats?test=abc#okay', 'example b');

        $urls = $config->getFeedUrls();
        $this->assertCount(2, $urls);
        $this->assertEquals('https://example.com', $urls[0]);
        $this->assertEquals('https://example-b.com/cats?test=abc#okay', $urls[1]);
    }

    public function test_remove_feed_url(): void
    {
        $config = new RssConfig();
        $config->addFeed('https://example.com', 'a');
        $config->addFeed('https://example-B.com/cats?test=abc#okay', 'b');

        $existingRemoved = $config->removeFeed('https://example-B.com/cats?test=abc#okay');
        $nonExistingRemoved = $config->removeFeed('https://example-c.com');

        $this->assertTrue($existingRemoved);
        $this->assertFalse($nonExistingRemoved);
        $this->assertCount(1, $config->getFeedUrls());
    }

    public function test_to_string(): void
    {
        $config = new RssConfig();
        $config->addFeed('https://example.com', 'a', ['#cat', '#dog']);
        $config->addFeed('https://example-B.com/cats?test=abc#okay', 'b');

        $expected = "https://example.com a #cat #dog\nhttps://example-B.com/cats?test=abc#okay b";
        $this->assertEquals($expected, $config->toString());
    }

    public function test_parse_from_string_tolerates_extra_whitespace(): void
    {
        $config = new RssConfig();
        $config->parseFromString(implode("\n", [
            'https://a.com/feed Globex #Ecosystem  #globex',
            'https://b.com/feed  Example #news',
            "https://c.com/feed\tTabbed\t#a\t\t#b",
            'https://d.com/feed Stray #a stray #b',
        ]));

        $this->assertEquals('Globex', $config->getName('https://a.com/feed'));
        $this->assertSame(['#Ecosystem', '#globex'], $config->getTags('https://a.com/feed'));
        $this->assertEquals('Example', $config->getName('https://b.com/feed'));
        $this->assertSame(['#news'], $config->getTags('https://b.com/feed'));
        $this->assertEquals('Tabbed', $config->getName('https://c.com/feed'));
        $this->assertSame(['#a', '#b'], $config->getTags('https://c.com/feed'));
        $this->assertSame(['#a', '#b'], $config->getTags('https://d.com/feed'));

        foreach ($config->getFeedUrls() as $url) {
            $this->assertTrue(array_is_list($config->getTags($url)), "Tags for {$url} are not a list");
        }
    }

    public function test_parse_from_string(): void
    {
        $config = new RssConfig();
        $config->parseFromString("
https://example-B.com/cats?test=abc#okay a
https://example.com b[#000] #dog #cat
# A comment
https://example-C.com/cats?test=abc#okay

-https://example-hidden-a.com Hidden_A #news
- https://example-hidden-b.com/ Hidden_B

http://beans.com/feed.xml#food d_is_cool #cooking
        ");

        $this->assertCount(5, $config->getFeedUrls());
        $this->assertCount(0, $config->getTags('https://example-B.com/cats?test=abc#okay'));
        $this->assertEquals(['#dog', '#cat'], $config->getTags('https://example.com'));
        $this->assertEquals(['#cooking'], $config->getTags('http://beans.com/feed.xml#food'));
        $this->assertEquals('a', $config->getName('https://example-B.com/cats?test=abc#okay'));
        $this->assertEquals('b', $config->getName('https://example.com'));
        $this->assertEquals('#000', $config->getColor('https://example.com'));
        $this->assertEquals('d is cool', $config->getName('http://beans.com/feed.xml#food'));
        $this->assertTrue($config->getHidden('https://example-hidden-a.com'));
        $this->assertTrue($config->getHidden('https://example-hidden-b.com/'));
        $this->assertFalse($config->getHidden('http://beans.com/feed.xml#food'));
    }


    public function test_encode_for_url_without_compression(): void
    {
        $config = new RssConfig();
        $config->addFeed('https://a.com', 'a', ['#a', '#b']);

        $expected = 'thttps%3A%2F%2Fa.com+a+%23a+%23b';
        $this->assertEquals($expected, $config->encodeForUrl());
    }

    public function test_encode_for_url_with_compression(): void
    {
        $config = new RssConfig();
        $config->addFeed('https://a.com', 'a', ['#a', '#b']);
        $config->addFeed('https://b.com', 'b', ['#a', '#b']);

        $expected = 'c' . base64_encode(gzcompress("https://a.com a #a #b\nhttps://b.com b #a #b"));
        $this->assertEquals($expected, $config->encodeForUrl());
    }

    public function test_decode_from_url_without_compression(): void
    {
        $config = new RssConfig();
        $config->decodeFromUrl('thttps%3A%2F%2Fa.com+a+%23a+%23b%0Ahttps%3A%2F%2Fb.com+b+%23a+%23b');

        $this->assertCount(2, $config->getFeedUrls());
        $this->assertEquals(['#a', '#b'], $config->getTags('https://a.com'));
        $this->assertEquals(['#a', '#b'], $config->getTags('https://b.com'));
        $this->assertEquals('a', $config->getName('https://a.com'));
        $this->assertEquals('b', $config->getName('https://b.com'));
    }

    public function test_decode_from_url_with_compression(): void
    {
        $config = new RssConfig();
        $config->decodeFromUrl('ceJzLKCkpKLbS10/US87PVUhUUAaiJK4MqGgSWDQJIgoAKUYM0w==');

        $this->assertCount(2, $config->getFeedUrls());
        $this->assertEquals(['#a', '#b'], $config->getTags('https://a.com'));
        $this->assertEquals(['#a', '#b'], $config->getTags('https://b.com'));
        $this->assertEquals('a', $config->getName('https://a.com'));
        $this->assertEquals('b', $config->getName('https://b.com'));
    }

    public function test_site_token_is_parsed(): void
    {
        $config = new RssConfig();
        $config->parseFromString(implode("\n", [
            'https://news.google.com/rss/search?q=Acme Acme-News site=https://www.example.com #acme',
            'https://example.com/feed.xml Example #news',
            'https://example.org/feed.xml Invalid site=example.com #news',
        ]));

        $acme = 'https://news.google.com/rss/search?q=Acme';
        $this->assertEquals('Acme-News', $config->getName($acme));
        $this->assertEquals(['#acme'], $config->getTags($acme));
        $this->assertEquals('https://www.example.com', $config->getSite($acme));

        $this->assertEquals('', $config->getSite('https://example.com/feed.xml'));

        $this->assertEquals('', $config->getSite('https://example.org/feed.xml'));
        $this->assertEquals('Invalid', $config->getName('https://example.org/feed.xml'));
        $this->assertEquals(['#news'], $config->getTags('https://example.org/feed.xml'));
    }

    public function test_site_token_survives_url_round_trip(): void
    {
        $config = new RssConfig();
        $config->addFeed('https://a.com', 'Initech', ['#a'], '#fff', true, 'https://www.example.org');

        $this->assertEquals('-https://a.com Initech[#fff] site=https://www.example.org #a', $config->toString());

        $decoded = new RssConfig();
        $decoded->decodeFromUrl($config->encodeForUrl());

        $this->assertEquals('https://www.example.org', $decoded->getSite('https://a.com'));
        $this->assertEquals(['#a'], $decoded->getTags('https://a.com'));
        $this->assertTrue($decoded->getHidden('https://a.com'));
    }

    public function test_decode_from_url_with_empty_input(): void
    {
        $config = new RssConfig();
        $config->decodeFromUrl('');

        $this->assertCount(0, $config->getFeedUrls());
    }
}
