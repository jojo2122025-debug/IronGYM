<?php

namespace App\Http\Controllers;

use App\Models\Payment;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class PaymentInvoiceController extends Controller
{
    public function show(Request $request, Payment $payment): Response
    {
        $user = $request->session()->get('user');
        abort_unless(is_array($user), 401);

        $isStaff = in_array($user['role'] ?? '', [
            'مدير النظام', 'مدير الصالة', 'موظف الاستقبال', 'المحاسب', 'المدقق المالي',
        ], true);
        $isOwnPayment = ($user['role'] ?? '') === 'مشترك'
            && $payment->member_id !== null
            && (string) ($user['member_id'] ?? '') === (string) $payment->member_id;
        abort_unless($isStaff || $isOwnPayment, 403);

        $payment->load(['subscription', 'sale.items.product']);

        return response()->view('payment-invoice', [
            'payment' => $payment,
            'issuedAt' => now(),
        ])->header('Cache-Control', 'private, no-store');
    }
}
