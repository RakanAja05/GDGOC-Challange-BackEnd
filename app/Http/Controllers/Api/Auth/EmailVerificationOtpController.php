<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use App\Models\EmailVerificationOtp;
use App\Models\EmailVerificationStatus;
use App\Models\User;
use App\Services\Auth\EmailVerificationOtpService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;

class EmailVerificationOtpController extends Controller
{
    public function __construct(
        private readonly EmailVerificationOtpService $otp,
    ) {
    }

    public function request(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email'],
        ]);

        $user = User::query()->where('email', $validated['email'])->first();

        // Avoid user enumeration: always success.
        if (! $user) {
            return response()->json(['message' => 'Verification code sent.'], 200);
        }

        if ($user->hasVerifiedEmail()) {
            return response()->json(['message' => 'Email already verified.'], 200);
        }

        $this->otp->send($user);

        return response()->json(['message' => 'Verification code sent.'], 200);
    }

    public function verify(Request $request): JsonResponse
    {
        $length = (int) env('EMAIL_VERIFICATION_OTP_LENGTH', 6);

        $validated = $request->validate([
            'email' => ['required', 'email'],
            'code' => ['required', 'digits:'.$length],
        ]);

        $user = User::query()->where('email', $validated['email'])->first();
        if (! $user) {
            return response()->json(['message' => 'Invalid or expired code.'], 422);
        }

        if ($user->hasVerifiedEmail()) {
            EmailVerificationStatus::updateOrCreate(
                ['user_id' => $user->id],
                ['status' => 'verified', 'verified_at' => $user->email_verified_at ?? now()]
            );

            return response()->json([
                'message' => 'Email already verified.',
                'user_id' => $user->id,
            ], 200);
        }

        $ok = $this->otp->verify($user, $validated['code']);

        if (! $ok) {
            return response()->json([
                'message' => 'Invalid or expired code.',
            ], 422);
        }

        $freshUser = $user->fresh();

        EmailVerificationStatus::updateOrCreate(
            ['user_id' => $freshUser->id],
            ['status' => 'verified', 'verified_at' => $freshUser->email_verified_at ?? now()]
        );

        return response()->json([
            'message' => 'Email verified.',
            'user_id' => $freshUser->id,
        ], 200);
    }

    public function status(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email'],
        ]);

        $user = User::query()->where('email', $validated['email'])->first();
        if (! $user) {
            return response()->json([
                'status' => 'none',
                'verified' => false,
            ], 200);
        }

        $statusRow = EmailVerificationStatus::query()->where('user_id', $user->id)->first();
        $verified = $user->hasVerifiedEmail() || ($statusRow && $statusRow->status === 'verified');

        if ($verified) {
            return response()->json([
                'status' => 'verified',
                'verified' => true,
                'email_verified_at' => $user->email_verified_at,
            ], 200);
        }

        if ($statusRow) {
            return response()->json([
                'status' => $statusRow->status,
                'verified' => false,
            ], 200);
        }

        return response()->json(['status' => 'unverified', 'verified' => false], 200);
    }

    /**
     * Dev-only: exposes the latest OTP code so the frontend can read it during local/testing.
     */
    public function code(Request $request): JsonResponse
    {
        if (! app()->environment(['local', 'testing'])) {
            return response()->json(['message' => 'Not found.'], 404);
        }

        $validated = $request->validate([
            'email' => ['required', 'email'],
        ]);

        $user = User::query()->where('email', $validated['email'])->first();
        if (! $user) {
            return response()->json(['message' => 'OTP not available.'], 404);
        }

        $otp = EmailVerificationOtp::query()
            ->where('user_id', $user->id)
            ->whereNull('used_at')
            ->latest('id')
            ->first();

        if (! $otp || ! $otp->expires_at || $otp->expires_at->isPast() || empty($otp->code_encrypted)) {
            return response()->json(['message' => 'OTP not available.'], 404);
        }

        return response()->json([
            'email' => $user->email,
            'code' => Crypt::decryptString($otp->code_encrypted),
            'expires_at' => $otp->expires_at,
        ], 200);
    }
}
