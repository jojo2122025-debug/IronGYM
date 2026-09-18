<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\GymApiController;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;

Route::match(['get', 'post'], '/{action}', [GymApiController::class, 'handle'])
    ->middleware('web')
    ->withoutMiddleware([VerifyCsrfToken::class]);
