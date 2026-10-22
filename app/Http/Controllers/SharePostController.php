<?php

namespace App\Http\Controllers;

use App\Jobs\GenerateShareTextJob;
use App\Models\SharedPost;
use App\Models\StarredPost;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

/**
 * The share list: starred posts picked for sharing, each with an AI-generated text.
 * One list for the whole installation, keyed by post URL.
 */
class SharePostController extends Controller
{
    protected const int MAX_TEXT_LENGTH = 5000;

    public function index()
    {
        return Inertia::render('Share', [
            'posts' => SharedPost::query()->orderByDesc('shared_at')->orderByDesc('id')->get(),
        ]);
    }

    /**
     * Add a starred post. The post details are copied from the star, so the entry outlives unstarring.
     */
    public function store(Request $request): Response
    {
        $url = $request->validate(['url' => ['required', 'string', 'max:2048']])['url'];

        if (SharedPost::query()->where('url', $url)->exists()) {
            return response()->noContent();
        }

        $starred = StarredPost::query()->where('url', $url)->first();
        if (!$starred) {
            throw ValidationException::withMessages(['url' => 'Only starred posts can be added to the share list.']);
        }

        SharedPost::query()->create([
            'url' => $starred->url,
            'title' => $starred->title,
            'description' => $starred->description,
            'published_at' => $starred->published_at,
            'thumbnail' => $this->copyThumbnail($starred),
            'source' => $starred->source,
            'feed' => $starred->feed,
            'feeds' => $starred->feeds,
            'status' => SharedPost::PENDING,
            'shared_at' => time(),
        ]);
        GenerateShareTextJob::dispatch($url);

        return response()->noContent();
    }

    /**
     * Save an edited text. Only a generated text can be edited, so generation can't overwrite an edit.
     */
    public function update(Request $request): Response
    {
        $data = $request->validate([
            'url' => ['required', 'string', 'max:2048'],
            'text' => ['required', 'string', 'max:' . self::MAX_TEXT_LENGTH],
        ]);

        $post = $this->findOrFail($data['url']);
        if ($post->status !== SharedPost::READY) {
            throw ValidationException::withMessages(['text' => 'The text can be edited once it has been generated.']);
        }
        $post->update(['text' => $data['text']]);

        return response()->noContent();
    }

    /**
     * Generate again after a failure. A generated text is never replaced.
     */
    public function retry(Request $request): Response
    {
        $url = $request->validate(['url' => ['required', 'string', 'max:2048']])['url'];

        $post = $this->findOrFail($url);
        if ($post->status !== SharedPost::FAILED) {
            throw ValidationException::withMessages(['url' => 'Only a failed text can be generated again.']);
        }
        $post->update(['status' => SharedPost::PENDING, 'error' => null]);
        GenerateShareTextJob::dispatch($url);

        return response()->noContent();
    }

    public function destroy(Request $request): Response
    {
        $url = $request->validate(['url' => ['required', 'string', 'max:2048']])['url'];

        $post = SharedPost::query()->where('url', $url)->first();
        if ($post) {
            if (str_starts_with($post->thumbnail, 'shared/')) {
                Storage::disk('public')->delete($post->thumbnail);
            }
            $post->delete();
        }

        return response()->noContent();
    }

    protected function findOrFail(string $url): SharedPost
    {
        $post = SharedPost::query()->where('url', $url)->first();
        if (!$post) {
            throw ValidationException::withMessages(['url' => 'This post is not in the share list.']);
        }

        return $post;
    }

    /**
     * Copy the star's thumbnail, which is deleted on unstarring. Returns the copy's path, or '' when there is none.
     */
    protected function copyThumbnail(StarredPost $starred): string
    {
        if (!preg_match('/^starred\/[A-Za-z0-9_\/-]+\.([A-Za-z0-9]+)$/', $starred->thumbnail, $match)) {
            return '';
        }

        $storage = Storage::disk('public');
        if (!$storage->exists($starred->thumbnail)) {
            return '';
        }

        $copy = 'shared/' . sha1($starred->url) . '.' . strtolower($match[1]);
        $storage->copy($starred->thumbnail, $copy);

        return $copy;
    }
}
