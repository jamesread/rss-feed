<?php

namespace App\Rss;

use App\Models\Post;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Psr\Http\Message\UriInterface;
use UnexpectedValueException;

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

    /**
     * Image types that may be stored, mapped to their file extension.
     * SVG is excluded since it can contain script and is served from our origin.
     */
    protected const array IMAGE_EXTENSIONS = [
        'image/jpeg' => 'jpeg',
        'image/png' => 'png',
        'image/gif' => 'gif',
        'image/webp' => 'webp',
        'image/avif' => 'avif',
    ];

    public function fetchAndStoreForPost(Post $post): bool
    {
        if (!$this->isSafeUrl($post->url)) {
            return false;
        }

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
    public function isSafeUrl(string $url): bool
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
     * @param array<string, string> $allowedTypes MIME types mapped to the extension to use
     * @throws ConnectionException
     */
    public function downloadImageFromUrl(string $url, array $allowedTypes = self::IMAGE_EXTENSIONS): ?array
    {
        if (!$this->isSafeUrl($url)) {
            return null;
        }

        $imageResponse = $this->get($url);
        if (!$imageResponse?->successful()) {
            return null;
        }

        $imageData = $imageResponse->body();
        // > 1MB
        if (strlen($imageData) > 1000000) {
            return null;
        }

        $tempFile = tmpfile();
        fwrite($tempFile, $imageData);
        $mimeType = mime_content_type($tempFile) ?: '';
        fclose($tempFile);

        $extension = $allowedTypes[$mimeType] ?? null;
        if (!$extension) {
            return null;
        }

        return ['data' => $imageData, 'extension' => $extension];
    }

    /**
     * GET the given URL, following redirects only to safe targets.
     * Returns null if a redirect pointed to an unsafe target.
     * @throws ConnectionException
     */
    public function get(string $url): ?Response
    {
        $redirects = [
            'max' => 5,
            'on_redirect' => function ($request, $response, UriInterface $uri) {
                if (!$this->isSafeUrl((string) $uri)) {
                    throw new UnexpectedValueException("Unsafe redirect to {$uri}");
                }
            },
        ];

        try {
            return Http::timeout(10)
                ->withUserAgent('rss/4.5.6')
                ->withOptions(['allow_redirects' => $redirects])
                ->get($url);
        } catch (UnexpectedValueException) {
            return null;
        }
    }

    protected function getThumbLinkFromUrl(string $url): string
    {
        $pageResponse = $this->get($url);
        if (!$pageResponse?->successful()) {
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
