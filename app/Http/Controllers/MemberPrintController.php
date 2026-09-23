<?php

namespace App\Http\Controllers;

use App\Models\Member;
use App\Models\MembershipCard;
use App\Models\Payment;
use App\Models\Subscription;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class MemberPrintController extends Controller
{
    public function show(Request $request, string $member): View
    {
        $user = $request->session()->get('user');
        if (!is_array($user)) {
            abort(401);
        }

        $staffRoles = ['مدير النظام', 'مدير الصالة', 'موظف الاستقبال', 'المحاسب', 'المدقق المالي'];
        $isStaff = in_array($user['role'] ?? '', $staffRoles, true);
        $isOwnMember = ($user['role'] ?? '') === 'مشترك'
            && (string) ($user['member_id'] ?? '') === $member;
        if (!$isStaff && !$isOwnMember) {
            abort(403);
        }

        $memberRecord = Member::findOrFail($member);
        $subscriptions = Subscription::query()
            ->where('member_id', $memberRecord->id)
            ->orderByDesc('start_date')
            ->get();
        $payments = Payment::query()
            ->where('member_id', $memberRecord->id)
            ->whereNull('sale_id')
            ->orderByDesc('date')
            ->get();
        $cardCode = MembershipCard::query()
            ->where('member_id', $memberRecord->id)
            ->where('status', 'active')
            ->orderByDesc('id')
            ->value('code') ?: ($memberRecord->membership_number ?: $memberRecord->id);

        $photoPath = ltrim(str_replace('\\', '/', (string) $memberRecord->image_path), '/');
        $photoUrl = preg_match('~^uploads/[A-Za-z0-9._-]+$~', $photoPath) && is_file(public_path($photoPath))
            ? '/' . $photoPath
            : null;

        return view('member-print', [
            'member' => $memberRecord,
            'subscriptions' => $subscriptions,
            'payments' => $payments,
            'cardCode' => $cardCode,
            'photoUrl' => $photoUrl,
            'printedAt' => now(),
            'totals' => [
                'subscriptions' => (float) $subscriptions->sum('amount'),
                'payments' => (float) $payments->sum('amount'),
                'subscriptionPaid' => (float) $subscriptions->sum('paid'),
                'remaining' => (float) $subscriptions->sum('remaining'),
            ],
        ]);
    }
}
