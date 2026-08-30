<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\Assessments\ExamController;
use App\Http\Controllers\Api\V1\Assessments\ExamPeriodController;
use App\Http\Controllers\Api\V1\Assessments\ReportCardController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Assessments & Exams Capability Routes  (Slice 7)
|--------------------------------------------------------------------------
|
| Auto-mounted under /api/v1/assessments by CapabilityRouteServiceProvider
| with ['auth:sanctum','throttle:authenticated','tenant'] middleware.
|
*/

// Exam periods
Route::get('periods', [ExamPeriodController::class, 'index'])->name('periods.index');
Route::post('periods', [ExamPeriodController::class, 'store'])->name('periods.store');
Route::post('periods/bulk', [ExamPeriodController::class, 'bulk'])->name('periods.bulk');
Route::get('periods/{exam_period}', [ExamPeriodController::class, 'show'])->name('periods.show');
Route::patch('periods/{exam_period}', [ExamPeriodController::class, 'update'])->name('periods.update');
Route::post('periods/{exam_period}/status', [ExamPeriodController::class, 'setStatus'])->name('periods.status');
Route::delete('periods/{exam_period}', [ExamPeriodController::class, 'destroy'])->name('periods.destroy');

// Exam papers
Route::get('exams', [ExamController::class, 'index'])->name('exams.index');
Route::post('exams', [ExamController::class, 'store'])->name('exams.store');
Route::post('exams/bulk', [ExamController::class, 'bulk'])->name('exams.bulk');
Route::get('exams/{exam}', [ExamController::class, 'show'])->name('exams.show');
Route::patch('exams/{exam}', [ExamController::class, 'update'])->name('exams.update');
Route::post('exams/{exam}/status', [ExamController::class, 'setStatus'])->name('exams.status');
Route::post('exams/{exam}/results', [ExamController::class, 'setResult'])->name('exams.results.set');
Route::post('exams/{exam}/results/bulk', [ExamController::class, 'bulkResults'])->name('exams.results.bulk');
Route::post('exams/{exam}/results/fill', [ExamController::class, 'fillResults'])->name('exams.results.fill');
Route::post('exams/{exam}/results/curve', [ExamController::class, 'curveResults'])->name('exams.results.curve');
Route::delete('exams/{exam}', [ExamController::class, 'destroy'])->name('exams.destroy');

// Report cards
Route::get('reports/term', [ReportCardController::class, 'term'])->name('reports.term');
