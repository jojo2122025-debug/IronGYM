@php
    $activeSubscription = $subscriptions->first(fn ($subscription) => $subscription->status === 'فعال');
    $initials = collect(preg_split('/\s+/u', trim((string) $member->name)))->filter()->take(2)->map(fn ($part) => mb_substr($part, 0, 1))->implode('');
    $subscriptionNames = $subscriptions->keyBy('id');
    $money = fn ($amount) => number_format((float) $amount, 2) . ' ₪';
@endphp
<!doctype html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>استمارة المشترك — {{ $member->name }} | IRONGYM</title>
    <style>
        :root { --ink:#171717; --muted:#626262; --line:#d9d7d3; --paper:#fff; --soft:#f7f5f2; --red:#aa1d21; }
        * { box-sizing:border-box; }
        html { background:#e8e6e2; }
        body { margin:0; color:var(--ink); font-family:Tahoma,Arial,sans-serif; font-size:11px; line-height:1.55; }
        button { font:inherit; cursor:pointer; }
        .toolbar { width:min(210mm,calc(100% - 24px)); margin:18px auto 10px; display:flex; align-items:center; justify-content:space-between; gap:10px; }
        .toolbar-hint { color:#555; font-size:12px; }
        .toolbar-actions { display:flex; gap:8px; }
        .toolbar button { border:1px solid #b8b4af; background:#fff; color:#171717; padding:9px 15px; border-radius:4px; font-weight:700; }
        .toolbar button.primary { background:var(--red); border-color:var(--red); color:#fff; }
        .toolbar button:hover { filter:brightness(.94); }
        .toolbar button:focus-visible { outline:3px solid #efadad; outline-offset:2px; }
        .sheet { width:210mm; min-height:297mm; margin:0 auto 28px; padding:15mm 13mm 15mm; background:var(--paper); box-shadow:0 15px 45px #0002; }
        .masthead { display:flex; justify-content:space-between; align-items:flex-start; gap:18px; border-top:5px solid var(--red); border-bottom:1px solid var(--ink); padding:13px 0 14px; }
        .brand { display:flex; align-items:center; gap:11px; direction:ltr; }
        .brand img { width:48px; height:48px; object-fit:contain; }
        .brand-word { display:flex; align-items:center; direction:ltr; font-size:22px; font-weight:900; letter-spacing:1px; line-height:1; }
        .brand-word .iron { color:var(--red); }
        .brand-word .gym { color:#fff; background:#171717; padding:5px 6px 6px; margin-left:2px; border-radius:2px; }
        .brand-caption { font-size:9px; letter-spacing:2px; color:var(--muted); margin-top:5px; }
        .document-title { text-align:left; }
        .document-title h1 { font-size:18px; margin:0 0 5px; line-height:1.3; }
        .document-title p { margin:0; color:var(--muted); font-size:10px; }
        .identity { display:flex; gap:14px; align-items:stretch; margin:17px 0 14px; padding:12px; background:var(--soft); border-right:3px solid var(--red); break-inside:avoid; }
        .portrait { width:30mm; height:30mm; flex:0 0 30mm; background:#e4e0db; overflow:hidden; display:flex; align-items:center; justify-content:center; position:relative; }
        .portrait img { width:100%; height:100%; object-fit:cover; }
        .portrait-fallback { color:#6a6260; font-size:32px; font-weight:700; }
        .portrait-cue { position:absolute; bottom:0; right:0; left:0; background:#fffD; color:#666; text-align:center; font-size:8px; padding:2px; }
        .identity-main { flex:1; min-width:0; display:flex; flex-direction:column; justify-content:center; }
        .eyebrow { color:var(--red); letter-spacing:1px; font-size:9px; font-weight:800; }
        .identity h2 { font-size:21px; margin:3px 0 6px; line-height:1.3; overflow-wrap:anywhere; }
        .identity-code { font-size:10px; color:var(--muted); }
        .identity-code strong { color:var(--ink); direction:ltr; unicode-bidi:isolate; display:inline-block; }
        .profile-grid { display:grid; grid-template-columns:repeat(4,minmax(0,1fr)); gap:10px 14px; margin:0 0 15px; }
        .field-label { display:block; color:var(--muted); font-size:9px; margin-bottom:2px; }
        .field-value { display:block; font-size:11px; font-weight:700; overflow-wrap:anywhere; }
        .ltr { direction:ltr; unicode-bidi:isolate; display:inline-block; }
        .section { margin:17px 0 0; }
        .section-heading { display:flex; align-items:baseline; gap:9px; border-bottom:1px solid var(--ink); padding-bottom:5px; margin-bottom:9px; break-after:avoid; }
        .section-index { color:var(--red); font-size:12px; font-weight:900; letter-spacing:1px; }
        .section-heading h3 { font-size:13px; margin:0; }
        .section-note { margin-right:auto; color:var(--muted); font-size:9px; }
        .summary { display:grid; grid-template-columns:repeat(4,minmax(0,1fr)); border:1px solid var(--line); margin-top:8px; break-inside:avoid; }
        .summary > div { padding:9px 10px; border-left:1px solid var(--line); }
        .summary > div:last-child { border-left:0; }
        .summary span { display:block; color:var(--muted); font-size:9px; }
        .summary strong { display:block; margin-top:3px; font-size:12px; white-space:nowrap; }
        .summary .remaining strong { color:var(--red); }
        .active-subscription { display:flex; justify-content:space-between; align-items:center; gap:14px; background:#fbf4f3; border-right:3px solid var(--red); padding:10px 12px; margin:10px 0 12px; break-inside:avoid; }
        .active-subscription .eyebrow { display:block; margin-bottom:3px; }
        .active-subscription strong { font-size:12px; }
        .active-subscription .period { text-align:left; font-size:10px; }
        .active-subscription .period span { display:block; color:var(--muted); }
        .active-status { display:inline-block; border:1px solid #d3aaa9; color:#851519; padding:1px 7px; margin-right:5px; font-size:9px; font-weight:700; }
        .table-wrap { overflow-x:auto; }
        table { border-collapse:collapse; width:100%; font-size:10px; }
        th { background:#f2f0ed; text-align:right; font-weight:700; border-bottom:1px solid #aaa49f; padding:7px 6px; white-space:nowrap; }
        td { border-bottom:1px solid var(--line); padding:7px 6px; vertical-align:top; overflow-wrap:anywhere; }
        tbody tr:nth-child(even) { background:#fbfaf8; }
        tbody tr { break-inside:avoid; page-break-inside:avoid; }
        thead { display:table-header-group; }
        .money { white-space:nowrap; direction:ltr; unicode-bidi:isolate; text-align:right; }
        .emphasis { color:var(--red); font-weight:700; }
        .muted { color:var(--muted); }
        .minor { display:block; color:var(--muted); font-size:9px; margin-top:2px; }
        .empty { border:1px dashed var(--line); color:var(--muted); padding:12px; text-align:center; }
        .signature-row { display:flex; gap:25px; margin:31px 0 13px; break-inside:avoid; }
        .signature { flex:1; padding-top:12px; border-top:1px solid #9b9691; color:var(--muted); font-size:10px; }
        .sheet-footer { display:flex; justify-content:space-between; border-top:1px solid var(--line); padding-top:8px; margin-top:20px; color:var(--muted); font-size:9px; }
        @page { size:A4 portrait; margin:12mm 13mm 15mm; }
        @media print {
            html,body { background:#fff; }
            .toolbar { display:none !important; }
            .sheet { width:auto; min-height:0; margin:0; padding:0; box-shadow:none; }
            .masthead,.identity,.active-subscription,.summary,th { print-color-adjust:exact; -webkit-print-color-adjust:exact; }
            .table-wrap { overflow:visible; }
            .sheet-footer { position:relative; }
        }
        @media screen and (max-width:800px) {
            .sheet { width:calc(100% - 20px); min-height:0; padding:22px 18px; }
            .toolbar { flex-wrap:wrap; }
            .profile-grid,.summary { grid-template-columns:repeat(2,minmax(0,1fr)); }
            .summary > div:nth-child(2) { border-left:0; }
            .summary > div:nth-child(-n+2) { border-bottom:1px solid var(--line); }
            table { min-width:650px; }
        }
        @media screen and (max-width:520px) {
            .masthead { align-items:center; }
            .brand img { width:35px; height:35px; }
            .brand-word { font-size:17px; }
            .document-title h1 { font-size:14px; }
            .identity h2 { font-size:18px; }
            .active-subscription { align-items:flex-start; flex-direction:column; }
            .active-subscription .period { text-align:right; }
        }
    </style>
</head>
<body>
    <nav class="toolbar" aria-label="إجراءات الاستمارة">
        <span class="toolbar-hint">استمارة المشترك جاهزة للطباعة على ورق A4</span>
        <div class="toolbar-actions">
            <button type="button" onclick="window.close()">إغلاق</button>
            <button type="button" class="primary" onclick="window.print()">طباعة / حفظ PDF</button>
        </div>
    </nav>
    <main class="sheet">
        <header class="masthead">
            <div class="brand">
                <img src="{{ asset('images/irongym-logo.png') }}" alt="شعار IRONGYM">
                <div><div class="brand-word"><span class="iron">IRON</span><span class="gym">GYM</span></div><div class="brand-caption">MEMBER RECORD</div></div>
            </div>
            <div class="document-title">
                <h1>استمارة المشترك</h1>
                <p>تاريخ الإصدار: <span class="ltr">{{ $printedAt->format('d-m-Y H:i') }}</span></p>
                <p>رقم الملف: <span class="ltr">{{ $member->id }}</span></p>
            </div>
        </header>

        <section aria-labelledby="profile-title">
            <div class="identity">
                <div class="portrait">
                    @if ($photoUrl)
                        <img src="{{ $photoUrl }}" alt="صورة المشترك {{ $member->name }}">
                    @else
                        <span class="portrait-fallback">{{ $initials ?: '—' }}</span>
                        <span class="portrait-cue">لا توجد صورة</span>
                    @endif
                </div>
                <div class="identity-main">
                    <span class="eyebrow" id="profile-title">01 / بيانات المشترك</span>
                    <h2>{{ $member->name }}</h2>
                    <div class="identity-code">رقم العضوية <strong>{{ $cardCode }}</strong></div>
                </div>
            </div>
            <div class="profile-grid">
                <div><span class="field-label">رقم الهاتف</span><span class="field-value ltr">{{ $member->phone ?: '—' }}</span></div>
                <div><span class="field-label">واتساب</span><span class="field-value ltr">{{ $member->whatsapp ?: '—' }}</span></div>
                <div><span class="field-label">الجنس</span><span class="field-value">{{ $member->gender ?: '—' }}</span></div>
                <div><span class="field-label">تاريخ التسجيل</span><span class="field-value ltr">{{ $member->created_at?->format('d-m-Y') ?: '—' }}</span></div>
                @if ($member->birth_date)
                    <div><span class="field-label">تاريخ الميلاد</span><span class="field-value ltr">{{ $member->birth_date->format('d-m-Y') }}</span></div>
                @endif
            </div>
        </section>

        <section class="section" aria-labelledby="summary-title">
            <div class="section-heading"><span class="section-index">02</span><h3 id="summary-title">ملخص الحساب</h3><span class="section-note">المبالغ بالشيكل</span></div>
            <div class="summary">
                <div><span>قيمة الاشتراكات</span><strong class="ltr">{{ $money($totals['subscriptions']) }}</strong></div>
                <div><span>المدفوع على الاشتراكات</span><strong class="ltr">{{ $money($totals['subscriptionPaid']) }}</strong></div>
                <div><span>إجمالي سجلات الدفعات</span><strong class="ltr">{{ $money($totals['payments']) }}</strong></div>
                <div class="remaining"><span>المتبقي على الاشتراكات</span><strong class="ltr">{{ $money($totals['remaining']) }}</strong></div>
            </div>
        </section>

        <section class="section" aria-labelledby="subscription-title">
            <div class="section-heading"><span class="section-index">03</span><h3 id="subscription-title">الاشتراك ونوعه</h3><span class="section-note">{{ $subscriptions->count() }} اشتراك مسجل</span></div>
            @if ($activeSubscription)
                <div class="active-subscription">
                    <div><span class="eyebrow">الاشتراك الحالي</span><strong>{{ $activeSubscription->plan_name ?: ($activeSubscription->plan?->name ?: 'اشتراك') }}</strong><span class="active-status">{{ $activeSubscription->status }}</span></div>
                    <div class="period"><span>مدة الاشتراك</span><b class="ltr">{{ $activeSubscription->start_date?->format('d-m-Y') ?: '—' }} — {{ $activeSubscription->end_date?->format('d-m-Y') ?: '—' }}</b></div>
                </div>
            @endif
            @if ($subscriptions->isEmpty())
                <div class="empty">لا توجد اشتراكات مسجلة لهذا المشترك.</div>
            @else
                <div class="table-wrap"><table>
                    <thead><tr><th>نوع الاشتراك</th><th>الفترة</th><th>الحالة</th><th>القيمة</th><th>المدفوع</th><th>المتبقي</th></tr></thead>
                    <tbody>
                    @foreach ($subscriptions as $subscription)
                        <tr>
                            <td><strong>{{ $subscription->plan_name ?: ($subscription->plan?->name ?: 'اشتراك') }}</strong><span class="minor ltr">{{ $subscription->id }}</span></td>
                            <td><span class="ltr">{{ $subscription->start_date?->format('d-m-Y') ?: '—' }}</span><span class="minor">حتى <span class="ltr">{{ $subscription->end_date?->format('d-m-Y') ?: '—' }}</span></span></td>
                            <td>{{ $subscription->status ?: '—' }}</td>
                            <td class="money">{{ $money($subscription->amount) }}</td>
                            <td class="money">{{ $money($subscription->paid) }}</td>
                            <td class="money {{ (float) $subscription->remaining > 0 ? 'emphasis' : '' }}">{{ $money($subscription->remaining) }}</td>
                        </tr>
                    @endforeach
                    </tbody>
                </table></div>
            @endif
        </section>

        <section class="section" aria-labelledby="payments-title">
            <div class="section-heading"><span class="section-index">04</span><h3 id="payments-title">المدفوعات</h3><span class="section-note">{{ $payments->count() }} دفعة مسجلة</span></div>
            @if ($payments->isEmpty())
                <div class="empty">لا توجد دفعات اشتراك مسجلة لهذا المشترك.</div>
            @else
                <div class="table-wrap"><table>
                    <thead><tr><th>التاريخ والوقت</th><th>المرجع</th><th>الاشتراك المرتبط</th><th>الطريقة / التحويل</th><th>المبلغ</th><th>ملاحظة</th></tr></thead>
                    <tbody>
                    @foreach ($payments as $payment)
                        @php $linkedSubscription = $payment->subscription_id ? $subscriptionNames->get($payment->subscription_id) : null; @endphp
                        <tr>
                            <td class="ltr">{{ $payment->date?->format('d-m-Y H:i') ?: '—' }}</td>
                            <td><span class="ltr">{{ $payment->receipt_number ?: $payment->id }}</span></td>
                            <td>
                                @if ($linkedSubscription)
                                    {{ $linkedSubscription->plan_name ?: ($linkedSubscription->plan?->name ?: 'اشتراك') }}<span class="minor ltr">{{ $linkedSubscription->id }}</span>
                                @else
                                    <span class="muted">دفعة عضوية غير مرتبطة باشتراك محدد</span>
                                @endif
                            </td>
                            <td>{{ $payment->method ?: '—' }}@if ($payment->transfer_from_account)<span class="minor">حساب التحويل: <span class="ltr">{{ $payment->transfer_from_account }}</span></span>@endif</td>
                            <td class="money"><strong>{{ $money($payment->amount) }}</strong></td>
                            <td>{{ $payment->note ?: '—' }}</td>
                        </tr>
                    @endforeach
                    </tbody>
                </table></div>
            @endif
        </section>

        <div class="signature-row"><div class="signature">توقيع المشترك</div><div class="signature">توقيع الموظف المختص</div></div>
        <footer class="sheet-footer"><span>IRONGYM · استمارة المشترك</span><span>أُصدرت بتاريخ <span class="ltr">{{ $printedAt->format('d-m-Y') }}</span></span></footer>
    </main>
</body>
</html>
