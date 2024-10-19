<?php

namespace App\Rss;

use App\Models\Feed;
use App\Models\Post;
use Illuminate\Support\Facades\Http;

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
        $feedResponse = Http::timeout(10)->withUserAgent('rss/4.5.6')->get($feed->url);
        if (!$feedResponse->successful()) {
            return [];
        }

        $rssData = trim($feedResponse->body());
        return $this->rssParser->rssDataToPosts($rssData);
    }
}
