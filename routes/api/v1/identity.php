<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\Identity\AccountController;
use App\Http\Controllers\Api\V1\Identity\AuditEventController;
use App\Http\Controllers\Api\V1\Identity\InvitationController;
use App\Http\Controllers\Api\V1\Identity\PermissionController;
use App\Http\Controllers\Api\V1\Identity\PublicInvitationController;
use App\Http\Controllers\Api\V1\Identity\RoleController;
use App\Http\Controllers\Api\V1\Identity\SessionController;
use App\Http\Controllers\Api\V1\Identity\TenantController;
use App\Http\Controllers\Api\V1\Identity\UserController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Identity & Access Capability Routes  (Slice 1)
|--------------------------------------------------------------------------
|
| Auto-loaded under /api/v1/identity with
| ['auth:sanctum','throttle:authenticated','resolve.tenant'] middleware.
|
| This capability owns the ENTIRE auth surface. AuthController is retired —
| do not register /api/v1/login|logout|me|register|forgot-password anymore.
|
| Public endpoints opt OUT of the group defaults via ->withoutMiddleware().
|
*/

// ---- Public (auth-establishing / credential recovery) ------------------------
Route::withoutMiddleware(['auth:sanctum', 'resolve.tenant'])
    ->middleware('throttle:auth')
    ->group(function (): void {
        Route::post('session', [SessionController::class, 'login'])->name('session.login');
        Route::post('registration', [SessionController::class, 'register'])->name('registration.store');
        Route::post('invitations/accept', [PublicInvitationController::class, 'accept'])
            ->name('invitations.accept');
        Route::post('password/forgot', [AccountController::class, 'forgotPassword'])->name('password.forgot');
        Route::post('password/reset', [AccountController::class, 'resetPassword'])->name('password.reset');
        Route::post('email/resend', [AccountController::class, 'resendVerification'])->name('email.resend');
    });

// ---- Session -----------------------------------------------------------------
// Onboarding surface: a freshly registered user has no membership yet, so these
// must not run resolve.tenant (it fails closed with 403).
Route::withoutMiddleware(['resolve.tenant'])->group(function (): void {
    Route::delete('session', [SessionController::class, 'logout'])->name('session.logout');
    Route::get('session', [SessionController::class, 'me'])->name('session.me');
    Route::post('session/switch-tenant', [SessionController::class, 'switchTenant'])
        ->name('session.switch-tenant');
});

// ---- Email verification (signed link, authenticated) --------------------------
Route::post('email/verify/{id}/{hash}', [AccountController::class, 'verifyEmail'])
    ->withoutMiddleware(['resolve.tenant', 'verified'])
    ->name('verification.verify');

// ---- Tenants -----------------------------------------------------------------
// index/store are part of Day-0 onboarding (no membership exists yet).
Route::withoutMiddleware(['resolve.tenant'])->group(function (): void {
    Route::get('tenants', [TenantController::class, 'index'])->name('tenants.index');
    Route::post('tenants', [TenantController::class, 'store'])->name('tenants.store');
});
Route::get('tenants/{tenant}', [TenantController::class, 'show'])->name('tenants.show');
Route::patch('tenants/{tenant}', [TenantController::class, 'update'])->name('tenants.update');

// ---- Users -------------------------------------------------------------------
Route::get('users', [UserController::class, 'index'])->name('users.index');
Route::get('users/{user}', [UserController::class, 'show'])->name('users.show');
Route::put('users/{user}/roles', [UserController::class, 'assignRoles'])->name('users.roles');
Route::post('users/{user}/suspend', [UserController::class, 'suspend'])->name('users.suspend');
Route::post('users/{user}/reactivate', [UserController::class, 'reactivate'])->name('users.reactivate');

// ---- Roles -------------------------------------------------------------------
Route::get('roles', [RoleController::class, 'index'])->name('roles.index');
Route::post('roles', [RoleController::class, 'store'])->name('roles.store');
Route::get('roles/{role}', [RoleController::class, 'show'])->name('roles.show');
Route::patch('roles/{role}', [RoleController::class, 'update'])->name('roles.update');
Route::delete('roles/{role}', [RoleController::class, 'destroy'])->name('roles.destroy');

// ---- Permissions (catalog, read-only) ----------------------------------------
Route::get('permissions', [PermissionController::class, 'index'])->name('permissions.index');

// ---- Invitations -------------------------------------------------------------
Route::get('invitations', [InvitationController::class, 'index'])->name('invitations.index');
Route::post('invitations', [InvitationController::class, 'store'])->name('invitations.store');
Route::post('invitations/{invitation}/resend', [InvitationController::class, 'resend'])->name('invitations.resend');
Route::post('invitations/{invitation}/revoke', [InvitationController::class, 'revoke'])->name('invitations.revoke');
Route::post('invitations/bulk', [InvitationController::class, 'bulk'])->name('invitations.bulk');

// ---- Activity log (append-only audit projection) ------------------------------
Route::get('audit-events', [AuditEventController::class, 'index'])->name('audit-events.index');
