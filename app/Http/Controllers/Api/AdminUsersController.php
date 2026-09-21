<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;

class AdminUsersController extends Controller
{
    // GET /api/admin/users
    public function index()
    {
        return response()->json(
            User::select('id', 'name', 'email', 'role', 'created_at')
                ->latest()
                ->get()
        );
    }

    // PATCH /api/admin/users/{user}/role
    public function updateRole(User $user, Request $request)
    {
        $data = $request->validate([
            'role' => ['required', 'integer', 'in:1,2,3,4,5'], // عدّلها إذا عندك نظام مختلف
        ]);

        // ✅ لا تسمح للأدمن يغيّر دوره بنفسه (حماية)
        if ($request->user()->id === $user->id) {
            return response()->json(['message' => 'لا يمكن تغيير دور حسابك الحالي'], 422);
        }

        $user->role = (int) $data['role'];
        $user->save();

        return response()->json([
            'message' => 'تم تحديث الدور',
            'user' => $user->only(['id','name','email','role'])
        ]);
    }

    // DELETE /api/admin/users/{user}
    public function destroy(User $user, Request $request)
    {
        // ✅ لا تحذف نفسك
        if ($request->user()->id === $user->id) {
            return response()->json(['message' => 'لا يمكن حذف حسابك الحالي'], 422);
        }

        // ✅ خيار: منع حذف الأدمن
        if ($user->role === User::ROLE_ADMIN) {
            return response()->json(['message' => 'لا يمكن حذف الأدمن'], 422);
        }

        $user->delete();

        return response()->json(['message' => 'تم حذف المستخدم']);
    }
}
