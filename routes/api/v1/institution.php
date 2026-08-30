<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\Institution\AcademicYearController;
use App\Http\Controllers\Api\V1\Institution\CalendarEventController;
use App\Http\Controllers\Api\V1\Institution\CampusController;
use App\Http\Controllers\Api\V1\Institution\InstitutionProfileController;
use App\Http\Controllers\Api\V1\Institution\TermController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Institution Capability Routes  (Slice 2)
|--------------------------------------------------------------------------
|
| Auto-loaded under /api/v1/institution by CapabilityRouteServiceProvider
| with ['auth:sanctum','throttle:authenticated','tenant'] middleware.
|
| Route model bindings expect the following URL param names:
|   {campus}, {academic_year}, {term}, {calendar_event}
| Laravel will resolve these via Eloquent + the tenant global scope,
| so cross-tenant access returns 404 by construction.
|
*/

// ---- Institution profile (singleton per tenant) -----------------------------
Route::get('profile', [InstitutionProfileController::class, 'show'])->name('profile.show');
Route::put('profile', [InstitutionProfileController::class, 'update'])->name('profile.update');
Route::post('profile/logo', [InstitutionProfileController::class, 'uploadLogo'])->name('profile.logo.store');
Route::delete('profile/logo', [InstitutionProfileController::class, 'destroyLogo'])->name('profile.logo.destroy');

// ---- Campuses ----------------------------------------------------------------
Route::get('campuses', [CampusController::class, 'index'])->name('campuses.index');
Route::post('campuses', [CampusController::class, 'store'])->name('campuses.store');
Route::post('campuses/bulk', [CampusController::class, 'bulk'])->name('campuses.bulk');
Route::get('campuses/{campus}', [CampusController::class, 'show'])->name('campuses.show');
Route::patch('campuses/{campus}', [CampusController::class, 'update'])->name('campuses.update');
Route::delete('campuses/{campus}', [CampusController::class, 'destroy'])->name('campuses.destroy');

// ---- Academic years ----------------------------------------------------------
Route::get('academic-years', [AcademicYearController::class, 'index'])->name('academic-years.index');
Route::post('academic-years', [AcademicYearController::class, 'store'])->name('academic-years.store');
Route::get('academic-years/{academic_year}', [AcademicYearController::class, 'show'])->name('academic-years.show');
Route::patch('academic-years/{academic_year}', [AcademicYearController::class, 'update'])->name('academic-years.update');
Route::post('academic-years/{academic_year}/transition', [AcademicYearController::class, 'transition'])
    ->name('academic-years.transition');
Route::delete('academic-years/{academic_year}', [AcademicYearController::class, 'destroy'])
    ->name('academic-years.destroy');
Route::post('academic-years/{academic_year}/set-current', [AcademicYearController::class, 'setCurrent'])
    ->name('academic-years.set-current');

// ---- Terms (nested under academic year) --------------------------------------
Route::get('academic-years/{academic_year}/terms', [TermController::class, 'index'])->name('terms.index');
Route::post('academic-years/{academic_year}/terms', [TermController::class, 'store'])->name('terms.store');
Route::patch('academic-years/{academic_year}/terms/{term}', [TermController::class, 'update'])->name('terms.update');
Route::post('academic-years/{academic_year}/terms/{term}/transition', [TermController::class, 'transition'])
    ->name('terms.transition');
Route::delete('academic-years/{academic_year}/terms/{term}', [TermController::class, 'destroy'])->name('terms.destroy');

// ---- Calendar events ---------------------------------------------------------
Route::get('calendar', [CalendarEventController::class, 'index'])->name('calendar.index');
Route::post('calendar', [CalendarEventController::class, 'store'])->name('calendar.store');
Route::get('calendar/{calendar_event}', [CalendarEventController::class, 'show'])->name('calendar.show');
Route::patch('calendar/{calendar_event}', [CalendarEventController::class, 'update'])->name('calendar.update');
Route::delete('calendar/{calendar_event}', [CalendarEventController::class, 'destroy'])->name('calendar.destroy');
