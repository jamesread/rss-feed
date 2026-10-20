<?php

namespace App\Rss;

use App\Models\Feed;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Storage;

class FeedFaviconFetcher
{
    /**
     * Icon types that may be stored, mapped to their file extension.
     * SVG is excluded since it can contain script and is served from our origin.
     */
    protected const array ICON_EXTENSIONS = [
        'image/png' => 'png',
        'image/jpeg' => 'jpeg',
        'image/gif' => 'gif',
        'image/webp' => 'webp',
        'image/vnd.microsoft.icon' => 'ico',
        'image/x-icon' => 'ico',
    ];

    public function __construct(
        protected PostThumbnailFetcher $downloader
    ) {
    }

    /**
     * Find, download and store the favicon of the website behind the given feed.
     * Records the attempt time whether or not an icon was found.
     */
    public function fetchAndStoreForFeed(Feed $feed): bool
    {
        $stored = false;
        $origin = $this->getSiteOrigin($feed);

        if ($origin && $this->downloader->isSafeUrl($origin)) {
            foreach ($this->getIconUrls($origin) as $iconUrl) {
                if ($this->storeIcon($feed, $iconUrl)) {
                    $stored = true;
                    break;
                }
            }
        }

        $feed->favicon_checked_at = time();
        $feed->save();

        return $stored;
    }

    /**
     * Get the origin of the site the feed's posts link to, falling back to the feed URL origin.
     */
    protected function getSiteOrigin(Feed $feed): string
    {
        $newestPostUrl = $feed->posts()->orderBy('published_at', 'desc')->value('url');
        $parts = parse_url($newestPostUrl ?: $feed->url);
        if (empty($parts['host']) || !in_array($parts['scheme'] ?? '', ['http', 'https'])) {
            return '';
        }

        $port = isset($parts['port']) ? ":{$parts['port']}" : '';
        return "{$parts['scheme']}://{$parts['host']}{$port}";
    }

    /**
     * Get candidate icon URLs for the site, largest declared icon first, ending with /favicon.ico.
     * @return string[]
     */
    protected function getIconUrls(string $origin): array
    {
        $icons = [];

        try {
            $response = $this->downloader->get($origin . '/');
        } catch (ConnectionException) {
            $response = null;
        }

        if ($response?->successful()) {
            $head = substr($response->body(), 0, 1000000);
            preg_match_all('/<link\s[^>]*>/i', $head, $linkMatches);

            foreach ($linkMatches[0] as $link) {
                $rel = strtolower($this->getAttribute($link, 'rel'));
                $href = html_entity_decode(trim($this->getAttribute($link, 'href')));
                if (!preg_match('/\bicon\b/', $rel) || !$href) {
                    continue;
                }

                $size = intval($this->getAttribute($link, 'sizes'));
                if ($size === 0 && str_contains($rel, 'apple-touch-icon')) {
                    $size = 180;
                }

                $url = $this->resolveUrl($origin, $href);
                if ($url) {
                    $icons[$url] = max($icons[$url] ?? 0, $size);
                }
            }
        }

        arsort($icons);
        $urls = array_keys($icons);
        $urls[] = $origin . '/favicon.ico';

        return array_values(array_unique($urls));
    }

    /**
     * Get an attribute value from an HTML tag, quoted or unquoted.
     */
    protected function getAttribute(string $tag, string $name): string
    {
        $pattern = '/\s' . $name . '\s*=\s*(?:"([^"]*)"|\'([^\']*)\'|([^\s"\'>]+))/i';
        if (!preg_match($pattern, $tag, $matches)) {
            return '';
        }

        // Only one of the double-quoted, single-quoted or unquoted groups matches
        return implode('', array_slice($matches, 1));
    }

    protected function resolveUrl(string $origin, string $href): string
    {
        if (str_starts_with($href, 'https://') || str_starts_with($href, 'http://')) {
            return $href;
        }

        if (str_starts_with($href, '//')) {
            return parse_url($origin, PHP_URL_SCHEME) . ':' . $href;
        }

        if (str_contains($href, ':')) {
            // Other schemes, such as data: URIs
            return '';
        }

        return $origin . '/' . ltrim($href, '/');
    }

    protected function storeIcon(Feed $feed, string $iconUrl): bool
    {
        try {
            $image = $this->downloader->downloadImageFromUrl($iconUrl, self::ICON_EXTENSIONS);
        } catch (ConnectionException) {
            return false;
        }

        if (!$image) {
            return false;
        }

        $path = "favicons/{$feed->id}.{$image['extension']}";
        if (!Storage::disk('public')->put($path, $image['data'])) {
            return false;
        }

        if ($feed->favicon && $feed->favicon !== $path) {
            Storage::disk('public')->delete($feed->favicon);
        }

        $feed->favicon = $path;
        return true;
    }
}
