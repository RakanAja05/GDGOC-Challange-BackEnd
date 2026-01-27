<?php

namespace App\Services\Auth;

use App\Mail\EmailVerificationOtpMail;
use App\Models\EmailVerificationOtp;
use App\Models\User;
use Illuminate\Auth\Events\Verified;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;

class EmailVerificationOtpService
{
    public function send(User $user): void
    {
        $resendSeconds = (int) env('EMAIL_VERIFICATION_OTP_RESEND_SECONDS', 60);

        $latest = EmailVerificationOtp::query()
            ->where('user_id', $user->id)
            ->latest('id')
            ->first();

        if ($latest && $latest->created_at && $latest->created_at->addSeconds($resendSeconds)->isFuture()) {
            throw ValidationException::withMessages([
                'otp' => ["Please wait {$resendSeconds} seconds before requesting a new code."],
            ]);
        }

        $length = (int) env('EMAIL_VERIFICATION_OTP_LENGTH', 6);
        $expiresMinutes = (int) env('EMAIL_VERIFICATION_OTP_EXPIRES_MINUTES', 10);

        $max = (10 ** $length) - 1;
        $code = str_pad((string) random_int(0, $max), $length, '0', STR_PAD_LEFT);

        EmailVerificationOtp::create([
            'user_id' => $user->id,
            'code_hash' => Hash::make($code),
            'code_encrypted' => Crypt::encryptString($code),
            'expires_at' => Carbon::now()->addMinutes($expiresMinutes),
        ]);

        Mail::to($user->email)->send(new EmailVerificationOtpMail($code, $expiresMinutes));
    }

    public function verify(User $user, string $code): bool
    {
        $otp = EmailVerificationOtp::query()
            ->where('user_id', $user->id)
            ->whereNull('used_at')
            ->latest('id')
            ->first();

        if (! $otp) {
            return false;
        }

        if ($otp->expires_at->isPast()) {
            return false;
        }

        if (! Hash::check($code, $otp->code_hash)) {
            return false;
        }

        $otp->forceFill(['used_at' => Carbon::now()])->save();

        if (! $user->hasVerifiedEmail()) {
            $user->markEmailAsVerified();
            event(new Verified($user));
        }

        return true;
    }
}
