<section id="pricing" class="section pricing-section">
    <div class="container mx-auto px-6 py-12 max-w-[1100px]">
        <div class="section-head text-center">
            <h2>خطط الاشتراك</h2>
            <p class="muted">اختر الخطة المناسبة لصالتك سواء كانت صغيرة أو سلاسل متعددة الفروع.</p>
        </div>

        <div class="pricing-grid">
            <div class="pricing-card">
                <span class="pricing-tier">الأساسي</span>
                <div class="pricing-value">₪99<span>/شهرياً</span></div>
                <ul>
                    <li>إدارة صالة واحدة</li>
                    <li>قاعدة مشتركين حتى 500</li>
                    <li>تقارير أساسية</li>
                </ul>
                <a href="{{ url('/dashboard') }}" class="btn btn-primary">ابدأ الآن</a>
            </div>
            <div class="pricing-card pricing-card-featured">
                <span class="pricing-tier">الأفضل</span>
                <div class="pricing-value">₪199<span>/شهرياً</span></div>
                <ul>
                    <li>دعم فروع متعددة</li>
                    <li>مشتركين غير محدودين</li>
                    <li>تقارير متقدمة وتنبيهات</li>
                </ul>
                <a href="{{ url('/dashboard') }}" class="btn btn-secondary">اختر هذه الخطة</a>
            </div>
            <div class="pricing-card">
                <span class="pricing-tier">المتقدم</span>
                <div class="pricing-value">₪299<span>/شهرياً</span></div>
                <ul>
                    <li>فريق عمل متعدد</li>
                    <li>دعم مخصص</li>
                    <li>تكاملات مستقبلية</li>
                </ul>
                <a href="{{ url('/dashboard') }}" class="btn btn-primary">انضم الآن</a>
            </div>
        </div>
    </div>
</section>
