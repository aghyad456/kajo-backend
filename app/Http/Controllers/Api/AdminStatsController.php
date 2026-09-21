<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\JoinRequest;
use Illuminate\Http\Request;

class AdminStatsController extends Controller
{
    public function index(Request $request)
    {
        // ملاحظة: عدّل أسماء الجداول/الموديلات إذا كانت مختلفة عندك
        // clinics/stores/shelters حسب موديلاتك (إن وجدت)
        $usersCount = User::count();
        $pendingJoinRequests = JoinRequest::where('status', 'pending')->count();
        $approvedJoinRequests = JoinRequest::where('status', 'approved')->count();
        $rejectedJoinRequests = JoinRequest::where('status', 'rejected')->count();

        return response()->json([
            'users' => $usersCount,
            'join_requests' => [
                'pending' => $pendingJoinRequests,
                'approved' => $approvedJoinRequests,
                'rejected' => $rejectedJoinRequests,
            ],

            // إذا عندك جداول/موديلات لهم فعلاً، فعّلهم:
            // 'clinics' => Clinic::count(),
            // 'stores' => Store::count(),
            // 'shelters' => Shelter::count(),
        ]);
    }
}
