<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\FeedPost;
use App\Models\PostComment;
use Illuminate\Http\Request;

class PostCommentController extends Controller
{
    public function index(FeedPost $post)
    {
        $comments = PostComment::query()
            ->where('post_id', $post->id)
            ->with(['user:id,name,avatar_path,updated_at'])
            ->latest()
            ->get();

        return response()->json(['comments' => $comments]);
    }

    public function store(Request $request, FeedPost $post)
    {
        $data = $request->validate([
            'text' => 'required|string|max:2000',
        ]);

        $comment = PostComment::create([
            'post_id' => $post->id,
            'user_id' => $request->user()->id,
            'text' => $data['text'],
        ]);

        $comment->load(['user:id,name,avatar_path,updated_at']);

        return response()->json(['comment' => $comment], 201);
    }
}