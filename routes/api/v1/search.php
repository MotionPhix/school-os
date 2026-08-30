<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\Search\SearchController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Search Capability Routes  (Discovery)
|--------------------------------------------------------------------------
|
| Auto-mounted under /api/v1/search. One endpoint, typed results per
| resource, permission-gated and tenant-scoped (see SearchController).
*/

Route::get('', [SearchController::class, 'index'])->name('index');
