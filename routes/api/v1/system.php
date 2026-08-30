<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\System\HealthController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| System Routes  (Observability)
|--------------------------------------------------------------------------
|
| Auto-mounted under /api/v1/system. Health probe for hosts and load
| balancers — component status only, no tenant data.
*/

Route::get('health', HealthController::class)->name('health');
