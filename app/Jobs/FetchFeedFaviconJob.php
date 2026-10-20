<?php

namespace App\Jobs;

use App\Models\Feed;
use App\Rss\FeedFaviconFetcher;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class FetchFeedFaviconJob implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /**
     * How long this unique lock exists for this kind of job.
     */
    public int $uniqueFor = 7200;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct(
        protected Feed $feed
    ) {
    }

    /**
     * Execute the job.
     */
    public function handle(FeedFaviconFetcher $faviconFetcher): void
    {
        $faviconFetcher->fetchAndStoreForFeed($this->feed);
    }

    /**
     * Get the unique key for these jobs.
     */
    public function uniqueId(): string
    {
        return strval($this->feed->id);
    }
}
