<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\MemberPrintController;

Route::get('/', function () {
    return view('dashboard');
})->name('login');

Route::redirect('/dashboard', '/');

Route::get('/members/{member}/print', [MemberPrintController::class, 'show'])
    ->name('members.print');
