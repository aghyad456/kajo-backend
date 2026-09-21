<?php

namespace App\Http\Controllers;

use App\Models\Appointment;
use App\Models\Clinic;
use App\Models\ClinicSlot;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class BookingController extends Controller
{
    // GET /api/clinics/{clinic}/slots
    public function clinicSlotsPublic($clinic)
    {
        $clinic = Clinic::findOrFail($clinic);

        $slots = ClinicSlot::where('clinic_id', $clinic->id)
            ->where('status', 'available')
            ->where(function ($q) {
                $nowDate = now()->format('Y-m-d');
                $nowTime = now()->format('H:i:s');

                $q->where('date', '>', $nowDate)
                  ->orWhere(function ($q2) use ($nowDate, $nowTime) {
                      $q2->where('date', $nowDate)
                         ->where('time', '>=', $nowTime);
                  });
            })
            ->orderBy('date')
            ->orderBy('time')
            ->get()
            ->map(function ($slot) {
                return [
                    'id' => $slot->id,
                    'date' => $slot->date->format('Y-m-d'),
                    'time' => substr($slot->time, 0, 5),
                    'status' => $slot->status,
                ];
            });

        return response()->json([
            'slots' => $slots,
        ]);
    }

    // POST /api/bookings
    public function book(Request $request)
    {
        $user = $request->user();

        $validated = $request->validate([
            'slot_id' => ['required', 'exists:clinic_slots,id'],
            'animal_type' => ['required', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        return DB::transaction(function () use ($validated, $user) {
            $slot = ClinicSlot::lockForUpdate()->findOrFail($validated['slot_id']);

            if ($slot->status !== 'available') {
                return response()->json([
                    'message' => 'هذا الوقت لم يعد متاحاً',
                ], 409);
            }

            // ✅ إصلاح مشكلة الفورمات
            $slotDateTime = Carbon::parse(
                $slot->date->format('Y-m-d') . ' ' . substr($slot->time, 0, 5)
            );

            if ($slotDateTime->lt(now())) {
                return response()->json([
                    'message' => 'لا يمكن حجز وقت في الماضي',
                ], 422);
            }

            $appointment = Appointment::create([
                'clinic_id' => $slot->clinic_id,
                'user_id' => $user->id,
                'animal_type' => $validated['animal_type'],
                'date' => $slot->date->format('Y-m-d'),
                'time' => $slot->time,
                'status' => 'pending',
                'notes' => $validated['notes'] ?? null,
            ]);

            $slot->update([
                'status' => 'booked',
            ]);

            return response()->json([
                'message' => 'تم إرسال طلب الحجز',
                'appointment' => [
                    'id' => $appointment->id,
                    'status' => $appointment->status,
                    'date' => $appointment->date->format('Y-m-d'),
                    'time' => substr($appointment->time, 0, 5),
                ],
            ], 201);
        });
    }
}