<?php

namespace App\Rss;

use App\Models\Post;

class FeedFetchResult
{
    /**
     * @param Post[] $posts
     * @param string $error Reason the fetch failed, empty on success
     */
    public function __construct(
        public array $posts = [],
        public string $error = '',
    ) {
    }

    public function failed(): bool
    {
        return $this->error !== '';
    }
}
