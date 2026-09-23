@extends('layouts.app')

@section('content')
    <!-- Login Screen -->
    <div id="login-container" class="login-wrapper" dir="rtl">
        <section class="login-right" aria-labelledby="login-title">
            <header class="login-logo-header">
                <div class="login-logo-text">
                    <p class="login-logo-subtitle">لوحة إدارة الصالة الرياضية</p>
                    <h1 class="login-logo-title"><span class="brand-iron">IRON</span><span class="brand-gym">GYM</span><small>آيرون جيم</small></h1>
                </div>
                <div class="login-logo-icon"><img src="/images/irongym-logo.png" alt="شعار IRONGYM"></div>
            </header>

            <div class="login-header-text">
                <p class="login-kicker">مرحباً بعودتك</p>
                <h2 id="login-title" class="login-title">إدارة صالتك تبدأ<br><span>من هنا.</span></h2>
                <p class="login-subtitle">سجّل الدخول للوصول إلى لوحة تحكم IRONGYM وإدارة عملياتك بكل سهولة.</p>
            </div>

            <form id="form-login" onsubmit="handleLogin(event)" class="login-form">
                <div class="form-group">
                    <label for="login-username" class="form-label">البريد الإلكتروني أو اسم المستخدم</label>
                    <div class="login-input-wrap">
                        <i data-lucide="mail" aria-hidden="true"></i>
                        <input type="text" id="login-username" class="form-control" placeholder="أدخل اسم المستخدم" required autocomplete="username" aria-describedby="login-username-error" aria-invalid="false">
                    </div>
                    <div id="login-username-error" class="field-error" aria-live="polite"></div>
                </div>
                <div class="form-group">
                    <div class="password-label-row">
                        <label for="login-password" class="form-label">كلمة المرور</label>
                        <button type="button" class="forgot-link">نسيت كلمة المرور؟</button>
                    </div>
                    <div class="login-input-wrap">
                        <i data-lucide="lock-keyhole" aria-hidden="true"></i>
                        <input type="password" id="login-password" class="form-control" placeholder="أدخل كلمة المرور" required autocomplete="current-password" aria-describedby="login-password-error" aria-invalid="false">
                        <button type="button" class="password-toggle" aria-label="إظهار كلمة المرور" onclick="const input=document.getElementById('login-password'); const isPassword=input.type === 'password'; input.type=isPassword ? 'text' : 'password'; this.setAttribute('aria-label', isPassword ? 'إخفاء كلمة المرور' : 'إظهار كلمة المرور');"><i data-lucide="eye" aria-hidden="true"></i></button>
                    </div>
                    <div id="login-password-error" class="field-error" aria-live="polite"></div>
                </div>
                <div class="login-options">
                    <label class="remember-label"><input type="checkbox" checked><span class="checkmark"></span>تذكرني</label>
                    <span class="secure-note"><span class="secure-dot"></span>اتصال آمن</span>
                </div>
                <button type="submit" class="btn btn-primary login-btn"><span>تسجيل الدخول</span><span aria-hidden="true">←</span></button>
            </form>

            <p class="login-account-note">استخدم بيانات الحساب التي أعدّها مدير النظام.</p>
            <footer class="login-footer"><span>© 2025 IRONGYM</span><span>الخصوصية والدعم</span></footer>
        </section>

        <section class="login-left" aria-label="IRONGYM fitness">
            <div class="login-left-bg"></div>
            <div class="hero-topline"><span></span>THE STRONGER YOU</div>
            <div class="login-left-content">
                <p class="login-tag"><b></b> IRONGYM PERFORMANCE CLUB</p>
                <p class="hero-index">01 <span>/ 03</span></p>
                <h2 class="login-motto">قوّتك<br><em>تبدأ الآن.</em></h2>
                <p class="hero-copy">خطوة واحدة تفصلك عن إدارة أكثر ذكاءً<br>وصالة أقوى أداءً.</p>
            </div>
            <footer class="hero-footer"><span>EST. 2018</span><span>TRAIN • TRACK • TRANSFORM</span></footer>
        </section>
    </div>

    <div class="app-container" style="display: none;">
        <!-- Sidebar Navigation -->
        <aside class="sidebar">
            <div class="sidebar-header">
                <div class="logo-container">
                    <div class="logo-icon"><img src="/images/irongym-logo.png" alt="IRONGYM"></div>
                    <div class="logo-text">
                        <span class="logo-title"><span class="brand-iron">IRON</span><span class="brand-gym">GYM</span></span>
                        <span class="logo-subtitle">لوحة إدارة الصالة الرياضية</span>
                    </div>
                </div>
            </div>
            <nav class="sidebar-nav">
                <a class="nav-link active" data-view="dashboard">
                    <i data-lucide="layout-dashboard"></i>
                    <span>لوحة التحكم</span>
                </a>
                <a class="nav-link" data-view="check-in">
                    <i data-lucide="user-check"></i>
                    <span>تسجيل الحضور</span>
                </a>
                <a class="nav-link" data-view="members">
                    <i data-lucide="users"></i>
                    <span>المشتركون</span>
                </a>
                <a class="nav-link" data-view="subscriptions">
                    <i data-lucide="clipboard-list"></i>
                    <span>الاشتراكات</span>
                </a>
                <a class="nav-link" data-view="plans">
                    <i data-lucide="award"></i>
                    <span>خطط الاشتراك</span>
                </a>
                <a class="nav-link" data-view="payments">
                    <i data-lucide="credit-card"></i>
                    <span>المدفوعات</span>
                </a>
                <a class="nav-link" data-view="expenses">
                    <i data-lucide="wallet-cards"></i>
                    <span>المصروفات</span>
                </a>
                <a class="nav-link" data-view="products">
                    <i data-lucide="shopping-bag"></i>
                    <span>المنتجات</span>
                </a>
                <a class="nav-link" data-view="reports">
                    <i data-lucide="bar-chart-3"></i>
                    <span>التقارير</span>
                </a>
                <a class="nav-link" data-view="notifications" id="nav-notifications" style="display: none;">
                    <i data-lucide="bell-ring"></i>
                    <span>الإشعارات</span>
                </a>
                <a class="nav-link" data-view="sync-center" id="nav-sync-center" style="display: none;">
                    <i data-lucide="refresh-cw"></i>
                    <span>مركز المزامنة</span>
                </a>
                <a class="nav-link" data-view="trainers" id="nav-trainers" style="display: none;">
                    <i data-lucide="dumbbell"></i>
                    <span>المدربين</span>
                </a>
                <a class="nav-link" data-view="global-search" id="nav-global-search">
                    <i data-lucide="search"></i>
                    <span>الاستعلام الشامل</span>
                </a>
                <a class="nav-link" data-view="import" id="nav-import" style="display: none;">
                    <i data-lucide="file-up"></i>
                    <span>الاستيراد الذكي</span>
                </a>
                <a class="nav-link" data-view="users" id="nav-users" style="display: none;">
                    <i data-lucide="shield-check"></i>
                    <span>إدارة المستخدمين</span>
                </a>
                <a class="nav-link" data-view="activity-log" id="nav-activity-log" style="display: none;">
                    <i data-lucide="file-clock"></i>
                    <span>سجل الحركات</span>
                </a>
                <a class="nav-link" data-view="member-portal" id="nav-member-portal" style="display: none;">
                    <i data-lucide="user"></i>
                    <span>بوابة المشترك</span>
                </a>
                <a class="nav-link" data-view="trainer-portal" id="nav-trainer-portal" style="display: none;">
                    <i data-lucide="dumbbell"></i>
                    <span>بوابة المدرب</span>
                </a>
            </nav>
            <div class="sidebar-footer">
                <div class="profile-card">
                    <div class="avatar" id="avatar-circle">م</div>
                    <div class="profile-info">
                        <div class="profile-name" id="user-display-name">مدير النظام</div>
                        <div class="profile-role" id="user-display-role">ADMIN</div>
                    </div>
                    <div style="display: flex; gap: 4px;">
                        <button class="btn-logout" title="تغيير كلمة المرور" onclick="openChangePasswordModal()">
                            <i data-lucide="key"></i>
                        </button>
                        <button class="btn-logout" title="تسجيل الخروج" onclick="handleLogout()">
                            <i data-lucide="log-out"></i>
                        </button>
                    </div>
                </div>
            </div>
        </aside>

        <!-- Main Content Area -->
        <main class="main-content">
            <div class="page-header">
                <div class="header-info">
                    <span class="header-tag" id="header-tag">إدارة IRONGYM</span>
                    <h1 class="header-title" id="header-title">لوحة التحكم</h1>
                    <p class="header-desc" id="header-desc">نظرة شاملة على نشاط الصالة اليومية</p>
                </div>
                <div class="header-actions">
                    <div class="datetime-stamp" id="datetime-stamp">الثلاثاء، 2 يونيو 2026</div>
                </div>
            </div>
            <div id="view-dashboard" class="view-panel active">
                <section class="dashboard-today" aria-labelledby="dashboard-today-title" aria-busy="true">
                    <div class="dashboard-today-head">
                        <div><span class="dashboard-eyebrow">العمليات اليومية</span><h2 id="dashboard-today-title">نبض الصالة اليوم</h2><p id="dash-date-label">جارٍ تحميل بيانات اليوم…</p></div>
                        <button id="dash-refresh" class="dashboard-refresh" type="button" onclick="refreshDashboard()"><i data-lucide="refresh-cw" aria-hidden="true"></i><span>تحديث البيانات</span></button>
                    </div>
                    <p id="dash-load-status" class="dashboard-load-status" role="status" aria-live="polite">جارٍ تحميل المؤشرات…</p>
                    <div class="dashboard-primary-metrics">
                        <article class="dashboard-today-metric is-attendance"><span class="dashboard-metric-label"><i data-lucide="user-check" aria-hidden="true"></i>الحضور اليوم</span><strong id="dash-today-attendance">—</strong><small>عملية دخول مسجلة</small></article>
                        <article class="dashboard-today-metric is-inside"><span class="dashboard-metric-label"><i data-lucide="activity" aria-hidden="true"></i>الموجودون الآن</span><strong id="dash-currently-inside">—</strong><small>داخل الصالة حاليًا</small></article>
                        <article class="dashboard-today-metric is-revenue"><span class="dashboard-metric-label"><i data-lucide="wallet" aria-hidden="true"></i>تحصيل اليوم</span><strong id="dash-today-revenue">—</strong><small><span id="dash-payments-today">—</span> دفعة مستلمة</small></article>
                        <article class="dashboard-today-metric is-renewal"><span class="dashboard-metric-label"><i data-lucide="calendar-clock" aria-hidden="true"></i>تجديدات قريبة</span><strong id="dash-expiring-members">—</strong><small>تنتهي خلال 3 أيام</small></article>
                    </div>
                    <div class="dashboard-secondary-metrics" aria-label="تفاصيل نشاط اليوم">
                        <div><span>مشتركون جدد</span><strong id="dash-new-members">—</strong></div>
                        <div><span>نقدًا</span><strong id="dash-cash-today">—</strong></div>
                        <div><span>تحويل</span><strong id="dash-transfer-today">—</strong></div>
                        <div><span>مصروفات</span><strong id="dash-expenses-today">—</strong></div>
                        <div class="is-net"><span>صافي التدفق</span><strong id="dash-net-today">—</strong></div>
                    </div>
                </section>

                <section class="dashboard-shortcuts" aria-label="إجراءات سريعة">
                    <span>إجراءات سريعة</span>
                    <button type="button" data-dashboard-action="member" onclick="dashboardQuickAction('members', 'modal-add-member')"><i data-lucide="user-plus" aria-hidden="true"></i>مشترك جديد</button>
                    <button type="button" data-dashboard-action="attendance" onclick="dashboardQuickAction('check-in')"><i data-lucide="scan-line" aria-hidden="true"></i>تسجيل حضور</button>
                    <button type="button" data-dashboard-action="payment" onclick="dashboardQuickAction('payments', 'modal-add-payment')"><i data-lucide="credit-card" aria-hidden="true"></i>إضافة دفعة</button>
                    <button type="button" data-dashboard-action="subscription" onclick="dashboardQuickAction('subscriptions', 'modal-add-subscription')"><i data-lucide="clipboard-plus" aria-hidden="true"></i>اشتراك جديد</button>
                </section>

                <section class="dashboard-member-summary" aria-label="حالة العضويات">
                    <div><span>إجمالي المشتركين</span><strong id="dash-total-members">—</strong></div>
                    <div><span>اشتراكات مجمدة</span><strong id="dash-frozen-members">—</strong></div>
                    <div><span>اشتراكات منتهية</span><strong id="dash-expired-members">—</strong></div>
                </section>

                <div class="charts-grid dashboard-charts-grid">
                    <section class="card chart-card dashboard-chart-card">
                        <div class="dashboard-section-head">
                            <div>
                                <span class="chart-subtitle-tag">آخر 14 يوم</span>
                                <h2 class="chart-main-title">الإيرادات</h2>
                            </div>
                            <div class="dashboard-period-tabs" role="group" aria-label="فترة الإيرادات">
                                <button class="dashboard-period-tab" type="button" data-revenue-days="1" aria-pressed="false">اليوم</button>
                                <button class="dashboard-period-tab" type="button" data-revenue-days="7" aria-pressed="false">7 أيام</button>
                                <button class="dashboard-period-tab active" type="button" data-revenue-days="14" aria-pressed="true">14 يومًا</button>
                            </div>
                        </div>
                        <div class="dashboard-canvas-wrap"><canvas id="chart-revenue-dashboard"></canvas></div>
                    </section>
                    <section class="card chart-card dashboard-chart-card">
                        <div class="dashboard-section-head">
                            <div>
                                <span class="chart-subtitle-tag">اليوم</span>
                                <h2 class="chart-main-title">أوقات الذروة</h2>
                            </div>
                            <div class="dashboard-period-tabs" role="group" aria-label="فترة أوقات الذروة">
                                <button class="dashboard-period-tab active" type="button" data-peak-range="day" aria-pressed="true">اليوم</button>
                                <button class="dashboard-period-tab" type="button" data-peak-range="week" aria-pressed="false">الأسبوع</button>
                                <button class="dashboard-period-tab" type="button" data-peak-range="month" aria-pressed="false">الشهر</button>
                            </div>
                        </div>
                        <div class="dashboard-canvas-wrap"><canvas id="chart-attendance-dashboard"></canvas></div>
                    </section>
                </div>

                <div class="dashboard-bottom-grid">
                    <section class="card dashboard-panel">
                        <div class="dashboard-section-head">
                            <div><h2 class="chart-main-title">أحدث المشتركين</h2><span class="chart-subtitle-tag">آخر 5 مشتركين</span></div>
                        </div>
                        <div id="dash-recent-members" class="recent-members-list"></div>
                        <button class="dashboard-link-btn" type="button" onclick="switchView('members')">عرض جميع المشتركين <i data-lucide="arrow-left"></i></button>
                    </section>
                    <section class="card dashboard-panel">
                        <div class="dashboard-section-head"><div><h2 class="chart-main-title">رؤى سريعة</h2><span class="chart-subtitle-tag">ملخص الأداء</span></div></div>
                        <div id="dash-insights" class="dashboard-insights"></div>
                    </section>
                    <section class="card dashboard-panel">
                        <div class="dashboard-section-head"><div><h2 class="chart-main-title">التنبيهات الأخيرة</h2><span class="chart-subtitle-tag">تحتاج متابعة</span></div></div>
                        <div id="dash-alerts" class="dashboard-alerts"></div>
                    </section>
                </div>
            </div>

            <div id="view-check-in" class="view-panel">
                <div class="gate-layout">
                    <section class="card gate-scan-card">
                        <div class="gate-card-header">
                            <div>
                                <span class="chart-subtitle-tag">RECEPTION GATE</span>
                                <h2 class="gate-title">بوابة الحضور</h2>
                            </div>
                            <div class="metric-icon-box icon-cyan">
                                <i data-lucide="qr-code"></i>
                            </div>
                        </div>

                        <form class="gate-form" onsubmit="event.preventDefault(); handleMemberCheckin();">
                            <input type="hidden" id="checkin-member-id-val">
                            <div class="form-group gate-search-wrap">
                                <label for="checkin-search-input" class="form-label">رقم العضوية أو كود QR/Barcode</label>
                                <input
                                    type="text"
                                    id="checkin-search-input"
                                    class="form-control gate-scan-input"
                                    placeholder="مثال: GYM-M00001 أو M00001"
                                    autocomplete="off"
                                    oninput="onCheckinSearchInput()"
                                    onfocus="showCheckinSuggestions()"
                                    onblur="hideCheckinSuggestionsDeferred()"
                                >
                                <div id="checkin-suggestions-box" class="checkin-suggestions-box"></div>
                            </div>
                            <div class="gate-actions">
                                <button type="submit" class="btn btn-primary">
                                    <i data-lucide="log-in"></i>
                                    <span>تسجيل حضور</span>
                                </button>
                                <button type="button" class="btn btn-secondary" onclick="handleMemberCheckout()">
                                    <i data-lucide="log-out"></i>
                                    <span>تسجيل المغادرة</span>
                                </button>
                            </div>
                        </form>
                    </section>

                    <section class="card gate-result-card" id="gate-result-card">
                        <div class="gate-empty-state" id="gate-empty-state">
                            <i data-lucide="scan-line"></i>
                            <span>بانتظار مسح بطاقة المشترك</span>
                        </div>
                        <div class="gate-member-result" id="gate-member-result" style="display: none;">
                            <div class="gate-member-photo" id="gate-member-photo">
                                <i data-lucide="user"></i>
                            </div>
                            <div class="gate-member-info">
                                <div class="gate-status-pill" id="gate-status-pill">جاهز</div>
                                <h2 id="gate-member-name">—</h2>
                                <div class="gate-member-meta">
                                    <span id="gate-member-id">—</span>
                                    <span id="gate-member-phone">—</span>
                                </div>
                                <div class="gate-subscription-line" id="gate-subscription-line">—</div>
                            </div>
                        </div>
                    </section>
                </div>

                <section class="card" style="margin-top: 24px;">
                    <div class="chart-header" style="margin-bottom: 16px; display: flex; justify-content: space-between; align-items: center; gap: 12px; flex-wrap: wrap;">
                        <div class="chart-title">
                            <span class="chart-subtitle-tag">TODAY LOG</span>
                            <span class="chart-main-title">سجل الحضور والمغادرة</span>
                        </div>
                        <button type="button" id="toggle-checkin-log-btn" class="btn btn-secondary btn-sm" onclick="toggleDailyCheckinLog()">
                            <i data-lucide="list-checks"></i>
                            <span id="toggle-checkin-log-text">عرض سجل اليوم</span>
                        </button>
                    </div>
                    <div id="checkin-log-panel" style="display: none;">
                        <div class="table-responsive">
                            <table class="custom-table">
                                <thead>
                                    <tr>
                                        <th>رقم العضوية</th>
                                        <th>اسم المشترك</th>
                                        <th>وقت الحضور</th>
                                        <th>وقت المغادرة</th>
                                        <th>الحالة</th>
                                        <th>إجراء</th>
                                    </tr>
                                </thead>
                                <tbody id="checkins-table-body">
                                    <tr>
                                        <td colspan="6" class="text-center" style="color: var(--text-muted);">لا يوجد تسجيل حضور لهذا اليوم حتى الآن</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </section>
            </div>
        </main>
    </div>
@endsection
