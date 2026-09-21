<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Appointment;
use App\Models\StoreOrder;
use App\Models\ShelterAdoptionRequest;
use App\Models\ShelterDonationSubmission;

class MyActivitiesController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();

        // ---------- Clinic Appointments ----------
        $appointments = Appointment::with('clinic:id,name,specialty,location,phone')
            ->where('user_id', $user->id)
            ->orderByDesc('created_at')
            ->get()
            ->map(function ($appointment) {
                return [
                    'id' => $appointment->id,
                    'type' => 'clinic',
                    'title' => $appointment->clinic?->name ?? 'عيادة',
                    'subtitle' => $appointment->clinic?->specialty ?? '—',
                    'location' => $appointment->clinic?->location ?? '—',
                    'phone' => $appointment->clinic?->phone ?? '—',
                    'animal_type' => $appointment->animal_type ?? '—',
                    'date' => $appointment->date ? $appointment->date->format('Y-m-d') : null,
                    'time' => $appointment->time ? substr((string) $appointment->time, 0, 5) : null,
                    'status' => $appointment->status ?? 'pending',
                    'notes' => $appointment->notes ?? null,
                    'created_at' => optional($appointment->created_at)->format('Y-m-d H:i'),
                ];
            });

        // ---------- Store Orders ----------
        $storeOrders = StoreOrder::with(['store:id,name,location,phone', 'product:id,name'])
            ->where('user_id', $user->id)
            ->orderByDesc('created_at')
            ->get()
            ->map(function ($order) {
                return [
                    'id' => $order->id,
                    'type' => 'store',
                    'title' => $order->store?->name ?? 'متجر',
                    'subtitle' => $order->product?->name ?? 'منتج',
                    'location' => $order->store?->location ?? '—',
                    'phone' => $order->store?->phone ?? '—',
                    'quantity' => $order->quantity,
                    'status' => $order->status ?? 'pending',
                    'notes' => $order->notes ?? null,
                    'created_at' => optional($order->created_at)->format('Y-m-d H:i'),
                ];
            });

        // ---------- Shelter Adoption Requests ----------
        $shelterAdoptions = ShelterAdoptionRequest::with([
                'shelter:id,name,location,phone',
                'animal:id,name,type'
            ])
            ->where('user_id', $user->id)
            ->orderByDesc('created_at')
            ->get()
            ->map(function ($item) {
                return [
                    'id' => $item->id,
                    'type' => 'shelter',
                    'subtype' => 'adoption',
                    'title' => $item->shelter?->name ?? 'ملجأ',
                    'subtitle' => $item->animal?->name ?? 'حيوان',
                    'location' => $item->shelter?->location ?? '—',
                    'phone' => $item->shelter?->phone ?? '—',
                    'animal_type' => $item->animal?->type ?? '—',
                    'status' => $item->status ?? 'pending',
                    'notes' => $item->note ?? null,
                    'delivery_date' => $item->delivery_date ? $item->delivery_date->format('Y-m-d') : null,
                    'has_adopted_before' => (bool) $item->has_adopted_before,
                    'created_at' => optional($item->created_at)->format('Y-m-d H:i'),
                ];
            });

        // ---------- Shelter Donation Submissions ----------
        $shelterDonations = ShelterDonationSubmission::with([
                'shelter:id,name,location,phone',
                'donationRequest:id,item'
            ])
            ->where('user_id', $user->id)
            ->orderByDesc('created_at')
            ->get()
            ->map(function ($item) {
                return [
                    'id' => $item->id,
                    'type' => 'shelter',
                    'subtype' => 'donation',
                    'title' => $item->shelter?->name ?? 'ملجأ',
                    'subtitle' => $item->donationRequest?->item ?? 'تبرع',
                    'location' => $item->shelter?->location ?? '—',
                    'phone' => $item->shelter?->phone ?? '—',
                    'donation_type' => $item->donation_type ?? '—',
                    'amount_or_item' => $item->amount_or_item ?? '—',
                    'status' => $item->status ?? 'pending',
                    'notes' => $item->note ?? null,
                    'created_at' => optional($item->created_at)->format('Y-m-d H:i'),
                ];
            });

        $all = collect()
            ->concat($appointments)
            ->concat($storeOrders)
            ->concat($shelterAdoptions)
            ->concat($shelterDonations)
            ->sortByDesc('created_at')
            ->values();

        return response()->json([
            'activities' => [
                'all' => $all,
                'clinics' => $appointments->values(),
                'stores' => $storeOrders->values(),
                'shelters' => collect()
                    ->concat($shelterAdoptions)
                    ->concat($shelterDonations)
                    ->sortByDesc('created_at')
                    ->values(),
            ],
        ]);
    }
}