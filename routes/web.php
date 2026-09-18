<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('dashboard', ['entryMode' => 'gym']);
})->name('dashboard');

Route::redirect('/portal', '/');
Route::redirect('/portal/login', '/');

Route::redirect('/dashboard', '/');
