<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\JoinRequest;
use App\Models\User;
use Illuminate\Http\Request;

class AdminJoinRequestController extends Controller
{
    // GET /api/admin/join-requests?status=pending|approved|rejected
    public function index(Request $request)
    {
        $status = $request->query('status', 'pending');

        $q = JoinRequest::with('user')->latest();

        if (in_array($status, ['pending', 'approved', 'rejected'])) {
            $q->where('status', $status);
        }

        $data = $q->get();

        return response()->json([
            'data' => $data
        ]);
    }

    public function approve(JoinRequest $joinRequest)
{
    if ($joinRequest->status !== 'pending') {
        return response()->json(['message' => 'الطلب ليس قيد المراجعة'], 422);
        }

        $user = $joinRequest->user;

        // ✅ حوّل الدور النصي لرقم حسب نظامك
        $newRole = match ($joinRequest->requested_role) {
            'doctor' => User::ROLE_DOCTOR,
            'shop_owner' => User::ROLE_SHOP,
            'shelter_owner' => User::ROLE_SHELTER,
            default => User::ROLE_USER,
        };

        // ✅ حدّث المستخدم
        $user->role = $newRole;
        $user->save();

        // ✅ حدّث حالة الطلب
        $joinRequest->status = 'approved';
        $joinRequest->save();

        return response()->json([
            'message' => 'تم قبول الطلب بنجاح',
            'data' => $joinRequest->load('user')
        ]);
    }

    // POST /api/admin/join-requests/{id}/reject
    public function reject(JoinRequest $joinRequest)
    {
        if ($joinRequest->status !== 'pending') {
            return response()->json(['message' => 'الطلب ليس قيد المراجعة'], 422);
        }

        $joinRequest->status = 'rejected';
        $joinRequest->save();

        return response()->json([
            'message' => 'تم رفض الطلب',
            'data' => $joinRequest->load('user')
        ]);
    }
}
