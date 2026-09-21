<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\JoinRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Password;
use Illuminate\Auth\Events\PasswordReset;

class AuthController extends Controller
{
    public function register(Request $request)
    {
        $data = $request->validate([
            'name' => ['required','string','max:255'],
            'email' => ['required','email','max:255','unique:users,email'],
            'password' => ['required','string','min:8'], // ✅ 8 محارف
            'requested_role' => ['nullable', 'in:user,doctor,shop_owner,shelter_owner'],
        ]);

        // ✅ دائماً أنشئه كمستخدم عادي role=0
        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
            'role' => User::ROLE_USER,

        ]);

        // ✅ إذا طلب دور خاص → أنشئ JoinRequest pending
        $requestedRole = $data['requested_role'] ?? 'user';
        if (in_array($requestedRole, ['doctor', 'shop_owner', 'shelter_owner'])) {
            JoinRequest::create([
                'user_id' => $user->id,
                'requested_role' => $requestedRole,
                'status' => 'pending',
                'document_path' => null, // لاحقاً
            ]);
        }

        // ✅ أعطه توكن مباشرة حتى يدخل فوراً
        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'user' => $user,
            'token' => $token,
            'pending_request' => in_array($requestedRole, ['doctor', 'shop_owner', 'shelter_owner']),
            'requested_role' => $requestedRole,
        ], 201);
    }

    public function login(Request $request)
    {
        $data = $request->validate([
            'email' => ['required','email'],
            'password' => ['required','string'],
        ]);

        $user = User::where('email', $data['email'])->first();

        if (!$user || !Hash::check($data['password'], $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['Invalid credentials.'],
            ]);
        }

        $user->tokens()->delete();
        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'user' => $user,
            'token' => $token,
        ]);
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'message' => 'Logged out',
        ]);
    }

    public function me(Request $request)
    {
        return response()->json([
            'user' => $request->user(),
        ]);
    }

    public function forgotPassword(Request $request)
    {
        $request->validate([
            'email' => ['required', 'email'],
        ]);

        $user = User::where('email', $request->email)->first();

        if (!$user) {
            return response()->json([
                'message' => 'هذا البريد غير موجود'
            ], 404);
        }

        $token = Password::createToken($user);

        $email = urlencode($user->email);
        $link = "http://localhost:5173/reset-password?token={$token}&email={$email}";

        return response()->json([
            'message' => 'تم إنشاء رابط إعادة التعيين (وضع التطوير)',
            'reset_link' => $link,
        ]);
    }

    public function resetPassword(Request $request)
    {
        $request->validate([
            'token' => ['required'],
            'email' => ['required', 'email'],
            'password' => ['required', 'min:8'], // ✅ 8 محارف
        ]);

        $status = Password::reset(
            $request->only('email', 'password', 'token'),
            function ($user, $password) {
                $user->forceFill([
                    'password' => Hash::make($password)
                ])->save();

                event(new PasswordReset($user));
            }
        );

        return response()->json([
            'message' => __($status)
        ]);
    }
}
