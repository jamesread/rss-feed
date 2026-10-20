<?php

namespace App\Rss;

use App\Models\Feed;
use Exception;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class FeedPostFetcher
{
    public function __construct(
        protected RssParser $rssParser
    ) {
    }

    public function fetchForFeed(Feed $feed): FeedFetchResult
    {
        try {
            $feedResponse = Http::timeout(10)->withUserAgent('rss/4.5.6')->get($feed->url);
        } catch (ConnectionException $exception) {
            return $this->failure($feed, $exception->getMessage());
        }

        if (!$feedResponse->successful()) {
            return $this->failure($feed, "HTTP {$feedResponse->status()}");
        }

        try {
            $posts = $this->rssParser->parseRssDataToPosts(trim($feedResponse->body()));
        } catch (Exception) {
            return $this->failure($feed, 'Invalid feed content');
        }

        return new FeedFetchResult($posts);
    }

    protected function failure(Feed $feed, string $reason): FeedFetchResult
    {
        Log::warning('Feed fetch failed', ['url' => $feed->url, 'reason' => $reason]);
        return new FeedFetchResult([], mb_substr($reason, 0, 250));
    }
}
