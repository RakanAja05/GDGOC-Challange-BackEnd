<?php

namespace App\Http\Middleware;

use App\Models\EmailVerificationStatus;
use Closure;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureEmailIsVerified
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        if ($user instanceof MustVerifyEmail) {
            $verified = $user->hasVerifiedEmail();

            if (! $verified) {
                $statusRow = EmailVerificationStatus::query()->where('user_id', $user->id)->first();
                $verified = $statusRow && $statusRow->status === 'verified';
            }

            if (! $verified) {
                return response()->json(['message' => 'Your email address is not verified.'], 409);
            }
        }

        return $next($request);
    }
}
