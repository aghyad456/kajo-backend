<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class RequireAdmin
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();

        if (!$user || (int)$user->role !== 1) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        return $next($request);
    }
}
