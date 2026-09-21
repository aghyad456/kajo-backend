<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Clinic;
use App\Models\Appointment;
use App\Models\ClinicSlot;
use Illuminate\Support\Facades\DB;

class DoctorController extends Controller
{
    public function dashboard(Request $request)
    {
        $user = $request->user();

        // تحقق من الدور
        if ((int) $user->role !== 5) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        // هات عيادة الدكتور
        $clinic = Clinic::where('user_id', $user->id)->first();

        // إذا ما عنده عيادة لسا
        if (!$clinic) {
            return response()->json([
                'profile' => null,
                'statistics' => [
                    'today' => 0,
                    'upcoming' => 0,
                    'patients' => 0,
                ],
                'appointments' => [],
            ], 200);
        }

        // جلب المواعيد
        $appointments = $clinic->appointments()
            ->with('user')
            ->orderBy('date')
            ->orderBy('time')
            ->get()
            ->map(function ($appointment) {
                return [
                    'id' => $appointment->id,
                    'owner_name' => $appointment->user?->name ?? '—',
                    'owner_email' => $appointment->user?->email ?? '—',
                    'animal_type' => $appointment->animal_type ?? '—',
                    'date' => $appointment->date ? $appointment->date->format('Y-m-d') : null,
                    'time' => $appointment->time ? substr((string) $appointment->time, 0, 5) : null,
                    'status' => $appointment->status ?? 'pending',
                    'notes' => $appointment->notes ?? null,
                ];
            });

        // إحصائيات
        $today = $clinic->appointments()->whereDate('date', now()->toDateString())->count();
        $upcoming = $clinic->appointments()->whereDate('date', '>=', now()->toDateString())->count();
        $patients = $clinic->appointments()->distinct('user_id')->count('user_id');

        // صورة
        $avatar = $clinic->avatar_url ?? ($user->avatar_url ?? null);

        return response()->json([
            'profile' => [
                'clinic_id' => $clinic->id,
                'name' => $clinic->name,
                'specialty' => $clinic->specialty,
                'location' => $clinic->location,
                'phone' => $clinic->phone,
                'avatar' => $avatar,
                'is_active' => (bool) $clinic->is_active,
            ],
            'statistics' => [
                'today' => $today,
                'upcoming' => $upcoming,
                'patients' => $patients,
            ],
            'appointments' => $appointments,
        ], 200);
    }

    // GET /api/doctor/appointments?status=pending
    public function appointments(Request $request)
    {
        $user = $request->user();

        if ((int) $user->role !== 5) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $clinic = Clinic::where('user_id', $user->id)->first();

        if (!$clinic) {
            return response()->json(['appointments' => []], 200);
        }

        $status = $request->query('status');

        $query = Appointment::with('user')
            ->where('clinic_id', $clinic->id)
            ->orderByDesc('created_at');

        if ($status && in_array($status, ['pending', 'approved', 'rejected'], true)) {
            $query->where('status', $status);
        }

        $appointments = $query->get()->map(function ($appointment) {
            return [
                'id' => $appointment->id,
                'owner_name' => $appointment->user?->name ?? '—',
                'owner_email' => $appointment->user?->email ?? '—',
                'animal_type' => $appointment->animal_type ?? '—',
                'date' => $appointment->date ? $appointment->date->format('Y-m-d') : null,
                'time' => $appointment->time ? substr((string) $appointment->time, 0, 5) : null,
                'status' => $appointment->status ?? 'pending',
                'notes' => $appointment->notes ?? null,
            ];
        });

        return response()->json([
            'appointments' => $appointments
        ], 200);
    }

    // PATCH /api/doctor/appointments/{id}
    public function updateAppointmentStatus(Request $request, $id)
    {
        $user = $request->user();

        if ((int) $user->role !== 5) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $validated = $request->validate([
            'status' => 'required|in:approved,rejected',
        ]);

        $clinic = Clinic::where('user_id', $user->id)->first();

        if (!$clinic) {
            return response()->json(['message' => 'Clinic not found'], 404);
        }

        return DB::transaction(function () use ($clinic, $id, $validated) {
            $appointment = Appointment::where('clinic_id', $clinic->id)
                ->where('id', $id)
                ->lockForUpdate()
                ->first();

            if (!$appointment) {
                return response()->json(['message' => 'Appointment not found'], 404);
            }

            if ($appointment->status !== 'pending') {
                return response()->json([
                    'message' => 'تمت معالجة هذا الحجز مسبقاً'
                ], 422);
            }

            $appointment->status = $validated['status'];
            $appointment->save();

            // إذا تم الرفض نرجع الـ slot إلى available
            if ($validated['status'] === 'rejected') {
                ClinicSlot::where('clinic_id', $clinic->id)
                    ->where('date', $appointment->date)
                    ->where('time', $appointment->time)
                    ->update(['status' => 'available']);
            }

            // إذا تم التأكيد يبقى booked
            if ($validated['status'] === 'approved') {
                ClinicSlot::where('clinic_id', $clinic->id)
                    ->where('date', $appointment->date)
                    ->where('time', $appointment->time)
                    ->update(['status' => 'booked']);
            }

            return response()->json([
                'message' => $validated['status'] === 'approved'
                    ? 'تم تأكيد الحجز بنجاح'
                    : 'تم رفض الحجز وإعادة فتح الموعد',
                'appointment' => [
                    'id' => $appointment->id,
                    'status' => $appointment->status,
                ]
            ], 200);
        });
    }
}