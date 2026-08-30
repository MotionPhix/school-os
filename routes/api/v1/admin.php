<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\Admin\TrashController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Admin Capability Routes
|--------------------------------------------------------------------------
|
| Auto-mounted under /api/v1/admin with the standard capability
| middleware group. Trash restore is tenant-scoped and gated by the
| `platform.trash.restore` permission (see config/admin.php whitelist).
*/

Route::post('trash/{resource}/{id}/restore', [TrashController::class, 'restore'])->name('trash.restore');
