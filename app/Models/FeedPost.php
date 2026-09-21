<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class FeedPost extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'type',
        'text',
        'media_path',
    ];

    protected $appends = ['media_url'];

    public function getMediaUrlAttribute()
    {
        if (!$this->media_path) {
            return null;
        }

        if (Str::startsWith($this->media_path, ['http://', 'https://'])) {
            return $this->media_path;
        }

        return asset('storage/' . $this->media_path);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function comments()
    {
        return $this->hasMany(PostComment::class, 'post_id');
    }

    public function likes()
    {
        return $this->hasMany(PostLike::class, 'post_id');
    }

    public function saves()
    {
        return $this->hasMany(SavedPost::class, 'post_id');
    }
}