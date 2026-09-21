<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Shelter;
use App\Models\ShelterAnimal;
use App\Models\ShelterDonationRequest;
use App\Models\ShelterDonationSubmission;
use App\Models\ShelterAdoptionRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ShelterController extends Controller
{
    // ---------- Public ----------
    public function index(Request $request)
    {
        $perPage = (int) ($request->query('per_page', 12));

        $shelters = Shelter::query()
            ->where('is_active', true)
            ->orderByDesc('id')
            ->paginate($perPage);

        $data = $shelters->getCollection()->map(fn($shelter) => $this->serializeShelter($shelter));

        return response()->json([
            'data' => $data,
            'meta' => [
                'current_page' => $shelters->currentPage(),
                'last_page' => $shelters->lastPage(),
                'per_page' => $shelters->perPage(),
                'total' => $shelters->total(),
            ],
        ]);
    }

    public function show(Shelter $shelter)
    {
        return response()->json([
            'shelter' => $this->serializeShelter($shelter),
        ]);
    }

    public function publicAnimals(Shelter $shelter)
    {
        $animals = $shelter->animals()
            ->where('is_available', true)
            ->orderByDesc('id')
            ->get()
            ->map(fn($animal) => $this->serializeAnimal($animal));

        return response()->json([
            'animals' => $animals,
        ]);
    }

    public function publicDonationRequests(Shelter $shelter)
    {
        $requests = $shelter->donationRequests()
            ->orderByDesc('id')
            ->get()
            ->map(fn($item) => $this->serializeDonationRequest($item));

        return response()->json([
            'donation_requests' => $requests,
        ]);
    }

    // ---------- Setup ----------
    public function setup(Request $request)
    {
        $user = $request->user();

        if ((int) $user->role !== 4) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'specialty' => ['nullable', 'string', 'max:255'],
            'location' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:255'],
            'avatar' => ['nullable', 'image', 'max:5120'],
        ]);

        $shelter = Shelter::where('user_id', $user->id)->first();
        $avatarPath = $shelter?->avatar_path;

        if ($request->hasFile('avatar')) {
            $avatarPath = $request->file('avatar')->store('shelters', 'public');
        }

        $shelter = Shelter::updateOrCreate(
            ['user_id' => $user->id],
            [
                'name' => $validated['name'],
                'specialty' => $validated['specialty'] ?? null,
                'location' => $validated['location'] ?? null,
                'phone' => $validated['phone'] ?? null,
                'avatar_path' => $avatarPath,
                'is_active' => true,
            ]
        );

        if (!$user->has_setup) {
            $user->has_setup = true;
            $user->save();
        }

        return response()->json([
            'message' => 'تم إعداد الملجأ بنجاح',
            'shelter' => $this->serializeShelter($shelter),
            'user' => $user,
        ]);
    }

    // ---------- Dashboard ----------
    public function dashboard(Request $request)
    {
        $user = $request->user();

        if ((int) $user->role !== 4) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $shelter = Shelter::where('user_id', $user->id)->first();

        if (!$shelter) {
            return response()->json([
                'profile' => null,
                'statistics' => [
                    'animals' => 0,
                    'pending_adoptions' => 0,
                    'donation_requests' => 0,
                    'pending_donations' => 0,
                ],
                'animals' => [],
                'adoptions' => [],
                'donation_requests' => [],
                'donation_submissions' => [],
            ]);
        }

        $animals = $shelter->animals()
            ->orderByDesc('id')
            ->get()
            ->map(fn($animal) => $this->serializeAnimal($animal));

        $adoptions = $shelter->adoptionRequests()
            ->with(['user:id,name,email', 'animal:id,name,type'])
            ->orderByDesc('created_at')
            ->get()
            ->map(fn($adoption) => $this->serializeAdoptionRequest($adoption));

        $donationRequests = $shelter->donationRequests()
            ->orderByDesc('id')
            ->get()
            ->map(fn($item) => $this->serializeDonationRequest($item));

        $donationSubmissions = $shelter->donationSubmissions()
            ->with(['user:id,name,email', 'donationRequest:id,item'])
            ->orderByDesc('created_at')
            ->get()
            ->map(fn($item) => $this->serializeDonationSubmission($item));

        return response()->json([
            'profile' => $this->serializeShelter($shelter),
            'statistics' => [
                'animals' => $shelter->animals()->count(),
                'pending_adoptions' => $shelter->adoptionRequests()->where('status', 'pending')->count(),
                'donation_requests' => $shelter->donationRequests()->count(),
                'pending_donations' => $shelter->donationSubmissions()->where('status', 'pending')->count(),
            ],
            'animals' => $animals,
            'adoptions' => $adoptions,
            'donation_requests' => $donationRequests,
            'donation_submissions' => $donationSubmissions,
        ]);
    }

    // ---------- Animals ----------
    public function animals(Request $request)
    {
        $user = $request->user();

        if ((int) $user->role !== 4) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $shelter = Shelter::where('user_id', $user->id)->first();

        if (!$shelter) {
            return response()->json(['animals' => []]);
        }

        $animals = $shelter->animals()
            ->orderByDesc('id')
            ->get()
            ->map(fn($animal) => $this->serializeAnimal($animal));

        return response()->json(['animals' => $animals]);
    }

    public function storeAnimal(Request $request)
    {
        $user = $request->user();

        if ((int) $user->role !== 4) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $shelter = Shelter::where('user_id', $user->id)->first();
        if (!$shelter) {
            return response()->json(['message' => 'Shelter not found'], 404);
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', 'string', 'max:255'],
            'age' => ['nullable', 'string', 'max:255'],
            'health_status' => ['nullable', 'string', 'max:255'],
            'gender' => ['nullable', 'string', 'max:255'],
            'vaccines' => ['nullable', 'string', 'max:255'],
            'note' => ['nullable', 'string', 'max:2000'],
            'image' => ['nullable', 'image', 'max:5120'],
        ]);

        $imagePath = null;

        if ($request->hasFile('image')) {
            $imagePath = $request->file('image')->store('shelter_animals', 'public');
        }

        $animal = ShelterAnimal::create([
            'shelter_id' => $shelter->id,
            'name' => $validated['name'],
            'type' => $validated['type'],
            'age' => $validated['age'] ?? null,
            'health_status' => $validated['health_status'] ?? null,
            'gender' => $validated['gender'] ?? null,
            'vaccines' => $validated['vaccines'] ?? null,
            'note' => $validated['note'] ?? null,
            'image_path' => $imagePath,
            'is_available' => true,
        ]);

        return response()->json([
            'message' => 'تمت إضافة الحيوان',
            'animal' => $this->serializeAnimal($animal),
        ], 201);
    }

    // ---------- Donation Requests ----------
    public function donationRequests(Request $request)
    {
        $user = $request->user();

        if ((int) $user->role !== 4) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $shelter = Shelter::where('user_id', $user->id)->first();
        if (!$shelter) {
            return response()->json(['donation_requests' => []]);
        }

        $requests = $shelter->donationRequests()
            ->orderByDesc('id')
            ->get()
            ->map(fn($item) => $this->serializeDonationRequest($item));

        return response()->json([
            'donation_requests' => $requests,
        ]);
    }

    public function storeDonationRequest(Request $request)
    {
        $user = $request->user();

        if ((int) $user->role !== 4) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $shelter = Shelter::where('user_id', $user->id)->first();
        if (!$shelter) {
            return response()->json(['message' => 'Shelter not found'], 404);
        }

        $validated = $request->validate([
            'item' => ['required', 'string', 'max:255'],
            'priority' => ['nullable', 'string', 'max:255'],
            'quantity' => ['nullable', 'string', 'max:255'],
            'date' => ['nullable', 'date'],
            'status' => ['nullable', 'string', 'max:255'],
        ]);

        $item = ShelterDonationRequest::create([
            'shelter_id' => $shelter->id,
            'item' => $validated['item'],
            'priority' => $validated['priority'] ?? null,
            'quantity' => $validated['quantity'] ?? null,
            'date' => $validated['date'] ?? null,
            'status' => $validated['status'] ?? 'active',
        ]);

        return response()->json([
            'message' => 'تمت إضافة طلب التبرع',
            'donation_request' => $this->serializeDonationRequest($item),
        ], 201);
    }

    public function deleteDonationRequest(Request $request, $id)
    {
        $user = $request->user();

        if ((int) $user->role !== 4) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $shelter = Shelter::where('user_id', $user->id)->first();
        if (!$shelter) {
            return response()->json(['message' => 'Shelter not found'], 404);
        }

        $item = ShelterDonationRequest::where('shelter_id', $shelter->id)
            ->where('id', $id)
            ->first();

        if (!$item) {
            return response()->json(['message' => 'طلب التبرع غير موجود'], 404);
        }

        $item->delete();

        return response()->json([
            'message' => 'تم حذف طلب التبرع بنجاح',
        ]);
    }

    // ---------- Donation Submissions ----------
    public function createDonationSubmission(Request $request, $donationRequestId)
    {
        $user = $request->user();

        $validated = $request->validate([
            'donor_name' => ['required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:255'],
            'donation_type' => ['nullable', 'string', 'max:255'],
            'amount_or_item' => ['nullable', 'string', 'max:255'],
            'note' => ['nullable', 'string', 'max:2000'],
        ]);

        $donationRequest = ShelterDonationRequest::find($donationRequestId);

        if (!$donationRequest) {
            return response()->json(['message' => 'طلب التبرع غير موجود'], 404);
        }

        $submission = ShelterDonationSubmission::create([
            'shelter_id' => $donationRequest->shelter_id,
            'donation_request_id' => $donationRequest->id,
            'user_id' => $user->id,
            'donor_name' => $validated['donor_name'],
            'phone' => $validated['phone'] ?? null,
            'donation_type' => $validated['donation_type'] ?? null,
            'amount_or_item' => $validated['amount_or_item'] ?? null,
            'note' => $validated['note'] ?? null,
            'status' => 'pending',
        ]);

        $submission->load(['user:id,name,email', 'donationRequest:id,item']);

        return response()->json([
            'message' => 'تم إرسال طلب التبرع بنجاح',
            'donation_submission' => $this->serializeDonationSubmission($submission),
        ], 201);
    }

    public function donationSubmissions(Request $request)
    {
        $user = $request->user();

        if ((int) $user->role !== 4) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $shelter = Shelter::where('user_id', $user->id)->first();
        if (!$shelter) {
            return response()->json(['donation_submissions' => []]);
        }

        $items = $shelter->donationSubmissions()
            ->with(['user:id,name,email', 'donationRequest:id,item'])
            ->orderByDesc('created_at')
            ->get()
            ->map(fn($item) => $this->serializeDonationSubmission($item));

        return response()->json([
            'donation_submissions' => $items,
        ]);
    }

    public function updateDonationSubmissionStatus(Request $request, $id)
    {
        $user = $request->user();

        if ((int) $user->role !== 4) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $validated = $request->validate([
            'status' => ['required', 'in:approved,rejected'],
        ]);

        $shelter = Shelter::where('user_id', $user->id)->first();
        if (!$shelter) {
            return response()->json(['message' => 'Shelter not found'], 404);
        }

        $item = ShelterDonationSubmission::where('shelter_id', $shelter->id)
            ->where('id', $id)
            ->first();

        if (!$item) {
            return response()->json(['message' => 'طلب التبرع غير موجود'], 404);
        }

        $item->status = $validated['status'];
        $item->save();

        return response()->json([
            'message' => $validated['status'] === 'approved'
                ? 'تم قبول التبرع'
                : 'تم رفض التبرع',
        ]);
    }

    // ---------- Adoption Requests ----------
    public function createAdoptionRequest(Request $request, $animal)
    {
        $user = $request->user();

        $validated = $request->validate([
            'phone' => ['required', 'string', 'max:255'],
            'has_adopted_before' => ['required', 'boolean'],
            'delivery_date' => ['nullable', 'date'],
            'note' => ['nullable', 'string', 'max:2000'],
        ]);

        $animal = ShelterAnimal::with('shelter')->find($animal);

        if (!$animal || !$animal->shelter || !$animal->is_available) {
            return response()->json(['message' => 'الحيوان غير متاح'], 404);
        }

        $requestModel = ShelterAdoptionRequest::create([
            'shelter_id' => $animal->shelter_id,
            'animal_id' => $animal->id,
            'user_id' => $user->id,
            'phone' => $validated['phone'],
            'has_adopted_before' => $validated['has_adopted_before'],
            'delivery_date' => $validated['delivery_date'] ?? null,
            'note' => $validated['note'] ?? null,
            'status' => 'pending',
        ]);

        $requestModel->load(['user:id,name,email', 'animal:id,name,type', 'shelter:id,name']);

        return response()->json([
            'message' => 'تم إرسال طلب التبني',
            'adoption_request' => $this->serializeAdoptionRequest($requestModel),
        ], 201);
    }

    public function adoptionRequests(Request $request)
    {
        $user = $request->user();

        if ((int) $user->role !== 4) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $shelter = Shelter::where('user_id', $user->id)->first();
        if (!$shelter) {
            return response()->json(['adoptions' => []]);
        }

        $items = $shelter->adoptionRequests()
            ->with(['user:id,name,email', 'animal:id,name,type'])
            ->orderByDesc('created_at')
            ->get()
            ->map(fn($item) => $this->serializeAdoptionRequest($item));

        return response()->json([
            'adoptions' => $items,
        ]);
    }

    public function updateAdoptionRequestStatus(Request $request, $id)
    {
        $user = $request->user();

        if ((int) $user->role !== 4) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $validated = $request->validate([
            'status' => ['required', 'in:approved,rejected'],
        ]);

        $shelter = Shelter::where('user_id', $user->id)->first();
        if (!$shelter) {
            return response()->json(['message' => 'Shelter not found'], 404);
        }

        return DB::transaction(function () use ($shelter, $id, $validated) {
            $adoption = ShelterAdoptionRequest::where('shelter_id', $shelter->id)
                ->where('id', $id)
                ->lockForUpdate()
                ->first();

            if (!$adoption) {
                return response()->json(['message' => 'طلب التبني غير موجود'], 404);
            }

            if ($adoption->status !== 'pending') {
                return response()->json(['message' => 'تمت معالجة الطلب مسبقاً'], 422);
            }

            $animal = ShelterAnimal::where('shelter_id', $shelter->id)
                ->where('id', $adoption->animal_id)
                ->lockForUpdate()
                ->first();

            if (!$animal) {
                return response()->json(['message' => 'الحيوان غير موجود'], 404);
            }

            if ($validated['status'] === 'approved') {
                $animal->is_available = false;
                $animal->save();
            }

            $adoption->status = $validated['status'];
            $adoption->save();

            return response()->json([
                'message' => $validated['status'] === 'approved'
                    ? 'تم قبول طلب التبني'
                    : 'تم رفض طلب التبني',
            ]);
        });
    }

    // ---------- Serializers ----------
    private function serializeShelter(Shelter $shelter): array
    {
        return [
            'id' => $shelter->id,
            'name' => $shelter->name,
            'specialty' => $shelter->specialty,
            'location' => $shelter->location,
            'phone' => $shelter->phone,
            'rating' => (float) $shelter->rating,
            'avatar_url' => $shelter->avatar_url,
            'avatar_path' => $shelter->avatar_path,
            'is_active' => (bool) $shelter->is_active,
        ];
    }

    private function serializeAnimal(ShelterAnimal $animal): array
    {
        return [
            'id' => $animal->id,
            'name' => $animal->name,
            'type' => $animal->type,
            'age' => $animal->age,
            'health_status' => $animal->health_status,
            'gender' => $animal->gender,
            'vaccines' => $animal->vaccines,
            'note' => $animal->note,
            'image_url' => $animal->image_url,
            'image_path' => $animal->image_path,
            'is_available' => (bool) $animal->is_available,
        ];
    }

    private function serializeDonationRequest(ShelterDonationRequest $item): array
    {
        return [
            'id' => $item->id,
            'item' => $item->item,
            'priority' => $item->priority,
            'quantity' => $item->quantity,
            'date' => $item->date?->format('Y-m-d'),
            'status' => $item->status,
        ];
    }

    private function serializeDonationSubmission(ShelterDonationSubmission $item): array
    {
        return [
            'id' => $item->id,
            'donor_name' => $item->donor_name,
            'customer_name' => $item->user?->name ?? $item->donor_name,
            'customer_email' => $item->user?->email ?? '—',
            'phone' => $item->phone,
            'donation_type' => $item->donation_type,
            'amount_or_item' => $item->amount_or_item,
            'request_item' => $item->donationRequest?->item ?? '—',
            'note' => $item->note,
            'status' => $item->status,
            'created_at' => optional($item->created_at)->format('Y-m-d H:i'),
        ];
    }

    private function serializeAdoptionRequest(ShelterAdoptionRequest $item): array
    {
        return [
            'id' => $item->id,
            'animal_id' => $item->animal_id,
            'animal_name' => $item->animal?->name ?? '—',
            'animal_type' => $item->animal?->type ?? '—',
            'customer_name' => $item->user?->name ?? '—',
            'customer_email' => $item->user?->email ?? '—',
            'phone' => $item->phone,
            'has_adopted_before' => $item->has_adopted_before,
            'delivery_date' => $item->delivery_date?->format('Y-m-d'),
            'note' => $item->note,
            'status' => $item->status,
            'created_at' => optional($item->created_at)->format('Y-m-d H:i'),
        ];
    }
}