<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
})->name('home');

Route::redirect('/portal', '/dashboard');
Route::redirect('/portal/login', '/dashboard');

Route::get('/dashboard', function () {
    return view('dashboard', ['entryMode' => 'gym']);
})->name('dashboard');
