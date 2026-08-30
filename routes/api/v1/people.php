<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\People\GuardianController;
use App\Http\Controllers\Api\V1\People\StaffMemberController;
use App\Http\Controllers\Api\V1\People\StudentController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| People Capability Routes  (Slice 3)
|--------------------------------------------------------------------------
|
| Auto-mounted under /api/v1/people by CapabilityRouteServiceProvider
| with ['auth:sanctum','throttle:authenticated','tenant'] middleware.
|
| Route model bindings use these URL param names:
|   {student}, {guardian}, {staff_member}, {document}
|
*/

// ---- Students ----------------------------------------------------------------
Route::post('students/bulk', [StudentController::class, 'bulk'])->name('students.bulk');
Route::get('students', [StudentController::class, 'index'])->name('students.index');
Route::post('students', [StudentController::class, 'store'])->name('students.store');
Route::get('students/{student}', [StudentController::class, 'show'])->name('students.show');
Route::patch('students/{student}', [StudentController::class, 'update'])->name('students.update');
Route::delete('students/{student}', [StudentController::class, 'destroy'])->name('students.destroy');
Route::post('students/{student}/status', [StudentController::class, 'setStatus'])->name('students.status');

Route::post('students/{student}/transfer', [StudentController::class, 'transfer'])->name('students.transfer');

Route::post('students/{student}/avatar', [StudentController::class, 'uploadAvatar'])->name('students.avatar');
Route::post('students/{student}/documents', [StudentController::class, 'uploadDocument'])->name('students.documents.store');
Route::delete('students/{student}/documents/{document}', [StudentController::class, 'destroyDocument'])->name('students.documents.destroy');

// ---- Guardians ---------------------------------------------------------------
Route::post('guardians/bulk', [GuardianController::class, 'bulk'])->name('guardians.bulk');
Route::get('guardians', [GuardianController::class, 'index'])->name('guardians.index');
Route::post('guardians', [GuardianController::class, 'store'])->name('guardians.store');
Route::get('guardians/{guardian}', [GuardianController::class, 'show'])->name('guardians.show');
Route::patch('guardians/{guardian}', [GuardianController::class, 'update'])->name('guardians.update');
Route::delete('guardians/{guardian}', [GuardianController::class, 'destroy'])->name('guardians.destroy');

Route::post('guardians/{guardian}/invite', [GuardianController::class, 'invite'])->name('guardians.invite');
Route::post('guardians/{guardian}/portal-status', [GuardianController::class, 'setPortalStatus'])->name('guardians.portal_status');

Route::post('guardians/{guardian}/avatar', [GuardianController::class, 'uploadAvatar'])->name('guardians.avatar');
Route::post('guardians/{guardian}/documents', [GuardianController::class, 'uploadDocument'])->name('guardians.documents.store');
Route::delete('guardians/{guardian}/documents/{document}', [GuardianController::class, 'destroyDocument'])->name('guardians.documents.destroy');

// ---- Student <-> Guardian links ---------------------------------------------
Route::put('students/{student}/guardians/{guardian}', [GuardianController::class, 'link'])->name('students.guardians.link');
Route::delete('students/{student}/guardians/{guardian}', [GuardianController::class, 'unlink'])->name('students.guardians.unlink');

// ---- Staff -------------------------------------------------------------------
Route::post('staff/bulk', [StaffMemberController::class, 'bulk'])->name('staff.bulk');
Route::get('staff', [StaffMemberController::class, 'index'])->name('staff.index');
Route::post('staff', [StaffMemberController::class, 'store'])->name('staff.store');
Route::get('staff/{staff_member}', [StaffMemberController::class, 'show'])->name('staff.show');
Route::patch('staff/{staff_member}', [StaffMemberController::class, 'update'])->name('staff.update');
Route::delete('staff/{staff_member}', [StaffMemberController::class, 'destroy'])->name('staff.destroy');
Route::post('staff/{staff_member}/status', [StaffMemberController::class, 'setStatus'])->name('staff.status');

Route::post('staff/{staff_member}/login', [StaffMemberController::class, 'issueLogin'])->name('staff.login.issue');
Route::delete('staff/{staff_member}/login', [StaffMemberController::class, 'revokeLogin'])->name('staff.login.revoke');

Route::post('staff/{staff_member}/avatar', [StaffMemberController::class, 'uploadAvatar'])->name('staff.avatar');
Route::post('staff/{staff_member}/documents', [StaffMemberController::class, 'uploadDocument'])->name('staff.documents.store');
Route::delete('staff/{staff_member}/documents/{document}', [StaffMemberController::class, 'destroyDocument'])->name('staff.documents.destroy');
