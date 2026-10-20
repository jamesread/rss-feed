<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $feed_id
 * @property string $url
 * @property string $title
 * @property string $description
 * @property string $thumbnail
 * @property string $published_at
 * @property string $guid
 * @property string $source
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class Post extends Model
{
    use HasFactory;

    protected $fillable = ['url', 'title', 'description', 'published_at', 'guid', 'source'];
    protected $hidden = ['id', 'feed_id', 'guid', 'created_at', 'updated_at'];
}
