<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use Illuminate\Auth\Events\Verified;
use Illuminate\Foundation\Auth\EmailVerificationRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

final class EmailVerificationController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [new Middleware('throttle:6,1', only: ['resend'])];
    }

    /**
     * $request is EmailVerificationRequest, which Laravel resolves only
     * after validating the route's signature and the authenticated
     * user's id/hash — a forged or expired link 403s before reaching here.
     */
    public function verify(EmailVerificationRequest $request): JsonResponse
    {
        if ($request->user()->hasVerifiedEmail()) {
            return response()->json(['message' => 'Email already verified.']);
        }

        $request->fulfill(); // marks verified, fires Verified event

        AuditLog::record(
            workspaceId: null,
            userId: $request->user()->id,
            action: 'user.email_verified',
            ipAddress: $request->ip(),
        );

        return response()->json(['message' => 'Email verified.']);
    }

    public function resend(Request $request): JsonResponse
    {
        if ($request->user()->hasVerifiedEmail()) {
            return response()->json(['message' => 'Email already verified.']);
        }

        $request->user()->sendEmailVerificationNotification();

        return response()->json(['message' => 'Verification link sent.']);
    }
}
