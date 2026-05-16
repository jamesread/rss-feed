<?php

namespace App\Rss;

use App\Models\Post;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

class PostThumbnailFetcher
{
    /**
     * Private and reserved IP ranges that should not be fetched.
     */
    protected const array BLOCKED_IP_PATTERNS = [
        '0.',          // "This" network
        '10.',         // Private
        '127.',        // Loopback
        '169.254.',    // Link-local
        '172.16.',     // Private (172.16.0.0 - 172.31.255.255)
        '172.17.',
        '172.18.',
        '172.19.',
        '172.20.',
        '172.21.',
        '172.22.',
        '172.23.',
        '172.24.',
        '172.25.',
        '172.26.',
        '172.27.',
        '172.28.',
        '172.29.',
        '172.30.',
        '172.31.',
        '192.168.',    // Private
        '192.0.0.',    // IETF protocol assignments
        '192.0.2.',    // TEST-NET-1
        '198.18.',     // Benchmark testing
        '198.19.',
        '198.51.100.', // TEST-NET-2
        '203.0.113.',  // TEST-NET-3
        '224.',        // Multicast
        '240.',        // Reserved
        '::1',         // IPv6 loopback
        'fc00:',       // IPv6 unique local
        'fd00:',       // IPv6 unique local
        'fe80:',       // IPv6 link-local
        '::ffff:0.',   // IPv4-mapped IPv6 "this"
        '::ffff:10.',  // IPv4-mapped private
        '::ffff:127.', // IPv4-mapped loopback
        '::ffff:172.16.',
        '::ffff:172.17.',
        '::ffff:172.18.',
        '::ffff:172.19.',
        '::ffff:172.20.',
        '::ffff:172.21.',
        '::ffff:172.22.',
        '::ffff:172.23.',
        '::ffff:172.24.',
        '::ffff:172.25.',
        '::ffff:172.26.',
        '::ffff:172.27.',
        '::ffff:172.28.',
        '::ffff:172.29.',
        '::ffff:172.30.',
        '::ffff:172.31.',
        '::ffff:192.168.',
    ];

    public function fetchAndStoreForPost(Post $post): bool
    {
        $imageUrl = $this->getThumbLinkFromUrl($post->url);
        if (!$imageUrl) {
            return false;
        }

        if (!$this->isSafeUrl($imageUrl)) {
            return false;
        }

        $imageInfo = $this->downloadImageFromUrl($imageUrl);
        if (!$imageInfo) {
            return false;
        }

        $path = "thumbs/{$post->feed_id}/{$post->id}.{$imageInfo['extension']}";
        $complete = Storage::disk('public')->put($path, $imageInfo['data']);
        if (!$complete) {
            return false;
        }

        $post->thumbnail = $path;
        $post->save();

        return true;
    }

    /**
     * Check if a URL is safe to fetch (not pointing to internal/private IPs).
     */
    protected function isSafeUrl(string $url): bool
    {
        $host = parse_url($url, PHP_URL_HOST);
        if (!$host) {
            return false;
        }

        // Resolve hostname to IP
        $ip = gethostbyname($host);
        if ($ip === $host) {
            // DNS resolution failed; could be a literal IP or unresolvable
            // Treat literal IPs directly, hostnames as unsafe if unresolvable
            if (filter_var($host, FILTER_VALIDATE_IP) === false) {
                return false;
            }
        }

        $lowerIp = strtolower($ip);
        foreach (self::BLOCKED_IP_PATTERNS as $pattern) {
            if (str_starts_with($lowerIp, strtolower($pattern))) {
                return false;
            }
        }

        return true;
    }

    /**
     * @return null|array{extension: string, data: string}
     * @throws ConnectionException
     */
    protected function downloadImageFromUrl(string $url): ?array
    {
        if (!$this->isSafeUrl($url)) {
            return null;
        }

        $imageResponse = Http::timeout(10)->withUserAgent('rss/4.5.6')->get($url);
        if (!$imageResponse->successful()) {
            return null;
        }

        $imageData = $imageResponse->body();
        // > 1MB
        if (strlen($imageData) > 1000000) {
            return null;
        }

        $tempFile = tmpfile();
        fwrite($tempFile, $imageData);
        $mimeSplit = explode('/', mime_content_type($tempFile) ?: '');
        fclose($tempFile);
        if (count($mimeSplit) < 2 || $mimeSplit[0] !== 'image') {
            return null;
        }

        $extension = $mimeSplit[1];
        return ['data' => $imageData, 'extension' => $extension];
    }

    protected function getThumbLinkFromUrl(string $url): string
    {
        $pageResponse = Http::timeout(10)->withUserAgent('rss/4.5.6')->get($url);
        if (!$pageResponse->successful()) {
            return '';
        }

        $postHead = substr($pageResponse->body(), 0, 1000000);
        $metaMatches = [];
        $metaPattern = '/<meta [^<>]*property=["\']og:image["\'].*?>/';
        if (!preg_match($metaPattern, $postHead, $metaMatches)) {
            return '';
        }

        $linkMatches = [];
        $linkPattern = '/content=["\'](.*?)["\']/';
        if (!preg_match($linkPattern, $metaMatches[0], $linkMatches)) {
            return '';
        }

        $link = html_entity_decode($linkMatches[1]);

        if (!str_starts_with($link, 'https://') && !str_starts_with($link, 'http://')) {
            return '';
        }

        return $link;
    }
}
