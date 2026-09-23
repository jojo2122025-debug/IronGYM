@php
    $money = fn ($value) => number_format((float) $value, 2) . ' ₪';
    $isSale = $payment->sale !== null;
    $saleItems = $payment->sale?->items ?? collect();
    $documentNumber = $payment->receipt_number ?: $payment->id;
    $payerName = $payment->member_name ?: ($payment->member?->name ?: 'عميل نقدي');
@endphp
<!doctype html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>فاتورة دفع {{ $documentNumber }} | IRONGYM</title>
    <style>
        :root { --ink:#1a1d22; --muted:#5b626d; --line:#d8dce1; --paper:#fff; --soft:#f5f6f8; --red:#ad1f27; }
        * { box-sizing:border-box; }
        html { background:#e9ebee; }
        body { margin:0; color:var(--ink); font:11px/1.6 Tahoma,Arial,sans-serif; }
        .toolbar { width:min(148mm,calc(100% - 24px)); margin:16px auto 10px; display:flex; justify-content:space-between; align-items:center; gap:10px; }
        .toolbar p { margin:0; color:#414852; font-size:11px; }
        button { font:inherit; cursor:pointer; border:0; }
        .print-button { min-height:40px; padding:8px 16px; background:var(--red); color:#fff; border-radius:6px; font-weight:700; white-space:nowrap; }
        .print-button:focus-visible { outline:3px solid #b92b30; outline-offset:3px; }
        .sheet { width:148mm; min-height:210mm; padding:10mm; margin:0 auto 28px; background:var(--paper); box-shadow:0 12px 40px #0002; }
        .top { display:flex; justify-content:space-between; align-items:flex-start; gap:10px; border-top:4px solid var(--red); border-bottom:1px solid var(--line); padding:11px 0 13px; }
        .brand { display:flex; align-items:center; gap:8px; direction:ltr; }
        .brand img { width:35px; height:35px; object-fit:contain; }
        .brand-name { font-size:19px; font-weight:900; letter-spacing:.4px; line-height:1; direction:ltr; white-space:nowrap; }
        .brand-name .iron { color:var(--red); }
        .brand-name .gym { color:#fff; background:var(--ink); padding:4px 5px 5px; margin-left:2px; border-radius:2px; }
        .brand-caption { display:block; margin-top:4px; color:var(--muted); font-size:8px; letter-spacing:1px; }
        h1 { margin:0; font-size:16px; line-height:1.2; }
        .doc-meta { margin-top:5px; color:var(--muted); font-size:9px; }
        .doc-meta strong { color:var(--ink); }
        .ltr { direction:ltr; unicode-bidi:isolate; display:inline-block; }
        .intro { display:flex; justify-content:space-between; align-items:center; gap:12px; margin:16px 0; }
        .intro p { margin:0; color:var(--muted); font-size:10px; }
        .intro strong { display:block; font-size:14px; color:var(--ink); overflow-wrap:anywhere; }
        .paid-badge { border:1px solid #c89799; color:var(--red); border-radius:30px; padding:4px 10px; font-weight:800; white-space:nowrap; }
        .details { display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); gap:11px 15px; padding:13px; background:var(--soft); border-right:3px solid var(--red); break-inside:avoid; }
        .details span { display:block; color:var(--muted); font-size:9px; }
        .details strong { display:block; font-size:11px; overflow-wrap:anywhere; }
        h2 { font-size:12px; margin:20px 0 7px; }
        table { width:100%; border-collapse:collapse; font-size:10px; }
        th { padding:7px 5px; background:var(--soft); text-align:right; border-bottom:1px solid #b8bdc4; }
        td { padding:8px 5px; border-bottom:1px solid var(--line); vertical-align:top; overflow-wrap:anywhere; }
        tr { break-inside:avoid; }
        .qty,.amount { white-space:nowrap; }
        .amount { text-align:left; direction:ltr; unicode-bidi:isolate; }
        .description small { display:block; color:var(--muted); font-size:9px; }
        .total { display:flex; align-items:baseline; justify-content:space-between; gap:10px; border-top:2px solid var(--ink); padding:12px 0; margin-top:18px; break-inside:avoid; }
        .total span { font-weight:700; }
        .total strong { font-size:21px; color:var(--red); white-space:nowrap; }
        .note { margin-top:11px; padding:10px 12px; background:var(--soft); overflow-wrap:anywhere; }
        .note span { display:block; color:var(--muted); font-size:9px; }
        .footer { display:flex; justify-content:space-between; gap:10px; border-top:1px solid var(--line); margin-top:26px; padding-top:9px; font-size:9px; color:var(--muted); break-inside:avoid; }
        @page { size:A5 portrait; margin:8mm; }
        @media print {
            html,body { background:#fff; }
            .toolbar { display:none !important; }
            .sheet { width:auto; min-height:0; margin:0; padding:0; box-shadow:none; }
            .top,.details,th,.note { print-color-adjust:exact; -webkit-print-color-adjust:exact; }
        }
        @media (max-width:580px) {
            .toolbar { flex-wrap:wrap; }
            .sheet { width:100%; min-height:0; padding:22px 18px; margin-bottom:0; }
            .top { flex-wrap:wrap; }
        }
    </style>
</head>
<body>
    <div class="toolbar">
        <p>ورقة A5 · يمكنك الطباعة أو الحفظ كملف PDF</p>
        <button class="print-button" type="button" onclick="window.print()">طباعة الفاتورة</button>
    </div>
    <main class="sheet">
        <header class="top">
            <div class="brand"><img src="/images/irongym-logo.png" alt="شعار IRONGYM"><div><div class="brand-name"><span class="iron">IRON</span><span class="gym">GYM</span></div><span class="brand-caption">GYM MANAGEMENT</span></div></div>
            <div><h1>فاتورة دفع</h1><div class="doc-meta">رقم الفاتورة: <strong class="ltr">{{ $documentNumber }}</strong></div></div>
        </header>

        <section class="intro" aria-label="معلومات المستفيد">
            <div><p>الاسم</p><strong>{{ $payerName }}</strong></div>
            <span class="paid-badge">مدفوعة</span>
        </section>

        <section class="details" aria-label="تفاصيل الدفعة">
            <div><span>تاريخ الدفع</span><strong class="ltr">{{ $payment->date?->format('d-m-Y H:i') ?? '—' }}</strong></div>
            <div><span>طريقة الدفع</span><strong>{{ $payment->method }}</strong></div>
            <div><span>رقم العضوية</span><strong class="ltr">{{ $payment->member_id ?: '—' }}</strong></div>
            <div><span>نوع العملية</span><strong>{{ $isSale ? 'مبيعات منتجات' : ($payment->subscription ? 'اشتراك رياضي' : 'دفعة مالية') }}</strong></div>
            @if ($payment->method === 'تحويل' && $payment->transfer_from_account)
                <div style="grid-column:1/-1"><span>اسم الشخص أو الحساب المُحوِّل</span><strong>{{ $payment->transfer_from_account }}</strong></div>
            @endif
        </section>

        <section aria-labelledby="items-title">
            <h2 id="items-title">تفاصيل العملية</h2>
            <table>
                <thead><tr><th>البيان</th><th class="qty">الكمية</th><th class="amount">المبلغ</th></tr></thead>
                <tbody>
                    @if ($isSale && $saleItems->isNotEmpty())
                        @foreach ($saleItems as $item)
                            <tr><td class="description">{{ $item->product?->name ?: 'منتج' }}<small>سعر الوحدة: {{ $money($item->unit_price) }}</small></td><td class="qty">{{ $item->quantity }}</td><td class="amount">{{ $money($item->total) }}</td></tr>
                        @endforeach
                    @elseif ($isSale)
                        <tr><td class="description">{{ $payment->sale->products ?: 'مبيعات منتجات' }}</td><td class="qty">1</td><td class="amount">{{ $money($payment->amount) }}</td></tr>
                    @else
                        <tr><td class="description">{{ $payment->subscription?->plan_name ?: 'دفعة مالية' }}@if ($payment->subscription)<small>الاشتراك {{ $payment->subscription_id }} · {{ $payment->subscription->start_date?->format('d-m-Y') }} — {{ $payment->subscription->end_date?->format('d-m-Y') }}</small>@endif</td><td class="qty">1</td><td class="amount">{{ $money($payment->amount) }}</td></tr>
                    @endif
                </tbody>
            </table>
        </section>

        <div class="total"><span>المبلغ المدفوع</span><strong class="ltr">{{ $money($payment->amount) }}</strong></div>
        @if ($payment->note && $payment->note !== '—')
            <div class="note"><span>ملاحظات</span>{{ $payment->note }}</div>
        @endif
        <footer class="footer"><span>صادرة إلكترونيًا من IRONGYM</span><span>تاريخ العرض: <span class="ltr">{{ $issuedAt->format('d-m-Y H:i') }}</span></span></footer>
    </main>
</body>
</html>
