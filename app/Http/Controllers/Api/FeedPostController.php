<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\FeedPost;
use App\Models\PostLike;
use App\Models\SavedPost;
use Illuminate\Http\Request;

class FeedPostController extends Controller
{
    public function index(Request $request)
    {
        $userId = $request->user()->id;

        $posts = FeedPost::query()
            ->with(['user'])
            ->withCount(['comments', 'likes'])
            ->latest()
            ->paginate(10);

        $postIds = $posts->getCollection()->pluck('id');

        $likedPostIds = PostLike::query()
            ->where('user_id', $userId)
            ->whereIn('post_id', $postIds)
            ->pluck('post_id')
            ->flip();

        $savedPostIds = SavedPost::query()
            ->where('user_id', $userId)
            ->whereIn('post_id', $postIds)
            ->pluck('post_id')
            ->flip();

        $posts->getCollection()->transform(function ($p) use ($likedPostIds, $savedPostIds) {
            $p->liked_by_me = $likedPostIds->has($p->id);
            $p->saved_by_me = $savedPostIds->has($p->id);
            return $p;
        });

        return response()->json([
            'posts' => $posts,
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'type'  => 'required|in:text,image,video,audio',
            'text'  => 'nullable|string|max:5000',
            'media' => 'nullable|file|max:51200',
        ]);

        $mediaPath = null;

        if ($request->hasFile('media')) {
            $mediaPath = $request->file('media')->store('feed', 'public');
        }

        $post = FeedPost::create([
            'user_id'    => $request->user()->id,
            'type'       => $data['type'],
            'text'       => $data['text'] ?? null,
            'media_path' => $mediaPath,
        ]);

        $post->load('user');

        $post->comments_count = 0;
        $post->likes_count = 0;
        $post->liked_by_me = false;
        $post->saved_by_me = false;

        return response()->json([
            'post' => $post,
        ], 201);
    }

    public function show(Request $request, FeedPost $post)
    {
        $userId = $request->user()->id;

        $post->load(['user']);
        $post->comments_count = $post->comments()->count();
        $post->likes_count = $post->likes()->count();
        $post->liked_by_me = $post->likes()->where('user_id', $userId)->exists();
        $post->saved_by_me = $post->saves()->where('user_id', $userId)->exists();

        return response()->json([
            'post' => $post,
        ]);
    }

    public function toggleLike(Request $request, FeedPost $post)
    {
        $userId = $request->user()->id;

        $like = $post->likes()->where('user_id', $userId)->first();

        if ($like) {
            $like->delete();
            $liked = false;
        } else {
            $post->likes()->create(['user_id' => $userId]);
            $liked = true;
        }

        return response()->json([
            'liked'       => $liked,
            'likes_count' => $post->likes()->count(),
        ]);
    }
}