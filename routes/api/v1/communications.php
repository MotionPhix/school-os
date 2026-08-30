<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\Communications\AnnouncementController;
use App\Http\Controllers\Api\V1\Communications\BroadcastController;
use App\Http\Controllers\Api\V1\Communications\CommunicationsOverviewController;
use App\Http\Controllers\Api\V1\Communications\MessageThreadController;
use App\Http\Controllers\Api\V1\Communications\NotificationInboxController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Communications Capability Routes  (Slice 9)
|--------------------------------------------------------------------------
|
| Auto-mounted under /api/v1/communications by CapabilityRouteServiceProvider
| with ['auth:sanctum','throttle:authenticated','tenant'] middleware.
|
*/

// Overview dashboard KPIs
Route::get('overview', CommunicationsOverviewController::class)->name('overview');

// Announcements
Route::get('announcements', [AnnouncementController::class, 'index'])->name('announcements.index');
Route::post('announcements', [AnnouncementController::class, 'store'])->name('announcements.store');
Route::post('announcements/bulk', [AnnouncementController::class, 'bulk'])->name('announcements.bulk');
// Personal notification inbox (event-driven stored notifications)
Route::get('notifications', [NotificationInboxController::class, 'index'])->name('notifications.index');
Route::post('notifications/{notification}/read', [NotificationInboxController::class, 'markRead'])->name('notifications.read');

Route::get('announcements/{announcement}', [AnnouncementController::class, 'show'])->name('announcements.show');
Route::patch('announcements/{announcement}', [AnnouncementController::class, 'update'])->name('announcements.update');
Route::post('announcements/{announcement}/send', [AnnouncementController::class, 'send'])->name('announcements.send');
Route::post('announcements/{announcement}/archive', [AnnouncementController::class, 'archive'])->name('announcements.archive');
Route::post('announcements/{announcement}/unschedule', [AnnouncementController::class, 'unschedule'])->name('announcements.unschedule');

// Message threads
Route::get('threads', [MessageThreadController::class, 'index'])->name('threads.index');
Route::post('threads', [MessageThreadController::class, 'store'])->name('threads.store');
Route::post('threads/bulk', [MessageThreadController::class, 'bulk'])->name('threads.bulk');
Route::get('threads/{message_thread}', [MessageThreadController::class, 'show'])->name('threads.show');
Route::post('threads/{message_thread}/reply', [MessageThreadController::class, 'reply'])->name('threads.reply');
Route::post('threads/{message_thread}/status', [MessageThreadController::class, 'setStatus'])->name('threads.status');
Route::post('threads/{message_thread}/read', [MessageThreadController::class, 'markRead'])->name('threads.read');

// Broadcasts
Route::get('broadcasts', [BroadcastController::class, 'index'])->name('broadcasts.index');
Route::post('broadcasts', [BroadcastController::class, 'store'])->name('broadcasts.store');
Route::post('broadcasts/bulk', [BroadcastController::class, 'bulk'])->name('broadcasts.bulk');
Route::get('broadcasts/{broadcast}', [BroadcastController::class, 'show'])->name('broadcasts.show');
Route::post('broadcasts/{broadcast}/start', [BroadcastController::class, 'start'])->name('broadcasts.start');
Route::post('broadcasts/{broadcast}/cancel', [BroadcastController::class, 'cancel'])->name('broadcasts.cancel');
Route::post('broadcasts/{broadcast}/complete', [BroadcastController::class, 'complete'])->name('broadcasts.complete');
Route::post('broadcasts/{broadcast}/duplicate', [BroadcastController::class, 'duplicate'])->name('broadcasts.duplicate');
