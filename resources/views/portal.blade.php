@extends('layouts.app')

@section('content')
<section class="mx-auto max-w-[1200px] px-6 py-16">
    <div class="grid gap-8 lg:grid-cols-[1.15fr_0.85fr] lg:items-center">
        <div>
            <span class="badge">إدارة صالة واحدة</span>
            <h1 class="mt-4 text-4xl font-bold tracking-tight text-slate-900 lg:text-5xl"><span class="irongym-wordmark"><span class="brand-iron">IRON</span><span class="brand-gym">GYM</span></span> — لوحة إدارة الصالة</h1>
            <p class="mt-4 max-w-2xl text-lg leading-8 text-slate-600">هذه المساحة مخصّصة لإدارة الصالة اليومية: متابعة المشتركين، الاشتراكات، المدفوعات، والحضور من مكان واحد.</p>

            <div class="mt-8 flex flex-wrap gap-3">
                <a href="{{ url('/dashboard') }}" class="btn btn-primary">الدخول إلى لوحة الإدارة</a>
            </div>
        </div>

        <div class="rounded-3xl border border-slate-200 bg-white p-6 shadow-lg">
            <h2 class="text-xl font-bold text-slate-900">ما الذي يمكن إدارته؟</h2>
            <ul class="mt-4 space-y-3 text-slate-700">
                <li>إدارة المشتركين والملفات الشخصية.</li>
                <li>تتبع الاشتراكات والمدفوعات.</li>
                <li>مراقبة الحضور والتقارير اليومية.</li>
                <li>العمل داخل لوحة واحدة موجهة لصالة IRONGYM.</li>
            </ul>
        </div>
    </div>
</section>
@endsection
