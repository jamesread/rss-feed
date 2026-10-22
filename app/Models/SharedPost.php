<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * A post in the share list, copied from its starred post so it outlives unstarring,
 * with the AI-generated text for sharing it.
 *
 * @property int $id
 * @property string $url
 * @property string $title
 * @property string $description
 * @property int $published_at
 * @property string $thumbnail
 * @property string $source
 * @property array $feed
 * @property array $feeds
 * @property string|null $text
 * @property string $status
 * @property string|null $error
 * @property int $shared_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class SharedPost extends Model
{
    public const string PENDING = 'pending';
    public const string READY = 'ready';
    public const string FAILED = 'failed';

    protected $fillable = ['url', 'title', 'description', 'published_at', 'thumbnail', 'source', 'feed', 'feeds', 'text', 'status', 'error', 'shared_at'];
    protected $hidden = ['id', 'created_at', 'updated_at'];

    protected function casts(): array
    {
        return [
            'published_at' => 'integer',
            'shared_at' => 'integer',
            'feed' => 'array',
            'feeds' => 'array',
        ];
    }
}
