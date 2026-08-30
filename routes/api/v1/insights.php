<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\Insights\AcademicReportController;
use App\Http\Controllers\Api\V1\Insights\AiAssistantController;
use App\Http\Controllers\Api\V1\Insights\EnrollmentReportController;
use App\Http\Controllers\Api\V1\Insights\FinancialInsightsController;
use App\Http\Controllers\Api\V1\Insights\InstitutionSnapshotController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Insights & Reports Capability Routes  (Slice 10)
|--------------------------------------------------------------------------
|
| Read-only dashboards composed from People, Admissions, Academics,
| Attendance, Assessments, and Finance. Every endpoint accepts an
| optional `period` (see InsightPeriod enum) or explicit from/to range.
|
*/

Route::get('institution/snapshot', InstitutionSnapshotController::class)->name('institution.snapshot');
Route::get('enrollment/report', EnrollmentReportController::class)->name('enrollment.report');
Route::get('academic/report', AcademicReportController::class)->name('academic.report');
Route::get('financial/report', FinancialInsightsController::class)->name('financial.report');
Route::post('ai/ask', AiAssistantController::class)
    ->middleware('throttle:insights_ai')
    ->name('ai.ask');
