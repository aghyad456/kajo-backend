<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ProfileController extends Controller
{
    public function show(Request $request)
    {
        $u = $request->user()->fresh();

        return response()->json([
            'user' => $u, // ✅ سيرجع avatar_url تلقائياً من User accessor
        ]);
    }

    public function update(Request $request)
    {
        $u = $request->user();

        $data = $request->validate([
            'name'    => ['nullable', 'string', 'max:255'],
            'phone'   => ['nullable', 'string', 'max:50'],
            'address' => ['nullable', 'string', 'max:255'],
            'bio'     => ['nullable', 'string', 'max:2000'],
            'avatar'  => ['nullable', 'image', 'max:4096'], // 4MB
        ]);

        if ($request->hasFile('avatar')) {
            // حذف القديمة
            if ($u->avatar_path) {
                Storage::disk('public')->delete($u->avatar_path);
            }

            // ✅ اسم فريد تلقائياً
            $path = $request->file('avatar')->store('avatars', 'public');
            $u->avatar_path = $path;
        }

        foreach (['name', 'phone', 'address', 'bio'] as $field) {
            if (array_key_exists($field, $data)) {
                $u->$field = $data[$field];
            }
        }

        $u->save();

        // ✅ مهم: نرجّع نسخة fresh (updated_at جديد + avatar_url جديد)
        $u = $u->fresh();

        return response()->json([
            'user' => $u,
        ]);
    }
}