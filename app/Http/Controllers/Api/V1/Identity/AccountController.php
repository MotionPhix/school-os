<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Identity;

use App\Http\Controllers\Api\V1\CapabilityController;
use App\Http\Requests\Api\V1\Identity\ForgotPasswordRequest;
use App\Http\Requests\Api\V1\Identity\ResendVerificationRequest;
use App\Http\Requests\Api\V1\Identity\ResetPasswordRequest;
use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Auth\Events\Verified;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Account credential lifecycle: email verification and password reset.
 *
 * This is the SchoolOS replacement for the credential half of the old
 * App\Http\Controllers\Api\V1\AuthController. Login/logout/me/register live
 * in SessionController — there is exactly one implementation of each.
 */
final class AccountController extends CapabilityController
{
    public function verifyEmail(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        if (! $user->hasVerifiedEmail() && $user->markEmailAsVerified()) {
            event(new Verified($user));
        }

        return $this->respondNoContent();
    }

    public function resendVerification(ResendVerificationRequest $request): JsonResponse
    {
        $user = User::query()->where('email', mb_strtolower($request->validated('email')))->first();

        // Do not leak account existence.
        if ($user !== null && ! $user->hasVerifiedEmail()) {
            $user->sendEmailVerificationNotification();
        }

        return $this->respondNoContent();
    }

    public function forgotPassword(ForgotPasswordRequest $request): JsonResponse
    {
        Password::sendResetLink(['email' => mb_strtolower($request->validated('email'))]);

        // Always 204 — enumeration-safe.
        return $this->respondNoContent();
    }

    public function resetPassword(ResetPasswordRequest $request): JsonResponse
    {
        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function (User $user, string $password): void {
                $user->forceFill(['password' => Hash::make($password)])->save();
                $user->tokens()->delete();
                event(new PasswordReset($user));
            },
        );

        if ($status !== Password::PASSWORD_RESET) {
            throw new HttpException(422, match ($status) {
                Password::INVALID_TOKEN => 'Invalid or expired reset token.',
                Password::INVALID_USER => 'No account matches that email.',
                default => 'Unable to reset password.',
            });
        }

        return $this->respondNoContent();
    }
}
