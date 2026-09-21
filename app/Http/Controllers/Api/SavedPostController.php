<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\FeedPost;
use App\Models\SavedPost;
use Illuminate\Http\Request;

class SavedPostController extends Controller
{
    public function index(Request $request)
    {
        $userId = $request->user()->id;

        $posts = FeedPost::query()
            ->whereIn('id', function ($q) use ($userId) {
                $q->select('post_id')
                    ->from('saved_posts')
                    ->where('user_id', $userId);
            })
            ->with(['user'])
            ->withCount(['comments', 'likes'])
            ->latest()
            ->get();

        $likedPostIds = $request->user()->id
            ? \App\Models\PostLike::query()
                ->where('user_id', $userId)
                ->whereIn('post_id', $posts->pluck('id'))
                ->pluck('post_id')
                ->flip()
            : collect();

        $savedPostIds = $posts->pluck('id')->flip();

        $posts->transform(function ($post) use ($likedPostIds, $savedPostIds) {
            $post->liked_by_me = $likedPostIds->has($post->id);
            $post->saved_by_me = $savedPostIds->has($post->id);
            return $post;
        });

        return response()->json([
            'posts' => $posts,
        ]);
    }

    public function toggle(Request $request, FeedPost $post)
    {
        $userId = $request->user()->id;

        $existing = SavedPost::where('user_id', $userId)
            ->where('post_id', $post->id)
            ->first();

        if ($existing) {
            $existing->delete();
            $saved = false;
        } else {
            SavedPost::create([
                'user_id' => $userId,
                'post_id' => $post->id,
            ]);
            $saved = true;
        }

        return response()->json([
            'saved' => $saved,
        ]);
    }
}