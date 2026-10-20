<?php

namespace App\Rss;

use App\Config\ConfiguredFeed;
use App\Config\ConfiguredFeedList;
use App\Models\Post;
use Illuminate\Support\Collection;

class PostProvider
{
    /**
     * Normalized titles shorter than this are too generic to match duplicates on.
     */
    protected const int MIN_MATCH_TITLE_LENGTH = 20;

    /**
     * Get a page of the latest posts, with duplicate stories across feeds collapsed
     * into their newest post.
     * @return array{posts: Collection<Post>, hasMore: bool}
     */
    public function getLatest(ConfiguredFeedList $feeds, int $count, int $page, ?callable $condition = null): array
    {
        $posts = Post::query()
            ->when($condition, $condition)
            ->whereIn('feed_id', $feeds->getFeedIds())
            ->orderBy('published_at', 'desc')
            ->take($count + 1)
            ->skip(($page - 1) * $count)
            ->get();

        $hasMore = $posts->count() > $count;
        $posts = $this->collapseDuplicates($posts->take($count)->values(), $feeds);

        return ['posts' => $posts, 'hasMore' => $hasMore];
    }

    /**
     * Keep only the newest post of each group of duplicates, and attach to every kept
     * post its own feed and the list of all feeds the story came from, newest first.
     * Posts must be ordered newest first.
     * @param Collection<Post> $posts
     * @return Collection<Post>
     */
    protected function collapseDuplicates(Collection $posts, ConfiguredFeedList $feeds): Collection
    {
        $feedsById = $feeds->getMappedById();
        $groupOfKey = [];
        $groupOfIndex = [];
        $parent = [];

        $find = function (int $i) use (&$parent, &$find): int {
            return $parent[$i] === $i ? $i : ($parent[$i] = $find($parent[$i]));
        };

        foreach ($posts as $index => $post) {
            $parent[$index] = $index;
            foreach ($this->getMatchKeys($post) as $key) {
                if (isset($groupOfKey[$key])) {
                    // Union with the earlier (newer) post's group, keeping the newest index as root
                    $a = $find($groupOfKey[$key]);
                    $b = $find($index);
                    $parent[max($a, $b)] = min($a, $b);
                } else {
                    $groupOfKey[$key] = $index;
                }
            }
        }

        foreach ($posts as $index => $post) {
            $groupOfIndex[$find($index)][] = $index;
        }

        $kept = new Collection();
        foreach ($groupOfIndex as $root => $members) {
            $post = $posts[$root];
            $groupFeeds = [];
            foreach ($members as $member) {
                $feed = $feedsById[$posts[$member]->feed_id] ?? null;
                if ($feed && !isset($groupFeeds[$feed->url])) {
                    $groupFeeds[$feed->url] = $this->feedSummary($feed);
                }
            }

            $post->setAttribute('feed', $feedsById[$post->feed_id] ?? []);
            $post->setAttribute('feeds', array_values($groupFeeds));
            $kept->push($post);
        }

        return $kept;
    }

    /**
     * @return string[]
     */
    protected function getMatchKeys(Post $post): array
    {
        $keys = ['url:' . $post->url];

        $title = trim(preg_replace('/[^\p{L}\p{N}]+/u', ' ', mb_strtolower($post->title)));
        if (mb_strlen($title) >= self::MIN_MATCH_TITLE_LENGTH) {
            $keys[] = 'title:' . $title;
        }

        return $keys;
    }

    /**
     * @return array{name: string, url: string, color: string}
     */
    protected function feedSummary(ConfiguredFeed $feed): array
    {
        return ['name' => $feed->name, 'url' => $feed->url, 'color' => $feed->color];
    }
}
