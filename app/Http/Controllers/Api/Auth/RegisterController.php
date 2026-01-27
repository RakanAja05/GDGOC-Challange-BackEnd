<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\EmailVerificationStatus;
use App\Services\Auth\EmailVerificationOtpService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules;

class RegisterController extends Controller
{
    public function store(Request $request, EmailVerificationOtpService $otp): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:'.User::class],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
        ]);

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
        ]);

        EmailVerificationStatus::updateOrCreate(
            ['user_id' => $user->id],
            ['status' => 'unverified', 'verified_at' => null]
        );

        $otp->send($user);

        return response()->json([
            'message' => 'Registered. Verification code sent.',
            'user' => $user,
        ], 201);
    }
}
