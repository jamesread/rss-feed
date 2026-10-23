<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use App\Config\ConfiguredFeedProvider;
use Illuminate\Http\Request;

class FeedController extends Controller
{
    public function __construct(
        protected ConfiguredFeedProvider $feedProvider,
    ) {
    }

    public function get(Request $request): JsonResponse
    {
        $url = $request->input('url', '');
        $feed = $this->feedProvider->get($url);
        if (is_null($feed)) {
            return response()->json(null, 404);
        }

        return response()->json($feed);
    }

    /**
     * Start a fetch of the given feed, even if it is not yet outdated.
     */
    public function refresh(Request $request): JsonResponse
    {
        $url = $request->validate([
            'url' => ['required', 'string'],
        ])['url'];

        $feed = $this->feedProvider->get($url);
        if (is_null($feed)) {
            return response()->json(null, 404);
        }

        $feed->startReloading();

        return response()->json($feed);
    }
}
