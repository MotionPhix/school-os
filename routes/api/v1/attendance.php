<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\Attendance\AttendanceSessionController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Attendance Capability Routes  (Slice 6)
|--------------------------------------------------------------------------
|
| Auto-mounted under /api/v1/attendance by CapabilityRouteServiceProvider
| with ['auth:sanctum','throttle:authenticated','tenant'] middleware.
|
*/

// Sessions (register per course section × date × period)
Route::get('sessions', [AttendanceSessionController::class, 'index'])->name('sessions.index');
Route::post('sessions/open', [AttendanceSessionController::class, 'open'])->name('sessions.open');
Route::get('sessions/{attendance_session}', [AttendanceSessionController::class, 'show'])->name('sessions.show');
Route::post('sessions/{attendance_session}/marks', [AttendanceSessionController::class, 'mark'])->name('sessions.mark');
Route::post('sessions/{attendance_session}/marks/bulk', [AttendanceSessionController::class, 'markBulk'])->name('sessions.marks.bulk');
Route::post('sessions/bulk', [AttendanceSessionController::class, 'bulk'])->name('sessions.bulk');
Route::post('sessions/{attendance_session}/reopen', [AttendanceSessionController::class, 'reopen'])->name('sessions.reopen');
Route::post('sessions/{attendance_session}/submit', [AttendanceSessionController::class, 'submit'])->name('sessions.submit');
Route::delete('sessions/{attendance_session}', [AttendanceSessionController::class, 'destroy'])->name('sessions.destroy');

// Rollups
Route::get('summary', [AttendanceSessionController::class, 'summary'])->name('summary');
