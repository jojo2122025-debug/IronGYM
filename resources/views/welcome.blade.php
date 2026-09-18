@extends('layouts.app')

@section('content')
<div class="site-hero">
    <div class="hero-inner mx-auto max-w-[1200px] px-6 py-20">
        <div class="hero-grid">
            <div class="hero-copy">
                <div class="badge">إدارة صالة رياضية واحدة</div>
                <h1 class="hero-title"><span class="irongym-wordmark"><span class="brand-iron">IRON</span><span class="brand-gym">GYM</span></span> — إدارة صالة رياضية احترافية</h1>
                <p class="hero-sub">لوحة تشغيل مركزية لإدارة المشتركين، الاشتراكات، المدفوعات، الحضور، والتقارير داخل صالة IRONGYM بخبرة سريعة وواجهة عربية واضحة.</p>

                <div class="hero-ctas">
                    <a href="{{ url('/dashboard') }}" class="btn btn-primary">دخول لوحة الإدارة</a>
                    <a href="#features" class="btn btn-outline">استكشف الإمكانيات</a>
                </div>

                <ul class="hero-features">
                    <li>إدارة المشتركين والاشتراكات</li>
                    <li>متابعة المدفوعات والحضور</li>
                    <li>تقارير يومية وسريعة للمدير</li>
                </ul>
            </div>

            <div class="hero-visual">
                <div class="visual-card">
                    <div class="visual-header">لوحة إدارة IRONGYM</div>
                    <img src="{{ asset('images/dashboard-preview.png') }}" alt="IRONGYM dashboard preview" class="visual-img">
                </div>
            </div>
        </div>
    </div>
</div>

<section id="features" class="section features-section">
    <div class="container mx-auto px-6 py-12 max-w-[1100px]">
        <div class="section-head text-center">
            <h2>مزايا IRONGYM</h2>
            <p class="muted">كل ما تحتاجه لتشغيل صالتك اليومية بسهولة واحتراف.</p>
        </div>

        <div class="features-grid">
            <div class="feature">
                <h3>إدارة المشتركين</h3>
                <p>تابع بيانات المشتركين، الإشعارات، والوصول السريع إلى كل ملف.</p>
            </div>
            <div class="feature">
                <h3>الاشتراكات والمدفوعات</h3>
                <p>أنشئ الاشتراكات، راقب التجديدات، واحتفظ بسجل واضح للمدفوعات.</p>
            </div>
            <div class="feature">
                <h3>تقارير تشغيلية</h3>
                <p>اعرف الإيرادات، الحضور، والأنشطة اليومية من لوحة واحدة.</p>
            </div>
        </div>
    </div>
</section>

<footer class="site-footer">
    <div class="container mx-auto px-6 py-8 max-w-[1100px]">
        <div class="footer-grid">
            <div>
                <div class="brand irongym-wordmark"><span class="brand-iron">IRON</span><span class="brand-gym">GYM</span></div>
                <p class="muted">لوحة إدارة صالة رياضية متكاملة ومركزة.</p>
            </div>
            <div>
                <h4>روابط سريعة</h4>
                <ul class="footer-links">
                    <li><a href="#features">المزايا</a></li>
                    <li><a href="{{ url('/dashboard') }}">لوحة الإدارة</a></li>
                </ul>
            </div>
            <div>
                <h4>تواصل معنا</h4>
                <p class="muted">support@irongym.example</p>
            </div>
        </div>
        <div class="footer-bottom">© {{ date('Y') }} IRONGYM — جميع الحقوق محفوظة</div>
    </div>
</footer>

@endsection
