<?php

namespace App\Http\Controllers;

use App\Models\StarredPost;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;

/**
 * Stars are one list for the whole installation, keyed by post URL.
 * The client sends a snapshot of the post so stars outlive pruned posts.
 */
class StarredPostController extends Controller
{
    protected const int MAX_DESCRIPTION_LENGTH = 500;

    public function store(Request $request): Response
    {
        $snapshot = $request->validate($this->snapshotRules());
        $this->starIfMissing($snapshot, time());

        return response()->noContent();
    }

    public function import(Request $request): Response
    {
        $data = $request->validate(array_merge(
            ['posts' => ['required', 'array', 'max:5000']],
            $this->snapshotRules('posts.*.'),
        ));

        foreach ($data['posts'] as $snapshot) {
            $this->starIfMissing($snapshot, intval($snapshot['starred_at'] ?? 0) ?: time());
        }

        return response()->noContent();
    }

    public function destroy(Request $request): Response
    {
        $data = $request->validate(['url' => ['required', 'string', 'max:2048']]);

        $starred = StarredPost::query()->where('url', $data['url'])->first();
        if ($starred) {
            if (str_starts_with($starred->thumbnail, 'starred/')) {
                Storage::disk('public')->delete($starred->thumbnail);
            }
            $starred->delete();
        }

        return response()->noContent();
    }

    protected function snapshotRules(string $prefix = ''): array
    {
        return [
            $prefix . 'url' => ['required', 'string', 'max:2048', 'regex:/^https?:\/\//i'],
            $prefix . 'title' => ['required', 'string', 'max:1000'],
            $prefix . 'description' => ['nullable', 'string'],
            $prefix . 'published_at' => ['nullable', 'integer'],
            $prefix . 'starred_at' => ['nullable', 'integer'],
            $prefix . 'thumbnail' => ['nullable', 'string', 'max:250'],
            $prefix . 'source' => ['nullable', 'string', 'max:250'],
            $prefix . 'feed' => ['nullable', 'array'],
            $prefix . 'feeds' => ['nullable', 'array'],
        ];
    }

    /**
     * Store the snapshot unless the URL is already starred, which keeps the first copy and its star time.
     */
    protected function starIfMissing(array $snapshot, int $starredAt): void
    {
        $url = $snapshot['url'];
        if (StarredPost::query()->where('url', $url)->exists()) {
            return;
        }

        $feed = is_array($snapshot['feed'] ?? null) ? $snapshot['feed'] : [];
        $feeds = is_array($snapshot['feeds'] ?? null) ? $snapshot['feeds'] : [];

        StarredPost::query()->create([
            'url' => $url,
            'title' => $snapshot['title'],
            'description' => mb_substr($snapshot['description'] ?? '', 0, self::MAX_DESCRIPTION_LENGTH),
            'published_at' => intval($snapshot['published_at'] ?? 0),
            'thumbnail' => $this->keepThumbnail($snapshot['thumbnail'] ?? '', $url),
            'source' => $snapshot['source'] ?? '',
            'feed' => [
                ...$this->feedSummary($feed),
                'tags' => array_values(array_filter($feed['tags'] ?? [], 'is_string')),
                'favicon' => $this->stringValue($feed, 'favicon'),
            ],
            'feeds' => array_values(array_map(
                fn ($groupFeed) => $this->feedSummary(is_array($groupFeed) ? $groupFeed : []),
                $feeds
            )),
            'starred_at' => $starredAt,
        ]);
    }

    /**
     * Copy the post's thumbnail so it survives post pruning. Returns the copy's path, or '' when there is none.
     */
    protected function keepThumbnail(string $thumbnail, string $url): string
    {
        if (!preg_match('/^thumbs\/[A-Za-z0-9_\/-]+\.([A-Za-z0-9]+)$/', $thumbnail, $match)) {
            return '';
        }

        $storage = Storage::disk('public');
        if (!$storage->exists($thumbnail)) {
            return '';
        }

        $copy = 'starred/' . sha1($url) . '.' . strtolower($match[1]);
        $storage->copy($thumbnail, $copy);

        return $copy;
    }

    /**
     * @return array{name: string, color: string, url: string}
     */
    protected function feedSummary(array $feed): array
    {
        return [
            'name' => $this->stringValue($feed, 'name'),
            'color' => $this->stringValue($feed, 'color'),
            'url' => $this->stringValue($feed, 'url'),
        ];
    }

    protected function stringValue(array $data, string $key): string
    {
        return is_string($data[$key] ?? null) ? $data[$key] : '';
    }
}
