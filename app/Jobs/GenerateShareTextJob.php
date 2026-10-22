<?php

namespace App\Jobs;

use App\Models\SharedPost;
use App\Share\ShareTextException;
use App\Share\ShareTextGenerator;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Generate the share text of a share list entry. The text is written only while the entry is pending,
 * so a generated text is never replaced.
 */
class GenerateShareTextJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;

    /**
     * One attempt only: every attempt is a paid request, a failure is retried from the share view.
     */
    public int $tries = 1;

    public int $timeout = 90;

    public function __construct(
        protected string $url
    ) {
    }

    public function handle(ShareTextGenerator $generator): void
    {
        $post = $this->pendingPost();
        if (!$post) {
            return;
        }

        try {
            $text = $generator->generate($post);
        } catch (ShareTextException $exception) {
            $this->markFailed($exception->getMessage());
            return;
        }

        // Read again: the entry may have been removed while the provider was answering
        $this->pendingPost()?->update(['text' => $text, 'status' => SharedPost::READY, 'error' => null]);
    }

    /**
     * Called by the queue when the job dies, e.g. on a timeout or an unexpected exception.
     */
    public function failed(?Throwable $exception): void
    {
        if ($exception) {
            Log::error('Share text generation failed', ['url' => $this->url, 'exception' => $exception->getMessage()]);
        }
        $this->markFailed('The text could not be generated.');
    }

    protected function markFailed(string $error): void
    {
        $this->pendingPost()?->update(['status' => SharedPost::FAILED, 'error' => mb_substr($error, 0, 500)]);
    }

    protected function pendingPost(): ?SharedPost
    {
        return SharedPost::query()->where('url', $this->url)->where('status', SharedPost::PENDING)->first();
    }
}
