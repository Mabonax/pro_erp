<?php

use App\Domains\CitizenAccess\Controllers\PublicIntakeApiController;
use Illuminate\Support\Facades\Route;

Route::post('public/v1/intakes', [PublicIntakeApiController::class, 'store'])
    ->middleware('throttle:public-intakes')
    ->name('api.public.v1.intakes.store');
