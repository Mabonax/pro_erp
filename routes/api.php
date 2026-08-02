<?php

use App\Domains\CitizenAccess\Controllers\PublicIntakeApiController;
use App\Domains\CitizenAccess\Controllers\PublicOfferingApiController;
use Illuminate\Support\Facades\Route;

Route::get('public/v1/offerings', [PublicOfferingApiController::class, 'index'])
    ->middleware('throttle:public-intakes')
    ->name('api.public.v1.offerings.index');

Route::post('public/v1/intakes', [PublicIntakeApiController::class, 'store'])
    ->middleware('throttle:public-intakes')
    ->name('api.public.v1.intakes.store');
