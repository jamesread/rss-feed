<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $url
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property int $last_accessed_at
 * @property int $last_fetched_at
 * @property string $favicon
 * @property int $favicon_checked_at
 * @property string $favicon_site
 * @property string $last_fetch_error
 * @property int $last_fetch_succeeded_at
 */
class Feed extends Model
{
    use HasFactory;

    protected $hidden = ['id', 'created_at', 'updated_at', 'last_accessed_at'];

    public function posts(): HasMany
    {
        return $this->hasMany(Post::class);
    }
}
