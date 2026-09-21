<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Clinic;
use App\Models\ClinicSlot;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class DoctorSlotsController extends Controller
{
    // GET /api/doctor/slots
    public function index(Request $request)
    {
        $user = $request->user();

        // نفترض أن العيادة مربوطة بالمستخدم (Clinic.user_id)
        // إذا عندك علاقة مختلفة قلّي
        $clinic = Clinic::where('user_id', $user->id)->first();
        if (!$clinic) {
            return response()->json(['message' => 'العيادة غير موجودة لهذا الحساب'], 404);
        }

        $slots = ClinicSlot::where('clinic_id', $clinic->id)
            ->orderBy('date')
            ->orderBy('time')
            ->get()
            ->map(function ($s) {
                return [
                    'id' => $s->id,
                    'date' => $s->date->format('Y-m-d'),
                    'time' => substr($s->time, 0, 5), // HH:MM
                    'status' => $s->status,
                    'notes' => $s->notes,
                ];
            });

        return response()->json(['slots' => $slots]);
    }

    // POST /api/doctor/slots
    public function store(Request $request)
    {
        $user = $request->user();

        $clinic = Clinic::where('user_id', $user->id)->first();
        if (!$clinic) {
            return response()->json(['message' => 'العيادة غير موجودة لهذا الحساب'], 404);
        }

        $validated = $request->validate([
            'date' => ['required', 'date_format:Y-m-d'],
            'time' => ['required', 'date_format:H:i'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        // منع وقت بالماضي
        $slotDateTime = Carbon::createFromFormat('Y-m-d H:i', $validated['date'] . ' ' . $validated['time']);
        if ($slotDateTime->lt(now())) {
            return response()->json(['message' => 'لا يمكن إضافة وقت في الماضي'], 422);
        }

        // منع التكرار (عندك unique في DB، بس نرجع رسالة لطيفة)
        $exists = ClinicSlot::where('clinic_id', $clinic->id)
            ->where('date', $validated['date'])
            ->where('time', $validated['time'] . ':00') // لأن DB time قد تخزن ثواني
            ->exists();

        if ($exists) {
            return response()->json(['message' => 'هذا الوقت موجود مسبقاً'], 409);
        }

        $slot = ClinicSlot::create([
            'clinic_id' => $clinic->id,
            'date' => $validated['date'],
            'time' => $validated['time'],
            'status' => 'available',
            'notes' => $validated['notes'] ?? null,
        ]);

        return response()->json([
            'message' => 'تمت إضافة وقت متاح',
            'slot' => [
                'id' => $slot->id,
                'date' => $slot->date->format('Y-m-d'),
                'time' => substr($slot->time, 0, 5),
                'status' => $slot->status,
                'notes' => $slot->notes,
            ],
        ], 201);
    }

    // DELETE /api/doctor/slots/{id}
    public function destroy(Request $request, $id)
    {
        $user = $request->user();

        $clinic = Clinic::where('user_id', $user->id)->first();
        if (!$clinic) {
            return response()->json(['message' => 'العيادة غير موجودة لهذا الحساب'], 404);
        }

        $slot = ClinicSlot::where('clinic_id', $clinic->id)->where('id', $id)->first();
        if (!$slot) {
            return response()->json(['message' => 'الوقت غير موجود'], 404);
        }

        // ممنوع حذف إذا booked
        if ($slot->status === 'booked') {
            return response()->json(['message' => 'لا يمكن حذف وقت تم حجزه'], 422);
        }

        $slot->delete();
        return response()->json(['message' => 'تم حذف الوقت بنجاح']);
    }
}