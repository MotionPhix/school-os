<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\Admissions\ApplicationController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Admissions Capability Routes  (Slice 4)
|--------------------------------------------------------------------------
|
| Auto-mounted under /api/v1/admissions by CapabilityRouteServiceProvider
| with ['auth:sanctum','throttle:authenticated','tenant'] middleware.
|
| Route model binding uses {application}.
|
*/

Route::get('applications', [ApplicationController::class, 'index'])->name('applications.index');
Route::post('applications', [ApplicationController::class, 'store'])->name('applications.store');
Route::get('applications/{application}', [ApplicationController::class, 'show'])->name('applications.show');
Route::patch('applications/{application}', [ApplicationController::class, 'update'])->name('applications.update');
Route::delete('applications/{application}', [ApplicationController::class, 'destroy'])->name('applications.destroy');

// Pipeline transitions
Route::post('applications/{application}/advance', [ApplicationController::class, 'advance'])->name('applications.advance');

// Offer lifecycle
Route::post('applications/{application}/offer', [ApplicationController::class, 'sendOffer'])->name('applications.offer.send');
Route::post('applications/{application}/offer/response', [ApplicationController::class, 'respondOffer'])->name('applications.offer.respond');

// Terminal transition: convert to Student
Route::post('applications/{application}/enroll', [ApplicationController::class, 'enroll'])->name('applications.enroll');

// Assessment / interview scoring (precondition for the Offer stage)
Route::post('applications/{application}/scores', [ApplicationController::class, 'recordScores'])->name('applications.scores');

// Batch pipeline operations from the applications table
Route::post('applications/bulk', [ApplicationController::class, 'bulk'])->name('applications.bulk');
