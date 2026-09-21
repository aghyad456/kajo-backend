<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Clinic;

class ClinicController extends Controller
{
    // GET /api/clinics?page=1
    public function index(Request $request)
    {
        $perPage = (int) ($request->query('per_page', 12));

        $clinics = Clinic::query()
            ->with('user:id,name,email')
            ->where('is_active', true)
            ->orderByDesc('id')
            ->paginate($perPage);

        $data = $clinics->getCollection()->map(function ($clinic) {
            return $this->serializeClinic($clinic);
        });

        return response()->json([
            'data' => $data,
            'meta' => [
                'current_page' => $clinics->currentPage(),
                'last_page' => $clinics->lastPage(),
                'per_page' => $clinics->perPage(),
                'total' => $clinics->total(),
            ],
        ]);
    }

    // GET /api/clinics/{clinic}
    public function show(Clinic $clinic)
    {
        $clinic->load('user:id,name,email');

        return response()->json([
            'clinic' => $this->serializeClinic($clinic),
        ]);
    }

    // POST /api/clinic/setup
    public function setup(Request $request)
    {
        $user = $request->user();

        if ((int) $user->role !== 5) {
            return response()->json([
                'message' => 'Unauthorized'
            ], 403);
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'specialty' => ['required', 'string', 'max:255'],
            'location' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'max:255'],
            'avatar' => ['nullable', 'image', 'max:5120'],
        ]);

        $clinic = Clinic::where('user_id', $user->id)->first();

        $avatarPath = $clinic?->avatar_path;

        if ($request->hasFile('avatar')) {
            $avatarPath = $request->file('avatar')->store('clinics', 'public');
        }

        $clinic = Clinic::updateOrCreate(
            ['user_id' => $user->id],
            [
                'name' => $validated['name'],
                'specialty' => $validated['specialty'],
                'location' => $validated['location'],
                'phone' => $validated['phone'],
                'avatar_path' => $avatarPath,
                'is_active' => true,
            ]
        );

        if (!$user->has_setup) {
            $user->has_setup = true;
            $user->save();
        }

        $clinic->load('user:id,name,email');

        return response()->json([
            'message' => 'تم إعداد العيادة بنجاح',
            'clinic' => $this->serializeClinic($clinic),
            'user' => $user,
        ], 200);
    }

    private function serializeClinic(Clinic $clinic): array
    {
        return [
            'id' => $clinic->id,
            'name' => $clinic->name,
            'specialty' => $clinic->specialty,
            'location' => $clinic->location,
            'phone' => $clinic->phone,
            'is_active' => (bool) $clinic->is_active,
            'avatar_url' => $clinic->avatar_url,
            'avatar_path' => $clinic->avatar_path,
            'email' => $clinic->user?->email,
            'user' => [
                'id' => $clinic->user?->id,
                'name' => $clinic->user?->name,
                'email' => $clinic->user?->email,
            ],
        ];
    }
}