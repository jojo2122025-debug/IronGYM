<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\MemberPrintController;
use App\Http\Controllers\PaymentInvoiceController;

Route::get('/', function () {
    return view('dashboard');
})->name('login');

Route::redirect('/dashboard', '/');

Route::get('/members/{member}/print', [MemberPrintController::class, 'show'])
    ->name('members.print');

Route::get('/payments/{payment}/invoice', [PaymentInvoiceController::class, 'show'])
    ->name('payments.invoice');
