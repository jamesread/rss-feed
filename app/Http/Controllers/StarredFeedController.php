<?php

namespace App\Http\Controllers;

use App\Models\StarredPost;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;

/**
 * Publishes the starred posts as an RSS 2.0 feed, built fresh on every request.
 */
class StarredFeedController extends Controller
{
    public function __invoke(): Response
    {
        /** @var FilesystemAdapter $storage */
        $storage = Storage::disk('public');

        $items = StarredPost::query()
            ->orderByDesc('starred_at')
            ->orderByDesc('id')
            ->get()
            ->map(function (StarredPost $post) use ($storage) {
                $image = null;
                if ($post->thumbnail && $storage->exists($post->thumbnail)) {
                    $image = [
                        'url' => asset('storage/' . $post->thumbnail),
                        'type' => $storage->mimeType($post->thumbnail) ?: 'image/jpeg',
                        'length' => $storage->size($post->thumbnail),
                    ];
                }

                return [
                    'title' => $post->title,
                    'url' => $post->url,
                    'description' => $post->description,
                    'category' => $post->feed['name'] ?? '',
                    'date' => date(DATE_RSS, $post->starred_at),
                    'image' => $image,
                ];
            });

        $newest = StarredPost::query()->max('starred_at');

        return response()
            ->view('starred-feed', [
                'title' => config('app.name') . ' – Starred',
                'items' => $items,
                'buildDate' => date(DATE_RSS, $newest ?: time()),
            ])
            ->header('Content-Type', 'application/rss+xml; charset=UTF-8')
            ->header('Cache-Control', 'no-cache');
    }
}
