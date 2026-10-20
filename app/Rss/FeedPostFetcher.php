<?php

namespace App\Rss;

use App\Models\Feed;
use App\Models\Post;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class FeedPostFetcher
{
    public function __construct(
        protected RssParser $rssParser
    ) {
    }

    /**
     * @return Post[]
     */
    public function fetchForFeed(Feed $feed): array
    {
        try {
            $feedResponse = Http::timeout(10)->withUserAgent('rss/4.5.6')->get($feed->url);
        } catch (ConnectionException $exception) {
            $this->logFailure($feed, $exception->getMessage());
            return [];
        }

        if (!$feedResponse->successful()) {
            $this->logFailure($feed, "HTTP {$feedResponse->status()}");
            return [];
        }

        $rssData = trim($feedResponse->body());
        return $this->rssParser->rssDataToPosts($rssData);
    }

    protected function logFailure(Feed $feed, string $reason): void
    {
        Log::warning('Feed fetch failed', ['url' => $feed->url, 'reason' => $reason]);
    }
}
