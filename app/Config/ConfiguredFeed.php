<?php

namespace App\Config;

use App\Jobs\RefreshFeedJob;
use App\Models\Feed;
use JsonSerializable;

class ConfiguredFeed implements JsonSerializable
{
    public bool $reloading = false;

    public function __construct(
        public Feed $feed,
        public string $name,
        public string $url,
        public string $color,
        public array $tags,
        public bool $hidden,
        public string $site = '',
    ) {
    }

    public function jsonSerialize(): mixed
    {
        return [
            'name' => $this->name,
            'color' => $this->color,
            'url' => $this->url,
            'tags' => $this->tags,
            'hidden' => $this->hidden,
            // Versioned so browsers drop a cached icon when a new one is stored under the same path
            'favicon' => $this->feed->favicon ? "{$this->feed->favicon}?v={$this->feed->favicon_checked_at}" : '',
            'fetch_error' => $this->feed->last_fetch_error ?? '',
            'fetch_succeeded_at' => $this->feed->last_fetch_succeeded_at ?? 0,
            'reloading' => $this->reloading,
            'outdated' => $this->isOutdated(),
        ];
    }

    public function isOutdated(): bool
    {
        $configFrequency = intval(config('app.feed_update_frequency'));
        $expiry = time() - intval($configFrequency * 60);
        return $this->feed->last_fetched_at <= $expiry;
    }

    public function startReloading(): void
    {
        $refreshJob = new RefreshFeedJob($this->feed, $this->site);
        dispatch($refreshJob);
        $this->reloading = true;
    }
}
