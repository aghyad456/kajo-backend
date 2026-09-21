<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\FeedPost;
use App\Models\PostComment;

class AdminModerationController extends Controller
{
    public function deletePost(FeedPost $post)
    {
        $post->delete();
        return response()->json(['ok' => true]);
    }

    public function deleteComment(PostComment $comment)
    {
        $comment->delete();
        return response()->json(['ok' => true]);
    }
}