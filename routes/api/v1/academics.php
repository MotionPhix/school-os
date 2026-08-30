<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\Academics\CourseSectionController;
use App\Http\Controllers\Api\V1\Academics\GradebookController;
use App\Http\Controllers\Api\V1\Academics\SubjectController;
use App\Http\Controllers\Api\V1\Academics\TimetableController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Academics Capability Routes  (Slice 5)
|--------------------------------------------------------------------------
|
| Auto-mounted under /api/v1/academics by CapabilityRouteServiceProvider
| with ['auth:sanctum','throttle:authenticated','tenant'] middleware.
|
*/

// Subjects (catalog)
Route::get('subjects', [SubjectController::class, 'index'])->name('subjects.index');
Route::post('subjects', [SubjectController::class, 'store'])->name('subjects.store');
Route::post('subjects/bulk', [SubjectController::class, 'bulk'])->name('subjects.bulk');
Route::get('subjects/{subject}', [SubjectController::class, 'show'])->name('subjects.show');
Route::patch('subjects/{subject}', [SubjectController::class, 'update'])->name('subjects.update');
Route::delete('subjects/{subject}', [SubjectController::class, 'destroy'])->name('subjects.destroy');

// Course sections
Route::get('courses', [CourseSectionController::class, 'index'])->name('courses.index');
Route::post('courses', [CourseSectionController::class, 'store'])->name('courses.store');
Route::post('courses/bulk', [CourseSectionController::class, 'bulk'])->name('courses.bulk');
Route::get('courses/{course_section}', [CourseSectionController::class, 'show'])->name('courses.show');
Route::patch('courses/{course_section}', [CourseSectionController::class, 'update'])->name('courses.update');
Route::post('courses/{course_section}/duplicate', [CourseSectionController::class, 'duplicate'])->name('courses.duplicate');
Route::delete('courses/{course_section}', [CourseSectionController::class, 'destroy'])->name('courses.destroy');

// Course roster / enrollment
Route::get('courses/{course_section}/roster', [CourseSectionController::class, 'roster'])->name('courses.roster');
Route::post('courses/{course_section}/enrollments', [CourseSectionController::class, 'enroll'])->name('courses.enroll');
Route::delete('courses/{course_section}/enrollments/{student}', [CourseSectionController::class, 'drop'])->name('courses.drop');

// Timetable
Route::get('timetable', [TimetableController::class, 'index'])->name('timetable.index');
Route::post('courses/{course_section}/timetable', [TimetableController::class, 'schedule'])->name('timetable.schedule');
Route::patch('timetable/{timetable_slot}/move', [TimetableController::class, 'move'])->name('timetable.move');
Route::delete('timetable/{timetable_slot}', [TimetableController::class, 'destroy'])->name('timetable.destroy');

// Gradebook
Route::get('gradebook', [GradebookController::class, 'index'])->name('gradebook.index');
Route::post('gradebook', [GradebookController::class, 'upsert'])->name('gradebook.upsert');
Route::post('gradebook/bulk', [GradebookController::class, 'bulkSave'])->name('gradebook.bulk');
Route::post('gradebook/curve', [GradebookController::class, 'curve'])->name('gradebook.curve');
Route::delete('gradebook/{gradebook_entry}', [GradebookController::class, 'destroy'])->name('gradebook.destroy');
