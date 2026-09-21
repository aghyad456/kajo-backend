<?php

namespace App\Http\Middleware;

use Illuminate\Auth\Middleware\Authenticate as Middleware;

class Authenticate extends Middleware
{
    protected function redirectTo($request): ?string
    {
        // إذا الطلب API أو بدو JSON: ما تعمل redirect
        if ($request->expectsJson()) {
            return null;
        }

        // ما عندك route اسمها login، فخلّيه null
        return null;
    }
}