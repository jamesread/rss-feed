<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * A starred post, kept as a copy of the post details so it outlives pruning and feed removal.
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
 * @property int $starred_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class StarredPost extends Model
{
    protected $fillable = ['url', 'title', 'description', 'published_at', 'thumbnail', 'source', 'feed', 'feeds', 'starred_at'];
    protected $hidden = ['id', 'created_at', 'updated_at'];

    protected function casts(): array
    {
        return [
            'published_at' => 'integer',
            'starred_at' => 'integer',
            'feed' => 'array',
            'feeds' => 'array',
        ];
    }
}
