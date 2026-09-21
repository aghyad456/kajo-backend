<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\JoinRequest;

class JoinRequestController extends Controller
{
    public function me(Request $request)
    {
        $user = $request->user();

        if (!$user) {
            return response()->json([
                'message' => 'Unauthenticated'
            ], 401);
        }

        $req = JoinRequest::where('user_id', $user->id)
            ->latest()
            ->first();

        return response()->json([
            'has_request' => $req ? true : false,
            'request' => $req,
        ]);
    }

    public function index()
    {
        return JoinRequest::latest()->get();
    }

}
