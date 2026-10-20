<?php

namespace App\Http\Controllers;

use App\Config\ConfiguredFeedList;
use App\Config\ConfiguredFeedProvider;
use App\Rss\PostProvider;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Inertia\Inertia;

class PostViewController extends Controller
{
    /**
     * Time filter options, mapped to their period in seconds.
     */
    protected const array SINCE_PERIODS = [
        '24h' => 86400,
        '7d' => 604800,
        '30d' => 2592000,
    ];

    public function __construct(
        protected PostProvider $postProvider,
        protected ConfiguredFeedProvider $feedProvider,
    ) {
    }

    public function home(Request $request)
    {
        $displayFeeds = $this->feedProvider->getAll();
        $displayFeeds->reloadOutdatedFeeds();

        $postFeeds = $this->feedProvider->getVisible();

        return $this->renderPostsView($request, $displayFeeds, $postFeeds);
    }

    public function tag(Request $request, string $tag)
    {
        $feeds = $this->feedProvider->getForTag('#' . $tag);
        $feeds->reloadOutdatedFeeds();

        return $this->renderPostsView($request, $feeds, $feeds, ['tag' => $tag]);
    }

    public function feed(Request $request, string $feed)
    {
        $feed = urldecode($feed);

        $feeds = $this->feedProvider->getAsList($feed);
        $feeds->reloadOutdatedFeeds();

        return $this->renderPostsView($request, $feeds, $feeds, ['feed' => $feed]);
    }

    protected function renderPostsView(Request $request, ConfiguredFeedList $displayFeeds, ConfiguredFeedList $postFeeds, array $additionalData = [])
    {
        $page = max(intval($request->input('page')), 1);
        $query = $request->get('query', '');
        $since = $request->get('since', '');
        $since = is_string($since) && isset(self::SINCE_PERIODS[$since]) ? $since : '';
        $escapedQuery = str_replace(['%', '_', '\\'], ['\\%', '\\_', '\\\\'], $query);

        $subFilter = function (Builder $where) use ($query, $escapedQuery, $since) {
            if ($query) {
                $where->where(function (Builder $where) use ($escapedQuery) {
                    $where->where('title', 'like', '%' . $escapedQuery . '%')
                        ->orWhere('description', 'like', '%' . $escapedQuery . '%');
                });
            }

            if ($since) {
                $where->where('published_at', '>=', time() - self::SINCE_PERIODS[$since]);
            }
        };

        ['posts' => $posts, 'hasMore' => $hasMore] = $this->postProvider->getLatest(
            $postFeeds,
            100,
            $page,
            $subFilter
        );

        $coreData = [
            'feeds' => $displayFeeds,
            'posts' => $posts,
            'hasMore' => $hasMore,
            'page' => $page,
            'search' => $query,
            'since' => $since,
            'tag' => '',
            'feed' => '',
        ];

        return Inertia::render('Posts', array_merge($coreData, $additionalData));
    }
}
