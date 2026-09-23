// آيرون جيم IRON GYM - Application Controller & Database Sync

const state = {
    members: [],
    plans: [],
    subscriptions: [],
    payments: [],
    membershipCards: [],
    products: [],
    sales: [],
    checkins: [],
    trainers: [],
    trainerAssignments: [],
    trainingPrograms: [],
    nutritionPrograms: [],
    notificationTemplates: [],
    notifications: [],
    syncEvents: [],
    syncEventsHydrated: false,
    syncCenterStats: null,
    basket: [],
    
    // Calculated statistics
    todayRevenue: 0,
    monthlyRevenue: 0,
    todayAttendance: 0,
    renewalRate: 0,
    productSales: 0,
    
    // Graph datasets
    revenueHistory: {},
    peakHours: {},
    reports: {
        debtsTotal: 0,
        debtMembersCount: 0,
        endedLast30Days: 0,
        renewedLast30Days: 0,
        renewalRate: 0,
        attendanceHeatmap: [],
        topProductSales: [],
        stockAlerts: [],
        trainerPerformance: []
    },
    
    // User session state
    currentUser: null,
    memberData: null,
    trainerData: null,
    selectedSyncEventId: null,
};

let currentView = "dashboard";
const OFFLINE_QUEUE_KEY = "gym_offline_sync_queue_v1";

function resolveApiUrl(action) {
    return `/api/${String(action || '').toLowerCase()}`;
}

// ChartJS Instance Registry
let charts = {
    revenueDashboard: null,
    attendanceDashboard: null,
    revenueReports: null,
    attendanceReports: null
};

// 1. Initial State Loading on Startup
document.addEventListener("DOMContentLoaded", () => {
    const loginContainer = document.getElementById("login-container");
    // Initialize Navigation Side Links
    const navLinks = document.querySelectorAll(".sidebar-nav .nav-link");
    navLinks.forEach(link => {
        link.addEventListener("click", () => {
            const targetView = link.getAttribute("data-view");
            if (isViewAllowed(targetView)) {
                switchView(targetView);
            } else {
                showAppNotice("غير مصرح لك بدخول هذا القسم!");
            }
        });
    });

    // Populate current date/time in header
    updateDateTime();
    setInterval(updateDateTime, 1000);

    // Initialize all input dates to current date
    const todayStr = new Date().toISOString().split("T")[0];
    const dateInputs = document.querySelectorAll('input[type="date"]');
    dateInputs.forEach(input => input.value = todayStr);

    // Build missing view panels if Blade template is incomplete.
    ensureMissingViewScaffold();
    ensureFallbackModals();

    // Restore or initialize session
    checkSessionAndInit();

    // Offline/PWA foundation
    initOfflineSync();
    
    // Initialize Table Column Filters
    initAllTableFilters();
});

function formatDateTime(value, includeTime = true) {
    if (!value) return '—';
    const source = String(value).trim();
    const match = source.match(/^(\d{4})-(\d{2})-(\d{2})(?:[ T](\d{2}):(\d{2})(?::(\d{2}))?)?/);
    const date = match
        ? new Date(Number(match[1]), Number(match[2]) - 1, Number(match[3]), Number(match[4] || 0), Number(match[5] || 0), Number(match[6] || 0))
        : new Date(source);
    if (Number.isNaN(date.getTime())) return source;

    const pad = number => String(number).padStart(2, '0');
    const formattedDate = `${pad(date.getDate())}-${pad(date.getMonth() + 1)}-${date.getFullYear()}`;
    return includeTime && (match?.[4] || /[T ]\d{2}:\d{2}/.test(source))
        ? `${formattedDate} ${pad(date.getHours())}:${pad(date.getMinutes())}`
        : formattedDate;
}

function updateDateTime() {
    const arabicDate = formatDateTime(new Date().toLocaleString('sv-SE'), true);
    const stampEl = document.getElementById("datetime-stamp");
    if (stampEl) {
        stampEl.innerText = arabicDate;
    }
}

function initOfflineSync() {
    renderNetworkStatusBadge();
    refreshNetworkStatusBadge();

    window.addEventListener("online", () => {
        refreshNetworkStatusBadge();
        flushOfflineQueue();
    });
    window.addEventListener("offline", refreshNetworkStatusBadge);

    if ("serviceWorker" in navigator) {
        navigator.serviceWorker.register('/sw.js').catch(() => {});
    }

    if (navigator.onLine) {
        flushOfflineQueue();
    }

    setInterval(() => {
        if (navigator.onLine) {
            flushOfflineQueue();
        }
    }, 300000);
}

function renderNetworkStatusBadge() {
    if (document.getElementById("network-status-badge")) return;

    const badge = document.createElement("div");
    badge.id = "network-status-badge";
    badge.style.position = "fixed";
    badge.style.left = "14px";
    badge.style.bottom = "14px";
    badge.style.padding = "8px 12px";
    badge.style.borderRadius = "999px";
    badge.style.fontSize = "12px";
    badge.style.fontWeight = "700";
    badge.style.zIndex = "9999";
    badge.style.border = "1px solid var(--border-color)";
    badge.style.backdropFilter = "blur(6px)";
    document.body.appendChild(badge);
}

function ensureAppNoticeHost() {
    let host = document.getElementById('app-notice-host');
    if (host) return host;

    host = document.createElement('div');
    host.id = 'app-notice-host';
    host.style.position = 'fixed';
    host.style.left = '14px';
    host.style.top = '14px';
    host.style.zIndex = '10000';
    host.style.display = 'flex';
    host.style.flexDirection = 'column';
    host.style.gap = '8px';
    host.style.maxWidth = '360px';
    document.body.appendChild(host);
    return host;
}

function showAppNotice(message, type = 'info', timeout = 4200) {
    if (!message) return;

    const host = ensureAppNoticeHost();
    const notice = document.createElement('div');
    let dismissed = false;

    const typeStyles = {
        info: {
            background: 'rgba(27, 115, 240, 0.18)',
            color: '#0e3c7d',
            borderColor: 'rgba(27, 115, 240, 0.35)',
        },
        success: {
            background: 'rgba(66, 183, 102, 0.2)',
            color: '#0f4b27',
            borderColor: 'rgba(66, 183, 102, 0.34)',
        },
        warning: {
            background: 'rgba(245, 166, 35, 0.24)',
            color: '#6f4303',
            borderColor: 'rgba(245, 166, 35, 0.4)',
        },
        error: {
            background: 'rgba(229, 72, 77, 0.2)',
            color: '#7a1519',
            borderColor: 'rgba(229, 72, 77, 0.38)',
        },
    };

    const style = typeStyles[type] || typeStyles.info;
    notice.style.display = 'flex';
    notice.style.alignItems = 'flex-start';
    notice.style.justifyContent = 'space-between';
    notice.style.gap = '10px';
    notice.style.padding = '10px 12px';
    notice.style.borderRadius = '12px';
    notice.style.border = `1px solid ${style.borderColor}`;
    notice.style.background = style.background;
    notice.style.color = style.color;
    notice.style.fontSize = '13px';
    notice.style.fontWeight = '700';
    notice.style.lineHeight = '1.45';
    notice.style.boxShadow = '0 10px 20px rgba(15, 26, 47, 0.12)';
    notice.style.backdropFilter = 'blur(5px)';
    notice.style.opacity = '0';
    notice.style.transform = 'translateY(-6px)';
    notice.style.transition = 'opacity 160ms ease, transform 160ms ease';

    const messageNode = document.createElement('div');
    messageNode.textContent = message;
    messageNode.style.flex = '1';

    const closeBtn = document.createElement('button');
    closeBtn.type = 'button';
    closeBtn.textContent = '×';
    closeBtn.setAttribute('aria-label', 'إغلاق الإشعار');
    closeBtn.style.background = 'transparent';
    closeBtn.style.border = 'none';
    closeBtn.style.color = style.color;
    closeBtn.style.cursor = 'pointer';
    closeBtn.style.fontSize = '16px';
    closeBtn.style.lineHeight = '1';
    closeBtn.style.padding = '0 2px';
    closeBtn.style.fontWeight = '900';

    const dismiss = () => {
        if (dismissed) return;
        dismissed = true;
        notice.style.opacity = '0';
        notice.style.transform = 'translateY(-6px)';
        window.setTimeout(() => {
            notice.remove();
        }, 200);
    };

    closeBtn.addEventListener('click', dismiss);
    notice.appendChild(messageNode);
    notice.appendChild(closeBtn);

    host.appendChild(notice);
    requestAnimationFrame(() => {
        notice.style.opacity = '1';
        notice.style.transform = 'translateY(0)';
    });

    window.setTimeout(() => {
        dismiss();
    }, Math.max(1600, timeout));
}

function getReassignmentNoticeMessage(responseData) {
    if (!responseData?.reassigned || !responseData?.reassignedBranch) {
        return null;
    }

    const fallbackGymId = Number(responseData.reassignedBranch.gym_id);
    const fallbackBranchId = Number(responseData.reassignedBranch.branch_id);
    const gyms = Array.isArray(state.tenant?.gyms) ? state.tenant.gyms : [];
    const fallbackGym = gyms.find(g => Number(g.id) === fallbackGymId);
    const fallbackBranch = fallbackGym?.branches?.find(b => Number(b.id) === fallbackBranchId);

    const gymLabel = fallbackGym?.name || `الصالة #${fallbackGymId}`;
    const branchLabel = fallbackBranch?.name || `الفرع #${fallbackBranchId}`;

    return `تم نقلك تلقائياً إلى ${gymLabel} - ${branchLabel} قبل تعطيل الصالة الحالية.`;
}

function refreshNetworkStatusBadge() {
    const badge = document.getElementById("network-status-badge");
    if (!badge) return;

    const queue = readOfflineQueue();
    const online = navigator.onLine;

    if (online) {
        badge.textContent = queue.length > 0
            ? `متصل | بانتظار مزامنة ${queue.length}`
            : "متصل";
        badge.style.color = "#0d4e2b";
        badge.style.background = "rgba(80, 200, 120, 0.22)";
    } else {
        badge.textContent = `غير متصل | محفوظ ${queue.length}`;
        badge.style.color = "#7a130f";
        badge.style.background = "rgba(255, 110, 98, 0.24)";
    }
}

function readOfflineQueue() {
    try {
        const raw = localStorage.getItem(OFFLINE_QUEUE_KEY);
        const parsed = raw ? JSON.parse(raw) : [];
        return Array.isArray(parsed) ? parsed : [];
    } catch {
        return [];
    }
}

function writeOfflineQueue(events) {
    localStorage.setItem(OFFLINE_QUEUE_KEY, JSON.stringify(events));
    refreshNetworkStatusBadge();
}

function queueOfflineSyncEvent(action, payload) {
    const queue = readOfflineQueue();
    const nextEvent = {
        clientEventId: `evt_${Date.now()}_${Math.random().toString(16).slice(2, 10)}`,
        action,
        payload,
        createdAt: new Date().toISOString(),
    };

    queue.push(nextEvent);
    writeOfflineQueue(queue);
    return nextEvent;
}

function ensureGlobalLoader() {
    let loader = document.getElementById('global-loader');
    if (loader) return loader;
    loader = document.createElement('div');
    loader.id = 'global-loader';
    loader.setAttribute('aria-hidden', 'true');
    loader.innerHTML = '<div class="global-loader-inner" role="status" aria-live="polite"><div class="spinner"></div><span class="sr-only">جاري التحميل...</span></div>';
    document.body.appendChild(loader);
    return loader;
}

function showGlobalLoader() {
    const l = ensureGlobalLoader();
    l.style.display = 'flex';
    l.setAttribute('aria-hidden', 'false');
}

function hideGlobalLoader() {
    const l = document.getElementById('global-loader');
    if (!l) return;
    l.style.display = 'none';
    l.setAttribute('aria-hidden', 'true');
}

function showFieldError(fieldId, message) {
    try {
        const errEl = document.getElementById(fieldId + '-error');
        const input = document.getElementById(fieldId);
        if (errEl) errEl.innerText = message;
        if (input) input.setAttribute('aria-invalid', 'true');
    } catch {}
}

function clearFieldError(fieldId) {
    try {
        const errEl = document.getElementById(fieldId + '-error');
        const input = document.getElementById(fieldId);
        if (errEl) errEl.innerText = '';
        if (input) input.setAttribute('aria-invalid', 'false');
    } catch {}
}

function postApi(action, payload) {
    showGlobalLoader();
    // Offline sync is still handled by the legacy compatibility endpoint.
    // All normal dashboard actions must use the endpoint for the active entry mode.
    const url = action === 'sync_push_queue' ? `/api/${action}` : resolveApiUrl(action);
    return fetch(url, {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify(payload || {}),
        credentials: 'same-origin'
    })
    .then(r => r.json())
    .finally(() => hideGlobalLoader());
}

function flushOfflineQueue() {
    const queue = readOfflineQueue();
    if (!navigator.onLine || queue.length === 0) {
        refreshNetworkStatusBadge();
        return Promise.resolve({ success: true, skipped: true });
    }

    return postApi('sync_push_queue', { events: queue })
        .then(res => {
            if (!res.success) {
                return res;
            }

            const failedIds = new Set((res.results || [])
                .filter(item => item.status === 'failed')
                .map(item => item.clientEventId)
                .filter(Boolean));

            const nextQueue = queue.filter(item => failedIds.has(item.clientEventId));
            writeOfflineQueue(nextQueue);

            if ((res.summary?.processed || 0) > 0 && currentView === 'check-in') {
                loadStateAndRender('check-in');
            }

            return res;
        })
        .catch(() => ({ success: false }));
}

function ensureMissingViewScaffold() {
    const mainContent = document.querySelector(".main-content");
    if (!mainContent) return;

    const viewTemplates = {
        members: `
            <div class="card" style="margin-bottom: 16px;">
                <div style="display:flex; gap:8px; flex-wrap:wrap; align-items:center;">
                    <input id="members-search" class="form-control" style="flex:1; min-width:220px;" type="text" placeholder="ابحث بالاسم أو الهاتف أو رقم العضوية..." oninput="filterMembers()">
                    <button class="btn btn-primary" onclick="openModal('modal-add-member')">إضافة مشترك</button>
                </div>
            </div>
            <div id="members-grid" class="members-grid"></div>
        `,
        subscriptions: `
            <div class="card" style="margin-bottom: 16px;">
                <div style="display:flex; gap:8px; flex-wrap:wrap; justify-content:space-between; align-items:center;">
                    <div id="sub-filter-tabs" style="display: flex; gap: 8px; flex-wrap: wrap;">
                    <button class="tab-btn active" onclick="filterSubscriptions('all', this)">الكل</button>
                    <button class="tab-btn" onclick="filterSubscriptions('فعال', this)">فعال</button>
                    <button class="tab-btn" onclick="filterSubscriptions('مجمد', this)">مجمد</button>
                    <button class="tab-btn" onclick="filterSubscriptions('منتهي', this)">منتهي</button>
                    </div>
                    <button class="btn btn-primary" onclick="openModal('modal-add-subscription')">إضافة اشتراك</button>
                </div>
            </div>
            <div class="card table-responsive">
                <table class="custom-table">
                    <thead>
                        <tr>
                            <th>المشترك</th>
                            <th>الخطة</th>
                            <th>تاريخ البدء</th>
                            <th>تاريخ الانتهاء</th>
                            <th>المبلغ</th>
                            <th>المتبقي</th>
                            <th>الحالة</th>
                            <th>الإجراءات</th>
                        </tr>
                    </thead>
                    <tbody id="subscriptions-table-body"></tbody>
                </table>
            </div>
        `,
        plans: `
            <div class="card" style="margin-bottom: 16px;"><button class="btn btn-primary" onclick="openModal('modal-add-plan')">إضافة خطة</button></div>
            <div id="plans-grid" class="plans-grid"></div>
        `,
        payments: `
            <div class="card" style="margin-bottom: 16px;">
                <div style="display:flex; gap:8px; align-items:center; justify-content:space-between; flex-wrap:wrap;">
                    <strong>إجمالي المدفوعات: <span id="total-payments-value">0 ₪</span></strong>
                    <button class="btn btn-primary" onclick="openModal('modal-add-payment')">إضافة دفعة</button>
                </div>
            </div>
            <div class="card table-responsive">
                <table class="custom-table">
                    <thead>
                        <tr>
                            <th>التاريخ</th>
                            <th>المشترك / الوصف</th>
                            <th>المبلغ</th>
                            <th>الطريقة</th>
                            <th>الملاحظة</th>
                            <th>الإجراءات</th>
                        </tr>
                    </thead>
                    <tbody id="payments-table-body"></tbody>
                </table>
            </div>
        `,
        products: `
            <div class="products-toolbar">
                <span id="products-date-pill" class="products-date-pill">—</span>
                <button class="btn btn-primary" onclick="openModal('modal-add-product')">
                    <i data-lucide="plus"></i>
                    <span>منتج جديد</span>
                </button>
            </div>
            <div class="products-layout">
                <aside class="card basket-panel">
                    <div class="basket-header">
                        <h3>سلة البيع</h3>
                        <i data-lucide="shopping-cart"></i>
                    </div>
                    <div id="basket-items-container" class="basket-items"></div>
                    <div class="basket-summary">
                        <div class="basket-total-row">
                            <span>الإجمالي</span>
                            <span id="basket-total" class="basket-total-val">0 ₪</span>
                        </div>
                        <div class="basket-method">
                            <label class="basket-method-label" for="basket-payment-method">طريقة الدفع</label>
                            <select id="basket-payment-method" class="form-control basket-method-select" onchange="togglePaymentTransferAccountField('basket-payment-method','basket-transfer-account-group','basket-transfer-from-account')">
                                <option value="نقدي">نقدي</option>
                                <option value="تحويل">تحويل</option>
                            </select>
                        </div>
                        <div id="basket-transfer-account-group" class="basket-method" style="display:none;">
                            <label class="basket-method-label" for="basket-transfer-from-account">اسم الشخص أو الحساب المُحوِّل</label>
                            <input id="basket-transfer-from-account" class="form-control" type="text" placeholder="مثال: أحمد محمد أو حساب البنك الأهلي">
                        </div>
                        <div class="basket-method">
                            <label class="basket-method-label" for="basket-member-select">ربط بالمشترك (اختياري)</label>
                            <select id="basket-member-select" class="form-control basket-method-select"></select>
                        </div>
                        <button class="btn btn-primary basket-checkout-btn" onclick="checkoutBasket()">تأكيد عملية البيع</button>
                    </div>
                </aside>
                <section class="products-catalog">
                    <div id="products-grid" class="products-grid"></div>
                </section>
            </div>
        `,
        reports: `
            <div class="metrics-grid" style="margin-bottom: 16px;">
                <div class="card metric-card"><span>حضور اليوم</span><strong id="rep-today-attendance">0</strong></div>
                <div class="card metric-card"><span>إيراد الشهر</span><strong id="rep-month-revenue">0 ₪</strong></div>
                <div class="card metric-card"><span>مبيعات المنتجات</span><strong id="rep-product-sales">0 ₪</strong></div>
                <div class="card metric-card"><span>معدل التجديد</span><strong id="rep-renewal-rate">0%</strong></div>
                <div class="card metric-card"><span>إجمالي الديون</span><strong id="rep-debts-total">0 ₪</strong></div>
                <div class="card metric-card"><span>أعضاء عليهم ديون</span><strong id="rep-debt-members">0</strong></div>
                <div class="card metric-card"><span>تجديدات آخر 30 يوم</span><strong id="rep-renewed-count">0</strong></div>
                <div class="card metric-card"><span>منتجات منخفضة المخزون</span><strong id="rep-low-stock-count">0</strong></div>
            </div>
            <div class="card table-responsive" style="margin-bottom: 16px;">
                <div class="chart-header" style="margin-bottom:16px; display:flex; justify-content:space-between; gap:12px; flex-wrap:wrap; align-items:center;">
                    <div class="chart-title"><span class="chart-main-title">تقرير طرق الدفع</span><span class="chart-subtitle-tag">نقدي وتحويل</span></div>
                    <div style="display:flex; gap:8px; flex-wrap:wrap;">
                        <select id="payment-report-method" class="form-control" onchange="renderPaymentMethodReport()"><option value="all">كل الطرق</option><option value="نقدي">نقدي</option><option value="تحويل">تحويل</option></select>
                        <input id="payment-report-transfer-search" class="form-control" type="search" placeholder="ابحث باسم الشخص أو الحساب" oninput="renderPaymentMethodReport()">
                    </div>
                </div>
                <div class="metrics-grid" style="margin-bottom:16px;">
                    <div class="card metric-card"><span>إجمالي النقدي</span><strong id="rep-cash-total">0 ₪</strong></div>
                    <div class="card metric-card"><span>إجمالي التحويل</span><strong id="rep-transfer-total">0 ₪</strong></div>
                    <div class="card metric-card"><span>عدد التحويلات</span><strong id="rep-transfer-count">0</strong></div>
                </div>
                <table class="custom-table">
                    <thead><tr><th>التاريخ</th><th>المشترك</th><th>المبلغ</th><th>طريقة الدفع</th><th>الشخص أو الحساب المُحوِّل</th><th>ملاحظة</th></tr></thead>
                    <tbody id="payment-method-report-body"></tbody>
                </table>
            </div>
            <div class="card" style="height: 280px; margin-bottom: 16px;"><canvas id="chart-revenue-reports"></canvas></div>
            <div class="card" style="height: 280px; margin-bottom: 16px;"><canvas id="chart-attendance-reports"></canvas></div>
            <div class="card" style="margin-bottom: 16px;">
                <h3 style="margin-bottom: 10px;">حرارة الحضور حسب أيام الأسبوع (آخر 30 يوم)</h3>
                <div id="attendance-heatmap-grid" style="display:grid; grid-template-columns: repeat(7, minmax(0,1fr)); gap:8px;"></div>
            </div>
            <div class="card table-responsive" style="margin-bottom: 16px;">
                <table class="custom-table">
                    <thead>
                        <tr><th>التاريخ</th><th>المنتجات</th><th>الإجمالي</th><th>الطريقة</th></tr>
                    </thead>
                    <tbody id="sales-table-body"></tbody>
                </table>
            </div>
            <div class="card table-responsive" style="margin-bottom: 16px;">
                <h3 style="margin-bottom: 10px;">أفضل المنتجات مبيعاً</h3>
                <table class="custom-table">
                    <thead>
                        <tr><th>المنتج</th><th>الكمية المباعة</th><th>الإجمالي</th></tr>
                    </thead>
                    <tbody id="top-products-table-body"></tbody>
                </table>
            </div>
            <div class="card table-responsive" style="margin-bottom: 16px;">
                <h3 style="margin-bottom: 10px;">تنبيهات المخزون</h3>
                <table class="custom-table">
                    <thead>
                        <tr><th>المعرف</th><th>المنتج</th><th>المخزون</th></tr>
                    </thead>
                    <tbody id="stock-alerts-table-body"></tbody>
                </table>
            </div>
            <div class="card table-responsive">
                <h3 style="margin-bottom: 10px;">تقرير المدربين</h3>
                <table class="custom-table">
                    <thead>
                        <tr><th>المدرب</th><th>التخصص</th><th>أعضاء نشطون</th><th>برامج تدريبية</th><th>برامج غذائية</th></tr>
                    </thead>
                    <tbody id="trainer-performance-table-body"></tbody>
                </table>
            </div>
        `,
        notifications: `
            <div class="card" style="margin-bottom: 16px;">
                <div style="display:flex; gap:8px; flex-wrap:wrap; align-items:center; justify-content:space-between;">
                    <div style="display:flex; gap:8px; flex-wrap:wrap;">
                        <button class="btn btn-primary" onclick="openModal('modal-add-notification-template')">إضافة قالب إشعار</button>
                        <button class="btn btn-secondary" onclick="openModal('modal-send-notification')">إرسال إشعار</button>
                        <button class="btn btn-secondary" id="btn-process-notification-jobs" onclick="runNotificationJobs()">تشغيل تذكيرات الاشتراكات</button>
                    </div>
                    <div style="color: var(--text-muted); font-size: 13px;">إدارة قوالب الإشعارات وسجل الإرسال</div>
                </div>
            </div>
            <div class="card table-responsive" style="margin-bottom: 16px;">
                <h3 style="margin-bottom: 10px;">قوالب الإشعارات</h3>
                <table class="custom-table">
                    <thead><tr><th>الاسم</th><th>القناة</th><th>النوع</th><th>العنوان</th><th>الحالة</th></tr></thead>
                    <tbody id="notification-templates-table-body"></tbody>
                </table>
            </div>
            <div class="card table-responsive">
                <h3 style="margin-bottom: 10px;">سجل الإشعارات</h3>
                <table class="custom-table">
                    <thead><tr><th>الوقت</th><th>المشترك</th><th>القناة</th><th>النوع</th><th>العنوان</th><th>الحالة</th></tr></thead>
                    <tbody id="notifications-table-body"></tbody>
                </table>
            </div>
        `,
        "sync-center": `
            <div class="card" style="margin-bottom: 16px;">
                <div style="display:flex; gap:8px; flex-wrap:wrap; align-items:center; justify-content:space-between;">
                    <div style="display:flex; gap:8px; flex-wrap:wrap;">
                        <button class="btn btn-primary" onclick="loadSyncCenterData()">تحديث السجل</button>
                        <button class="btn btn-secondary" id="btn-retry-failed-sync" onclick="retryFailedSyncEvents()">إعادة محاولة الفاشل</button>
                        <button class="btn btn-secondary" id="btn-flush-local-queue" onclick="flushOfflineQueue().then(() => loadSyncCenterData())">مزامنة الطابور المحلي</button>
                        <button class="btn btn-secondary" id="btn-export-sync-csv" onclick="downloadSyncEventsCsv()">تصدير CSV</button>
                        <button class="btn btn-secondary" id="btn-export-sync-xlsx" onclick="downloadSyncEventsXlsx()">تصدير Excel</button>
                    </div>
                    <div id="sync-center-summary" style="color: var(--text-muted); font-size: 13px;">—</div>
                </div>
            </div>
            <div class="card" style="margin-bottom: 16px;">
                <div class="metrics-grid" style="margin-bottom: 12px;">
                    <div class="card metric-card"><span>إجمالي الأحداث</span><strong id="sync-stat-total">0</strong></div>
                    <div class="card metric-card"><span>تمت المعالجة</span><strong id="sync-stat-processed">0</strong></div>
                    <div class="card metric-card"><span>الفاشلة</span><strong id="sync-stat-failed">0</strong></div>
                    <div class="card metric-card"><span>متوسط المعالجة</span><strong id="sync-stat-average">0.00 s</strong></div>
                </div>
                <div style="display:grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 10px;">
                    <select id="sync-filter-status" class="form-control">
                        <option value="">كل الحالات</option>
                        <option value="pending">pending</option>
                        <option value="processed">processed</option>
                        <option value="failed">failed</option>
                        <option value="skipped">skipped</option>
                    </select>
                    <select id="sync-filter-action" class="form-control">
                        <option value="">كل الإجراءات</option>
                        <option value="check_in">check_in</option>
                        <option value="check_out">check_out</option>
                        <option value="add_measurement">add_measurement</option>
                        <option value="add_payment">add_payment</option>
                        <option value="checkout_basket">checkout_basket</option>
                    </select>
                    <input id="sync-filter-from" type="date" class="form-control">
                    <input id="sync-filter-to" type="date" class="form-control">
                </div>
            </div>
            <div class="card table-responsive" style="margin-bottom: 16px;">
                <h3 style="margin-bottom: 10px;">الطابور المحلي (على هذا الجهاز)</h3>
                <table class="custom-table">
                    <thead><tr><th>وقت الإضافة</th><th>الإجراء</th><th>المعرف المحلي</th><th>الحمولة</th></tr></thead>
                    <tbody id="local-sync-queue-body"></tbody>
                </table>
            </div>
            <div class="card table-responsive">
                <h3 style="margin-bottom: 10px;">سجل المزامنة على الخادم</h3>
                <table class="custom-table">
                    <thead><tr><th>#</th><th>الوقت</th><th>الإجراء</th><th>الحالة</th><th>النتيجة</th><th>المعرف المحلي</th><th>الإجراءات</th></tr></thead>
                    <tbody id="sync-events-table-body"></tbody>
                </table>
            </div>
        `,
        "sync-event-detail": `
            <div class="card" style="margin-bottom: 16px; display:flex; justify-content:space-between; align-items:center; gap:10px;">
                <div style="color: var(--text-muted); font-size: 13px;">تفاصيل كاملة لحدث مزامنة واحد</div>
                <div style="display:flex; gap:8px; align-items:center;">
                    <button id="sync-detail-prev" class="btn btn-secondary" onclick="gotoAdjacentSyncEvent(-1)">السابق</button>
                    <button id="sync-detail-next" class="btn btn-secondary" onclick="gotoAdjacentSyncEvent(1)">التالي</button>
                    <button class="btn btn-secondary" onclick="switchView('sync-center')">العودة إلى مركز المزامنة</button>
                </div>
            </div>
            <div class="card" style="margin-bottom: 16px;">
                <div style="display:grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 10px;">
                    <div><strong>المعرف:</strong> <span id="sync-detail-page-id" class="val-mono">—</span></div>
                    <div><strong>الوقت:</strong> <span id="sync-detail-page-time" class="val-mono">—</span></div>
                    <div><strong>الإجراء:</strong> <span id="sync-detail-page-action" class="val-mono">—</span></div>
                    <div><strong>الحالة:</strong> <span id="sync-detail-page-status">—</span></div>
                    <div><strong>المعرف المحلي:</strong> <span id="sync-detail-page-client-id" class="val-mono">—</span></div>
                    <div><strong>المعالجة:</strong> <span id="sync-detail-page-processing" class="val-mono">—</span></div>
                </div>
            </div>
            <div class="card" style="margin-bottom: 16px;">
                <h3 style="margin-bottom: 10px;">نتيجة المعالجة</h3>
                <div id="sync-detail-page-result" class="form-control" style="height:auto; min-height: 60px;"></div>
            </div>
            <div class="card">
                <h3 style="margin-bottom: 10px;">الحمولة</h3>
                <pre id="sync-detail-page-payload" class="form-control" style="height:auto; min-height: 180px; white-space: pre-wrap; direction:ltr; text-align:left;"></pre>
            </div>
        `,
        trainers: `
            <div class="card" style="margin-bottom: 16px;">
                <div style="display:flex; gap:8px; flex-wrap:wrap; align-items:center; justify-content:space-between;">
                    <div style="display:flex; gap:8px; flex-wrap:wrap;">
                        <button class="btn btn-primary" onclick="openModal('modal-add-trainer-profile')">إضافة ملف مدرب</button>
                        <button class="btn btn-secondary" onclick="openAssignTrainerModal()">ربط مدرب بمشترك</button>
                        <button class="btn btn-secondary" onclick="openModal('modal-add-training-program')">إضافة برنامج تدريبي</button>
                        <button class="btn btn-secondary" onclick="openModal('modal-add-nutrition-program')">إضافة برنامج غذائي</button>
                    </div>
                    <div style="color: var(--text-muted); font-size: 13px;">إدارة ملفات المدربين وربطهم بالمشتركين</div>
                </div>
            </div>
            <div class="card table-responsive" style="margin-bottom: 16px;">
                <h3 style="margin-bottom: 10px;">ملفات المدربين</h3>
                <table class="custom-table">
                    <thead><tr><th>المعرف</th><th>المستخدم</th><th>التخصص</th><th>العمولة</th><th>الحالة</th></tr></thead>
                    <tbody id="trainers-table-body"></tbody>
                </table>
            </div>
            <div class="card table-responsive">
                <h3 style="margin-bottom: 10px;">ربط المدربين بالمشتركين</h3>
                <table class="custom-table">
                    <thead><tr><th>المدرب</th><th>المشترك</th><th>البداية</th><th>النهاية</th><th>الحالة</th><th>ملاحظة</th></tr></thead>
                    <tbody id="trainer-assignments-table-body"></tbody>
                </table>
            </div>
            <div class="card table-responsive" style="margin-top: 16px;">
                <h3 style="margin-bottom: 10px;">البرامج التدريبية</h3>
                <table class="custom-table">
                    <thead><tr><th>المدرب</th><th>المشترك</th><th>العنوان</th><th>الهدف</th><th>المحتوى</th><th>الإجراءات</th></tr></thead>
                    <tbody id="training-programs-table-body"></tbody>
                </table>
            </div>
            <div class="card table-responsive" style="margin-top: 16px;">
                <h3 style="margin-bottom: 10px;">البرامج الغذائية</h3>
                <table class="custom-table">
                    <thead><tr><th>المدرب</th><th>المشترك</th><th>العنوان</th><th>الهدف</th><th>المحتوى</th><th>الإجراءات</th></tr></thead>
                    <tbody id="nutrition-programs-table-body"></tbody>
                </table>
            </div>
        `,
        users: `
            <div class="card" style="margin-bottom: 16px;"><button class="btn btn-primary" onclick="openModal('modal-add-user')">إضافة مستخدم</button></div>
            <div class="card table-responsive"><table class="custom-table"><thead><tr><th>الاسم</th><th>المستخدم</th><th>الدور</th><th>ربط العضو</th><th>تاريخ الإنشاء</th><th>الإجراءات</th></tr></thead><tbody id="users-table-body"></tbody></table></div>
        `,
        "tenant-management": `
            <div class="card" style="margin-bottom: 16px; display:flex; gap:8px; flex-wrap:wrap; align-items:center; justify-content:space-between;">
                <div style="display:flex; gap:8px; flex-wrap:wrap;">
                    <button class="btn btn-primary" onclick="openAddGymModal()">إضافة صالة</button>
                    <button class="btn btn-secondary" onclick="openAddBranchModal()">إضافة فرع</button>
                    <button class="btn btn-secondary" onclick="renderTenantManagement()">تحديث</button>
                    <button class="btn btn-secondary" onclick="downloadTenantGymsCsv()">تصدير الصالات CSV</button>
                    <button class="btn btn-secondary" onclick="downloadTenantBranchesCsv()">تصدير الفروع CSV</button>
                    <button class="btn btn-secondary" onclick="downloadTenantManagementXlsx()">تصدير Excel</button>
                </div>
                <div style="color: var(--text-muted); font-size: 13px;">إدارة الصالات والفروع وتعيين الفرع الافتراضي</div>
            </div>
            <div class="card" style="margin-bottom: 16px; display:grid; gap:10px; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));">
                <input id="tenant-filter-gym-query" class="form-control" type="text" placeholder="بحث الصالات (اسم/رمز)" oninput="renderTenantManagement()">
                <select id="tenant-filter-gym-status" class="form-control" onchange="renderTenantManagement()"><option value="all">كل حالات الصالات</option><option value="active">نشطة</option><option value="inactive">غير نشطة</option></select>
                <select id="tenant-filter-gym-sort" class="form-control" onchange="renderTenantManagement()"><option value="name-asc">ترتيب الصالات: اسم تصاعدي</option><option value="name-desc">ترتيب الصالات: اسم تنازلي</option><option value="branches-desc">ترتيب الصالات: الأكثر فروعًا</option></select>
                <input id="tenant-filter-branch-query" class="form-control" type="text" placeholder="بحث الفروع (اسم/رمز)" oninput="renderTenantManagement()">
                <select id="tenant-filter-branch-status" class="form-control" onchange="renderTenantManagement()"><option value="all">كل حالات الفروع</option><option value="active">نشطة</option><option value="inactive">غير نشطة</option></select>
                <select id="tenant-filter-branch-gym" class="form-control" onchange="renderTenantManagement()"><option value="all">كل الصالات</option></select>
                <select id="tenant-filter-branch-sort" class="form-control" onchange="renderTenantManagement()"><option value="name-asc">ترتيب الفروع: اسم تصاعدي</option><option value="name-desc">ترتيب الفروع: اسم تنازلي</option><option value="gym-name">ترتيب الفروع: حسب الصالة</option></select>
            </div>
            <div class="card table-responsive" style="margin-bottom: 16px;">
                <h3 style="margin-bottom: 10px;">الصالـات</h3>
                <table class="custom-table">
                    <thead><tr><th>#</th><th>الاسم</th><th>الرمز</th><th>الحالة</th><th>عدد الفروع</th><th>الإجراءات</th></tr></thead>
                    <tbody id="tenant-gyms-table-body"></tbody>
                </table>
            </div>
            <div class="card table-responsive">
                <h3 style="margin-bottom: 10px;">الفروع</h3>
                <table class="custom-table">
                    <thead><tr><th>#</th><th>الصالة</th><th>الاسم</th><th>الرمز</th><th>الحالة</th><th>افتراضي</th><th>الإجراءات</th></tr></thead>
                    <tbody id="tenant-branches-table-body"></tbody>
                </table>
            </div>
        `,
        "member-detail": `
            <div class="card" style="margin-bottom: 16px;">
                <div style="display: flex; justify-content: space-between; gap: 16px; align-items: center; flex-wrap: wrap;">
                    <div style="display: flex; gap: 14px; align-items: center;">
                        <div id="md-avatar-container" style="width: 78px; height: 78px; border-radius: 50%; border: 1px solid var(--border-color); overflow: hidden; display: flex; align-items: center; justify-content: center; background: rgba(255,255,255,0.02);"></div>
                        <div>
                            <h2 id="md-member-name" style="margin: 0 0 4px;">—</h2>
                            <div id="md-member-phone" style="color: var(--text-secondary); font-size: 14px;">—</div>
                            <div id="md-member-whatsapp-container" style="display:none; color: var(--accent-green); font-size: 13px;"><span id="md-member-whatsapp">—</span></div>
                            <div style="display: flex; gap: 12px; margin-top: 4px; font-size: 13px; color: var(--text-muted);">
                                <span>الجنس: <strong id="md-member-gender">—</strong></span>
                                <span>المتبقي: <strong id="md-total-remaining-debt">0 ₪</strong></span>
                            </div>
                        </div>
                    </div>
                    <div style="text-align: center;">
                        <img id="md-qr-code" src="" alt="QR" style="width: 100px; height: 100px; border-radius: 8px; border: 1px solid var(--border-color);">
                        <div id="md-qr-label" class="val-mono" style="margin-top: 6px; color: var(--text-secondary);">—</div>
                    </div>
                </div>
            </div>

            <div class="card" style="margin-bottom: 16px; padding: 0; overflow: hidden;">
                <div style="display:flex; gap:0; border-bottom: 1px solid var(--border-color);">
                    <button class="md-tab-btn active" onclick="switchDetailTab('subscriptions', this)">الاشتراكات</button>
                    <button class="md-tab-btn" onclick="switchDetailTab('measurements', this)">القياسات</button>
                    <button class="md-tab-btn" onclick="switchDetailTab('payments', this)">المدفوعات</button>
                    <button class="md-tab-btn" onclick="switchDetailTab('programs', this)">البرامج</button>
                </div>

                <div id="md-tab-subscriptions" class="md-tab-panel" style="display:block; padding: 16px;">
                    <div id="md-subscriptions-card-container"></div>
                </div>

                <div id="md-tab-measurements" class="md-tab-panel" style="display:none; padding: 16px;">
                    <div class="table-responsive">
                        <table class="custom-table">
                            <thead><tr><th>التاريخ</th><th>الوزن</th><th>الطول</th><th>نسبة الدهون</th><th>الكتلة العضلية</th></tr></thead>
                            <tbody id="md-measurements-table-body"></tbody>
                        </table>
                    </div>
                </div>

                <div id="md-tab-payments" class="md-tab-panel" style="display:none; padding: 16px;">
                    <div class="table-responsive">
                        <table class="custom-table">
                            <thead><tr><th>التاريخ</th><th>المبلغ</th><th>الطريقة</th><th>الوصف</th></tr></thead>
                            <tbody id="md-payments-table-body"></tbody>
                        </table>
                    </div>
                </div>

                <div id="md-tab-programs" class="md-tab-panel" style="display:none; padding: 16px;">
                    <div class="table-responsive">
                        <table class="custom-table">
                            <thead><tr><th>النوع</th><th>المدرب</th><th>العنوان</th><th>الهدف</th><th>المحتوى</th></tr></thead>
                            <tbody id="md-programs-table-body"></tbody>
                        </table>
                    </div>
                </div>
            </div>
        `,
        "trainer-portal": `
            <div class="card" style="margin-bottom: 16px;">
                <h3 style="margin-bottom: 6px;">المشتركين المرتبطين بالمدرب</h3>
                <div id="trainer-portal-meta" style="color: var(--text-muted); font-size: 13px;">—</div>
            </div>
            <div class="card table-responsive">
                <table class="custom-table">
                    <thead>
                        <tr>
                            <th>المشترك</th>
                            <th>الجوال</th>
                            <th>الخطة الأحدث</th>
                            <th>تاريخ البداية</th>
                            <th>تاريخ النهاية</th>
                            <th>حالة الاشتراك</th>
                            <th>ملاحظة الربط</th>
                        </tr>
                    </thead>
                    <tbody id="trainer-portal-members-body"></tbody>
                </table>
            </div>
            <div class="card" style="margin-top: 16px; margin-bottom: 16px;">
                <div style="display: flex; gap: 8px; flex-wrap: wrap;">
                    <button class="btn btn-primary" onclick="openModal('modal-add-training-program')">إضافة برنامج تدريبي</button>
                    <button class="btn btn-secondary" onclick="openModal('modal-add-nutrition-program')">إضافة برنامج غذائي</button>
                </div>
            </div>
            <div class="card table-responsive" style="margin-top: 16px;">
                <h3 style="margin-bottom: 10px;">برامجي التدريبية والغذائية</h3>
                <table class="custom-table">
                    <thead>
                        <tr>
                            <th>النوع</th>
                            <th>المشترك</th>
                            <th>العنوان</th>
                            <th>الهدف</th>
                            <th>المحتوى</th>
                            <th>الإجراءات</th>
                        </tr>
                    </thead>
                    <tbody id="trainer-portal-programs-body"></tbody>
                </table>
            </div>
        `,
        "activity-log": `<div class="card table-responsive"><table class="custom-table"><thead><tr><th>الوقت</th><th>المستخدم</th><th>الدور</th><th>الحركة</th><th>التفاصيل</th></tr></thead><tbody id="activity-log-table-body"></tbody></table></div>`,
        "global-search": `
            <div class="card" style="margin-bottom: 16px;">
                <div style="display:grid; grid-template-columns: 2fr 1fr 1fr 1fr 1fr; gap:8px; align-items:center;">
                    <input id="gsearch-input" class="form-control" type="text" placeholder="اكتب كلمة البحث الشامل...">
                    <select id="gsearch-category" class="form-control"></select>
                    <input id="gsearch-start-date" type="date" class="form-control">
                    <input id="gsearch-end-date" type="date" class="form-control">
                    <select id="gsearch-status" class="form-control">
                        <option value="all">كل الحالات</option>
                        <option value="active">فعال</option>
                        <option value="frozen">مجمد</option>
                        <option value="expired">منتهي</option>
                    </select>
                </div>
            </div>
            <div id="search-revenue-summary" class="metrics-grid" style="margin-bottom: 16px; display:none;">
                <div class="card metric-card"><span>اليوم</span><strong id="search-rev-daily">0 ₪</strong><small id="search-rev-daily-detail"></small></div>
                <div class="card metric-card"><span>الأسبوع</span><strong id="search-rev-weekly">0 ₪</strong><small id="search-rev-weekly-detail"></small></div>
                <div class="card metric-card"><span>الشهر</span><strong id="search-rev-monthly">0 ₪</strong><small id="search-rev-monthly-detail"></small></div>
                <div class="card metric-card"><span>السنة</span><strong id="search-rev-yearly">0 ₪</strong><small id="search-rev-yearly-detail"></small></div>
            </div>
            <div id="gsearch-results-container"></div>
        `,
        import: `
            <div class="card">
                <div id="import-dropzone" style="padding: 16px; border: 1px dashed var(--border-color); border-radius: 12px; margin-bottom: 12px; text-align: center; color: var(--text-secondary);">
                    اسحب الملف هنا أو اختره من الحقل أدناه
                </div>
                <input id="import-file-input" type="file" class="form-control" accept=".xlsx,.xls,.csv" onchange="onImportFileSelected(event)">
                <div id="import-summary-container" style="display:none; margin-top: 12px;">
                    <div class="card" style="margin-bottom: 12px;">
                        <div id="import-filename-label" style="font-weight: 700; margin-bottom: 8px;">—</div>
                        <div style="display: flex; gap: 12px; flex-wrap: wrap;">
                            <span>مشتركين: <strong id="import-count-members">0</strong></span>
                            <span>اشتراكات: <strong id="import-count-subscriptions">0</strong></span>
                            <span>مدفوعات: <strong id="import-count-payments">0</strong></span>
                        </div>
                    </div>
                    <div class="card" style="margin-bottom: 10px;">
                        <div style="display:flex; gap: 8px; flex-wrap: wrap; margin-bottom: 10px;">
                            <button class="import-tab-btn active" onclick="switchImportPreviewTab('members', this)">المشتركون</button>
                            <button class="import-tab-btn" onclick="switchImportPreviewTab('subscriptions', this)">الاشتراكات</button>
                            <button class="import-tab-btn" onclick="switchImportPreviewTab('payments', this)">المدفوعات</button>
                        </div>
                        <div id="import-preview-panel-members" class="import-preview-panel">
                            <div class="table-responsive"><table class="custom-table"><thead><tr><th>الرقم</th><th>الاسم</th><th>الجوال</th><th>واتساب</th><th>الجنس</th></tr></thead><tbody id="import-preview-body-members"></tbody></table></div>
                        </div>
                        <div id="import-preview-panel-subscriptions" class="import-preview-panel" style="display:none;">
                            <div class="table-responsive"><table class="custom-table"><thead><tr><th>رقم العضوية</th><th>الخطة</th><th>القيمة</th><th>المدفوع</th><th>المتبقي</th><th>الحالة</th><th>البدء</th><th>الانتهاء</th></tr></thead><tbody id="import-preview-body-subscriptions"></tbody></table></div>
                        </div>
                        <div id="import-preview-panel-payments" class="import-preview-panel" style="display:none;">
                            <div class="table-responsive"><table class="custom-table"><thead><tr><th>الاسم</th><th>المبلغ</th><th>الطريقة</th><th>التاريخ</th><th>ملاحظة</th></tr></thead><tbody id="import-preview-body-payments"></tbody></table></div>
                        </div>
                    </div>
                    <button id="btn-execute-import" class="btn btn-primary" onclick="submitSmartImport()">
                        <i data-lucide="check"></i><span>بدء الاستيراد الذكي وتصفية المكررات</span>
                    </button>
                </div>
            </div>
        `,
    };

    Object.entries(viewTemplates).forEach(([viewName, html]) => {
        const panelId = `view-${viewName}`;
        const existingPanel = document.getElementById(panelId);
        if (existingPanel) {
            existingPanel.innerHTML = html;
        } else {
            const panel = document.createElement("div");
            panel.id = panelId;
            panel.className = "view-panel";
            panel.innerHTML = html;
            mainContent.appendChild(panel);
        }
    });
}

// 2. State & Sync Controller
function loadStateAndRender(viewName) {
    // Show a loading text or indicator where necessary
    fetch(resolveApiUrl('get_state'))
        .then(async response => {
            const data = await response.json();
            return { ok: response.ok, status: response.status, data };
        })
        .then(({ ok, status, data }) => {
            if (status === 401 || (!ok && data && data.error === 'يجب تسجيل الدخول أولاً')) {
                state.currentUser = null;
                state.memberData = null;
                showLoginScreen();
                return;
            }

            const res = data;
            if (res.success) {
                // Sync backend data into local state object
                Object.assign(state, res.data);
                
                if (state.currentUser) {
                    updateProfileSidebar(state.currentUser);
                    applyRolePermissions(state.currentUser.role);
                }

                updateTenantHeader(state.tenant || null);
                
                // Recalculate frontend-only ratios
                calculateRenewalRate();
                
                // Route rendering to active view
                triggerViewRenderer(viewName);
                
                // Refresh modal select options
                populateDropdowns();
            } else {
                console.error("Failed to load state:", res.error);
                showAppNotice("حدث خطأ أثناء تحميل البيانات من قاعدة البيانات: " + res.error, 'error', 5200);
            }
        })
        .catch(err => {
            console.error("API error:", err);
        });
}

function calculateRenewalRate() {
    let activeSubs = state.subscriptions.filter(s => s.status === "فعال").length;
    let expiredSubs = state.subscriptions.filter(s => s.status === "منتهي").length;
    let totalActiveExpired = activeSubs + expiredSubs;
    state.renewalRate = totalActiveExpired > 0 ? (activeSubs / totalActiveExpired) * 100 : 0;
    
    // Update renewal rate in DOM
    const renRateEl = document.getElementById("rep-renewal-rate");
    if (renRateEl) renRateEl.innerText = state.renewalRate.toFixed(1) + "%";
}

function switchView(viewName) {
    currentView = viewName;
    updateViewQueryParams(viewName);
    
    // Manage sidebar active links visual state
    const navLinks = document.querySelectorAll(".nav-link");
    navLinks.forEach(link => {
        if (link.getAttribute("data-view") === viewName) {
            link.classList.add("active");
        } else {
            link.classList.remove("active");
        }
    });

    // Manage view panels visibility
    const views = document.querySelectorAll(".view-panel");
    views.forEach(view => {
        if (view.id === `view-${viewName}`) {
            view.classList.add("active");
        } else {
            view.classList.remove("active");
        }
    });

    // Update Header Tag, Title, and Description
    const headerTag = document.getElementById("header-tag");
    const headerTitle = document.getElementById("header-title");
    const headerDesc = document.getElementById("header-desc");

    const headerConfigs = {
        dashboard: { tag: "لوحة التحكم", title: "لوحة التحكم", desc: "نظرة شاملة على نشاط الصالة اليوم" },
        "check-in": { tag: "تسجيل الحضور", title: "تسجيل الحضور", desc: "تسجيل حضور المشتركين اليومي" },
        members: { tag: "المشتركون", title: "المشتركون", desc: "إدارة جميع مشتركي الصالة" },
        subscriptions: { tag: "الاشتراكات", title: "الاشتراكات", desc: "تفاصيل فترات اشتراك الأعضاء" },
        plans: { tag: "الخطط", title: "خطط الاشتراك", desc: "إدارة باقات وتكلفة الاشتراكات" },
        payments: { tag: "المدفوعات", title: "المدفوعات", desc: "سجل الإيرادات والتحصيل المالي" },
        products: { tag: "المنتجات", title: "المنتجات", desc: "المنتجات والمكملات" },
        reports: { tag: "التقارير", title: "التقارير", desc: "مؤشرات الأداء التشغيلي والمالي" },
        notifications: { tag: "الإشعارات", title: "الإشعارات", desc: "إدارة القوالب وسجل الإرسال وتنبيهات الاشتراكات" },
        "sync-center": { tag: "مركز المزامنة", title: "مركز المزامنة", desc: "مراقبة الطابور المحلي وسجل مزامنة الخادم" },
        "sync-event-detail": { tag: "تفاصيل المزامنة", title: "تفاصيل حدث المزامنة", desc: "تفاصيل الحدث والحمولة ونتيجة المعالجة" },
        trainers: { tag: "المدربون", title: "المدربين", desc: "إدارة ملفات المدربين وربطهم بالمشتركين" },
        users: { tag: "المستخدمون", title: "إدارة المستخدمين", desc: "إدارة مستخدمي النظام والصلاحيات" },
        "activity-log": { tag: "سجل الحركات", title: "سجل الحركات", desc: "سجل الحركات اليومية على النظام" },
        import: { tag: "الاستيراد", title: "الاستيراد الذكي", desc: "استيراد المشتركين والاشتراكات والمدفوعات من ملف إكسل وتصفية المكررات" },
        "member-portal": { tag: "بوابة المشترك", title: "بوابة المشترك", desc: "استعراض اشتراكاتك ومدفوعاتك ومشترياتك" },
        "trainer-portal": { tag: "بوابة المدرب", title: "بوابة المدرب", desc: "المشتركين المرتبطين بهذا المدرب" },
        "member-detail": { tag: "الملف الشخصي", title: "الملف الشخصي للمشترك", desc: "تفاصيل ملف العضوية والقياسات والبرامج المالية" },
        "global-search": { tag: "البحث الشامل", title: "الاستعلام الشامل", desc: "استعلام متقدم وبحث شامل في السجلات والتقارير المالية" }
    };

    if (headerConfigs[viewName] && headerTag && headerTitle && headerDesc) {
        headerTag.innerText = headerConfigs[viewName].tag;
        headerTitle.innerText = headerConfigs[viewName].title;
        headerDesc.innerText = headerConfigs[viewName].desc;
    }

    // Portal views can render from locally cached role data.
    if (viewName === 'member-portal' || viewName === 'trainer-portal') {
        const hasPortalData = viewName === 'member-portal' ? !!state.memberData : !!state.trainerData;
        if (hasPortalData) {
            triggerViewRenderer(viewName);
        } else {
            // Fetch state which contains role portal data.
            loadStateAndRender(viewName);
        }
    } else {
        // Load state from DB and then render the panel
        loadStateAndRender(viewName);
    }
}

function updateViewQueryParams(viewName) {
    if (!window.history || !window.location) return;

    const url = new URL(window.location.href);
    url.searchParams.delete('view');
    url.searchParams.delete('syncEventId');

    if (viewName === 'sync-center' || viewName === 'sync-event-detail') {
        url.searchParams.set('view', viewName);
    }

    if (viewName === 'sync-event-detail' && state.selectedSyncEventId) {
        url.searchParams.set('syncEventId', state.selectedSyncEventId);
    }

    window.history.replaceState({}, '', url.toString());
}

function resolveInitialViewForRole(role) {
    const defaultView = getDefaultViewForRole(role);
    const params = new URLSearchParams(window.location.search || '');
    const requestedView = (params.get('view') || '').trim();
    const syncEventId = (params.get('syncEventId') || '').trim();

    if (requestedView === 'sync-event-detail' && syncEventId !== '') {
        state.selectedSyncEventId = syncEventId;
    }

    if (requestedView && isViewAllowed(requestedView)) {
        return requestedView;
    }

    return defaultView;
}

function triggerViewRenderer(viewName) {
    switch (viewName) {
        case "dashboard":
            renderDashboard();
            break;
        case "check-in":
            renderCheckIn();
            break;
        case "members":
            renderMembers();
            break;
        case "subscriptions":
            renderSubscriptions();
            break;
        case "plans":
            renderPlans();
            break;
        case "payments":
            renderPayments();
            break;
        case "products":
            renderProducts();
            break;
        case "reports":
            renderReports();
            break;
        case "notifications":
            renderNotifications();
            break;
        case "sync-center":
            renderSyncCenter();
            break;
        case "sync-event-detail":
            renderSyncEventDetailPage();
            break;
        case "trainers":
            renderTrainers();
            break;
        case "users":
            renderUsers();
            break;
        case "tenant-management":
            renderTenantManagement();
            break;
        case "activity-log":
            renderActivityLog();
            break;
        case "import":
            renderImportView();
            break;
        case "member-portal":
            if (state.memberData) {
                renderMemberPortal(state.memberData);
            }
            break;
        case "trainer-portal":
            if (state.trainerData) {
                renderTrainerPortal(state.trainerData);
            }
            break;
        case "member-detail":
            if (state.selectedMemberId) {
                renderMemberDetail(state.selectedMemberId);
            }
            break;
        case "global-search":
            renderGlobalSearch();
            break;
    }
    
    // Re-bind Lucide SVG icons
    lucide.createIcons();
}

// 3. Modals and Dialog Controller
function createModalShell(modalId, title, bodyHtml) {
    const modal = document.createElement("div");
    modal.id = modalId;
    modal.className = "modal-overlay";
    modal.innerHTML = `
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">${title}</h3>
                <button type="button" class="btn-close-modal" onclick="closeModal('${modalId}')">✕</button>
            </div>
            ${bodyHtml}
        </div>
    `;
    document.body.appendChild(modal);
    return modal;
}

function ensureFallbackModal(modalId) {
    const existingModal = document.getElementById(modalId);

    const map = {
        "modal-change-password": {
            title: "تغيير كلمة المرور",
            body: `
                <form id="form-change-password" onsubmit="submitChangePassword(event)">
                    <div class="form-group"><label class="form-label">كلمة المرور الحالية</label><input id="cp-input-current" class="form-control" type="password" required></div>
                    <div class="form-group"><label class="form-label">كلمة المرور الجديدة</label><input id="cp-input-new" class="form-control" type="password" required></div>
                    <div class="form-group"><label class="form-label">تأكيد كلمة المرور الجديدة</label><input id="cp-input-confirm" class="form-control" type="password" required></div>
                    <div class="modal-actions"><button class="btn btn-secondary" type="button" onclick="closeModal('modal-change-password')">إلغاء</button><button class="btn btn-primary" type="submit">حفظ</button></div>
                </form>
            `,
        },
        "modal-edit-subscription": {
            title: "تعديل الاشتراك",
            body: `
                <form id="form-edit-subscription" onsubmit="submitEditSubscription(event)">
                    <input id="edit-sub-id" type="hidden">
                    <div class="form-group"><label class="form-label">المشترك</label><input id="edit-sub-member-name" class="form-control" type="text" readonly></div>
                    <div class="form-group"><label class="form-label">الخطة</label><input id="edit-sub-plan-name" class="form-control" type="text" required></div>
                    <div class="form-group"><label class="form-label">تاريخ البدء</label><input id="edit-sub-start-date" class="form-control" type="date" required></div>
                    <div class="form-group"><label class="form-label">تاريخ الانتهاء</label><input id="edit-sub-end-date" class="form-control" type="date" required></div>
                    <div class="form-group"><label class="form-label">المبلغ</label><input id="edit-sub-amount" class="form-control" type="number" step="0.01" required></div>
                    <div class="form-group"><label class="form-label">المدفوع</label><input id="edit-sub-paid" class="form-control" type="number" step="0.01" required></div>
                    <div class="form-group"><label class="form-label">الحالة</label><select id="edit-sub-status" class="form-control"><option>فعال</option><option>مجمد</option><option>منتهي</option></select></div>
                    <div class="modal-actions"><button class="btn btn-secondary" type="button" onclick="closeModal('modal-edit-subscription')">إلغاء</button><button class="btn btn-primary" type="submit">حفظ</button></div>
                </form>
            `,
        },
        "modal-edit-payment": {
            title: "تعديل الدفعة",
            body: `
                <form id="form-edit-payment" onsubmit="submitEditPayment(event)">
                    <input id="edit-pay-id" type="hidden">
                    <div class="form-group"><label class="form-label">المشترك</label><input id="edit-pay-member-name" class="form-control" type="text" readonly></div>
                    <div class="form-group"><label class="form-label">التاريخ</label><input id="edit-pay-date" class="form-control" type="text" required></div>
                    <div class="form-group"><label class="form-label">المبلغ</label><input id="edit-pay-amount" class="form-control" type="number" step="0.01" required></div>
                    <div class="form-group"><label class="form-label">الطريقة</label><select id="edit-pay-method" class="form-control" onchange="togglePaymentTransferAccountField('edit-pay-method','edit-pay-transfer-account-group','edit-pay-transfer-from-account')"><option>نقدي</option><option>تحويل</option></select></div>
                    <div id="edit-pay-transfer-account-group" class="form-group" style="display:none;"><label class="form-label">اسم الشخص أو الحساب المُحوِّل</label><input id="edit-pay-transfer-from-account" class="form-control" type="text" placeholder="مثال: أحمد محمد أو حساب البنك الأهلي"></div>
                    <div class="form-group"><label class="form-label">ملاحظة</label><input id="edit-pay-note" class="form-control" type="text"></div>
                    <div class="modal-actions"><button class="btn btn-secondary" type="button" onclick="closeModal('modal-edit-payment')">إلغاء</button><button class="btn btn-primary" type="submit">حفظ</button></div>
                </form>
            `,
        },
        "modal-edit-member": {
            title: "تعديل بيانات المشترك",
            body: `
                <form id="form-edit-member" onsubmit="submitEditMember(event)">
                    <input id="edit-member-input-id" type="hidden">
                    <div class="form-group"><label class="form-label">الاسم</label><input id="edit-member-input-name" class="form-control" type="text" required></div>
                    <div class="form-group"><label class="form-label">الجوال</label><input id="edit-member-input-phone" class="form-control" type="text" required inputmode="numeric" pattern="\\d{10}" maxlength="10" title="رقم الجوال يجب أن يكون 10 أرقام" ></div>
                    <div class="form-group"><label class="form-label">الجنس</label><select id="edit-member-input-gender" class="form-control"><option>ذكر</option><option>أنثى</option></select></div>
                    <div class="form-group"><label class="form-label">واتساب</label><input id="edit-member-input-whatsapp" class="form-control" type="text" inputmode="numeric" pattern="\\d{14}" maxlength="14" title="رقم الواتساب يجب أن يكون 14 رقمًا مثل 00972599466856"></div>
                    <div class="form-group"><label class="form-label">الصورة</label><input id="edit-member-input-image" class="form-control" type="file" accept="image/*" onchange="previewEditImage(event)"></div>
                    <div id="edit-member-image-preview-container" style="width:60px; height:60px; border-radius:50%; overflow:hidden; display:flex; align-items:center; justify-content:center; margin-bottom:8px;"></div>
                    <div class="modal-actions"><button class="btn btn-secondary" type="button" onclick="closeModal('modal-edit-member')">إلغاء</button><button class="btn btn-primary" type="submit">حفظ</button></div>
                </form>
            `,
        },
        "modal-add-member": {
            title: "إضافة مشترك",
            body: `
                <form id="form-add-member" onsubmit="submitAddMember(event)">
                    <div class="form-group"><label class="form-label">الاسم</label><input id="member-input-name" class="form-control" type="text" required></div>
                    <div class="form-group"><label class="form-label">الجوال</label><input id="member-input-phone" class="form-control" type="text" required inputmode="numeric" pattern="\\d{10}" maxlength="10" title="رقم الجوال يجب أن يكون 10 أرقام" ></div>
                    <div class="form-group"><label class="form-label">الجنس</label><select id="member-input-gender" class="form-control"><option>ذكر</option><option>أنثى</option></select></div>
                    <div class="form-group"><label class="form-label">واتساب</label><input id="member-input-whatsapp" class="form-control" type="text" inputmode="numeric" pattern="\\d{14}" maxlength="14" title="رقم الواتساب يجب أن يكون 14 رقمًا مثل 00972599466856"></div>
                    <div class="form-group"><label class="form-label">الصورة</label><input id="member-input-image" class="form-control" type="file" accept="image/*"></div>
                    <div class="modal-actions"><button class="btn btn-secondary" type="button" onclick="closeModal('modal-add-member')">إلغاء</button><button class="btn btn-primary" type="submit">حفظ</button></div>
                </form>
            `,
        },
        "modal-add-subscription": {
            title: "إضافة اشتراك",
            body: `
                <form id="form-add-subscription" onsubmit="submitAddSubscription(event)">
                    <div class="form-group"><label class="form-label">المشترك</label><select id="sub-input-member" class="form-control" required></select></div>
                    <div class="form-group"><label class="form-label">الخطة</label><select id="sub-input-plan" class="form-control" onchange="updateSubPrice()"></select></div>
                    <div class="form-group"><label class="form-label">تاريخ البدء</label><input id="sub-input-start" class="form-control" type="date" required></div>
                    <div class="form-group"><label class="form-label">المدفوع</label><input id="sub-input-paid" class="form-control" type="number" step="0.01" required></div>
                    <div class="modal-actions"><button class="btn btn-secondary" type="button" onclick="closeModal('modal-add-subscription')">إلغاء</button><button class="btn btn-primary" type="submit">حفظ</button></div>
                </form>
            `,
        },
        "modal-add-plan": {
            title: "إضافة خطة",
            body: `
                <form id="form-add-plan" onsubmit="submitAddPlan(event)">
                    <div class="form-group"><label class="form-label">اسم الخطة</label><input id="plan-input-name" class="form-control" type="text" required></div>
                    <div class="form-group"><label class="form-label">الوصف</label><input id="plan-input-desc" class="form-control" type="text"></div>
                    <div class="form-group"><label class="form-label">السعر</label><input id="plan-input-price" class="form-control" type="number" step="0.01" required></div>
                    <div class="form-group"><label class="form-label">الأيام</label><input id="plan-input-days" class="form-control" type="number" required></div>
                    <div class="modal-actions"><button class="btn btn-secondary" type="button" onclick="closeModal('modal-add-plan')">إلغاء</button><button class="btn btn-primary" type="submit">حفظ</button></div>
                </form>
            `,
        },
        "modal-edit-plan": {
            title: "تعديل الخطة",
            body: `
                <form id="form-edit-plan" onsubmit="submitEditPlan(event)">
                    <input id="edit-plan-input-id" type="hidden">
                    <div class="form-group"><label class="form-label">اسم الخطة</label><input id="edit-plan-input-name" class="form-control" type="text" required></div>
                    <div class="form-group"><label class="form-label">الوصف</label><input id="edit-plan-input-desc" class="form-control" type="text"></div>
                    <div class="form-group"><label class="form-label">السعر</label><input id="edit-plan-input-price" class="form-control" type="number" step="0.01" required></div>
                    <div class="form-group"><label class="form-label">الأيام</label><input id="edit-plan-input-days" class="form-control" type="number" required></div>
                    <div class="modal-actions"><button class="btn btn-secondary" type="button" onclick="closeModal('modal-edit-plan')">إلغاء</button><button class="btn btn-primary" type="submit">حفظ</button></div>
                </form>
            `,
        },
        "modal-add-payment": {
            title: "إضافة دفعة",
            body: `
                <form id="form-add-payment" onsubmit="submitAddPayment(event)">
                    <div class="form-group"><label class="form-label">المشترك</label><select id="pay-input-member" class="form-control" required></select></div>
                    <div class="form-group"><label class="form-label">المبلغ</label><input id="pay-input-amount" class="form-control" type="number" step="0.01" required></div>
                    <div class="form-group"><label class="form-label">الطريقة</label><select id="pay-input-method" class="form-control" onchange="togglePaymentTransferAccountField('pay-input-method','pay-transfer-account-group','pay-input-transfer-from-account')"><option>نقدي</option><option>تحويل</option></select></div>
                    <div id="pay-transfer-account-group" class="form-group" style="display:none;"><label class="form-label">اسم الشخص أو الحساب المُحوِّل</label><input id="pay-input-transfer-from-account" class="form-control" type="text" placeholder="مثال: أحمد محمد أو حساب البنك الأهلي"></div>
                    <div class="form-group"><label class="form-label">ملاحظة</label><input id="pay-input-note" class="form-control" type="text"></div>
                    <div class="modal-actions"><button class="btn btn-secondary" type="button" onclick="closeModal('modal-add-payment')">إلغاء</button><button class="btn btn-primary" type="submit">حفظ</button></div>
                </form>
            `,
        },
        "modal-add-product": {
            title: "إضافة منتج",
            body: `
                <form id="form-add-product" onsubmit="submitAddProduct(event)">
                    <div class="form-group"><label class="form-label">اسم المنتج</label><input id="product-input-name" class="form-control" type="text" required></div>
                    <div class="form-group"><label class="form-label">السعر</label><input id="product-input-price" class="form-control" type="number" step="0.01" required></div>
                    <div class="form-group"><label class="form-label">الكمية</label><input id="product-input-stock" class="form-control" type="number" required></div>
                    <div class="modal-actions"><button class="btn btn-secondary" type="button" onclick="closeModal('modal-add-product')">إلغاء</button><button class="btn btn-primary" type="submit">حفظ</button></div>
                </form>
            `,
        },
        "modal-edit-product-stock": {
            title: "تعديل المنتج والمخزون",
            body: `
                <form id="form-edit-product-stock" onsubmit="submitEditProductStock(event)">
                    <input id="edit-product-stock-id" type="hidden">
                    <div class="form-group"><label class="form-label">اسم المنتج</label><input id="edit-product-stock-name" class="form-control" type="text" required></div>
                    <div class="form-group"><label class="form-label">السعر</label><input id="edit-product-stock-price" class="form-control" type="number" step="0.01" required></div>
                    <div class="form-group"><label class="form-label">الكمية</label><input id="edit-product-stock-qty" class="form-control" type="number" required></div>
                    <div class="modal-actions"><button class="btn btn-secondary" type="button" onclick="closeModal('modal-edit-product-stock')">إلغاء</button><button class="btn btn-primary" type="submit">حفظ</button></div>
                </form>
            `,
        },
        "modal-add-user": {
            title: "إضافة مستخدم",
            body: `
                <form id="form-add-user" onsubmit="submitAddUser(event)">
                    <div class="form-group"><label class="form-label">الاسم</label><input id="user-input-name" class="form-control" type="text" required></div>
                    <div class="form-group"><label class="form-label">اسم المستخدم</label><input id="user-input-username" class="form-control" type="text" required></div>
                    <div class="form-group"><label class="form-label">كلمة المرور</label><input id="user-input-password" class="form-control" type="password" required></div>
                    <div class="form-group"><label class="form-label">الدور</label><select id="user-input-role" class="form-control" onchange="toggleAddUserMemberSelect()"><option>مدير النظام</option><option>موظف الاستقبال</option><option>المحاسب</option><option>المدقق المالي</option><option>مدرب</option><option>مشترك</option></select></div>
                    <div id="add-user-member-group" class="form-group" style="display:none;"><label class="form-label">ربط بعضو</label><select id="user-input-member-id" class="form-control"></select></div>
                    <div class="modal-actions"><button class="btn btn-secondary" type="button" onclick="closeModal('modal-add-user')">إلغاء</button><button class="btn btn-primary" type="submit">حفظ</button></div>
                </form>
            `,
        },
        "modal-add-trainer-profile": {
            title: "إضافة ملف مدرب",
            body: `
                <form id="form-add-trainer-profile" onsubmit="submitAddTrainerProfile(event)">
                    <div class="form-group"><label class="form-label">اسم المدرب</label><input id="trainer-input-name" class="form-control" type="text" placeholder="مثال: أحمد علي"></div>
                    <div class="form-group"><label class="form-label">معرف المستخدم (اختياري)</label><input id="trainer-input-user-id" class="form-control" type="number" min="1" placeholder="مثال: 5"></div>
                    <div class="form-group" style="color: var(--text-muted); font-size: 12px; margin-top: -8px;">إذا لم تدخل معرف مستخدم، سيتم إنشاء حساب مدرب تلقائيا من الاسم.</div>
                    <div class="form-group"><label class="form-label">التخصص</label><input id="trainer-input-specialty" class="form-control" type="text" placeholder="مثال: قوة ولياقة"></div>
                    <div class="form-group"><label class="form-label">نبذة</label><textarea id="trainer-input-bio" class="form-control" rows="3" placeholder="نبذة مختصرة"></textarea></div>
                    <div class="form-group"><label class="form-label">نسبة العمولة</label><input id="trainer-input-commission" class="form-control" type="number" min="0" max="100" step="0.01" value="0"></div>
                    <div class="form-group"><label class="form-label">الحالة</label><select id="trainer-input-status" class="form-control"><option value="active">نشط</option><option value="inactive">غير نشط</option></select></div>
                    <div class="modal-actions"><button class="btn btn-secondary" type="button" onclick="closeModal('modal-add-trainer-profile')">إلغاء</button><button class="btn btn-primary" type="submit">حفظ</button></div>
                </form>
            `,
        },
        "modal-assign-trainer-member": {
            title: "ربط مدرب بمشترك",
            body: `
                <form id="form-assign-trainer-member" onsubmit="submitAssignTrainerMember(event)">
                    <div class="form-group"><label class="form-label">المدرب</label><select id="assign-trainer-id" class="form-control" required></select></div>
                    <div class="form-group"><label class="form-label">المشترك</label><select id="assign-member-id" class="form-control" required onchange="syncAssignDatesFromMember()"></select></div>
                    <div class="form-group"><label class="form-label">تاريخ البداية</label><input id="assign-start-date" class="form-control" type="date" required></div>
                    <div class="form-group"><label class="form-label">تاريخ النهاية</label><input id="assign-end-date" class="form-control" type="date"></div>
                    <div class="form-group"><label class="form-label">الحالة</label><select id="assign-status" class="form-control"><option value="active">نشط</option><option value="inactive">غير نشط</option><option value="completed">مكتمل</option></select></div>
                    <div class="form-group"><label class="form-label">ملاحظة</label><input id="assign-note" class="form-control" type="text" placeholder="ملاحظة اختيارية"></div>
                    <div class="modal-actions"><button class="btn btn-secondary" type="button" onclick="closeModal('modal-assign-trainer-member')">إلغاء</button><button class="btn btn-primary" type="submit">حفظ</button></div>
                </form>
            `,
        },
        "modal-add-training-program": {
            title: "إضافة برنامج تدريبي",
            body: `
                <form id="form-add-training-program" onsubmit="submitAddTrainingProgram(event)">
                    <div class="form-group"><label class="form-label">الربط (مدرب - مشترك)</label><select id="training-program-assignment-id" class="form-control" required></select></div>
                    <div class="form-group"><label class="form-label">العنوان</label><input id="training-program-title" class="form-control" type="text" required></div>
                    <div class="form-group"><label class="form-label">الهدف</label><input id="training-program-goal" class="form-control" type="text" placeholder="هدف البرنامج"></div>
                    <div class="form-group"><label class="form-label">المحتوى</label><textarea id="training-program-content" class="form-control" rows="4" placeholder="يمكن كتابة ملاحظات أو JSON"></textarea></div>
                    <div class="modal-actions"><button class="btn btn-secondary" type="button" onclick="closeModal('modal-add-training-program')">إلغاء</button><button class="btn btn-primary" type="submit">حفظ</button></div>
                </form>
            `,
        },
        "modal-edit-training-program": {
            title: "تعديل برنامج تدريبي",
            body: `
                <form id="form-edit-training-program" onsubmit="submitEditTrainingProgram(event)">
                    <input id="edit-training-program-id" type="hidden">
                    <div class="form-group"><label class="form-label">الربط (مدرب - مشترك)</label><select id="edit-training-program-assignment-id" class="form-control" required></select></div>
                    <div class="form-group"><label class="form-label">العنوان</label><input id="edit-training-program-title" class="form-control" type="text" required></div>
                    <div class="form-group"><label class="form-label">الهدف</label><input id="edit-training-program-goal" class="form-control" type="text"></div>
                    <div class="form-group"><label class="form-label">المحتوى</label><textarea id="edit-training-program-content" class="form-control" rows="4"></textarea></div>
                    <div class="modal-actions"><button class="btn btn-secondary" type="button" onclick="closeModal('modal-edit-training-program')">إلغاء</button><button class="btn btn-primary" type="submit">حفظ</button></div>
                </form>
            `,
        },
        "modal-add-nutrition-program": {
            title: "إضافة برنامج غذائي",
            body: `
                <form id="form-add-nutrition-program" onsubmit="submitAddNutritionProgram(event)">
                    <div class="form-group"><label class="form-label">الربط (مدرب - مشترك)</label><select id="nutrition-program-assignment-id" class="form-control" required></select></div>
                    <div class="form-group"><label class="form-label">العنوان</label><input id="nutrition-program-title" class="form-control" type="text" required></div>
                    <div class="form-group"><label class="form-label">الهدف</label><input id="nutrition-program-goal" class="form-control" type="text" placeholder="هدف البرنامج"></div>
                    <div class="form-group"><label class="form-label">المحتوى</label><textarea id="nutrition-program-content" class="form-control" rows="4" placeholder="يمكن كتابة ملاحظات أو JSON"></textarea></div>
                    <div class="modal-actions"><button class="btn btn-secondary" type="button" onclick="closeModal('modal-add-nutrition-program')">إلغاء</button><button class="btn btn-primary" type="submit">حفظ</button></div>
                </form>
            `,
        },
        "modal-edit-nutrition-program": {
            title: "تعديل برنامج غذائي",
            body: `
                <form id="form-edit-nutrition-program" onsubmit="submitEditNutritionProgram(event)">
                    <input id="edit-nutrition-program-id" type="hidden">
                    <div class="form-group"><label class="form-label">الربط (مدرب - مشترك)</label><select id="edit-nutrition-program-assignment-id" class="form-control" required></select></div>
                    <div class="form-group"><label class="form-label">العنوان</label><input id="edit-nutrition-program-title" class="form-control" type="text" required></div>
                    <div class="form-group"><label class="form-label">الهدف</label><input id="edit-nutrition-program-goal" class="form-control" type="text"></div>
                    <div class="form-group"><label class="form-label">المحتوى</label><textarea id="edit-nutrition-program-content" class="form-control" rows="4"></textarea></div>
                    <div class="modal-actions"><button class="btn btn-secondary" type="button" onclick="closeModal('modal-edit-nutrition-program')">إلغاء</button><button class="btn btn-primary" type="submit">حفظ</button></div>
                </form>
            `,
        },
        "modal-add-notification-template": {
            title: "إضافة قالب إشعار",
            body: `
                <form id="form-add-notification-template" onsubmit="submitAddNotificationTemplate(event)">
                    <div class="form-group"><label class="form-label">اسم القالب</label><input id="notification-template-name" class="form-control" type="text" required></div>
                    <div class="form-group"><label class="form-label">القناة</label><select id="notification-template-channel" class="form-control"><option value="whatsapp">واتساب</option><option value="sms">SMS</option><option value="email">Email</option><option value="in_app">داخل النظام</option></select></div>
                    <div class="form-group"><label class="form-label">النوع</label><input id="notification-template-type" class="form-control" type="text" placeholder="مثال: subscription_expiring_3_days" required></div>
                    <div class="form-group"><label class="form-label">عنوان القالب</label><input id="notification-template-title" class="form-control" type="text" placeholder="مثال: تنبيه {member_name}" required></div>
                    <div class="form-group"><label class="form-label">محتوى القالب</label><textarea id="notification-template-body" class="form-control" rows="4" placeholder="يمكن استخدام {member_name} و {member_id} و {end_date}" required></textarea></div>
                    <div class="modal-actions"><button class="btn btn-secondary" type="button" onclick="closeModal('modal-add-notification-template')">إلغاء</button><button class="btn btn-primary" type="submit">حفظ</button></div>
                </form>
            `,
        },
        "modal-send-notification": {
            title: "إرسال إشعار",
            body: `
                <form id="form-send-notification" onsubmit="submitSendNotification(event)">
                    <div class="form-group"><label class="form-label">القالب (اختياري)</label><select id="send-notification-template-id" class="form-control" onchange="syncSendNotificationMode()"></select></div>
                    <div id="send-notification-mode-hint" style="font-size: 12px; color: var(--text-muted); margin-top: -8px; margin-bottom: 10px;"></div>
                    <div class="form-group"><label class="form-label">المشترك (اختياري)</label><select id="send-notification-member-id" class="form-control"></select></div>
                    <div class="form-group"><label class="form-label">القناة (يدوي فقط)</label><select id="send-notification-channel" class="form-control"><option value="whatsapp">واتساب</option><option value="sms">SMS</option><option value="email">Email</option><option value="in_app">داخل النظام</option></select></div>
                    <div class="form-group"><label class="form-label">نوع الإشعار (يدوي فقط)</label><input id="send-notification-type" class="form-control" type="text" value="manual"></div>
                    <div class="form-group"><label class="form-label">العنوان (يدوي فقط)</label><input id="send-notification-title" class="form-control" type="text"></div>
                    <div class="form-group"><label class="form-label">المحتوى (يدوي فقط)</label><textarea id="send-notification-body" class="form-control" rows="4"></textarea></div>
                    <div class="modal-actions"><button class="btn btn-secondary" type="button" onclick="closeModal('modal-send-notification')">إلغاء</button><button class="btn btn-primary" type="submit">إرسال</button></div>
                </form>
            `,
        },
        "modal-edit-user": {
            title: "تعديل المستخدم",
            body: `
                <form id="form-edit-user" onsubmit="submitEditUser(event)">
                    <input id="edit-user-input-id" type="hidden">
                    <div class="form-group"><label class="form-label">الاسم</label><input id="edit-user-input-name" class="form-control" type="text" required></div>
                    <div class="form-group"><label class="form-label">اسم المستخدم</label><input id="edit-user-input-username" class="form-control" type="text" required></div>
                    <div class="form-group"><label class="form-label">كلمة المرور الجديدة</label><input id="edit-user-input-password" class="form-control" type="password"></div>
                    <div class="form-group"><label class="form-label">الدور</label><select id="edit-user-input-role" class="form-control" onchange="toggleEditUserMemberSelect()"><option>مدير النظام</option><option>موظف الاستقبال</option><option>المحاسب</option><option>المدقق المالي</option><option>مدرب</option><option>مشترك</option></select></div>
                    <div id="edit-user-member-group" class="form-group" style="display:none;"><label class="form-label">ربط بعضو</label><select id="edit-user-input-member-id" class="form-control"></select></div>
                    <div class="modal-actions"><button class="btn btn-secondary" type="button" onclick="closeModal('modal-edit-user')">إلغاء</button><button class="btn btn-primary" type="submit">حفظ</button></div>
                </form>
            `,
        },
        "modal-add-gym": {
            title: "إضافة صالة",
            body: `
                <form id="form-add-gym" onsubmit="submitAddGymModal(event)">
                    <div class="form-group"><label class="form-label">اسم الصالة</label><input id="tenant-add-gym-name" class="form-control" type="text" required></div>
                    <div class="form-group"><label class="form-label">رمز الصالة</label><input id="tenant-add-gym-code" class="form-control" type="text" required></div>
                    <div class="modal-actions"><button class="btn btn-secondary" type="button" onclick="closeModal('modal-add-gym')">إلغاء</button><button class="btn btn-primary" type="submit">حفظ</button></div>
                </form>
            `,
        },
        "modal-add-branch": {
            title: "إضافة فرع",
            body: `
                <form id="form-add-branch" onsubmit="submitAddBranchModal(event)">
                    <div class="form-group"><label class="form-label">الصالة</label><select id="tenant-add-branch-gym-id" class="form-control" required></select></div>
                    <div class="form-group"><label class="form-label">اسم الفرع</label><input id="tenant-add-branch-name" class="form-control" type="text" required></div>
                    <div class="form-group"><label class="form-label">رمز الفرع</label><input id="tenant-add-branch-code" class="form-control" type="text" required></div>
                    <div class="form-group"><label class="form-label">العنوان</label><input id="tenant-add-branch-address" class="form-control" type="text"></div>
                    <div class="form-group"><label class="form-label">الهاتف</label><input id="tenant-add-branch-phone" class="form-control" type="text"></div>
                    <div class="form-group"><label class="form-label">افتراضي</label><select id="tenant-add-branch-default" class="form-control"><option value="0">لا</option><option value="1">نعم</option></select></div>
                    <div class="modal-actions"><button class="btn btn-secondary" type="button" onclick="closeModal('modal-add-branch')">إلغاء</button><button class="btn btn-primary" type="submit">حفظ</button></div>
                </form>
            `,
        },
        "modal-edit-gym": {
            title: "تعديل الصالة",
            body: `
                <form id="form-edit-gym" onsubmit="submitEditGymModal(event)">
                    <input id="tenant-edit-gym-id" type="hidden">
                    <div class="form-group"><label class="form-label">اسم الصالة</label><input id="tenant-edit-gym-name" class="form-control" type="text" required></div>
                    <div class="form-group"><label class="form-label">الحالة</label><select id="tenant-edit-gym-status" class="form-control"><option value="active">نشطة</option><option value="inactive">غير نشطة</option></select></div>
                    <div class="modal-actions"><button class="btn btn-secondary" type="button" onclick="closeModal('modal-edit-gym')">إلغاء</button><button class="btn btn-primary" type="submit">حفظ</button></div>
                </form>
            `,
        },
        "modal-edit-branch": {
            title: "تعديل الفرع",
            body: `
                <form id="form-edit-branch" onsubmit="submitEditBranchModal(event)">
                    <input id="tenant-edit-branch-id" type="hidden">
                    <div class="form-group"><label class="form-label">اسم الفرع</label><input id="tenant-edit-branch-name" class="form-control" type="text" required></div>
                    <div class="form-group"><label class="form-label">رمز الفرع</label><input id="tenant-edit-branch-code" class="form-control" type="text" required></div>
                    <div class="form-group"><label class="form-label">العنوان</label><input id="tenant-edit-branch-address" class="form-control" type="text"></div>
                    <div class="form-group"><label class="form-label">الهاتف</label><input id="tenant-edit-branch-phone" class="form-control" type="text"></div>
                    <div class="form-group"><label class="form-label">الحالة</label><select id="tenant-edit-branch-status" class="form-control"><option value="active">نشط</option><option value="inactive">غير نشط</option></select></div>
                    <div class="modal-actions"><button class="btn btn-secondary" type="button" onclick="closeModal('modal-edit-branch')">إلغاء</button><button class="btn btn-primary" type="submit">حفظ</button></div>
                </form>
            `,
        },
    };

    const config = map[modalId];
    if (!config) return;
    if (!existingModal) {
        return createModalShell(modalId, config.title, config.body);
    }

    existingModal.innerHTML = `
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">${config.title}</h3>
                <button type="button" class="btn-close-modal" onclick="closeModal('${modalId}')">✕</button>
            </div>
            ${config.body}
        </div>
    `;

    return existingModal;
}

function ensureFallbackModals() {
    [
        "modal-change-password",
        "modal-add-member",
        "modal-edit-member",
        "modal-add-subscription",
        "modal-edit-subscription",
        "modal-add-plan",
        "modal-edit-plan",
        "modal-add-payment",
        "modal-edit-payment",
        "modal-add-product",
        "modal-edit-product-stock",
        "modal-add-user",
        "modal-add-trainer-profile",
        "modal-assign-trainer-member",
        "modal-add-training-program",
        "modal-edit-training-program",
        "modal-add-nutrition-program",
        "modal-edit-nutrition-program",
        "modal-add-notification-template",
        "modal-send-notification",
        "modal-edit-user",
        "modal-add-gym",
        "modal-add-branch",
        "modal-edit-gym",
        "modal-edit-branch",
    ].forEach(ensureFallbackModal);
}

function openModal(modalId) {
    let modal = ensureFallbackModal(modalId);
    if (!modal) {
        modal = document.getElementById(modalId);
    }
    if (!modal) {
        console.warn(`Modal not found: ${modalId}`);
        return;
    }
    modal.classList.add("active");
    populateDropdowns();
    if (modalId === "modal-send-notification") {
        syncSendNotificationMode();
    }
}

function closeModal(modalId) {
    const modal = document.getElementById(modalId);
    if (!modal) return;
    modal.classList.remove("active");
}

function populateDropdowns() {
    // Populate subscription member list (sliced to 100 to prevent UI lag with 10k members)
    const subMemberSelect = document.getElementById("sub-input-member");
    if (subMemberSelect) {
        const options = state.members.slice(0, 100).map(m => `<option value="${m.id}">${m.name} (${m.id})</option>`).join("");
        subMemberSelect.innerHTML = `<option value="" disabled selected>${options ? '— اختر المشترك —' : 'لا يوجد مشتركون متاحون'}</option>${options}`;
    }

    // Populate subscription plan list
    const subPlanSelect = document.getElementById("sub-input-plan");
    if (subPlanSelect) {
        subPlanSelect.innerHTML = state.plans.map(p => `<option value="${p.id}" data-price="${p.price}">${p.name} - ${p.price} شيكل</option>`).join("");
        updateSubPrice();
    }

    // Populate payment member list (sliced to 100 to prevent UI lag with 10k members)
    const payMemberSelect = document.getElementById("pay-input-member");
    if (payMemberSelect) {
        const options = state.members.slice(0, 100).map(m => `<option value="${m.id}">${m.name}</option>`).join("");
        payMemberSelect.innerHTML = `<option value="" disabled selected>${options ? '— اختر المشترك —' : 'لا يوجد مشتركون متاحون'}</option>${options}`;
    }

    // Populate user link member dropdowns (sliced to 100 to prevent UI lag with 10k members)
    const userMemberSelect = document.getElementById("user-input-member-id");
    if (userMemberSelect) {
        userMemberSelect.innerHTML = `<option value="">— لا يوجد ربط (موظف) —</option>` + state.members.slice(0, 100).map(m => `<option value="${m.id}">${m.name} (${m.id})</option>`).join("");
    }

    const editUserMemberSelect = document.getElementById("edit-user-input-member-id");
    if (editUserMemberSelect) {
        editUserMemberSelect.innerHTML = `<option value="">— لا يوجد ربط (موظف) —</option>` + state.members.slice(0, 100).map(m => `<option value="${m.id}">${m.name} (${m.id})</option>`).join("");
    }

    // Populate POS basket member selection (sliced to 100 to prevent UI lag with 10k members)
    const basketMemberSelect = document.getElementById("basket-member-select");
    if (basketMemberSelect) {
        basketMemberSelect.innerHTML = `<option value="">— مبيعات كاشير عامة —</option>` + state.members.slice(0, 100).map(m => `<option value="${m.id}">${m.name}</option>`).join("");
    }

    const assignmentLabel = (a) => {
        const trainer = (state.trainers || []).find(t => Number(t.id) === Number(a.trainer_id));
        const member = (state.members || []).find(m => String(m.id) === String(a.member_id));
        const trainerName = trainer?.user?.name || `مدرب #${a.trainer_id}`;
        const memberName = member?.name || `#${a.member_id}`;
        return `${trainerName} - ${memberName}`;
    };

    const assignmentSource = (state.currentUser && state.currentUser.role === 'مدرب')
        ? ((state.trainerData && Array.isArray(state.trainerData.assignments)) ? state.trainerData.assignments : [])
        : (state.trainerAssignments || []);

    const assignmentOptions = assignmentSource
        .map(a => `<option value="${a.id}">${assignmentLabel(a)}</option>`)
        .join("");

    const programAssignmentSelectIds = [
        "training-program-assignment-id",
        "edit-training-program-assignment-id",
        "nutrition-program-assignment-id",
        "edit-nutrition-program-assignment-id",
    ];

    programAssignmentSelectIds.forEach((id) => {
        const select = document.getElementById(id);
        if (!select) return;
        select.innerHTML = assignmentOptions || '<option value="">لا يوجد ربط متاح</option>';
    });

    const sendNotificationMemberSelect = document.getElementById("send-notification-member-id");
    if (sendNotificationMemberSelect) {
        sendNotificationMemberSelect.innerHTML = `<option value="">— بدون ربط مشترك —</option>` + state.members.slice(0, 200).map(m => `<option value="${m.id}">${m.name} (${m.id})</option>`).join("");
    }

    const sendNotificationTemplateSelect = document.getElementById("send-notification-template-id");
    if (sendNotificationTemplateSelect) {
        const templates = Array.isArray(state.notificationTemplates) ? state.notificationTemplates : [];
        sendNotificationTemplateSelect.innerHTML = `<option value="">— إرسال يدوي بدون قالب —</option>` + templates.map(t => `<option value="${t.id}">${t.name} (${t.type})</option>`).join("");
    }
}

function updateSubPrice() {
    const select = document.getElementById("sub-input-plan");
    const paidInput = document.getElementById("sub-input-paid");
    if (!select || !paidInput) return;

    const activeOption = select.options[select.selectedIndex];
    if (activeOption) {
        const price = activeOption.getAttribute("data-price");
        paidInput.value = price;
    }
}

// Helper to write text content in DOM
function safeSetText(id, text) {
    const el = document.getElementById(id);
    if (el) el.innerText = text;
}

function escapeHtml(value) {
    return String(value ?? '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#39;');
}

function toNumber(value) {
    const num = Number(value);
    return Number.isFinite(num) ? num : 0;
}

function formatMoney(value) {
    return `${toNumber(value).toFixed(2)} ₪`;
}

function ensureEditSubscriptionPlanSelect(selectedPlanId, selectedPlanName) {
    const currentControl = document.getElementById("edit-sub-plan-name");
    if (!currentControl) return;

    let selectControl = currentControl;
    if (currentControl.tagName !== "SELECT") {
        selectControl = document.createElement("select");
        selectControl.id = currentControl.id;
        selectControl.className = currentControl.className;
        selectControl.required = true;
        currentControl.parentNode.replaceChild(selectControl, currentControl);
    }

    let options = state.plans.map(p =>
        `<option value="${p.id}">${p.name} - ${Number(p.price).toFixed(2)} ₪</option>`
    );

    if (options.length === 0) {
        options = ['<option value="">لا توجد خطط متاحة</option>'];
    }

    selectControl.innerHTML = options.join("");

    if (selectedPlanId && state.plans.some(p => p.id === selectedPlanId)) {
        selectControl.value = selectedPlanId;
        return;
    }

    const planByName = state.plans.find(p => p.name === selectedPlanName);
    if (planByName) {
        selectControl.value = planByName.id;
        return;
    }

    if (selectedPlanName) {
        const legacyOption = document.createElement("option");
        legacyOption.value = selectedPlanName;
        legacyOption.textContent = `${selectedPlanName} (خطة قديمة)`;
        selectControl.appendChild(legacyOption);
        selectControl.value = selectedPlanName;
    }
}

function setEditSubscriptionHint(message, tone = "info") {
    const planControl = document.getElementById("edit-sub-plan-name");
    if (!planControl || !planControl.parentNode) return;

    let hint = document.getElementById("edit-sub-autosync-hint");
    if (!hint) {
        hint = document.createElement("div");
        hint.id = "edit-sub-autosync-hint";
        hint.style.fontSize = "12px";
        hint.style.marginTop = "6px";
        planControl.parentNode.appendChild(hint);
    }

    const colors = {
        info: "var(--text-muted)",
        success: "var(--accent-green)",
        warning: "var(--accent-orange)",
    };

    hint.style.color = colors[tone] || colors.info;
    hint.textContent = message || "";
}

function syncEditSubscriptionAmountFromPlan(force = false) {
    const planControl = document.getElementById("edit-sub-plan-name");
    const amountInput = document.getElementById("edit-sub-amount");
    const paidInput = document.getElementById("edit-sub-paid");
    if (!planControl || !amountInput || !paidInput) {
        return { updatedAmount: false, updatedPaid: false, skippedByManual: false };
    }

    const selectedPlan = state.plans.find(p => p.id === planControl.value);
    if (!selectedPlan) {
        return { updatedAmount: false, updatedPaid: false, skippedByManual: false };
    }

    const planPrice = Number(selectedPlan.price);
    if (!Number.isFinite(planPrice)) {
        return { updatedAmount: false, updatedPaid: false, skippedByManual: false };
    }

    const amountEdited = amountInput.dataset.userEdited === "1";
    const paidEdited = paidInput.dataset.userEdited === "1";
    let updatedAmount = false;
    let updatedPaid = false;

    if (force || !amountEdited) {
        amountInput.value = planPrice.toFixed(2);
        updatedAmount = true;
    }

    if (force || !paidEdited) {
        paidInput.value = planPrice.toFixed(2);
        updatedPaid = true;
    }

    return {
        updatedAmount,
        updatedPaid,
        skippedByManual: (!force && (amountEdited || paidEdited)),
    };
}

function getMemberNameById(memberId) {
    if (!memberId) return null;
    const member = state.members.find(m => String(m.id) === String(memberId));
    return member ? member.name : null;
}

function getPaymentMemberDisplay(payment) {
    return getMemberNameById(payment.member_id) || payment.member_name || '—';
}

function getActiveMembershipCardCode(memberId) {
    if (!memberId) return null;
    const cards = (state.membershipCards || []).filter(c => c.member_id === memberId);
    if (cards.length === 0) return null;

    const active = cards.find(c => c.status === 'active');
    if (active && active.code) return active.code;

    const latest = cards.slice().sort((a, b) => Number(b.id || 0) - Number(a.id || 0))[0];
    return latest && latest.code ? latest.code : null;
}

function ensureMemberQrActions(cardCode) {
    const qrLabel = document.getElementById("md-qr-label");
    if (!qrLabel || !qrLabel.parentNode) return;

    const parent = qrLabel.parentNode;
    let actions = document.getElementById("md-qr-actions");
    if (!actions) {
        actions = document.createElement("div");
        actions.id = "md-qr-actions";
        actions.style.display = "flex";
        actions.style.gap = "8px";
        actions.style.marginTop = "8px";
        actions.style.flexWrap = "wrap";
        parent.appendChild(actions);
    }

    actions.innerHTML = `
        <button type="button" class="btn btn-secondary btn-sm" onclick="copyMemberCardCode()">نسخ الكود</button>
        <button type="button" class="btn btn-secondary btn-sm" onclick="downloadMemberQrCode()">تنزيل PNG</button>
        <button type="button" class="btn btn-secondary btn-sm" onclick="printMemberQrCode()">طباعة QR</button>
    `;

    actions.dataset.cardCode = cardCode || "";
}

function copyMemberCardCode() {
    const actions = document.getElementById("md-qr-actions");
    const cardCode = actions ? actions.dataset.cardCode || "" : "";
    if (!cardCode) {
        showAppNotice("لا يوجد كود بطاقة لنسخه", 'warning');
        return;
    }

    if (navigator.clipboard && navigator.clipboard.writeText) {
        navigator.clipboard.writeText(cardCode)
            .then(() => showAppNotice("تم نسخ كود البطاقة", 'success'))
            .catch(() => showAppNotice("تعذر النسخ التلقائي. الرجاء نسخ الكود يدوياً: " + cardCode, 'warning', 6200));
        return;
    }

    showAppNotice("الرجاء نسخ الكود يدوياً: " + cardCode, 'warning', 6200);
}

function printMemberQrCode() {
    const actions = document.getElementById("md-qr-actions");
    const cardCode = actions ? actions.dataset.cardCode || "" : "";
    const qrImage = document.getElementById("md-qr-code");
    const qrSrc = qrImage ? qrImage.src : "";

    if (!cardCode || !qrSrc) {
        showAppNotice("لا توجد بيانات كافية لطباعة البطاقة", 'warning');
        return;
    }

    const printWindow = window.open("", "_blank", "width=420,height=620");
    if (!printWindow) {
        showAppNotice("تعذر فتح نافذة الطباعة", 'error', 5200);
        return;
    }

    printWindow.document.write(`
        <html>
            <head>
                <title>طباعة بطاقة العضوية</title>
                <style>
                    @page { size: 86mm 54mm; margin: 4mm; }
                    body { font-family: Tahoma, Arial, sans-serif; direction: rtl; text-align: center; margin: 0; }
                    .card { border: 1px solid #ddd; border-radius: 10px; padding: 10px; width: 100%; box-sizing: border-box; }
                    h2 { font-size: 14px; margin: 0 0 6px; }
                    img { width: 120px; height: 120px; margin: 6px auto; display: block; }
                    .code { font-size: 12px; font-weight: 700; letter-spacing: 0.4px; word-break: break-all; }
                </style>
            </head>
            <body>
                <div class="card">
                    <h2>بطاقة عضوية</h2>
                    <img src="${qrSrc}" alt="QR Code">
                    <div class="code">${cardCode}</div>
                </div>
            </body>
        </html>
    `);
    printWindow.document.close();
    printWindow.focus();
    printWindow.print();
}

function downloadMemberQrCode() {
    const actions = document.getElementById("md-qr-actions");
    const cardCode = actions ? actions.dataset.cardCode || "" : "";
    const qrImage = document.getElementById("md-qr-code");
    const qrSrc = qrImage ? qrImage.src : "";

    if (!cardCode || !qrSrc) {
        showAppNotice("لا توجد بيانات كافية لتنزيل البطاقة", 'warning');
        return;
    }

    const link = document.createElement("a");
    link.href = qrSrc;
    link.download = `member-qr-${cardCode}.png`;
    document.body.appendChild(link);
    link.click();
    link.remove();
}

// 4. Client Render Engines

// View 1: Dashboard
function renderDashboard() {
    safeSetText("dash-today-revenue", formatMoney(state.todayRevenue));
    safeSetText("dash-month-revenue", formatMoney(state.monthlyRevenue));
    
    // Active members
    let activeMembersSet = new Set(state.subscriptions.filter(s => s.status === "فعال").map(s => s.member_id || s.memberId));
    safeSetText("dash-active-members", activeMembersSet.size);
    safeSetText("dash-active-subtext", `من ${state.members.length} مشترك`);
    
    safeSetText("dash-today-attendance", state.todayAttendance);
    
    let expiredSubs = state.subscriptions.filter(s => s.status === "منتهي").length;
    let frozenSubs = state.subscriptions.filter(s => s.status === "مجمد").length;
    const today = new Date();
    today.setHours(0, 0, 0, 0);
    const expiringSubs = state.subscriptions.filter(s => {
        if (s.status !== "فعال" || !s.end_date) return false;
        const endDate = new Date(`${String(s.end_date).slice(0, 10)}T00:00:00`);
        const days = Math.round((endDate - today) / 86400000);
        return days >= 0 && days <= 3;
    });
    safeSetText("dash-frozen-members", frozenSubs);
    safeSetText("dash-expired-members", expiredSubs);
    safeSetText("dash-total-members", state.members.length);
    safeSetText("dash-expiring-members", expiringSubs.length);

    renderDashboardDetails(activeMembersSet.size, expiringSubs.length);
    initRevenueChart("chart-revenue-dashboard", "dashboard", 14);
    initAttendanceChart("chart-attendance-dashboard", "dashboard", 'day');
    document.querySelectorAll('[data-revenue-days]').forEach(button => {
        button.onclick = () => {
            document.querySelectorAll('[data-revenue-days]').forEach(tab => tab.classList.toggle('active', tab === button));
            initRevenueChart("chart-revenue-dashboard", "dashboard", Number(button.dataset.revenueDays));
        };
    });
    document.querySelectorAll('[data-peak-range]').forEach(button => {
        button.onclick = () => {
            document.querySelectorAll('[data-peak-range]').forEach(tab => tab.classList.toggle('active', tab === button));
            initAttendanceChart("chart-attendance-dashboard", "dashboard", button.dataset.peakRange);
        };
    });
}

function renderDashboardDetails(activeMembersCount, expiringCount) {
    const recentMembers = [...state.members].sort((a, b) => String(b.created_at || b.id).localeCompare(String(a.created_at || a.id))).slice(0, 5);
    const recentTarget = document.getElementById("dash-recent-members");
    if (recentTarget) {
        recentTarget.innerHTML = recentMembers.length ? recentMembers.map(member => {
            const subscription = state.subscriptions.find(s => (s.member_id || s.memberId) === member.id && s.status === 'فعال')
                || state.subscriptions.find(s => (s.member_id || s.memberId) === member.id);
            const status = subscription?.status || 'بدون اشتراك';
            const statusClass = status === 'فعال' ? 'is-active' : status === 'منتهي' ? 'is-ended' : 'is-frozen';
            return `<div class="recent-member-row">
                <div class="member-initial">${escapeHtml(String(member.name || 'م').trim().charAt(0) || 'م')}</div>
                <div class="recent-member-details"><strong>${escapeHtml(member.name || 'مشترك')}</strong><span>${escapeHtml(subscription?.plan_name || 'لا يوجد اشتراك')}</span></div>
                <div class="recent-member-status"><span class="dashboard-status ${statusClass}">${escapeHtml(status)}</span><small>${dashboardRelativeTime(member.created_at)}</small></div>
            </div>`;
        }).join('') : '<div class="dashboard-empty">لا يوجد مشتركون مسجلون بعد.</div>';
    }

    const memberCount = state.members.length || 0;
    const conversion = memberCount ? (activeMembersCount / memberCount) * 100 : 0;
    const subscriptionAmounts = state.subscriptions.map(s => toNumber(s.amount)).filter(amount => amount > 0);
    const averageSubscription = subscriptionAmounts.length ? subscriptionAmounts.reduce((sum, amount) => sum + amount, 0) / subscriptionAmounts.length : 0;
    const insights = [
        ['معدل التحويل', `${conversion.toFixed(1)}%`, 'من المشتركين النشطين'],
        ['متوسط قيمة الاشتراك', formatMoney(averageSubscription), 'حسب الاشتراكات المسجلة'],
        ['معدل التجديد', `${toNumber(state.renewalRate).toFixed(0)}%`, 'آخر 30 يوماً'],
        ['المدفوعات المعلقة', state.reports?.debtMembersCount || 0, 'مشترك بحاجة متابعة'],
    ];
    const insightsTarget = document.getElementById('dash-insights');
    if (insightsTarget) {
        insightsTarget.innerHTML = insights.map(([label, value, detail]) => `<div class="insight-row"><div><strong>${value}</strong><span>${label}</span></div><small>${detail}</small></div>`).join('');
    }

    const now = new Date();
    const absentCount = state.members.filter(member => {
        const lastCheckin = state.checkins.find(checkin => (checkin.member_id || checkin.memberId) === member.id);
        if (!lastCheckin) return false;
        const lastDate = new Date(lastCheckin.created_at || lastCheckin.checkin_time || 0);
        return !Number.isNaN(lastDate.getTime()) && (now - lastDate) > 30 * 86400000;
    }).length;
    const todayKey = now.toISOString().slice(0, 10);
    const todayPayments = state.payments.filter(p => String(p.date || '').slice(0, 10) === todayKey).length;
    const alerts = [
        [expiringCount, 'اشتراك ينتهي خلال 3 أيام', 'calendar-clock'],
        [todayPayments, 'دفعات تم استلامها اليوم', 'circle-dollar-sign'],
        [absentCount, 'أعضاء لم يزوروا منذ شهر', 'user-round-x'],
    ];
    const alertsTarget = document.getElementById('dash-alerts');
    if (alertsTarget) {
        alertsTarget.innerHTML = alerts.map(([count, label, icon]) => `<div class="dashboard-alert-row"><div class="dashboard-alert-icon"><i data-lucide="${icon}"></i></div><div><strong>${count} ${label}</strong><span>تحديث مباشر من بيانات النظام</span></div></div>`).join('');
    }
    if (window.lucide) window.lucide.createIcons();
}

function dashboardRelativeTime(value) {
    const date = new Date(value || 0);
    if (Number.isNaN(date.getTime()) || !value) return 'حديثاً';
    const hours = Math.max(0, Math.floor((Date.now() - date.getTime()) / 3600000));
    if (hours < 1) return 'منذ أقل من ساعة';
    if (hours < 24) return `منذ ${hours} ساعة`;
    const days = Math.floor(hours / 24);
    return `منذ ${days} يوم`;
}

// View 2: Check-In Gate
function renderCheckIn() {
    const tableBody = document.getElementById("checkins-table-body");
    if (tableBody) {
        if (state.checkins.length === 0) {
            tableBody.innerHTML = `<tr><td colspan="6" class="text-center" style="color: var(--text-muted);">لا يوجد تسجيل حضور لهذا اليوم حتى الآن</td></tr>`;
        } else {
            tableBody.innerHTML = state.checkins.map(c => `
                <tr>
                    <td class="val-mono">${c.member_id}</td>
                    <td>${c.member_name}</td>
                    <td class="val-mono">${c.time}</td>
                    <td class="val-mono">${formatDateTimeForGate(c.checkout_at)}</td>
                    <td>
                        <span class="badge ${c.status === 'denied' ? 'badge-expired' : 'badge-active'}">
                            ${c.status === 'denied' ? 'مرفوض' : 'مسجل'}
                        </span>
                    </td>
                    <td><button type="button" class="btn btn-danger btn-sm" onclick="deleteCheckinRecord(${Number(c.id)})">حذف</button></td>
                </tr>
            `).join("");
        }
        applyTableFiltersForBody("checkins-table-body");
    }

    updateDailyCheckinLogButton();
}

function updateDailyCheckinLogButton() {
    const panel = document.getElementById("checkin-log-panel");
    const buttonText = document.getElementById("toggle-checkin-log-text");
    if (!buttonText) return;

    const isOpen = panel && panel.style.display !== "none";
    const count = state.checkins.length || 0;
    buttonText.textContent = isOpen ? `إخفاء سجل اليوم (${count})` : `عرض سجل اليوم (${count})`;
}

function toggleDailyCheckinLog() {
    const panel = document.getElementById("checkin-log-panel");
    if (!panel) return;

    const isOpen = panel.style.display !== "none";
    panel.style.display = isOpen ? "none" : "block";
    updateDailyCheckinLogButton();

    if (!isOpen) {
        panel.scrollIntoView({ behavior: 'smooth', block: 'center' });
    }
}

function formatDateTimeForGate(value) {
    if (!value) return "—";
    const date = new Date(value);
    if (Number.isNaN(date.getTime())) return value;
    return date.toLocaleTimeString('ar-EG', { hour: '2-digit', minute: '2-digit' });
}

// Autocomplete functionality for Check-in Gate
function onCheckinSearchInput() {
    const searchInput = document.getElementById("checkin-search-input");
    const suggestionsBox = document.getElementById("checkin-suggestions-box");
    const memberIdVal = document.getElementById("checkin-member-id-val");
    
    if (!searchInput || !suggestionsBox) return;
    
    const query = searchInput.value.trim().toLowerCase();
    
    // Clear the selected member ID on new input
    if (memberIdVal) {
        memberIdVal.value = "";
    }
    
    if (query.length < 1) {
        suggestionsBox.innerHTML = "";
        suggestionsBox.style.display = "none";
        return;
    }
    
    // Filter members matching query by name, id, phone, or whatsapp
    const matches = state.members.filter(m => {
        const nameMatch = m.name && m.name.toLowerCase().includes(query);
        const idMatch = m.id && m.id.toLowerCase().includes(query);
        const phoneMatch = m.phone && m.phone.toLowerCase().includes(query);
        const whatsappMatch = m.whatsapp && m.whatsapp.toLowerCase().includes(query);
        return nameMatch || idMatch || phoneMatch || whatsappMatch;
    });
    
    if (matches.length === 0) {
        suggestionsBox.innerHTML = `<div style="padding: 10px 16px; color: var(--text-muted); font-size: 13px;">لا توجد نتائج مطابقة</div>`;
        suggestionsBox.style.display = "block";
        return;
    }
    
    // Limit to 15 suggestions to prevent lag
    const limitMatches = matches.slice(0, 15);
    
    suggestionsBox.innerHTML = limitMatches.map(m => {
        const phoneText = m.phone ? `جوال: ${m.phone}` : '';
        return `
            <div class="checkin-suggestion-item" onclick="selectCheckinMember('${m.id}', '${m.name.replace(/'/g, "\\'")}')">
                <div class="checkin-suggestion-info">
                    <span class="checkin-suggestion-name">${m.name}</span>
                    ${phoneText ? `<span class="checkin-suggestion-phone">${phoneText}</span>` : ''}
                </div>
                <span class="checkin-suggestion-id">${m.id}</span>
            </div>
        `;
    }).join("");
    
    suggestionsBox.style.display = "block";
}

function showCheckinSuggestions() {
    const searchInput = document.getElementById("checkin-search-input");
    if (searchInput && searchInput.value.trim().length >= 1) {
        onCheckinSearchInput();
    }
}

function hideCheckinSuggestionsDeferred() {
    // Timeout gives user time to click the suggestion before panel is hidden
    setTimeout(() => {
        const suggestionsBox = document.getElementById("checkin-suggestions-box");
        if (suggestionsBox) {
            suggestionsBox.style.display = "none";
        }
    }, 250);
}

function selectCheckinMember(id, name) {
    const searchInput = document.getElementById("checkin-search-input");
    const memberIdVal = document.getElementById("checkin-member-id-val");
    const suggestionsBox = document.getElementById("checkin-suggestions-box");
    
    if (searchInput) searchInput.value = `${name} (${id})`;
    if (memberIdVal) memberIdVal.value = id;
    if (suggestionsBox) {
        suggestionsBox.innerHTML = "";
        suggestionsBox.style.display = "none";
    }
}

function handleMemberCheckin() {
    const memberIdVal = document.getElementById("checkin-member-id-val");
    const searchInput = document.getElementById("checkin-search-input");
    const memberId = memberIdVal ? memberIdVal.value : '';
    const code = searchInput ? searchInput.value.trim() : '';
    
    if (!memberId && !code) {
        showAppNotice("الرجاء اختيار مشترك أو إدخال/مسح كود البطاقة.", 'warning');
        return;
    }
    
    const payload = { memberId, code };

    if (!navigator.onLine) {
        queueOfflineSyncEvent('check_in', payload);
        showAppNotice('لا يوجد اتصال. تم حفظ تسجيل الحضور محلياً وسيتم مزامنته تلقائياً.', 'warning', 6200);
        if (searchInput) searchInput.value = '';
        if (memberIdVal) memberIdVal.value = '';
        refreshNetworkStatusBadge();
        return;
    }

    postApi('check_in', payload).then(res => {
        if (res.success) {
            const name = res.member && res.member.name ? res.member.name : "المشترك";
            renderGateResult(res.member, "allowed", `تم تسجيل الحضور الساعة ${res.time || ''}`);
            showAppNotice(`تم تسجيل حضور ${name} بنجاح!`, 'success');
            
            // Clear inputs
            if (searchInput) searchInput.value = '';
            if (memberIdVal) memberIdVal.value = '';
            
            loadStateAndRender("check-in");
        } else {
            if (res.member) {
                renderGateResult(res.member, "denied", res.error || "تم رفض الدخول");
            }
            showAppNotice("خطأ: " + res.error, 'error', 5200);
            loadStateAndRender("check-in");
        }
    }).catch(() => {
        queueOfflineSyncEvent('check_in', payload);
        showAppNotice('تعذر الوصول للخادم. تم حفظ العملية وسيتم مزامنتها تلقائياً.', 'warning', 6200);
        refreshNetworkStatusBadge();
    });
}

function handleMemberCheckout() {
    const memberIdVal = document.getElementById("checkin-member-id-val");
    const searchInput = document.getElementById("checkin-search-input");
    const memberId = memberIdVal ? memberIdVal.value : '';
    const code = searchInput ? searchInput.value.trim() : '';
    
    if (!memberId && !code) {
        showAppNotice("الرجاء اختيار مشترك أو إدخال/مسح كود البطاقة.", 'warning');
        return;
    }
    
    const payload = { memberId, code };

    if (!navigator.onLine) {
        queueOfflineSyncEvent('check_out', payload);
        showAppNotice('لا يوجد اتصال. تم حفظ تسجيل الخروج محلياً وسيتم مزامنته تلقائياً.', 'warning', 6200);
        if (searchInput) searchInput.value = '';
        if (memberIdVal) memberIdVal.value = '';
        refreshNetworkStatusBadge();
        return;
    }

    postApi('check_out', payload).then(res => {
        if (res.success) {
            renderGateResult(res.member, "checkout", "تم تسجيل الخروج بنجاح");
            showAppNotice('تم تسجيل الخروج بنجاح', 'success');
            if (searchInput) searchInput.value = '';
            if (memberIdVal) memberIdVal.value = '';
            loadStateAndRender("check-in");
        } else {
            if (res.member) {
                renderGateResult(res.member, "denied", res.error || "تعذر تسجيل الخروج");
            }
            showAppNotice("خطأ: " + res.error, 'error', 5200);
        }
    }).catch(() => {
        queueOfflineSyncEvent('check_out', payload);
        showAppNotice('تعذر الوصول للخادم. تم حفظ العملية وسيتم مزامنتها تلقائياً.', 'warning', 6200);
        refreshNetworkStatusBadge();
    });
}

function renderGateResult(member, mode, message) {
    const emptyState = document.getElementById("gate-empty-state");
    const result = document.getElementById("gate-member-result");
    const photo = document.getElementById("gate-member-photo");
    const statusPill = document.getElementById("gate-status-pill");
    const nameEl = document.getElementById("gate-member-name");
    const idEl = document.getElementById("gate-member-id");
    const phoneEl = document.getElementById("gate-member-phone");
    const subLine = document.getElementById("gate-subscription-line");

    if (!member || !result) return;

    if (emptyState) emptyState.style.display = "none";
    result.style.display = "flex";

    if (photo) {
        photo.innerHTML = member.imagePath
            ? `<img src="${member.imagePath}" alt="${member.name || ''}">`
            : `<i data-lucide="user"></i>`;
    }

    const config = {
        allowed: { label: "مسموح بالدخول", cls: "gate-status-allowed" },
        denied: { label: "مرفوض", cls: "gate-status-denied" },
        checkout: { label: "تم الخروج", cls: "gate-status-checkout" }
    }[mode] || { label: "جاهز", cls: "gate-status-checkout" };

    if (statusPill) {
        statusPill.className = `gate-status-pill ${config.cls}`;
        statusPill.innerText = config.label;
    }
    if (nameEl) nameEl.innerText = member.name || "—";
    if (idEl) idEl.innerText = member.membershipNumber || member.id || "—";
    if (phoneEl) phoneEl.innerText = member.phone || "—";
    if (subLine) {
        const endDate = member.subscriptionEndDate ? `حتى ${member.subscriptionEndDate}` : "";
        subLine.innerText = `${message || ""}${endDate ? " - " + endDate : ""}`;
    }

    lucide.createIcons();
}

// View 3: Members Card Grid
function renderMembers() {
    renderMembersGrid(state.members, "تم عرض أول 120 مشترك فقط لتسريع تصفح الصفحة. يرجى استخدام مربع البحث للوصول لأي مشترك آخر فوراً.");
}

function filterMembers() {
    const query = document.getElementById("members-search").value.toLowerCase();
    const filtered = state.members.filter(m => 
        m.name.toLowerCase().includes(query) || 
        m.phone.toLowerCase().includes(query) || 
        m.id.toLowerCase().includes(query)
    );

    renderMembersGrid(filtered, "تم عرض أول 120 مشترك مطابق للبحث فقط. يرجى كتابة اسم أكثر دقة لتضييق نتائج البحث.");
}

function renderMembersGrid(sourceList, overflowMessage) {
    const grid = document.getElementById("members-grid");
    if (!grid) return;

    const visible = sourceList.slice(0, 120);
    grid.innerHTML = visible.map(renderMemberCardHtml).join("");

    if (sourceList.length > 120) {
        grid.innerHTML += `
            <div class="card" style="grid-column: 1 / -1; padding: 20px; text-align: center; color: var(--text-muted); font-size: 14px; border: 1px dashed var(--border-color); background-color: rgba(255,255,255,0.01);">
                ${overflowMessage}
            </div>
        `;
    }

    lucide.createIcons();
}

function renderMemberCardHtml(m) {
    const numericMemberCode = String(m.id || '').replace(/^M/i, '');
    return `
        <div class="card member-card" onclick="if (!event.target.closest('button')) openMemberDetail('${m.id}')">
            <div class="member-card-actions">
                <button class="btn-card-arrow" title="عرض الملف" onclick="openMemberDetail('${m.id}')">
                    <i data-lucide="chevron-left"></i>
                </button>
                <button class="btn-edit-card" title="تعديل البيانات" onclick="openEditMemberModal('${m.id}')">
                    <i data-lucide="edit-2"></i>
                </button>
                <button class="btn-edit-card" title="إعادة إصدار بطاقة QR" onclick="issueMembershipCard('${m.id}')">
                    <i data-lucide="qr-code"></i>
                </button>
            </div>

            <div class="member-card-head">
                <div class="member-avatar-box">
                    ${m.image_path ? `<img src="${m.image_path}" alt="${m.name}">` : `<i data-lucide="user"></i>`}
                </div>
                <div class="member-title-block">
                    <h3 class="member-name">${m.name} <span class="member-inline-code">#${numericMemberCode}</span></h3>
                    <span class="member-id">${m.id}</span>
                </div>
            </div>

            <div class="member-details">
                <span class="member-meta-row">
                    <i data-lucide="user"></i>
                    <span>الجنس: ${m.gender || 'ذكر'}</span>
                </span>
                <span class="member-meta-row">
                    <i data-lucide="phone"></i>
                    <span>جوال: ${m.phone || '—'}</span>
                </span>
                ${m.whatsapp ? `
                    <span class="member-meta-row whatsapp">
                        <i data-lucide="message-circle"></i>
                        <span>واتساب: ${m.whatsapp}</span>
                    </span>
                ` : ''}
            </div>
        </div>
    `;
}

function openEditMemberModal(memberId) {
    const member = state.members.find(m => String(m.id) === String(memberId));
    if (!member) return;
    openModal("modal-edit-member");

    document.getElementById("edit-member-input-id").value = member.id;
    document.getElementById("edit-member-input-name").value = member.name;
    document.getElementById("edit-member-input-phone").value = member.phone;
    document.getElementById("edit-member-input-gender").value = member.gender || 'ذكر';
    document.getElementById("edit-member-input-whatsapp").value = member.whatsapp || '';
    document.getElementById("edit-member-input-image").value = ""; // clear file

    // Populate current image preview if exists
    const previewContainer = document.getElementById("edit-member-image-preview-container");
    if (previewContainer) {
        if (member.image_path) {
            previewContainer.innerHTML = `<img src="${member.image_path}" style="width: 100%; height: 100%; object-fit: cover;">`;
        } else {
            previewContainer.innerHTML = `<i data-lucide="user" style="width: 20px; height: 20px; color: var(--text-muted);"></i>`;
            lucide.createIcons();
        }
    }
}

function previewEditImage(event) {
    const input = event.target;
    const previewContainer = document.getElementById("edit-member-image-preview-container");
    if (previewContainer && input.files && input.files[0]) {
        const reader = new FileReader();
        reader.onload = function(e) {
            previewContainer.innerHTML = `<img src="${e.target.result}" style="width: 100%; height: 100%; object-fit: cover;">`;
        }
        reader.readAsDataURL(input.files[0]);
    }
}

function validateMemberContactFields(phone, whatsapp) {
    const trimmedPhone = (phone || '').trim();
    const trimmedWhatsapp = (whatsapp || '').trim();
    const phonePattern = /^\d{10}$/;
    const whatsappPattern = /^\d{14}$/;
    if (!phonePattern.test(trimmedPhone)) {
        return "رقم الجوال يجب أن يكون 10 أرقام مثل 0599466586.";
    }
    if (trimmedWhatsapp !== '' && !whatsappPattern.test(trimmedWhatsapp)) {
        return "رقم الواتساب يجب أن يكون 14 رقمًا مثل 00972599466856.";
    }
    return null;
}

function submitEditMember(e) {
    e.preventDefault();
    const id = document.getElementById("edit-member-input-id").value;
    const name = document.getElementById("edit-member-input-name").value;
    const phone = document.getElementById("edit-member-input-phone").value;
    const gender = document.getElementById("edit-member-input-gender").value;
    const whatsapp = document.getElementById("edit-member-input-whatsapp").value;
    const imageFile = document.getElementById("edit-member-input-image").files[0];

    const validationError = validateMemberContactFields(phone, whatsapp);
    if (validationError) {
        showAppNotice(validationError, 'error', 5200);
        return;
    }

    const formData = new FormData();
    formData.append("id", id);
    formData.append("name", name);
    formData.append("phone", phone);
    formData.append("gender", gender);
    formData.append("whatsapp", whatsapp);
    if (imageFile) {
        formData.append("image", imageFile);
    }

    fetch(resolveApiUrl('edit_member'), {
        method: 'POST',
        body: formData,
        credentials: 'same-origin'
    })
    .then(r => r.json())
    .then(res => {
        if (res.success) {
            e.target.reset();
            closeModal("modal-edit-member");
            showAppNotice("تم تعديل بيانات المشترك بنجاح!", 'success');
            loadStateAndRender("members");
        } else {
            showAppNotice("خطأ: " + res.error, 'error', 5200);
        }
    })
    .catch(() => {
        showAppNotice("تعذّر حفظ بيانات المشترك. تحقق من اتصال الخادم ثم أعد المحاولة.", 'error', 5200);
    });
}

function deleteCheckinRecord(id) {
    if (!id || !confirm('هل تريد حذف سجل الحضور والمغادرة هذا؟')) return;

    postApi('delete_checkin', { id }).then(res => {
        if (res.success) {
            showAppNotice('تم حذف سجل الحضور والمغادرة بنجاح', 'success');
            loadStateAndRender('check-in');
        } else {
            showAppNotice('خطأ: ' + (res.error || 'تعذر حذف السجل'), 'error', 5200);
        }
    }).catch(() => showAppNotice('تعذر الاتصال أثناء حذف السجل', 'error', 5200));
}

function formatVisibleDates(root = document.body) {
    if (!root) return;
    const walker = document.createTreeWalker(root, NodeFilter.SHOW_TEXT);
    const nodes = [];
    while (walker.nextNode()) nodes.push(walker.currentNode);

    nodes.forEach(node => {
        const parentTag = node.parentElement?.tagName;
        if (['SCRIPT', 'STYLE', 'TEXTAREA'].includes(parentTag) || !node.nodeValue) return;
        node.nodeValue = node.nodeValue
            .replace(/\b(\d{4}-\d{2}-\d{2})[ T](\d{2}:\d{2}(?::\d{2})?)\b/g, (_, date, time) => formatDateTime(`${date} ${time}`))
            .replace(/\b(\d{2}-\d{2}-\d{4})\s+(\d{2}:\d{2})(?::\d{2})?(?:\.\d+)?Z?\b/g, '$1 $2')
            .replace(/\b(\d{4}-\d{2}-\d{2})\b/g, (_, date) => formatDateTime(date, false));
    });
}

document.addEventListener('DOMContentLoaded', () => {
    formatVisibleDates();
    new MutationObserver(mutations => {
        mutations.forEach(mutation => mutation.addedNodes.forEach(node => {
            if (node.nodeType === Node.TEXT_NODE) formatVisibleDates(node.parentElement);
            else if (node.nodeType === Node.ELEMENT_NODE) formatVisibleDates(node);
        }));
    }).observe(document.body, { childList: true, subtree: true });
});

function submitAddMember(e) {
    e.preventDefault();
    const name = document.getElementById("member-input-name").value;
    const phone = document.getElementById("member-input-phone").value;
    const gender = document.getElementById("member-input-gender").value;
    const whatsapp = document.getElementById("member-input-whatsapp").value;
    const imageFile = document.getElementById("member-input-image").files[0];

    const validationError = validateMemberContactFields(phone, whatsapp);
    if (validationError) {
        showAppNotice(validationError, 'error', 5200);
        return;
    }

    const formData = new FormData();
    formData.append("name", name);
    formData.append("phone", phone);
    formData.append("gender", gender);
    formData.append("whatsapp", whatsapp);
    if (imageFile) {
        formData.append("image", imageFile);
    }

    fetch(resolveApiUrl('add_member'), {
        method: 'POST',
        body: formData
    })
    .then(r => r.json())
    .then(res => {
        if (res.success) {
            e.target.reset();
            closeModal("modal-add-member");
            showAppNotice(`تم حفظ المشترك بقاعدة البيانات. رقم العضوية: ${res.id}`, 'success', 5200);
            loadStateAndRender("members");
        } else {
            showAppNotice("خطأ أثناء الحفظ: " + res.error, 'error', 5200);
        }
    });
}

function issueMembershipCard(memberId) {
    if (!memberId) return;

    fetch('/api/issue_membership_card', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ memberId })
    })
    .then(r => r.json())
    .then(res => {
        if (res.success) {
            showAppNotice(`تم إصدار بطاقة جديدة بنجاح. الكود: ${res.cardCode}`, 'success', 6200);
            loadStateAndRender('members');
        } else {
            showAppNotice("خطأ: " + res.error, 'error', 5200);
        }
    });
}

// View 4: Subscriptions List Table
function renderSubscriptions(filterStatus = "all") {
    const tableBody = document.getElementById("subscriptions-table-body");
    if (tableBody) {
        let subsToRender = state.subscriptions;
        if (filterStatus !== "all") {
            subsToRender = state.subscriptions.filter(s => s.status === filterStatus);
        }

        if (subsToRender.length === 0) {
            tableBody.innerHTML = `<tr><td colspan="8" class="text-center" style="color: var(--text-muted);">لا توجد اشتراكات مطابقة للتصفية</td></tr>`;
        } else {
            const visibleSubs = subsToRender.slice(0, 150);
            tableBody.innerHTML = visibleSubs.map(s => {
                const member = state.members.find(m => m.id === s.member_id) || { name: "مجهول" };
                
                let badgeClass = "badge-active";
                if (s.status === "منتهي") badgeClass = "badge-expired";
                if (s.status === "مجمد") badgeClass = "badge-frozen";

                let actionBtnLabel = s.status === "مجمد" ? "استئناف" : "تجميد";
                let isBtnDisabled = s.status === "منتهي" ? 'disabled style="opacity: 0.5; cursor: not-allowed;"' : '';
                
                return `
                    <tr>
                        <td><strong>${member.name}</strong></td>
                        <td>${s.plan_name}</td>
                        <td class="val-mono">${s.start_date}</td>
                        <td class="val-mono">${s.end_date}</td>
                        <td class="val-mono">${formatMoney(s.amount)}</td>
                        <td><span class="${toNumber(s.remaining) > 0 ? 'val-negative' : 'val-positive'}">${formatMoney(s.remaining)}</span></td>
                        <td><span class="badge ${badgeClass}">${s.status}</span></td>
                        <td>
                            <div style="display: flex; gap: 8px;">
                                <button class="btn btn-secondary btn-sm" ${isBtnDisabled} onclick="toggleSubscriptionStatus('${s.id}')">
                                    ${actionBtnLabel}
                                </button>
                                ${state.currentUser && ['مدير النظام', 'المحاسب'].includes(state.currentUser.role) ? `
                                    <button class="btn btn-secondary btn-sm btn-edit-sub" onclick="openEditSubscriptionModal('${s.id}')" title="تعديل">
                                        <i data-lucide="edit-2" style="width: 13px; height: 13px;"></i>
                                    </button>
                                ` : ''}
                            </div>
                        </td>
                    </tr>
                `;
            }).join("");
            
            if (subsToRender.length > 150) {
                tableBody.innerHTML += `
                    <tr>
                        <td colspan="8" class="text-center" style="color: var(--text-muted); font-size: 13px; padding: 12px; border-top: 1px dashed var(--border-color); background-color: rgba(255,255,255,0.01);">
                            تم عرض أحدث 150 اشتراكاً فقط لتسريع تصفح الصفحة. يرجى استخدام تصفية الفئات بالأعلى.
                        </td>
                    </tr>
                `;
            }
        }
    }
    applyTableFiltersForBody("subscriptions-table-body");
}

function filterSubscriptions(status, btnElement) {
    const tabBtns = document.querySelectorAll("#sub-filter-tabs .tab-btn");
    tabBtns.forEach(btn => btn.classList.remove("active"));
    if (btnElement) btnElement.classList.add("active");
    renderSubscriptions(status);
}

function toggleSubscriptionStatus(subId) {
    fetch('/api/toggle_subscription', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ subId })
    })
    .then(r => r.json())
    .then(res => {
        if (res.success) {
            showAppNotice(`تم تغيير حالة الاشتراك إلى: ${res.newStatus}`, 'success');
            loadStateAndRender("subscriptions");
        } else {
            showAppNotice("خطأ: " + res.error, 'error', 5200);
        }
    });
}

function submitAddSubscription(e) {
    e.preventDefault();
    const memberId = document.getElementById("sub-input-member").value;
    const planId = document.getElementById("sub-input-plan").value;
    const startDate = document.getElementById("sub-input-start").value;
    const paid = Number(document.getElementById("sub-input-paid").value);

    fetch(resolveApiUrl('add_subscription'), {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ memberId, planId, startDate, paid })
    })
    .then(r => r.json())
    .then(res => {
        if (res.success) {
            e.target.reset();
            closeModal("modal-add-subscription");
            showAppNotice("تم تفعيل باقة الاشتراك وحفظ السجل المالي بقاعدة البيانات!", 'success');
            loadStateAndRender("subscriptions");
        } else {
            showAppNotice("خطأ: " + res.error, 'error', 5200);
        }
    });
}

// View 5: Subscription Plans card list
function renderPlans() {
    const grid = document.getElementById("plans-grid");
    if (grid) {
        if (!Array.isArray(state.plans) || state.plans.length === 0) {
            grid.innerHTML = `<div class="card dashboard-empty">لا توجد أنواع اشتراكات حالياً. أضف خطة جديدة للبدء.</div>`;
            return;
        }

        grid.innerHTML = state.plans.map(p => `
            <div class="card plan-card">
                <div class="plan-card-actions">
                    <button class="btn-edit-card" title="تعديل الخطة" onclick="openEditPlanModal('${p.id}')">
                        <i data-lucide="edit-2"></i>
                    </button>
                    <button class="btn-delete-card" title="حذف الخطة" onclick="deletePlan('${p.id}')">
                        <i data-lucide="trash-2"></i>
                    </button>
                </div>

                <div class="plan-card-head">
                    <div class="plan-icon-box">
                        <i data-lucide="award"></i>
                    </div>
                    <div class="plan-title-block">
                        <h3 class="plan-name">${p.name}</h3>
                        <span class="plan-id">${p.id}</span>
                    </div>
                </div>

                <div class="plan-details">
                    <span class="plan-meta-row">
                        <i data-lucide="calendar-days"></i>
                        <span>المدة: ${p.days} يوم</span>
                    </span>
                    <span class="plan-meta-row">
                        <i data-lucide="wallet"></i>
                        <span>السعر: <strong class="plan-inline-price">${formatMoney(p.price)}</strong></span>
                    </span>
                    <span class="plan-meta-row">
                        <i data-lucide="file-text"></i>
                        <span>${p.description || 'لا يوجد وصف للخطة'}</span>
                    </span>
                </div>
            </div>
        `).join("");

        lucide.createIcons();
    }
}

function openEditPlanModal(planId) {
    const plan = state.plans.find(p => p.id === planId);
    if (!plan) return;
    openModal("modal-edit-plan");
    const idInput = document.getElementById("edit-plan-input-id");
    const nameInput = document.getElementById("edit-plan-input-name");
    const descInput = document.getElementById("edit-plan-input-desc");
    const priceInput = document.getElementById("edit-plan-input-price");
    const daysInput = document.getElementById("edit-plan-input-days");

    if (idInput) idInput.value = plan.id;
    if (nameInput) nameInput.value = plan.name;
    if (descInput) descInput.value = plan.description || '';
    if (priceInput) priceInput.value = plan.price;
    if (daysInput) daysInput.value = plan.days;
}

function submitEditPlan(e) {
    e.preventDefault();
    const id = document.getElementById("edit-plan-input-id").value;
    const name = document.getElementById("edit-plan-input-name").value;
    const desc = document.getElementById("edit-plan-input-desc").value;
    const price = Number(document.getElementById("edit-plan-input-price").value);
    const days = Number(document.getElementById("edit-plan-input-days").value);

    fetch('/api/edit_plan', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ id, name, desc, price, days })
    })
    .then(r => r.json())
    .then(res => {
        if (res.success) {
            e.target.reset();
            closeModal("modal-edit-plan");
            showAppNotice("تم تعديل باقة الاشتراك بنجاح!", 'success');
            loadStateAndRender("plans");
        } else {
            showAppNotice("خطأ: " + res.error, 'error', 5200);
        }
    });
}

function deletePlan(planId) {
    showAppNotice("يمكن حذف وتعديل باقات التدريب مباشرة من خلال قاعدة البيانات (phpMyAdmin) لتفادي أخطاء حذف باقات نشطة حالياً للأعضاء.", 'info', 6200);
}

async function submitAddPlan(e) {
    e.preventDefault();
    const form = e.currentTarget || e.target;
    const name = (document.getElementById("plan-input-name")?.value || '').trim();
    const desc = (document.getElementById("plan-input-desc")?.value || '').trim();
    const price = Number(document.getElementById("plan-input-price")?.value);
    const days = Number(document.getElementById("plan-input-days")?.value);

    if (!name || !Number.isFinite(price) || price < 0 || !Number.isInteger(days) || days < 1) {
        showAppNotice('يرجى إدخال اسم الخطة وسعر صالح وعدد أيام لا يقل عن يوم واحد.', 'error');
        return;
    }

    const submitButton = form?.querySelector('button[type="submit"]');
    if (submitButton) submitButton.disabled = true;
    try {
        const res = await postApi('add_plan', { name, desc, price, days });
        if (res.success) {
            form?.reset();
            closeModal("modal-add-plan");
            showAppNotice("تم إنشاء باقة الاشتراك وحفظها بقاعدة البيانات!", 'success');
            loadStateAndRender("plans");
        } else {
            showAppNotice("خطأ أثناء حفظ الخطة: " + (res.error || 'تعذر إتمام العملية.'), 'error', 5200);
        }
    } catch (error) {
        showAppNotice("تعذر الاتصال بالخادم أثناء حفظ الخطة. أعد المحاولة.", 'error', 5200);
    } finally {
        if (submitButton) submitButton.disabled = false;
    }
}

// View 6: Payments List
function renderPayments() {
    const tableBody = document.getElementById("payments-table-body");
    if (tableBody) {
        const visiblePayments = state.payments.slice(0, 150);
        tableBody.innerHTML = visiblePayments.map(p => {
            const editBtn = state.currentUser && ['مدير النظام', 'المحاسب'].includes(state.currentUser.role) ? `
                <button class="btn btn-secondary btn-sm btn-edit-pay" onclick="openEditPaymentModal('${p.id}')" title="تعديل">
                    <i data-lucide="edit-2" style="width: 13px; height: 13px;"></i>
                </button>
            ` : '';
            return `
                <tr>
                    <td class="val-mono">${p.date}</td>
                    <td><strong>${getPaymentMemberDisplay(p)}</strong></td>
                    <td class="val-positive">${formatMoney(p.amount)}</td>
                    <td>${p.method}</td>
                    <td><span style="color: var(--text-muted); font-size: 13px;">${p.note}</span></td>
                    <td>
                        <div style="display: flex; gap: 8px;">
                            ${editBtn}
                        </div>
                    </td>
                </tr>
            `;
        }).join("");
        
        if (state.payments.length > 150) {
            tableBody.innerHTML += `
                <tr>
                    <td colspan="6" class="text-center" style="color: var(--text-muted); font-size: 13px; padding: 12px; border-top: 1px dashed var(--border-color); background-color: rgba(255,255,255,0.01);">
                        تم عرض أحدث 150 دفعة مالية فقط لتسريع العرض.
                    </td>
                </tr>
            `;
        }
    }
    applyTableFiltersForBody("payments-table-body");
    
    // Update summary in payment controls bar
    let totalAllTimePayments = state.payments.reduce((sum, p) => sum + Number(p.amount), 0);
    const totalPaymentsValueEl = document.getElementById("total-payments-value");
    if (totalPaymentsValueEl) totalPaymentsValueEl.innerText = totalAllTimePayments.toFixed(2) + " ₪";
}

function togglePaymentTransferAccountField(selectId, groupId, inputId) {
    const methodSelect = document.getElementById(selectId);
    const group = document.getElementById(groupId);
    const input = document.getElementById(inputId);
    if (!methodSelect || !group || !input) return;

    const isTransfer = methodSelect.value === 'تحويل';
    group.style.display = isTransfer ? 'block' : 'none';
    input.required = isTransfer;
    if (!isTransfer) {
        input.value = '';
    }
}

function submitAddPayment(e) {
    e.preventDefault();
    const memberId = document.getElementById("pay-input-member").value;
    const amount = Number(document.getElementById("pay-input-amount").value);
    const method = document.getElementById("pay-input-method").value;
    const transferFromAccount = document.getElementById("pay-input-transfer-from-account")?.value.trim() || "";
    const note = document.getElementById("pay-input-note").value || "—";

    if (method === 'تحويل' && !transferFromAccount) {
        showAppNotice("يرجى إدخال اسم الحساب المحول منه عند اختيار التحويل.", 'warning', 5200);
        return;
    }

    const payload = { memberId, amount, method, transferFromAccount, note };

    if (!navigator.onLine) {
        queueOfflineSyncEvent('add_payment', payload);
        e.target.reset();
        closeModal("modal-add-payment");
        showAppNotice("لا يوجد اتصال. تم حفظ الدفعة محلياً وستتم مزامنتها تلقائياً.", 'warning', 6200);
        refreshNetworkStatusBadge();
        return;
    }

    postApi('add_payment', payload).then(res => {
        if (res.success) {
            e.target.reset();
            closeModal("modal-add-payment");
            showAppNotice("تم حفظ السند المالي وتحديث مستحقات المشترك!", 'success');
            loadStateAndRender("payments");
        } else {
            showAppNotice("خطأ: " + res.error, 'error', 5200);
        }
    }).catch(() => {
        queueOfflineSyncEvent('add_payment', payload);
        e.target.reset();
        closeModal("modal-add-payment");
        showAppNotice("تعذر الوصول للخادم. تم حفظ الدفعة وسيتم مزامنتها تلقائياً.", 'warning', 6200);
        refreshNetworkStatusBadge();
    });
}

// View 7: Supplement Products Grid POS
function renderProducts() {
    const grid = document.getElementById("products-grid");
    const datePill = document.getElementById("products-date-pill");

    if (datePill) {
        const now = new Date();
        const formatted = now.toLocaleString('ar-EG', {
            weekday: 'long',
            year: 'numeric',
            month: 'long',
            day: 'numeric',
            hour: 'numeric',
            minute: '2-digit'
        });
        datePill.innerText = formatted;
    }

    if (grid) {
        grid.innerHTML = state.products.map(p => {
            const isOutOfStock = p.stock <= 0;
            return `
                <div class="card product-card">
                    <button class="btn-edit-card product-edit-btn" title="تعديل المنتج والمخزون" onclick="event.stopPropagation(); openEditProductStockModal('${p.id}', '${p.name.replace(/'/g, "\\'")}', ${p.price}, ${p.stock})">
                        <i data-lucide="edit-2" style="width: 14px; height: 14px;"></i>
                    </button>
                    <div class="product-image-container">
                        <i data-lucide="package"></i>
                    </div>
                    <div class="product-info">
                        <h3 class="product-name">${p.name}</h3>
                        <span class="product-price">${formatMoney(p.price)}</span>
                        <span class="product-stock" id="stock-label-${p.id}">المخزون: ${p.stock}</span>
                    </div>
                    <button class="btn btn-primary btn-add-basket" ${isOutOfStock ? 'disabled' : ''} onclick="addToBasket('${p.id}')">
                        <i data-lucide="shopping-cart"></i>
                        <span>${isOutOfStock ? 'منتهي المخزون' : 'أضف للسلة'}</span>
                    </button>
                </div>
            `;
        }).join("");
    }
    renderBasket();
}

function openEditProductStockModal(id, name, price, stock) {
    openModal("modal-edit-product-stock");
    const idInput = document.getElementById("edit-product-stock-id");
    const nameInput = document.getElementById("edit-product-stock-name");
    const priceInput = document.getElementById("edit-product-stock-price");
    const qtyInput = document.getElementById("edit-product-stock-qty");
    if (idInput) idInput.value = id;
    if (nameInput) nameInput.value = name;
    if (priceInput) priceInput.value = price;
    if (qtyInput) qtyInput.value = stock;
}

function submitEditProductStock(e) {
    e.preventDefault();
    const id = document.getElementById("edit-product-stock-id").value;
    const name = document.getElementById("edit-product-stock-name").value;
    const price = Number(document.getElementById("edit-product-stock-price").value);
    const stock = Number(document.getElementById("edit-product-stock-qty").value);

    fetch('/api/edit_product', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ id, name, price, stock })
    })
    .then(r => r.json())
    .then(res => {
        if (res.success) {
            closeModal("modal-edit-product-stock");
            showAppNotice("تم حفظ التغييرات وتحديث المخزون بنجاح!", 'success');
            loadStateAndRender("products");
        } else {
            showAppNotice("خطأ: " + res.error, 'error', 5200);
        }
    });
}

function submitAddProduct(e) {
    e.preventDefault();
    const name = document.getElementById("product-input-name").value;
    const price = Number(document.getElementById("product-input-price").value);
    const stock = Number(document.getElementById("product-input-stock").value);

    fetch(resolveApiUrl('add_product'), {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ name, price, stock })
    })
    .then(r => r.json())
    .then(res => {
        if (res.success) {
            e.target.reset();
            closeModal("modal-add-product");
            showAppNotice("تم إضافة المنتج الجديد وتخزينه بالمخزن!", 'success');
            loadStateAndRender("products");
        } else {
            showAppNotice("خطأ: " + res.error, 'error', 5200);
        }
    });
}

function addToBasket(productId) {
    const product = state.products.find(p => p.id === productId);
    if (product) {
        const basketItem = state.basket.find(item => item.product.id === productId);
        const currentInBasket = basketItem ? basketItem.qty : 0;
        
        if (product.stock > currentInBasket) {
            if (basketItem) {
                basketItem.qty += 1;
            } else {
                state.basket.push({ product, qty: 1 });
            }
            renderBasket();
        } else {
            showAppNotice("لا يمكن إضافة المزيد، لقد انتهت الكمية المتاحة بالمخزن!", 'warning');
        }
    }
}

function removeFromBasket(productId) {
    state.basket = state.basket.filter(item => item.product.id !== productId);
    renderBasket();
}

function renderBasket() {
    const container = document.getElementById("basket-items-container");
    if (!container) return;

    if (state.basket.length === 0) {
        container.innerHTML = `
            <div class="basket-empty">
                <i data-lucide="shopping-basket" style="width: 32px; height: 32px;"></i>
                <span>السلة فارغة</span>
            </div>
        `;
        document.getElementById("basket-total").innerText = "0";
        lucide.createIcons();
    } else {
        container.innerHTML = state.basket.map(item => `
            <div class="basket-item">
                <div class="basket-item-info">
                    <span class="basket-item-name">${item.product.name}</span>
                    <span class="basket-item-qty">الكمية: ${item.qty}</span>
                </div>
                <div style="display: flex; align-items: center; gap: 12px;">
                    <span class="basket-item-price">${(item.product.price * item.qty).toFixed(2)} ₪</span>
                    <button class="btn-delete-card" onclick="removeFromBasket('${item.product.id}')" title="إزالة">
                        <i data-lucide="x" style="width: 14px; height: 14px;"></i>
                    </button>
                </div>
            </div>
        `).join("");
        
        let total = state.basket.reduce((sum, item) => sum + (item.product.price * item.qty), 0);
        document.getElementById("basket-total").innerText = total.toFixed(2) + " ₪";
        lucide.createIcons();
    }
}

function checkoutBasket() {
    if (state.basket.length === 0) {
        showAppNotice("سلة المشتريات فارغة!", 'warning');
        return;
    }

    const paymentMethod = document.getElementById("basket-payment-method").value;
    const memberId = document.getElementById("basket-member-select") ? document.getElementById("basket-member-select").value : '';
    const transferFromAccount = document.getElementById("basket-transfer-from-account")?.value.trim() || '';

    if (paymentMethod === 'تحويل' && !transferFromAccount) {
        showAppNotice("يرجى إدخال اسم الشخص أو الحساب المُحوِّل عند اختيار التحويل.", 'warning', 5200);
        return;
    }
    
    const payload = { basket: state.basket, paymentMethod, memberId, transferFromAccount };

    if (!navigator.onLine) {
        queueOfflineSyncEvent('checkout_basket', payload);
        state.basket = [];
        renderBasket();
        showAppNotice("لا يوجد اتصال. تم حفظ عملية البيع محلياً وستتم مزامنتها تلقائياً.", 'warning', 6200);
        refreshNetworkStatusBadge();
        return;
    }

    postApi('checkout_basket', payload).then(res => {
        if (res.success) {
            state.basket = [];
            showAppNotice("تم إتمام عملية البيع وخصم الكميات من المخزن وحفظ الفاتورة بقاعدة البيانات!", 'success');
            loadStateAndRender("products");
        } else {
            showAppNotice("خطأ أثناء البيع: " + res.error, 'error', 5200);
        }
    }).catch(() => {
        queueOfflineSyncEvent('checkout_basket', payload);
        state.basket = [];
        renderBasket();
        showAppNotice("تعذر الوصول للخادم. تم حفظ البيع وسيتم مزامنته تلقائياً.", 'warning', 6200);
        refreshNetworkStatusBadge();
    });
}

// View 8: Reports page
function renderReports() {
    const reports = state.reports || {};

    safeSetText("rep-today-attendance", state.todayAttendance);
    safeSetText("rep-month-revenue", formatMoney(state.monthlyRevenue));
    safeSetText("rep-product-sales", formatMoney(state.productSales));
    safeSetText("rep-renewal-rate", `${toNumber(reports.renewalRate || state.renewalRate).toFixed(2)}%`);
    safeSetText("rep-debts-total", formatMoney(reports.debtsTotal));
    safeSetText("rep-debt-members", toNumber(reports.debtMembersCount));
    safeSetText("rep-renewed-count", toNumber(reports.renewedLast30Days));
    safeSetText("rep-low-stock-count", Array.isArray(reports.stockAlerts) ? reports.stockAlerts.length : 0);
    renderPaymentMethodReport();

    const tableBody = document.getElementById("sales-table-body");
    if (tableBody) {
        const visibleSales = state.sales.slice(0, 150);
        tableBody.innerHTML = visibleSales.map(s => `
            <tr>
                <td class="val-mono">${s.date}</td>
                <td><strong>${s.products}</strong></td>
                <td class="val-positive">${formatMoney(s.total)}</td>
                <td>${s.method}</td>
            </tr>
        `).join("");
        
        if (state.sales.length > 150) {
            tableBody.innerHTML += `
                <tr>
                    <td colspan="4" class="text-center" style="color: var(--text-muted); font-size: 13px; padding: 12px; border-top: 1px dashed var(--border-color); background-color: rgba(255,255,255,0.01);">
                        تم عرض أحدث 150 فاتورة مبيعات فقط لتسريع العرض.
                    </td>
                </tr>
            `;
        }
    }
    applyTableFiltersForBody("sales-table-body");

    const topProductsBody = document.getElementById("top-products-table-body");
    if (topProductsBody) {
        const topProducts = Array.isArray(reports.topProductSales) ? reports.topProductSales : [];
        if (topProducts.length === 0) {
            topProductsBody.innerHTML = `<tr><td colspan="3" class="text-center" style="color: var(--text-muted);">لا تتوفر بيانات مبيعات منتجات كافية</td></tr>`;
        } else {
            topProductsBody.innerHTML = topProducts.map(item => `
                <tr>
                    <td><strong>${item.productName || item.productId || '—'}</strong></td>
                    <td class="val-mono">${toNumber(item.quantity)}</td>
                    <td class="val-positive">${formatMoney(item.total)}</td>
                </tr>
            `).join("");
        }
    }

    const stockAlertsBody = document.getElementById("stock-alerts-table-body");
    if (stockAlertsBody) {
        const stockAlerts = Array.isArray(reports.stockAlerts) ? reports.stockAlerts : [];
        if (stockAlerts.length === 0) {
            stockAlertsBody.innerHTML = `<tr><td colspan="3" class="text-center" style="color: var(--text-muted);">المخزون ضمن المستوى الآمن</td></tr>`;
        } else {
            stockAlertsBody.innerHTML = stockAlerts.map(item => `
                <tr>
                    <td class="val-mono">${item.id || '—'}</td>
                    <td><strong>${item.name || '—'}</strong></td>
                    <td class="${toNumber(item.stock) <= 0 ? 'val-negative' : 'val-mono'}">${toNumber(item.stock)}</td>
                </tr>
            `).join("");
        }
    }

    const trainerPerformanceBody = document.getElementById("trainer-performance-table-body");
    if (trainerPerformanceBody) {
        const trainerPerformance = Array.isArray(reports.trainerPerformance) ? reports.trainerPerformance : [];
        if (trainerPerformance.length === 0) {
            trainerPerformanceBody.innerHTML = `<tr><td colspan="5" class="text-center" style="color: var(--text-muted);">لا تتوفر بيانات مدربين</td></tr>`;
        } else {
            trainerPerformanceBody.innerHTML = trainerPerformance.map(item => `
                <tr>
                    <td><strong>${item.trainerName || ('مدرب #' + item.trainerId)}</strong></td>
                    <td>${item.specialty || '—'}</td>
                    <td class="val-mono">${toNumber(item.activeMembers)}</td>
                    <td class="val-mono">${toNumber(item.trainingPrograms)}</td>
                    <td class="val-mono">${toNumber(item.nutritionPrograms)}</td>
                </tr>
            `).join("");
        }
    }

    const heatmapGrid = document.getElementById("attendance-heatmap-grid");
    if (heatmapGrid) {
        const heatmap = Array.isArray(reports.attendanceHeatmap) ? reports.attendanceHeatmap : [];
        const maxCount = heatmap.reduce((max, item) => Math.max(max, toNumber(item.count)), 0);
        heatmapGrid.innerHTML = heatmap.map(item => {
            const count = toNumber(item.count);
            const opacity = maxCount > 0 ? Math.max(0.15, count / maxCount) : 0.12;
            return `
                <div style="border: 1px solid var(--border-color); border-radius: 10px; padding: 10px; background: rgba(86, 132, 255, ${opacity.toFixed(2)}); text-align: center;">
                    <div style="font-size: 12px; color: var(--text-muted); margin-bottom: 4px;">${item.weekday || '—'}</div>
                    <div class="val-mono" style="font-size: 16px; font-weight: 700;">${count}</div>
                </div>
            `;
        }).join("");
    }

    applyTableFiltersForBody("top-products-table-body");
    applyTableFiltersForBody("stock-alerts-table-body");
    applyTableFiltersForBody("trainer-performance-table-body");

    // Load reports graphs
    initRevenueChart("chart-revenue-reports", "reports");
    initAttendanceChart("chart-attendance-reports", "reports");
}

function renderPaymentMethodReport() {
    const body = document.getElementById('payment-method-report-body');
    if (!body) return;

    const method = document.getElementById('payment-report-method')?.value || 'all';
    const query = (document.getElementById('payment-report-transfer-search')?.value || '').trim().toLowerCase();
    const payments = Array.isArray(state.payments) ? state.payments : [];
    const cashTotal = payments.filter(p => p.method === 'نقدي').reduce((sum, p) => sum + toNumber(p.amount), 0);
    const transfers = payments.filter(p => p.method === 'تحويل');
    const transferTotal = transfers.reduce((sum, p) => sum + toNumber(p.amount), 0);

    safeSetText('rep-cash-total', formatMoney(cashTotal));
    safeSetText('rep-transfer-total', formatMoney(transferTotal));
    safeSetText('rep-transfer-count', transfers.length);

    const filtered = payments.filter(payment => {
        const transferSource = String(payment.transfer_from_account || '');
        return (method === 'all' || payment.method === method)
            && (!query || transferSource.toLowerCase().includes(query));
    });

    body.innerHTML = filtered.length ? filtered.map(payment => `
        <tr>
            <td class="val-mono">${formatDateTime(payment.date)}</td>
            <td>${payment.member_name || '—'}</td>
            <td class="val-positive">${formatMoney(payment.amount)}</td>
            <td><span class="badge ${payment.method === 'تحويل' ? 'badge-frozen' : 'badge-active'}">${payment.method || '—'}</span></td>
            <td>${payment.method === 'تحويل' ? (payment.transfer_from_account || '—') : '—'}</td>
            <td>${payment.note || '—'}</td>
        </tr>
    `).join('') : '<tr><td colspan="6" class="text-center" style="color:var(--text-muted)">لا توجد دفعات مطابقة للفلتر</td></tr>';
}

function renderNotifications() {
    const templatesBody = document.getElementById("notification-templates-table-body");
    const notificationsBody = document.getElementById("notifications-table-body");
    const templates = Array.isArray(state.notificationTemplates) ? state.notificationTemplates : [];
    const notifications = Array.isArray(state.notifications) ? state.notifications : [];

    if (templatesBody) {
        if (templates.length === 0) {
            templatesBody.innerHTML = `<tr><td colspan="5" class="text-center" style="color: var(--text-muted);">لا توجد قوالب إشعارات بعد</td></tr>`;
        } else {
            templatesBody.innerHTML = templates.map(t => `
                <tr>
                    <td><strong>${t.name || '—'}</strong></td>
                    <td>${t.channel || '—'}</td>
                    <td class="val-mono">${t.type || '—'}</td>
                    <td>${t.title_template || '—'}</td>
                    <td><span class="badge ${t.is_active ? 'badge-active' : 'badge-frozen'}">${t.is_active ? 'نشط' : 'غير نشط'}</span></td>
                </tr>
            `).join("");
        }
    }

    if (notificationsBody) {
        if (notifications.length === 0) {
            notificationsBody.innerHTML = `<tr><td colspan="6" class="text-center" style="color: var(--text-muted);">لا يوجد سجل إشعارات</td></tr>`;
        } else {
            notificationsBody.innerHTML = notifications.slice(0, 300).map(n => {
                const memberName = (n.member_id ? (state.members.find(m => String(m.id) === String(n.member_id))?.name || n.member_id) : '—');
                return `
                    <tr>
                        <td class="val-mono">${n.sent_at || n.scheduled_at || n.created_at || '—'}</td>
                        <td>${memberName}</td>
                        <td>${n.channel || '—'}</td>
                        <td class="val-mono">${n.type || '—'}</td>
                        <td>${n.title || '—'}</td>
                        <td><span class="badge ${n.status === 'sent' ? 'badge-active' : (n.status === 'failed' ? 'badge-expired' : 'badge-frozen')}">${n.status || 'pending'}</span></td>
                    </tr>
                `;
            }).join("");
        }
    }

    const btnProcessJobs = document.getElementById("btn-process-notification-jobs");
    if (btnProcessJobs) {
        const isAdmin = state.currentUser && state.currentUser.role === 'مدير النظام';
        btnProcessJobs.style.display = isAdmin ? '' : 'none';
    }

    applyTableFiltersForBody("notification-templates-table-body");
    applyTableFiltersForBody("notifications-table-body");
}

function renderSyncCenter() {
    loadSyncCenterData();

    const statusFilter = document.getElementById("sync-filter-status");
    const actionFilter = document.getElementById("sync-filter-action");
    const fromFilter = document.getElementById("sync-filter-from");
    const toFilter = document.getElementById("sync-filter-to");

    [statusFilter, actionFilter, fromFilter, toFilter].forEach(el => {
        if (el && !el.dataset.bound) {
            el.dataset.bound = '1';
            el.addEventListener('change', () => loadSyncCenterData());
        }
    });

    const retryBtn = document.getElementById("btn-retry-failed-sync");
    if (retryBtn) {
        const isAuditor = state.currentUser && state.currentUser.role === 'المدقق المالي';
        retryBtn.style.display = isAuditor ? 'none' : '';
    }

    const flushBtn = document.getElementById("btn-flush-local-queue");
    if (flushBtn) {
        const isAuditor = state.currentUser && state.currentUser.role === 'المدقق المالي';
        flushBtn.style.display = isAuditor ? 'none' : '';
    }
}

function loadSyncCenterData() {
    const localBody = document.getElementById("local-sync-queue-body");
    const serverBody = document.getElementById("sync-events-table-body");
    const summary = document.getElementById("sync-center-summary");

    const localQueue = readOfflineQueue();

    if (localBody) {
        if (localQueue.length === 0) {
            localBody.innerHTML = `<tr><td colspan="4" class="text-center" style="color: var(--text-muted);">لا توجد عمليات معلقة محلياً</td></tr>`;
        } else {
            localBody.innerHTML = localQueue.map(item => `
                <tr>
                    <td class="val-mono">${item.createdAt || '—'}</td>
                    <td class="val-mono">${item.action || '—'}</td>
                    <td class="val-mono">${item.clientEventId || '—'}</td>
                    <td style="max-width: 340px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">${JSON.stringify(item.payload || {})}</td>
                </tr>
            `).join("");
        }
    }

    const statusFilter = document.getElementById("sync-filter-status")?.value || '';
    const actionFilter = document.getElementById("sync-filter-action")?.value || '';
    const fromFilter = document.getElementById("sync-filter-from")?.value || '';
    const toFilter = document.getElementById("sync-filter-to")?.value || '';
    const query = new URLSearchParams();
    if (statusFilter) query.set('status', statusFilter);
    if (actionFilter) query.set('action', actionFilter);
    if (fromFilter) query.set('from', fromFilter);
    if (toFilter) query.set('to', toFilter);

    fetch(`/api/sync_events_log${query.toString() ? `?${query.toString()}` : ''}`)
        .then(r => r.json())
        .then(res => {
            if (!res.success) {
                if (serverBody) {
                    serverBody.innerHTML = `<tr><td colspan="6" class="text-center" style="color: var(--text-muted);">تعذر تحميل سجل المزامنة</td></tr>`;
                }
                return;
            }

            const events = Array.isArray(res.events) ? res.events : [];

            if (serverBody) {
                if (events.length === 0) {
                    serverBody.innerHTML = `<tr><td colspan="7" class="text-center" style="color: var(--text-muted);">لا توجد أحداث مزامنة حتى الآن</td></tr>`;
                } else {
                    serverBody.innerHTML = events.map(ev => `
                        <tr>
                            <td class="val-mono">${ev.id}</td>
                            <td class="val-mono">${ev.created_at || '—'}</td>
                            <td class="val-mono">${ev.action || '—'}</td>
                            <td><span class="badge ${ev.status === 'processed' ? 'badge-active' : (ev.status === 'failed' ? 'badge-expired' : 'badge-frozen')}">${ev.status || 'pending'}</span></td>
                            <td>${ev.result_message || '—'}</td>
                            <td class="val-mono">${ev.client_event_id || '—'}</td>
                            <td><button class="btn btn-secondary btn-sm" onclick="openSyncEventDetail(${ev.id})">تفاصيل</button></td>
                        </tr>
                    `).join("");
                }
            }

            state.syncEvents = events;
            state.syncEventsHydrated = true;
            state.syncCenterStats = res.stats || null;

            if (summary) {
                const failed = events.filter(ev => ev.status === 'failed').length;
                const stats = res.stats || {};
                const avgSeconds = Number(stats.averageProcessingSeconds || 0);
                summary.innerText = `محلي: ${localQueue.length} | خادم: ${events.length} | فاشل: ${failed} | متوسط المعالجة: ${avgSeconds.toFixed(2)} ثانية`;
            }

            const syncStats = res.stats || {};
            safeSetText('sync-stat-total', syncStats.total ?? events.length);
            safeSetText('sync-stat-processed', syncStats.processed ?? 0);
            safeSetText('sync-stat-failed', syncStats.failed ?? 0);
            safeSetText('sync-stat-average', `${toNumber(syncStats.averageProcessingSeconds).toFixed(2)} s`);

            maybeAutoRetryFailedSyncEvents(events);

            applyTableFiltersForBody("local-sync-queue-body");
            applyTableFiltersForBody("sync-events-table-body");
        })
        .catch(() => {
            if (serverBody) {
                serverBody.innerHTML = `<tr><td colspan="6" class="text-center" style="color: var(--text-muted);">فشل الاتصال بخدمة سجل المزامنة</td></tr>`;
            }
        });
}

function retryFailedSyncEvents() {
    fetch('/api/sync_events_log?status=failed')
        .then(r => r.json())
        .then(res => {
            const failedRows = (res.events || []).filter(ev => ev.status === 'failed');
            if (failedRows.length === 0) {
                showAppNotice('لا توجد أحداث فاشلة لإعادة المحاولة.');
                return;
            }

            const events = failedRows.map(ev => ({
                clientEventId: ev.client_event_id || `retry_${ev.id}_${Date.now()}`,
                action: ev.action,
                payload: ev.payload || {}
            }));

            return postApi('sync_push_queue', { events }).then(pushRes => {
                if (pushRes && pushRes.success) {
                    showAppNotice(`تمت إعادة محاولة ${events.length} حدث.`);
                } else {
                    showAppNotice('تعذر إعادة المحاولة حالياً.');
                }
                loadSyncCenterData();
            });
        })
        .catch(() => showAppNotice('تعذر الوصول لسجل المزامنة.'));
}

function openSyncEventDetail(eventId) {
    state.selectedSyncEventId = String(eventId);
    switchView('sync-event-detail');
}

function renderSyncEventDetailPage() {
    if (!state.selectedSyncEventId) {
        safeSetText('sync-detail-page-result', 'لا يوجد حدث محدد.');
        return;
    }

    let eventItem = (Array.isArray(state.syncEvents) ? state.syncEvents : []).find(ev => String(ev.id) === String(state.selectedSyncEventId));

    if (!eventItem) {
        fetch(`/api/sync_events_log?id=${encodeURIComponent(state.selectedSyncEventId)}`)
            .then(r => r.json())
            .then(res => {
                if (res.success && Array.isArray(res.events) && res.events.length > 0) {
                    const loaded = res.events[0];
                    const existing = Array.isArray(state.syncEvents) ? state.syncEvents : [];
                    const already = existing.find(ev => String(ev.id) === String(loaded.id));
                    if (!already) {
                        state.syncEvents = [loaded, ...existing];
                    }
                    renderSyncEventDetailPage();
                } else {
                    safeSetText('sync-detail-page-result', 'الحدث غير موجود أو لا تملك صلاحية عرضه.');
                }
            })
            .catch(() => {
                safeSetText('sync-detail-page-result', 'تعذر تحميل تفاصيل الحدث من الخادم.');
            });
        return;
    }

    safeSetText('sync-detail-page-id', eventItem.id || '—');
    safeSetText('sync-detail-page-time', eventItem.created_at || '—');
    safeSetText('sync-detail-page-action', eventItem.action || '—');
    safeSetText('sync-detail-page-status', eventItem.status || '—');
    safeSetText('sync-detail-page-result', eventItem.result_message || '—');
    safeSetText('sync-detail-page-client-id', eventItem.client_event_id || '—');

    const processingText = `processed_at: ${eventItem.processed_at || '—'} | updated_at: ${eventItem.updated_at || '—'}`;
    safeSetText('sync-detail-page-processing', processingText);

    const payloadEl = document.getElementById('sync-detail-page-payload');
    if (payloadEl) {
        payloadEl.textContent = JSON.stringify(eventItem.payload || {}, null, 2);
    }

    if (!state.syncEventsHydrated && (Array.isArray(state.syncEvents) ? state.syncEvents.length : 0) <= 1) {
        fetch('/api/sync_events_log')
            .then(r => r.json())
            .then(res => {
                if (res.success && Array.isArray(res.events)) {
                    state.syncEvents = res.events;
                    state.syncEventsHydrated = true;
                    renderSyncEventDetailPage();
                }
            })
            .catch(() => {
                // Keep current detail data even if full list hydration fails.
            });
    }

    const ordered = (Array.isArray(state.syncEvents) ? [...state.syncEvents] : []).sort((a, b) => Number(b.id) - Number(a.id));
    const currentIndex = ordered.findIndex(ev => String(ev.id) === String(state.selectedSyncEventId));
    const prevBtn = document.getElementById('sync-detail-prev');
    const nextBtn = document.getElementById('sync-detail-next');
    if (prevBtn) prevBtn.disabled = currentIndex <= 0;
    if (nextBtn) nextBtn.disabled = currentIndex < 0 || currentIndex >= ordered.length - 1;
}

function gotoAdjacentSyncEvent(direction) {
    const ordered = (Array.isArray(state.syncEvents) ? [...state.syncEvents] : []).sort((a, b) => Number(b.id) - Number(a.id));
    const currentIndex = ordered.findIndex(ev => String(ev.id) === String(state.selectedSyncEventId));
    if (currentIndex < 0) return;

    const targetIndex = currentIndex + (direction > 0 ? 1 : -1);
    if (targetIndex < 0 || targetIndex >= ordered.length) return;

    state.selectedSyncEventId = String(ordered[targetIndex].id);
    updateViewQueryParams('sync-event-detail');
    renderSyncEventDetailPage();
}

function downloadSyncEventsCsv() {
    const events = Array.isArray(state.syncEvents) ? state.syncEvents : [];
    if (events.length === 0) {
        showAppNotice('لا توجد بيانات لتصديرها.');
        return;
    }

    const header = ['id', 'created_at', 'action', 'status', 'result_message', 'client_event_id'];
    const rows = events.map(ev => [
        ev.id,
        ev.created_at || '',
        ev.action || '',
        ev.status || '',
        (ev.result_message || '').replace(/"/g, '""'),
        ev.client_event_id || ''
    ]);

    const csv = [header.join(','), ...rows.map(cols => cols.map(val => `"${String(val ?? '')}"`).join(','))].join('\n');
    const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
    const url = URL.createObjectURL(blob);
    const link = document.createElement('a');
    link.href = url;
    link.download = `sync-events-${new Date().toISOString().slice(0, 19).replace(/[:T]/g, '-')}.csv`;
    document.body.appendChild(link);
    link.click();
    link.remove();
    URL.revokeObjectURL(url);
}

function downloadSyncEventsXlsx() {
    const events = Array.isArray(state.syncEvents) ? state.syncEvents : [];
    if (events.length === 0) {
        showAppNotice('لا توجد بيانات لتصديرها.');
        return;
    }

    if (typeof XLSX === 'undefined') {
        showAppNotice('مكتبة Excel غير متاحة حالياً.');
        return;
    }

    const rows = events.map(ev => ({
        id: ev.id,
        created_at: ev.created_at || '',
        action: ev.action || '',
        status: ev.status || '',
        result_message: ev.result_message || '',
        client_event_id: ev.client_event_id || '',
        processed_at: ev.processed_at || '',
    }));

    const worksheet = XLSX.utils.json_to_sheet(rows);
    const workbook = XLSX.utils.book_new();
    XLSX.utils.book_append_sheet(workbook, worksheet, 'sync_events');

    const fileName = `sync-events-${new Date().toISOString().slice(0, 19).replace(/[:T]/g, '-')}.xlsx`;
    XLSX.writeFile(workbook, fileName);
}

function maybeAutoRetryFailedSyncEvents(events) {
    if (!navigator.onLine || !Array.isArray(events) || events.length === 0) return;
    if (state.currentUser && state.currentUser.role === 'المدقق المالي') return;

    const failedCount = events.filter(ev => ev.status === 'failed').length;
    if (failedCount === 0) return;

    const lastAttempt = Number(localStorage.getItem('gym_sync_auto_retry_at') || 0);
    const nowTs = Date.now();
    if (nowTs - lastAttempt < 10 * 60 * 1000) return;

    localStorage.setItem('gym_sync_auto_retry_at', String(nowTs));
    retryFailedSyncEvents();
}

function submitAddNotificationTemplate(e) {
    e.preventDefault();

    const name = (document.getElementById("notification-template-name")?.value || '').trim();
    const channel = document.getElementById("notification-template-channel")?.value || 'whatsapp';
    const type = (document.getElementById("notification-template-type")?.value || '').trim();
    const titleTemplate = (document.getElementById("notification-template-title")?.value || '').trim();
    const bodyTemplate = (document.getElementById("notification-template-body")?.value || '').trim();

    fetch(resolveApiUrl('add_notification_template'), {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ name, channel, type, titleTemplate, bodyTemplate, isActive: true })
    })
    .then(r => r.json())
    .then(res => {
        if (res.success) {
            e.target.reset();
            closeModal("modal-add-notification-template");
            showAppNotice("تم حفظ قالب الإشعار بنجاح");
            loadStateAndRender("notifications");
        } else {
            showAppNotice("خطأ: " + res.error);
        }
    });
}

function submitSendNotification(e) {
    e.preventDefault();

    const templateIdRaw = document.getElementById("send-notification-template-id")?.value || '';
    const memberIdRaw = document.getElementById("send-notification-member-id")?.value || '';
    const channel = document.getElementById("send-notification-channel")?.value || 'whatsapp';
    const type = (document.getElementById("send-notification-type")?.value || 'manual').trim();
    const title = (document.getElementById("send-notification-title")?.value || '').trim();
    const body = (document.getElementById("send-notification-body")?.value || '').trim();

    const payload = {
        memberId: memberIdRaw || null,
    };

    if (templateIdRaw) {
        payload.templateId = Number(templateIdRaw);
    } else {
        if (!title || !body) {
            showAppNotice("عند الإرسال اليدوي، يجب إدخال العنوان والمحتوى");
            return;
        }
        payload.channel = channel;
        payload.type = type || 'manual';
        payload.title = title;
        payload.body = body;
    }

    fetch('/api/send_notification', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload)
    })
    .then(r => r.json())
    .then(res => {
        if (res.success) {
            e.target.reset();
            closeModal("modal-send-notification");
            showAppNotice("تم إرسال الإشعار بنجاح");
            loadStateAndRender("notifications");
        } else {
            showAppNotice("خطأ: " + res.error);
        }
    });
}

function syncSendNotificationMode() {
    const templateSelect = document.getElementById("send-notification-template-id");
    const manualFields = [
        document.getElementById("send-notification-channel"),
        document.getElementById("send-notification-type"),
        document.getElementById("send-notification-title"),
        document.getElementById("send-notification-body"),
    ];
    const hint = document.getElementById("send-notification-mode-hint");

    if (!templateSelect) return;

    const hasTemplate = !!String(templateSelect.value || "").trim();
    manualFields.forEach((field) => {
        if (!field) return;
        field.disabled = hasTemplate;
        field.style.opacity = hasTemplate ? "0.65" : "1";
    });

    if (hint) {
        hint.textContent = hasTemplate
            ? "تم اختيار قالب: سيتم إنشاء العنوان والمحتوى تلقائياً من القالب."
            : "إرسال يدوي: يجب إدخال العنوان والمحتوى.";
    }
}

function runNotificationJobs() {
    if (!confirm("هل تريد تشغيل تذكيرات الاشتراكات الآن؟")) return;

    fetch('/api/process_notification_jobs', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({})
    })
    .then(r => r.json())
    .then(res => {
        if (res.success) {
            const summary = res.summary || {};
            showAppNotice(
                "تم تشغيل التذكيرات\n" +
                "تنتهي خلال 3 أيام: " + (summary.expiring_3_days || 0) + "\n" +
                "تنتهي اليوم: " + (summary.expires_today || 0) + "\n" +
                "منتهية: " + (summary.expired || 0)
            );
            loadStateAndRender("notifications");
        } else {
            showAppNotice("خطأ: " + res.error);
        }
    });
}

// 5. Chart.js render engines (with MySQL values)
function initRevenueChart(canvasId, type, days = 7) {
    const ctx = document.getElementById(canvasId);
    if (!ctx) return;

    const chartKey = type === "dashboard" ? "revenueDashboard" : "revenueReports";
    if (charts[chartKey]) {
        charts[chartKey].destroy();
    }

    // Dashboard revenue is based on payments, including zero-value days for an honest trend.
    const labels = [];
    const values = [];
    for (let i = days - 1; i >= 0; i--) {
        const day = new Date();
        day.setDate(day.getDate() - i);
        const key = day.toISOString().slice(0, 10);
        labels.push(days === 1 ? 'اليوم' : key.slice(5));
        values.push(Number(toNumber(state.revenueHistory?.[key]).toFixed(2)));
    }

    charts[chartKey] = new Chart(ctx, {
        type: 'line',
        data: {
            labels,
            datasets: [{
                data: values,
                borderColor: '#ef4444',
                backgroundColor: createGradient(ctx.getContext('2d'), 'rgba(239,68,68,0.28)', 'rgba(239,68,68,0.02)'),
                borderWidth: 2,
                fill: true,
                tension: 0.35,
                pointRadius: 2,
                pointHoverRadius: 4,
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: false }
            },
            scales: {
                x: {
                    grid: { display: false },
                    ticks: { color: '#9ca3af', font: { family: 'Figtree' } }
                },
                y: {
                    ticks: {
                        color: '#9ca3af',
                        callback: (v) => `${v} ₪`
                    },
                    beginAtZero: true
                }
            }
        }
    });
}

function initAttendanceChart(canvasId, type, range = 'day') {
    const ctx = document.getElementById(canvasId);
    if (!ctx) return;

    const chartKey = type === "dashboard" ? "attendanceDashboard" : "attendanceReports";
    if (charts[chartKey]) {
        charts[chartKey].destroy();
    }

    const hours = type === 'dashboard'
        ? Array.from({ length: 18 }, (_, index) => String(index + 6).padStart(2, '0'))
        : ["01", "03", "05", "07", "09", "11", "13", "15", "17", "19", "21", "23"];
    const peakData = state.peakHoursByRange?.[range] || state.peakHours || {};
    const dataValues = hours.map(h => peakData[h] || 0);

    charts[chartKey] = new Chart(ctx, {
        type: 'bar',
        data: {
            labels: hours,
            datasets: [{
                data: dataValues,
                backgroundColor: '#3b82f6',
                borderRadius: 4,
                barPercentage: 0.5
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: false }
            },
            scales: {
                x: {
                    grid: { display: false },
                    ticks: { color: '#9ca3af', font: { family: 'Figtree' } }
                },
                y: {
                    grid: { color: 'rgba(255, 255, 255, 0.05)' },
                    ticks: { color: '#9ca3af', font: { family: 'Figtree' } },
                    min: 0,
                    suggestedMax: 4,
                    stepSize: 1
                }
            }
        }
    });
}

function createGradient(ctx, colorStart, colorEnd) {
    const gradient = ctx.createLinearGradient(0, 0, 0, 300);
    gradient.addColorStop(0, colorStart);
    gradient.addColorStop(1, colorEnd);
    return gradient;
}

// ==================== AUTHENTICATION & ROLE MANAGEMENT SYSTEM ====================

function checkSessionAndInit() {
    const loginContainerEl = document.getElementById("login-container");
    const appContainerEl = document.querySelector(".app-container");
    if (!loginContainerEl && !appContainerEl) {
        return; // No dashboard UI on this page
    }

    fetch(resolveApiUrl('get_state'))
        .then(r => r.json())
        .then(res => {
            if (res.success) {
                Object.assign(state, res.data);
                if (res.data.currentUser) {
                    state.currentUser = res.data.currentUser;
                    state.memberData = res.data.memberData;
                    state.trainerData = res.data.trainerData;

                    if (loginContainerEl) loginContainerEl.style.display = "none";
                    if (appContainerEl) appContainerEl.style.display = "flex";

                    if (appContainerEl) {
                        updateProfileSidebar(state.currentUser);
                        applyRolePermissions(state.currentUser.role);

                        const defaultView = resolveInitialViewForRole(state.currentUser.role);
                        switchView(defaultView);
                    }
                } else {
                    showLoginScreen();
                }
            } else {
                showLoginScreen();
            }
        })
        .catch(err => {
            console.error("Session restore error:", err);
            showLoginScreen();
        });
}

function showLoginScreen() {
    const loginContainerEl = document.getElementById("login-container");
    const appContainerEl = document.querySelector(".app-container");
    // Let the responsive login stylesheet select its intended grid layout.
    if (loginContainerEl) loginContainerEl.style.display = "";
    if (appContainerEl) appContainerEl.style.display = "none";
}

async function handleLogin(e) {
    e.preventDefault();
    const unameEl = document.getElementById("login-username");
    const pwdEl = document.getElementById("login-password");
    const username = unameEl ? unameEl.value : '';
    const password = pwdEl ? pwdEl.value : '';

    // Send as form-urlencoded and include credentials so cookies are set
    const params = new URLSearchParams();
    params.append('username', username);
    params.append('password', password);

    const submitBtn = document.querySelector('#form-login button[type="submit"]');
    const origBtnHTML = submitBtn ? submitBtn.innerHTML : null;
    try {
        // Clear previous field errors
        clearFieldError('login-username');
        clearFieldError('login-password');

        // Enhanced client-side validation
        function isValidEmail(s) {
            return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(s);
        }

        const unameTrim = username ? username.trim() : '';
        const pwdTrim = password ? password.trim() : '';
        const entryMode = state.entryMode || 'gym';
        params.append('entry_mode', entryMode);

        if (!unameTrim) {
            showFieldError('login-username', 'مطلوب اسم المستخدم أو البريد الإلكتروني');
            if (unameEl) unameEl.focus();
            return;
        }

        // If looks like email, validate format and length
        if (unameTrim.includes('@')) {
            if (!isValidEmail(unameTrim) || unameTrim.length > 254) {
                showFieldError('login-username', 'الرجاء إدخال بريد إلكتروني صالح وطويلة أقل من 254 حرف');
                if (unameEl) unameEl.focus();
                return;
            }
        } else {
            if (unameTrim.length < 3 || unameTrim.length > 100) {
                showFieldError('login-username', 'اسم المستخدم يجب أن يكون بين 3 و 100 حرف');
                if (unameEl) unameEl.focus();
                return;
            }
        }

        if (!pwdTrim) {
            showFieldError('login-password', 'مطلوب كلمة المرور');
            if (pwdEl) pwdEl.focus();
            return;
        }
        if (pwdTrim.length < 6) {
            showFieldError('login-password', 'كلمة المرور يجب أن تكون 6 أحرف على الأقل');
            if (pwdEl) pwdEl.focus();
            return;
        }

        showGlobalLoader();
        if (submitBtn) {
            submitBtn.disabled = true;
            submitBtn.classList.add('loading');
            submitBtn.innerHTML = '<span class="spinner" aria-hidden="true"></span> جاري التحقق...';
            submitBtn.setAttribute('aria-busy', 'true');
        }

        const resp = await fetch(resolveApiUrl('login'), {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: params.toString(),
            credentials: 'same-origin'
        });
        const res = await resp.json();

        if (res && res.success) {
            state.currentUser = res.user;
            state.memberData = res.memberData;
            state.trainerData = res.trainerData;

            const form = document.getElementById("form-login");
            if (form) form.reset();
            const loginContainer = document.getElementById("login-container");
            const appContainer = document.querySelector(".app-container");
            if (loginContainer) loginContainer.style.display = "none";
            if (appContainer) appContainer.style.display = "flex";

            updateProfileSidebar(res.user);
            applyRolePermissions(res.user.role);

            const defaultView = resolveInitialViewForRole(res.user.role);
            switchView(defaultView);
        } else {
            showAppNotice("خطأ في الدخول: " + (res?.error || 'خطأ غير معروف'), 'error');
        }
    } catch (err) {
        console.error("Login call error:", err);
        showAppNotice("حدث خطأ بالاتصال بالشبكة!", 'error');
    } finally {
        if (submitBtn) {
            submitBtn.disabled = false;
            submitBtn.classList.remove('loading');
            if (origBtnHTML !== null) submitBtn.innerHTML = origBtnHTML;
            submitBtn.removeAttribute('aria-busy');
        }
        hideGlobalLoader();
    }
}

function handleLogout() {
    if (!confirm("هل أنت متأكد من تسجيل الخروج؟")) return;

    fetch(resolveApiUrl('logout'))
    .then(r => r.json())
    .then(res => {
        if (res.success) {
            state.currentUser = null;
            state.memberData = null;
            state.trainerData = null;
            showLoginScreen();
        } else {
            showAppNotice("خطأ: " + res.error);
        }
    })
    .catch(err => console.error("Logout call error:", err));
}

function getDefaultViewForRole(role) {
    if (state.entryMode === 'platform') {
        switch (role) {
            case 'مدير النظام': return 'tenant-management';
                case 'مدير الصالة': return 'dashboard';
            case 'موظف الاستقبال': return 'check-in';
            case 'المحاسب': return 'reports';
            case 'المدقق المالي': return 'activity-log';
            case 'مدرب': return 'trainers';
            case 'مشترك': return 'member-portal';
            default: return 'tenant-management';
        }
    }

    switch (role) {
        case 'مدير النظام': return 'dashboard';
        case 'موظف الاستقبال': return 'check-in';
        case 'المحاسب': return 'subscriptions';
        case 'المدقق المالي': return 'reports';
        case 'مدرب': return 'trainer-portal';
        case 'مشترك': return 'member-portal';
        default: return 'dashboard';
    }
}

function isViewAllowed(viewName) {
    if (!state.currentUser) return false;
    const role = state.currentUser.role;

    if (role === 'مدير النظام' || role === 'مدير الصالة') {
        return ['dashboard', 'check-in', 'members', 'member-detail', 'subscriptions', 'plans', 'payments', 'products', 'reports', 'notifications', 'sync-center', 'sync-event-detail', 'trainers', 'users', 'activity-log', 'global-search'].includes(viewName);
    }

    const permissions = {
        'موظف الاستقبال': ['check-in', 'members', 'member-detail', 'trainers', 'notifications', 'sync-center', 'sync-event-detail', 'global-search'],
        'المحاسب': ['subscriptions', 'plans', 'payments', 'products', 'reports', 'notifications', 'sync-center', 'sync-event-detail', 'member-detail', 'global-search'],
        'المدقق المالي': ['subscriptions', 'payments', 'reports', 'notifications', 'sync-center', 'sync-event-detail', 'trainers', 'activity-log', 'member-detail', 'global-search'],
        'مدرب': ['trainer-portal'],
        'مشترك': ['member-portal']
    };

    const allowed = permissions[role] || [];
    return allowed.includes(viewName);
}

function applyRolePermissions(role) {
    const navLinks = document.querySelectorAll(".sidebar-nav .nav-link");
    navLinks.forEach(link => {
        const view = link.getAttribute("data-view");
        if (view === 'users') {
            link.style.display = (role === 'مدير النظام' || role === 'مدير الصالة') ? 'flex' : 'none';
        } else if (view === 'activity-log') {
            link.style.display = (role === 'مدير النظام' || role === 'المدقق المالي') ? 'flex' : 'none';
        } else if (view === 'import') {
            link.style.display = (role === 'مدير النظام') ? 'flex' : 'none';
        } else if (view === 'trainers') {
            link.style.display = (role === 'مدير النظام' || role === 'مدير الصالة' || role === 'موظف الاستقبال' || role === 'المدقق المالي') ? 'flex' : 'none';
        } else if (view === 'notifications') {
            link.style.display = (role === 'مدير النظام' || role === 'مدير الصالة' || role === 'موظف الاستقبال' || role === 'المحاسب' || role === 'المدقق المالي') ? 'flex' : 'none';
        } else if (view === 'sync-center') {
            link.style.display = (role === 'مدير النظام' || role === 'مدير الصالة' || role === 'موظف الاستقبال' || role === 'المحاسب' || role === 'المدقق المالي') ? 'flex' : 'none';
        } else if (view === 'member-portal') {
            link.style.display = (role === 'مشترك') ? 'flex' : 'none';
        } else if (view === 'trainer-portal') {
            link.style.display = (role === 'مدرب') ? 'flex' : 'none';
        } else if (view === 'tenant-management') {
            link.style.display = 'none';
        } else {
            link.style.display = isViewAllowed(view) ? 'flex' : 'none';
        }
    });

    // Auditor/Read-only checks
    const audButtons = document.querySelectorAll("#view-subscriptions .btn-primary, #view-payments .btn-primary, #view-plans .btn-primary, #view-products .btn-primary, #view-members .btn-primary, .btn-delete-card, .btn-edit-card");
    audButtons.forEach(btn => {
        if (role === 'المدقق المالي') {
            btn.style.display = "none";
        } else {
            btn.style.display = "";
        }
    });
}

function updateProfileSidebar(user) {
    safeSetText("user-display-name", user.name);
    safeSetText("user-display-role", user.role);
    const av = document.getElementById("avatar-circle");
    if (av && user.name) av.innerText = user.name.charAt(0);
}

function updateTenantHeader(tenant) {
    const gyms = Array.isArray(tenant?.gyms) ? tenant.gyms : [];
    const selectedGym = gyms.find(g => String(g.id) === String(tenant?.selectedGymId)) || gyms[0] || null;
    const selectedBranch = selectedGym
        ? (selectedGym.branches || []).find(b => String(b.id) === String(tenant?.selectedBranchId)) || (selectedGym.branches || [])[0] || null
        : null;

    safeSetText('tenant-gym-name', selectedGym?.name || '—');
    safeSetText('tenant-branch-name', selectedBranch?.name || '—');

    const branchSelect = document.getElementById('tenant-branch-select');
    const addGymBtn = document.getElementById('tenant-add-gym-btn');
    const addBranchBtn = document.getElementById('tenant-add-branch-btn');
    const canManageTenant = !!state.currentUser && state.currentUser.role === 'مدير النظام';
    if (addGymBtn) addGymBtn.style.display = canManageTenant ? '' : 'none';
    if (addBranchBtn) addBranchBtn.style.display = canManageTenant ? '' : 'none';
    if (!branchSelect) return;

    const branchOptions = [];
    gyms.forEach(gym => {
        (gym.branches || []).forEach(branch => {
            branchOptions.push({
                value: String(branch.id),
                label: `${gym.name} / ${branch.name}`,
                selected: String(branch.id) === String(tenant?.selectedBranchId),
            });
        });
    });

    if (branchOptions.length === 0) {
        branchSelect.innerHTML = '<option value="">لا توجد فروع</option>';
        branchSelect.disabled = true;
        return;
    }

    branchSelect.disabled = !canManageTenant;
    branchSelect.innerHTML = branchOptions.map(option => `<option value="${option.value}" ${option.selected ? 'selected' : ''}>${option.label}</option>`).join('');
}

function selectTenantBranchFromHeader() {
    const branchSelect = document.getElementById('tenant-branch-select');
    if (!branchSelect || !branchSelect.value) return;

    fetch('/api/select_branch', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ branchId: branchSelect.value })
    })
        .then(async response => ({ ok: response.ok, status: response.status, data: await response.json() }))
        .then(({ ok, status, data }) => {
            if (!ok || !data.success) {
                showAppNotice(data.error || 'تعذر تغيير الفرع', 'error', 5200);
                return;
            }

            showAppNotice('تم تغيير الفرع الحالي بنجاح', 'success');
            loadStateAndRender(currentView);
        })
        .catch(() => showAppNotice('تعذر تغيير الفرع', 'error', 5200));
}

function syncTenantStateAfterMutation(data, successMessage) {
    if (data.tenant) {
        state.tenant = data.tenant;
        updateTenantHeader(state.tenant);
    }

    if (successMessage) {
        showAppNotice(successMessage, 'success');
    }

    if (currentView === 'tenant-management') {
        renderTenantManagement();
    }
}

function canManageTenantOrAlert() {
    if (!state.currentUser || state.currentUser.role !== 'مدير النظام') {
        showAppNotice('غير مصرح لك', 'error', 4200);
        return false;
    }

    return true;
}

function openAddGymModal() {
    if (!canManageTenantOrAlert()) return;

    openModal('modal-add-gym');
    const nameInput = document.getElementById('tenant-add-gym-name');
    const codeInput = document.getElementById('tenant-add-gym-code');
    if (nameInput) nameInput.value = '';
    if (codeInput) codeInput.value = '';
}

function submitAddGymModal(event) {
    event.preventDefault();
    if (!canManageTenantOrAlert()) return;

    const name = (document.getElementById('tenant-add-gym-name')?.value || '').trim();
    const code = (document.getElementById('tenant-add-gym-code')?.value || '').trim();
    if (!name || !code) {
        showAppNotice('يرجى تعبئة اسم الصالة والرمز', 'warning');
        return;
    }

    fetch(resolveApiUrl('add_gym'), {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ name, code }),
    })
        .then(async response => ({ ok: response.ok, data: await response.json() }))
        .then(({ ok, data }) => {
            if (!ok || !data.success) {
                showAppNotice(data.error || 'تعذر إضافة الصالة', 'error', 5200);
                return;
            }

            closeModal('modal-add-gym');
            syncTenantStateAfterMutation(data, 'تمت إضافة الصالة بنجاح');
        })
        .catch(() => showAppNotice('تعذر إضافة الصالة', 'error', 5200));
}

function openAddBranchModal() {
    if (!canManageTenantOrAlert()) return;

    const gyms = Array.isArray(state.tenant?.gyms) ? state.tenant.gyms : [];
    if (gyms.length === 0) {
        showAppNotice('لا توجد صالات متاحة', 'warning');
        return;
    }

    openModal('modal-add-branch');
    const gymSelect = document.getElementById('tenant-add-branch-gym-id');
    if (gymSelect) {
        gymSelect.innerHTML = gyms.map(g => `<option value="${g.id}">${escapeHtml(g.name)} (${escapeHtml(g.code)})</option>`).join('');
        if (state.tenant?.selectedGymId) {
            gymSelect.value = String(state.tenant.selectedGymId);
        }
    }

    const nameInput = document.getElementById('tenant-add-branch-name');
    const codeInput = document.getElementById('tenant-add-branch-code');
    const addressInput = document.getElementById('tenant-add-branch-address');
    const phoneInput = document.getElementById('tenant-add-branch-phone');
    const defaultSelect = document.getElementById('tenant-add-branch-default');
    if (nameInput) nameInput.value = '';
    if (codeInput) codeInput.value = '';
    if (addressInput) addressInput.value = '';
    if (phoneInput) phoneInput.value = '';
    if (defaultSelect) defaultSelect.value = '0';
}

function submitAddBranchModal(event) {
    event.preventDefault();
    if (!canManageTenantOrAlert()) return;

    const gymId = Number(document.getElementById('tenant-add-branch-gym-id')?.value || 0);
    const name = (document.getElementById('tenant-add-branch-name')?.value || '').trim();
    const code = (document.getElementById('tenant-add-branch-code')?.value || '').trim();
    const address = (document.getElementById('tenant-add-branch-address')?.value || '').trim();
    const phone = (document.getElementById('tenant-add-branch-phone')?.value || '').trim();
    const isDefault = (document.getElementById('tenant-add-branch-default')?.value || '0') === '1';

    if (!gymId || !name || !code) {
        showAppNotice('يرجى تعبئة الصالة واسم الفرع والرمز', 'warning');
        return;
    }

    fetch(resolveApiUrl('add_branch'), {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            gymId,
            name,
            code,
            address: address || null,
            phone: phone || null,
            isDefault,
        }),
    })
        .then(async response => ({ ok: response.ok, data: await response.json() }))
        .then(({ ok, data }) => {
            if (!ok || !data.success) {
                showAppNotice(data.error || 'تعذر إضافة الفرع', 'error', 5200);
                return;
            }

            closeModal('modal-add-branch');
            syncTenantStateAfterMutation(data, 'تمت إضافة الفرع بنجاح');
        })
        .catch(() => showAppNotice('تعذر إضافة الفرع', 'error', 5200));
}

function openAddGymPrompt() {
    openAddGymModal();
}

function openAddBranchPrompt() {
    openAddBranchModal();
}

function readTenantManagementFilters(tenantGyms) {
    const defaults = {
        gymQuery: '',
        gymStatus: 'all',
        gymSort: 'name-asc',
        branchQuery: '',
        branchStatus: 'all',
        branchGym: 'all',
        branchSort: 'name-asc',
    };

    if (!state.tenantFilters) {
        state.tenantFilters = { ...defaults };
    }

    const gymFilterSelect = document.getElementById('tenant-filter-branch-gym');
    if (gymFilterSelect) {
        const previous = state.tenantFilters.branchGym || 'all';
        gymFilterSelect.innerHTML = '<option value="all">كل الصالات</option>' + tenantGyms
            .map(g => `<option value="${g.id}">${escapeHtml(g.name)} (${escapeHtml(g.code)})</option>`)
            .join('');
        gymFilterSelect.value = tenantGyms.some(g => String(g.id) === String(previous)) ? String(previous) : 'all';
    }

    const inputs = {
        gymQuery: document.getElementById('tenant-filter-gym-query'),
        gymStatus: document.getElementById('tenant-filter-gym-status'),
        gymSort: document.getElementById('tenant-filter-gym-sort'),
        branchQuery: document.getElementById('tenant-filter-branch-query'),
        branchStatus: document.getElementById('tenant-filter-branch-status'),
        branchGym: document.getElementById('tenant-filter-branch-gym'),
        branchSort: document.getElementById('tenant-filter-branch-sort'),
    };

    Object.entries(inputs).forEach(([key, element]) => {
        if (!element) return;
        if (element.value === '' && state.tenantFilters[key]) {
            element.value = state.tenantFilters[key];
        }
        state.tenantFilters[key] = element.value || defaults[key];
    });

    return { ...defaults, ...state.tenantFilters };
}

function renderTenantManagement() {
    const gymsBody = document.getElementById('tenant-gyms-table-body');
    const branchesBody = document.getElementById('tenant-branches-table-body');
    if (!gymsBody || !branchesBody) return;

    const tenantGyms = Array.isArray(state.tenant?.gyms) ? state.tenant.gyms : [];
    const filters = readTenantManagementFilters(tenantGyms);
    const gymQuery = String(filters.gymQuery || '').trim().toLowerCase();
    const branchQuery = String(filters.branchQuery || '').trim().toLowerCase();

    let filteredGyms = tenantGyms.filter(gym => {
        const statusOk = filters.gymStatus === 'all' || gym.status === filters.gymStatus;
        const text = `${gym.name || ''} ${gym.code || ''}`.toLowerCase();
        const queryOk = !gymQuery || text.includes(gymQuery);
        return statusOk && queryOk;
    });

    if (filters.gymSort === 'name-desc') {
        filteredGyms = filteredGyms.sort((a, b) => String(b.name || '').localeCompare(String(a.name || ''), 'ar'));
    } else if (filters.gymSort === 'branches-desc') {
        filteredGyms = filteredGyms.sort((a, b) => (b.branches || []).length - (a.branches || []).length);
    } else {
        filteredGyms = filteredGyms.sort((a, b) => String(a.name || '').localeCompare(String(b.name || ''), 'ar'));
    }

    gymsBody.innerHTML = filteredGyms.map((gym, index) => `
        <tr>
            <td>${index + 1}</td>
            <td>${escapeHtml(gym.name || '—')}</td>
            <td>${escapeHtml(gym.code || '—')}</td>
            <td>${gym.status === 'active' ? 'نشطة' : 'غير نشطة'}</td>
            <td>${Array.isArray(gym.branches) ? gym.branches.length : 0}</td>
            <td>
                <button class="btn btn-secondary btn-sm" onclick="openEditGymModal(${gym.id})">تعديل</button>
                <button class="btn btn-secondary btn-sm" onclick="toggleGymStatusFromTenantView(${gym.id}, '${gym.status === 'active' ? 'inactive' : 'active'}')">${gym.status === 'active' ? 'تعطيل' : 'تفعيل'}</button>
            </td>
        </tr>
    `).join('') || '<tr><td colspan="6" style="text-align:center; color: var(--text-muted);">لا توجد صالات</td></tr>';

    let branchEntries = [];
    tenantGyms.forEach(gym => {
        (gym.branches || []).forEach(branch => {
            const statusOk = filters.branchStatus === 'all' || branch.status === filters.branchStatus;
            const gymOk = filters.branchGym === 'all' || String(gym.id) === String(filters.branchGym);
            const text = `${gym.name || ''} ${gym.code || ''} ${branch.name || ''} ${branch.code || ''}`.toLowerCase();
            const queryOk = !branchQuery || text.includes(branchQuery);
            if (!(statusOk && gymOk && queryOk)) return;

            branchEntries.push({ gym, branch });
        });
    });

    if (filters.branchSort === 'name-desc') {
        branchEntries.sort((a, b) => String(b.branch.name || '').localeCompare(String(a.branch.name || ''), 'ar'));
    } else if (filters.branchSort === 'gym-name') {
        branchEntries.sort((a, b) => String(a.gym.name || '').localeCompare(String(b.gym.name || ''), 'ar'));
    } else {
        branchEntries.sort((a, b) => String(a.branch.name || '').localeCompare(String(b.branch.name || ''), 'ar'));
    }

    state.tenantFilteredSnapshot = {
        gyms: filteredGyms.map(gym => ({
            id: gym.id,
            name: gym.name || '',
            code: gym.code || '',
            status: gym.status || '',
            branches_count: Array.isArray(gym.branches) ? gym.branches.length : 0,
        })),
        branches: branchEntries.map(({ gym, branch }) => ({
            id: branch.id,
            gym_id: gym.id,
            gym_name: gym.name || '',
            gym_code: gym.code || '',
            name: branch.name || '',
            code: branch.code || '',
            status: branch.status || '',
            is_default: !!branch.is_default,
        })),
    };

    let branchRows = branchEntries.map(({ gym, branch }) => `
        <tr>
            <td>${branch.id}</td>
            <td>${escapeHtml(gym.name || '—')}</td>
            <td>${escapeHtml(branch.name || '—')}</td>
            <td>${escapeHtml(branch.code || '—')}</td>
            <td>${branch.status === 'active' ? 'نشط' : 'غير نشط'}</td>
            <td>${branch.is_default ? 'نعم' : 'لا'}</td>
            <td style="display:flex; gap:6px; flex-wrap:wrap;">
                <button class="btn btn-secondary btn-sm" onclick="openEditBranchModal(${branch.id})">تعديل</button>
                <button class="btn btn-primary btn-sm" onclick="setDefaultBranchFromTenantView(${branch.id})" ${(branch.is_default || branch.status !== 'active') ? 'disabled' : ''}>افتراضي</button>
                <button class="btn btn-secondary btn-sm" onclick="toggleBranchStatusFromTenantView(${branch.id}, '${branch.status === 'active' ? 'inactive' : 'active'}')" ${branch.status !== 'active' && gym.status !== 'active' ? 'disabled' : ''}>${branch.status === 'active' ? 'تعطيل' : 'تفعيل'}</button>
            </td>
        </tr>
    `);

    branchesBody.innerHTML = branchRows.join('') || '<tr><td colspan="7" style="text-align:center; color: var(--text-muted);">لا توجد فروع</td></tr>';
}

function csvEscape(value) {
    return `"${String(value ?? '').replace(/"/g, '""')}"`;
}

function downloadCsvFromRows(header, rows, fileNamePrefix) {
    if (!Array.isArray(rows) || rows.length === 0) {
        showAppNotice('لا توجد بيانات لتصديرها.', 'warning');
        return;
    }

    const csv = [header.join(','), ...rows.map(cols => cols.map(csvEscape).join(','))].join('\n');
    const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
    const url = URL.createObjectURL(blob);
    const link = document.createElement('a');
    link.href = url;
    link.download = `${fileNamePrefix}-${new Date().toISOString().slice(0, 19).replace(/[:T]/g, '-')}.csv`;
    document.body.appendChild(link);
    link.click();
    link.remove();
    URL.revokeObjectURL(url);
}

function downloadTenantGymsCsv() {
    const gyms = Array.isArray(state.tenantFilteredSnapshot?.gyms) ? state.tenantFilteredSnapshot.gyms : [];
    const header = ['id', 'name', 'code', 'status', 'branches_count'];
    const rows = gyms.map(g => [g.id, g.name, g.code, g.status, g.branches_count]);
    downloadCsvFromRows(header, rows, 'tenant-gyms');
}

function downloadTenantBranchesCsv() {
    const branches = Array.isArray(state.tenantFilteredSnapshot?.branches) ? state.tenantFilteredSnapshot.branches : [];
    const header = ['id', 'gym_id', 'gym_name', 'gym_code', 'name', 'code', 'status', 'is_default'];
    const rows = branches.map(b => [b.id, b.gym_id, b.gym_name, b.gym_code, b.name, b.code, b.status, b.is_default ? 1 : 0]);
    downloadCsvFromRows(header, rows, 'tenant-branches');
}

function downloadTenantManagementXlsx() {
    const gyms = Array.isArray(state.tenantFilteredSnapshot?.gyms) ? state.tenantFilteredSnapshot.gyms : [];
    const branches = Array.isArray(state.tenantFilteredSnapshot?.branches) ? state.tenantFilteredSnapshot.branches : [];

    if (gyms.length === 0 && branches.length === 0) {
        showAppNotice('لا توجد بيانات لتصديرها.', 'warning');
        return;
    }

    if (typeof XLSX === 'undefined') {
        showAppNotice('مكتبة Excel غير متاحة حالياً.', 'error', 5200);
        return;
    }

    const workbook = XLSX.utils.book_new();

    const gymsSheet = XLSX.utils.json_to_sheet(gyms);
    XLSX.utils.book_append_sheet(workbook, gymsSheet, 'tenant_gyms');

    const branchesSheet = XLSX.utils.json_to_sheet(branches);
    XLSX.utils.book_append_sheet(workbook, branchesSheet, 'tenant_branches');

    const fileName = `tenant-management-${new Date().toISOString().slice(0, 19).replace(/[:T]/g, '-')}.xlsx`;
    XLSX.writeFile(workbook, fileName);
}

function openEditGymModal(gymId) {
    const gym = (state.tenant?.gyms || []).find(g => String(g.id) === String(gymId));
    if (!gym) {
        showAppNotice('الصالة غير موجودة', 'warning');
        return;
    }

    openModal('modal-edit-gym');
    const idInput = document.getElementById('tenant-edit-gym-id');
    const nameInput = document.getElementById('tenant-edit-gym-name');
    const statusInput = document.getElementById('tenant-edit-gym-status');

    if (idInput) idInput.value = String(gym.id);
    if (nameInput) nameInput.value = gym.name || '';
    if (statusInput) statusInput.value = gym.status === 'inactive' ? 'inactive' : 'active';
}

function submitEditGymModal(event) {
    event.preventDefault();
    if (!canManageTenantOrAlert()) return;

    const gymId = Number(document.getElementById('tenant-edit-gym-id')?.value || 0);
    const name = (document.getElementById('tenant-edit-gym-name')?.value || '').trim();
    const status = (document.getElementById('tenant-edit-gym-status')?.value || '').trim().toLowerCase();
    if (!gymId || !name || !status) {
        showAppNotice('بيانات الصالة غير مكتملة', 'warning');
        return;
    }

    fetch('/api/update_gym', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            gymId,
            name,
            status,
        }),
    })
        .then(async response => ({ ok: response.ok, data: await response.json() }))
        .then(({ ok, data }) => {
            if (!ok || !data.success) {
                showAppNotice(data.error || 'تعذر تحديث الصالة', 'error', 5200);
                return;
            }

            closeModal('modal-edit-gym');
            const reassignmentNotice = getReassignmentNoticeMessage(data);
            if (reassignmentNotice) showAppNotice(reassignmentNotice, 'warning', 5200);
            syncTenantStateAfterMutation(data, 'تم تحديث بيانات الصالة بنجاح');
        })
        .catch(() => showAppNotice('تعذر تحديث الصالة', 'error', 5200));
}

function openEditBranchModal(branchId) {
    const gyms = Array.isArray(state.tenant?.gyms) ? state.tenant.gyms : [];
    let targetBranch = null;
    gyms.forEach(gym => {
        (gym.branches || []).forEach(branch => {
            if (String(branch.id) === String(branchId)) {
                targetBranch = branch;
            }
        });
    });
    if (!targetBranch) {
        showAppNotice('الفرع غير موجود', 'warning');
        return;
    }

    openModal('modal-edit-branch');
    const idInput = document.getElementById('tenant-edit-branch-id');
    const nameInput = document.getElementById('tenant-edit-branch-name');
    const codeInput = document.getElementById('tenant-edit-branch-code');
    const addressInput = document.getElementById('tenant-edit-branch-address');
    const phoneInput = document.getElementById('tenant-edit-branch-phone');
    const statusInput = document.getElementById('tenant-edit-branch-status');

    if (idInput) idInput.value = String(targetBranch.id);
    if (nameInput) nameInput.value = targetBranch.name || '';
    if (codeInput) codeInput.value = targetBranch.code || '';
    if (addressInput) addressInput.value = targetBranch.address || '';
    if (phoneInput) phoneInput.value = targetBranch.phone || '';
    if (statusInput) statusInput.value = targetBranch.status === 'inactive' ? 'inactive' : 'active';
}

function submitEditBranchModal(event) {
    event.preventDefault();
    if (!canManageTenantOrAlert()) return;

    const branchId = Number(document.getElementById('tenant-edit-branch-id')?.value || 0);
    const name = (document.getElementById('tenant-edit-branch-name')?.value || '').trim();
    const code = (document.getElementById('tenant-edit-branch-code')?.value || '').trim();
    const status = (document.getElementById('tenant-edit-branch-status')?.value || '').trim().toLowerCase();
    const address = (document.getElementById('tenant-edit-branch-address')?.value || '').trim();
    const phone = (document.getElementById('tenant-edit-branch-phone')?.value || '').trim();

    if (!branchId || !name || !code || !status) {
        showAppNotice('بيانات الفرع غير مكتملة', 'warning');
        return;
    }

    fetch('/api/update_branch', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            branchId,
            name,
            code,
            status,
            address: address || null,
            phone: phone || null,
        }),
    })
        .then(async response => ({ ok: response.ok, data: await response.json() }))
        .then(({ ok, data }) => {
            if (!ok || !data.success) {
                showAppNotice(data.error || 'تعذر تحديث الفرع', 'error', 5200);
                return;
            }

            closeModal('modal-edit-branch');
            syncTenantStateAfterMutation(data, 'تم تحديث بيانات الفرع بنجاح');
        })
        .catch(() => showAppNotice('تعذر تحديث الفرع', 'error', 5200));
}

function setDefaultBranchFromTenantView(branchId) {
    fetch('/api/set_default_branch', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ branchId }),
    })
        .then(async response => ({ ok: response.ok, data: await response.json() }))
        .then(({ ok, data }) => {
            if (!ok || !data.success) {
                showAppNotice(data.error || 'تعذر تعيين الفرع الافتراضي', 'error', 5200);
                return;
            }

            syncTenantStateAfterMutation(data, 'تم تعيين الفرع كافتراضي بنجاح');
        })
        .catch(() => showAppNotice('تعذر تعيين الفرع الافتراضي', 'error', 5200));
}

function toggleGymStatusFromTenantView(gymId, targetStatus) {
    const actionLabel = targetStatus === 'active' ? 'تفعيل' : 'تعطيل';
    if (!window.confirm(`هل تريد ${actionLabel} هذه الصالة؟`)) return;

    fetch('/api/toggle_gym_status', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            gymId,
            status: targetStatus,
        }),
    })
        .then(async response => ({ ok: response.ok, data: await response.json() }))
        .then(({ ok, data }) => {
            if (!ok || !data.success) {
                showAppNotice(data.error || 'تعذر تحديث حالة الصالة', 'error', 5200);
                return;
            }

            const reassignmentNotice = getReassignmentNoticeMessage(data);
            if (reassignmentNotice) showAppNotice(reassignmentNotice, 'warning', 5200);
            const actionDone = targetStatus === 'active' ? 'تفعيل' : 'تعطيل';
            syncTenantStateAfterMutation(data, `تم ${actionDone} الصالة بنجاح`);
        })
        .catch(() => showAppNotice('تعذر تحديث حالة الصالة', 'error', 5200));
}

function toggleBranchStatusFromTenantView(branchId, targetStatus) {
    const actionLabel = targetStatus === 'active' ? 'تفعيل' : 'تعطيل';
    if (!window.confirm(`هل تريد ${actionLabel} هذا الفرع؟`)) return;

    fetch('/api/toggle_branch_status', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            branchId,
            status: targetStatus,
        }),
    })
        .then(async response => ({ ok: response.ok, data: await response.json() }))
        .then(({ ok, data }) => {
            if (!ok || !data.success) {
                showAppNotice(data.error || 'تعذر تحديث حالة الفرع', 'error', 5200);
                return;
            }

            const actionLabel = targetStatus === 'active' ? 'تفعيل' : 'تعطيل';
            syncTenantStateAfterMutation(data, `تم ${actionLabel} الفرع بنجاح`);
        })
        .catch(() => showAppNotice('تعذر تحديث حالة الفرع', 'error', 5200));
}

function renderTrainers() {
    const trainersBody = document.getElementById("trainers-table-body");
    const assignmentsBody = document.getElementById("trainer-assignments-table-body");
    const trainingProgramsBody = document.getElementById("training-programs-table-body");
    const nutritionProgramsBody = document.getElementById("nutrition-programs-table-body");

    const trainers = Array.isArray(state.trainers) ? state.trainers : [];
    const assignments = Array.isArray(state.trainerAssignments) ? state.trainerAssignments : [];
    const trainingPrograms = Array.isArray(state.trainingPrograms) ? state.trainingPrograms : [];
    const nutritionPrograms = Array.isArray(state.nutritionPrograms) ? state.nutritionPrograms : [];

    const assignmentLabel = (assignmentId) => {
        const assignment = assignments.find(a => Number(a.id) === Number(assignmentId));
        if (!assignment) return '—';

        const trainer = trainers.find(t => Number(t.id) === Number(assignment.trainer_id));
        const trainerName = trainer ? ((trainer.user && trainer.user.name) ? trainer.user.name : `مدرب #${trainer.id}`) : `#${assignment.trainer_id}`;
        const member = state.members.find(m => String(m.id) === String(assignment.member_id));
        const memberName = member ? `${member.name} (${member.id})` : (assignment.member_id || '—');

        return { trainerName, memberName };
    };

    const summarizeProgramContent = (content) => {
        if (!content) return '—';
        if (typeof content === 'string') return content;
        if (Array.isArray(content)) return `عناصر: ${content.length}`;
        if (typeof content === 'object') {
            if (content.notes) return String(content.notes);
            const keys = Object.keys(content);
            return keys.length ? `حقول: ${keys.join('، ')}` : '—';
        }
        return '—';
    };

    if (trainersBody) {
        if (trainers.length === 0) {
            trainersBody.innerHTML = `<tr><td colspan="5" class="text-center" style="color: var(--text-muted);">لا يوجد مدربين بعد</td></tr>`;
        } else {
            trainersBody.innerHTML = trainers.map(t => {
                const name = (t.user && t.user.name) ? t.user.name : `مدرب #${t.id}`;
                const username = (t.user && t.user.username) ? `(${t.user.username})` : '';
                return `
                    <tr>
                        <td class="val-mono">${t.id}</td>
                        <td>${name} ${username}</td>
                        <td>${t.specialty || '—'}</td>
                        <td class="val-mono">${toNumber(t.commission_rate).toFixed(2)}%</td>
                        <td><span class="badge ${t.status === 'active' ? 'badge-active' : 'badge-frozen'}">${t.status === 'active' ? 'نشط' : 'غير نشط'}</span></td>
                    </tr>
                `;
            }).join("");
        }
    }

    if (assignmentsBody) {
        if (assignments.length === 0) {
            assignmentsBody.innerHTML = `<tr><td colspan="6" class="text-center" style="color: var(--text-muted);">لا يوجد ربط مدربين بالمشتركين بعد</td></tr>`;
        } else {
            assignmentsBody.innerHTML = assignments.map(a => {
                const trainer = trainers.find(t => Number(t.id) === Number(a.trainer_id));
                const trainerName = trainer ? ((trainer.user && trainer.user.name) ? trainer.user.name : `مدرب #${trainer.id}`) : `#${a.trainer_id}`;
                const member = state.members.find(m => String(m.id) === String(a.member_id));
                const memberName = member ? `${member.name} (${member.id})` : (a.member_id || '—');
                const statusLabel = a.status === 'active' ? 'نشط' : (a.status === 'completed' ? 'مكتمل' : 'غير نشط');
                return `
                    <tr>
                        <td>${trainerName}</td>
                        <td>${memberName}</td>
                        <td class="val-mono">${a.start_date || '—'}</td>
                        <td class="val-mono">${a.end_date || '—'}</td>
                        <td><span class="badge ${a.status === 'active' ? 'badge-active' : 'badge-frozen'}">${statusLabel}</span></td>
                        <td>${a.note || '—'}</td>
                    </tr>
                `;
            }).join("");
        }
    }

    if (trainingProgramsBody) {
        if (trainingPrograms.length === 0) {
            trainingProgramsBody.innerHTML = `<tr><td colspan="6" class="text-center" style="color: var(--text-muted);">لا توجد برامج تدريبية بعد</td></tr>`;
        } else {
            trainingProgramsBody.innerHTML = trainingPrograms.map(p => {
                const labels = assignmentLabel(p.trainer_assignment_id);
                return `
                    <tr>
                        <td>${labels.trainerName || '—'}</td>
                        <td>${labels.memberName || '—'}</td>
                        <td><strong>${p.title || '—'}</strong></td>
                        <td>${p.goal || '—'}</td>
                        <td>${summarizeProgramContent(p.content_json)}</td>
                        <td>
                            <div style="display: flex; gap: 8px;">
                                <button class="btn btn-secondary btn-sm" onclick="openEditTrainingProgramModal('${p.id}')">تعديل</button>
                                <button class="btn btn-primary btn-sm" style="background-color: var(--accent-red);" onclick="deleteTrainingProgram('${p.id}')">حذف</button>
                            </div>
                        </td>
                    </tr>
                `;
            }).join("");
        }
    }

    if (nutritionProgramsBody) {
        if (nutritionPrograms.length === 0) {
            nutritionProgramsBody.innerHTML = `<tr><td colspan="6" class="text-center" style="color: var(--text-muted);">لا توجد برامج غذائية بعد</td></tr>`;
        } else {
            nutritionProgramsBody.innerHTML = nutritionPrograms.map(p => {
                const labels = assignmentLabel(p.trainer_assignment_id);
                return `
                    <tr>
                        <td>${labels.trainerName || '—'}</td>
                        <td>${labels.memberName || '—'}</td>
                        <td><strong>${p.title || '—'}</strong></td>
                        <td>${p.goal || '—'}</td>
                        <td>${summarizeProgramContent(p.content_json)}</td>
                        <td>
                            <div style="display: flex; gap: 8px;">
                                <button class="btn btn-secondary btn-sm" onclick="openEditNutritionProgramModal('${p.id}')">تعديل</button>
                                <button class="btn btn-primary btn-sm" style="background-color: var(--accent-red);" onclick="deleteNutritionProgram('${p.id}')">حذف</button>
                            </div>
                        </td>
                    </tr>
                `;
            }).join("");
        }
    }
}

function getLatestSubscriptionForMember(memberId) {
    if (!memberId) return null;
    const subs = (state.subscriptions || []).filter(s => String(s.member_id) === String(memberId));
    if (subs.length === 0) return null;

    return subs.slice().sort((a, b) => {
        const aEnd = a.end_date || '';
        const bEnd = b.end_date || '';
        if (aEnd !== bEnd) return bEnd.localeCompare(aEnd);

        const aStart = a.start_date || '';
        const bStart = b.start_date || '';
        if (aStart !== bStart) return bStart.localeCompare(aStart);

        return String(b.id || '').localeCompare(String(a.id || ''));
    })[0];
}

function normalizeDateForInput(dateValue) {
    if (!dateValue) return '';
    const raw = String(dateValue).trim();
    if (!raw) return '';

    const direct = raw.slice(0, 10);
    if (/^\d{4}-\d{2}-\d{2}$/.test(direct)) return direct;

    const parsed = new Date(raw);
    if (!Number.isNaN(parsed.getTime())) {
        const y = parsed.getFullYear();
        const m = String(parsed.getMonth() + 1).padStart(2, '0');
        const d = String(parsed.getDate()).padStart(2, '0');
        return `${y}-${m}-${d}`;
    }

    return '';
}

function syncAssignDatesFromMember() {
    const memberId = document.getElementById("assign-member-id")?.value || '';
    const startDateInput = document.getElementById("assign-start-date");
    const endDateInput = document.getElementById("assign-end-date");
    if (!startDateInput || !endDateInput) return;

    const latestSub = getLatestSubscriptionForMember(memberId);
    if (latestSub) {
        startDateInput.value = normalizeDateForInput(latestSub.start_date);
        endDateInput.value = normalizeDateForInput(latestSub.end_date);
        return;
    }

    startDateInput.value = new Date().toISOString().split('T')[0];
    endDateInput.value = '';
}

function openAssignTrainerModal() {
    ensureFallbackModal("modal-assign-trainer-member");
    const fillOptions = () => {
        const trainerSelect = document.getElementById("assign-trainer-id");
        const memberSelect = document.getElementById("assign-member-id");
        const startDateInput = document.getElementById("assign-start-date");

        if (trainerSelect) {
            const trainers = Array.isArray(state.trainers) ? state.trainers : [];
            trainerSelect.innerHTML = trainers.length
                ? trainers.map(t => {
                    const name = (t.user && t.user.name) ? t.user.name : `مدرب #${t.id}`;
                    return `<option value="${t.id}">${name}</option>`;
                }).join("")
                : '<option value="">لا يوجد مدربين متاحين</option>';
        }

        if (memberSelect) {
            const members = Array.isArray(state.members) ? state.members : [];
            memberSelect.innerHTML = members.length
                ? members.slice(0, 300).map(m => `<option value="${m.id}">${m.name} (${m.id})</option>`).join("")
                : '<option value="">لا يوجد مشتركين متاحين</option>';
        }

        if (startDateInput && !startDateInput.value) {
            startDateInput.value = new Date().toISOString().split('T')[0];
        }

        syncAssignDatesFromMember();
    };

    fetch(resolveApiUrl('get_state'))
        .then(r => r.json())
        .then(res => {
            if (res.success && res.data) {
                Object.assign(state, res.data);
            }
            openModal("modal-assign-trainer-member");
            fillOptions();
        })
        .catch(() => {
            openModal("modal-assign-trainer-member");
            fillOptions();
        });
}

function submitAddTrainerProfile(e) {
    e.preventDefault();
    const trainerName = (document.getElementById("trainer-input-name")?.value || '').trim();
    const userIdRaw = (document.getElementById("trainer-input-user-id")?.value || '').trim();
    const specialty = (document.getElementById("trainer-input-specialty")?.value || '').trim();
    const bio = (document.getElementById("trainer-input-bio")?.value || '').trim();
    const commissionRate = Number(document.getElementById("trainer-input-commission")?.value || 0);
    const status = document.getElementById("trainer-input-status")?.value || 'active';

    if (!trainerName && !userIdRaw) {
        showAppNotice("يرجى إدخال اسم المدرب أو معرف مستخدم مرتبط");
        return;
    }

    fetch(resolveApiUrl('add_trainer_profile'), {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            trainerName,
            userId: userIdRaw ? Number(userIdRaw) : null,
            specialty,
            bio,
            commissionRate,
            status,
        })
    })
    .then(r => r.json())
    .then(res => {
        if (res.success) {
            e.target.reset();
            closeModal("modal-add-trainer-profile");
            if (res.autoCreatedUser) {
                showAppNotice(
                    "تم إنشاء ملف المدرب بنجاح\n" +
                    "تم إنشاء حساب مدرب تلقائيا:\n" +
                    "اسم المستخدم: " + res.autoCreatedUser.username + "\n" +
                    "كلمة المرور: " + res.autoCreatedUser.password
                );
            } else {
                showAppNotice("تم إنشاء ملف المدرب بنجاح");
            }
            loadStateAndRender("trainers");
        } else {
            showAppNotice("خطأ: " + res.error);
        }
    });
}

function submitAssignTrainerMember(e) {
    e.preventDefault();
    const trainerId = Number(document.getElementById("assign-trainer-id")?.value || 0);
    const memberId = document.getElementById("assign-member-id")?.value || '';
    const startDate = document.getElementById("assign-start-date")?.value || '';
    const endDate = document.getElementById("assign-end-date")?.value || '';
    const status = document.getElementById("assign-status")?.value || 'active';
    const note = (document.getElementById("assign-note")?.value || '').trim();

    fetch('/api/assign_trainer_member', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            trainerId,
            memberId,
            startDate,
            endDate: endDate || null,
            status,
            note,
        })
    })
    .then(r => r.json())
    .then(res => {
        if (res.success) {
            e.target.reset();
            closeModal("modal-assign-trainer-member");
            showAppNotice("تم ربط المدرب بالمشترك بنجاح");
            loadStateAndRender("trainers");
        } else {
            showAppNotice("خطأ: " + res.error);
        }
    });
}

function openEditTrainingProgramModal(programId) {
    ensureFallbackModal("modal-edit-training-program");
    const program = (state.trainingPrograms || []).find(p => String(p.id) === String(programId));
    if (!program) return;

    populateDropdowns();
    const idInput = document.getElementById("edit-training-program-id");
    const assignmentInput = document.getElementById("edit-training-program-assignment-id");
    const titleInput = document.getElementById("edit-training-program-title");
    const goalInput = document.getElementById("edit-training-program-goal");
    const contentInput = document.getElementById("edit-training-program-content");

    if (idInput) idInput.value = program.id;
    if (assignmentInput) assignmentInput.value = String(program.trainer_assignment_id || '');
    if (titleInput) titleInput.value = program.title || '';
    if (goalInput) goalInput.value = program.goal || '';
    if (contentInput) contentInput.value = program.content_json ? JSON.stringify(program.content_json, null, 2) : '';

    openModal("modal-edit-training-program");
}

function openEditNutritionProgramModal(programId) {
    ensureFallbackModal("modal-edit-nutrition-program");
    const program = (state.nutritionPrograms || []).find(p => String(p.id) === String(programId));
    if (!program) return;

    populateDropdowns();
    const idInput = document.getElementById("edit-nutrition-program-id");
    const assignmentInput = document.getElementById("edit-nutrition-program-assignment-id");
    const titleInput = document.getElementById("edit-nutrition-program-title");
    const goalInput = document.getElementById("edit-nutrition-program-goal");
    const contentInput = document.getElementById("edit-nutrition-program-content");

    if (idInput) idInput.value = program.id;
    if (assignmentInput) assignmentInput.value = String(program.trainer_assignment_id || '');
    if (titleInput) titleInput.value = program.title || '';
    if (goalInput) goalInput.value = program.goal || '';
    if (contentInput) contentInput.value = program.content_json ? JSON.stringify(program.content_json, null, 2) : '';

    openModal("modal-edit-nutrition-program");
}

function parseProgramContent(rawContent) {
    const raw = String(rawContent || '').trim();
    if (!raw) return null;

    try {
        return JSON.parse(raw);
    } catch {
        return raw;
    }
}

function refreshProgramsViewAfterMutation() {
    if (state.currentUser && state.currentUser.role === 'مدرب') {
        loadStateAndRender("trainer-portal");
    } else {
        loadStateAndRender("trainers");
    }
}

function submitAddTrainingProgram(e) {
    e.preventDefault();

    const trainerAssignmentId = Number(document.getElementById("training-program-assignment-id")?.value || 0);
    const title = (document.getElementById("training-program-title")?.value || '').trim();
    const goal = (document.getElementById("training-program-goal")?.value || '').trim();
    const content = parseProgramContent(document.getElementById("training-program-content")?.value || '');

    fetch(resolveApiUrl('add_training_program'), {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ trainerAssignmentId, title, goal, content })
    })
    .then(r => r.json())
    .then(res => {
        if (res.success) {
            e.target.reset();
            closeModal("modal-add-training-program");
            showAppNotice("تمت إضافة البرنامج التدريبي بنجاح");
            refreshProgramsViewAfterMutation();
        } else {
            showAppNotice("خطأ: " + res.error);
        }
    });
}

function submitEditTrainingProgram(e) {
    e.preventDefault();

    const id = Number(document.getElementById("edit-training-program-id")?.value || 0);
    const trainerAssignmentId = Number(document.getElementById("edit-training-program-assignment-id")?.value || 0);
    const title = (document.getElementById("edit-training-program-title")?.value || '').trim();
    const goal = (document.getElementById("edit-training-program-goal")?.value || '').trim();
    const content = parseProgramContent(document.getElementById("edit-training-program-content")?.value || '');

    fetch('/api/edit_training_program', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ id, trainerAssignmentId, title, goal, content })
    })
    .then(r => r.json())
    .then(res => {
        if (res.success) {
            closeModal("modal-edit-training-program");
            showAppNotice("تم تعديل البرنامج التدريبي بنجاح");
            refreshProgramsViewAfterMutation();
        } else {
            showAppNotice("خطأ: " + res.error);
        }
    });
}

function deleteTrainingProgram(programId) {
    if (!confirm("هل تريد حذف البرنامج التدريبي؟")) return;

    fetch('/api/delete_training_program', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ id: Number(programId) })
    })
    .then(r => r.json())
    .then(res => {
        if (res.success) {
            showAppNotice("تم حذف البرنامج التدريبي");
            refreshProgramsViewAfterMutation();
        } else {
            showAppNotice("خطأ: " + res.error);
        }
    });
}

function submitAddNutritionProgram(e) {
    e.preventDefault();

    const trainerAssignmentId = Number(document.getElementById("nutrition-program-assignment-id")?.value || 0);
    const title = (document.getElementById("nutrition-program-title")?.value || '').trim();
    const goal = (document.getElementById("nutrition-program-goal")?.value || '').trim();
    const content = parseProgramContent(document.getElementById("nutrition-program-content")?.value || '');

    fetch(resolveApiUrl('add_nutrition_program'), {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ trainerAssignmentId, title, goal, content })
    })
    .then(r => r.json())
    .then(res => {
        if (res.success) {
            e.target.reset();
            closeModal("modal-add-nutrition-program");
            showAppNotice("تمت إضافة البرنامج الغذائي بنجاح");
            refreshProgramsViewAfterMutation();
        } else {
            showAppNotice("خطأ: " + res.error);
        }
    });
}

function submitEditNutritionProgram(e) {
    e.preventDefault();

    const id = Number(document.getElementById("edit-nutrition-program-id")?.value || 0);
    const trainerAssignmentId = Number(document.getElementById("edit-nutrition-program-assignment-id")?.value || 0);
    const title = (document.getElementById("edit-nutrition-program-title")?.value || '').trim();
    const goal = (document.getElementById("edit-nutrition-program-goal")?.value || '').trim();
    const content = parseProgramContent(document.getElementById("edit-nutrition-program-content")?.value || '');

    fetch('/api/edit_nutrition_program', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ id, trainerAssignmentId, title, goal, content })
    })
    .then(r => r.json())
    .then(res => {
        if (res.success) {
            closeModal("modal-edit-nutrition-program");
            showAppNotice("تم تعديل البرنامج الغذائي بنجاح");
            refreshProgramsViewAfterMutation();
        } else {
            showAppNotice("خطأ: " + res.error);
        }
    });
}

function deleteNutritionProgram(programId) {
    if (!confirm("هل تريد حذف البرنامج الغذائي؟")) return;

    fetch('/api/delete_nutrition_program', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ id: Number(programId) })
    })
    .then(r => r.json())
    .then(res => {
        if (res.success) {
            showAppNotice("تم حذف البرنامج الغذائي");
            refreshProgramsViewAfterMutation();
        } else {
            showAppNotice("خطأ: " + res.error);
        }
    });
}

// ==================== USER MANAGEMENT VIEW SYSTEM (CRUD) ====================

function renderUsers() {
    fetch(resolveApiUrl('get_state')) // Refresh data
        .then(r => r.json())
        .then(res => {
            if (res.success) {
                Object.assign(state, res.data);
            }
        });

    fetch('/api/get_users')
    .then(r => r.json())
    .then(res => {
        const tbody = document.getElementById("users-table-body");
        if (!tbody) return;

        if (res.success) {
            if (res.users.length === 0) {
                tbody.innerHTML = `<tr><td colspan="6" class="text-center" style="color: var(--text-muted);">لا يوجد مستخدمين آخرين</td></tr>`;
            } else {
                tbody.innerHTML = res.users.map(u => `
                    <tr>
                        <td><strong>${u.name}</strong></td>
                        <td class="val-mono">${u.username}</td>
                        <td><span class="badge ${u.role === 'مدير النظام' ? 'badge-active' : 'badge-frozen'}">${u.role}</span></td>
                        <td class="val-mono">${u.member_id || '—'}</td>
                        <td class="val-mono">${u.created_at}</td>
                        <td>
                            <div style="display: flex; gap: 8px;">
                                <button class="btn btn-secondary btn-sm" onclick="openEditUserModal('${u.id}')">تعديل</button>
                                <button class="btn btn-primary btn-sm" style="background-color: var(--accent-red);" onclick="deleteUser('${u.id}')" ${u.username === 'admin' ? 'disabled style="opacity: 0.5; cursor: not-allowed;"' : ''}>حذف</button>
                            </div>
                        </td>
                    </tr>
                `).join("");
            }
        }
        applyTableFiltersForBody("users-table-body");
    });
}

function toggleAddUserMemberSelect() {
    const role = document.getElementById("user-input-role").value;
    const group = document.getElementById("add-user-member-group");
    group.style.display = (role === 'مشترك') ? 'block' : 'none';
}

function toggleEditUserMemberSelect() {
    const role = document.getElementById("edit-user-input-role").value;
    const group = document.getElementById("edit-user-member-group");
    group.style.display = (role === 'مشترك') ? 'block' : 'none';
}

function submitAddUser(e) {
    e.preventDefault();
    const name = document.getElementById("user-input-name").value;
    const username = document.getElementById("user-input-username").value;
    const password = document.getElementById("user-input-password").value;
    const role = document.getElementById("user-input-role").value;
    const memberId = document.getElementById("user-input-member-id").value;

    fetch(resolveApiUrl('add_user'), {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ name, username, password, role, memberId })
    })
    .then(r => r.json())
    .then(res => {
        if (res.success) {
            e.target.reset();
            document.getElementById("add-user-member-group").style.display = "none";
            closeModal("modal-add-user");
            showAppNotice("تم حفظ المستخدم بنجاح!");
            renderUsers();
        } else {
            showAppNotice("خطأ: " + res.error);
        }
    });
}

function openEditUserModal(userId) {
    ensureFallbackModal("modal-edit-user");
    // We fetch user details from server or find them from table
    fetch('/api/get_users')
    .then(r => r.json())
    .then(res => {
        if (res.success) {
            const user = res.users.find(u => String(u.id) === String(userId));
            if (!user) return;

            // Open first: openModal refreshes the fallback markup, including the hidden id field.
            openModal("modal-edit-user");

            const idInput = document.getElementById("edit-user-input-id");
            const nameInput = document.getElementById("edit-user-input-name");
            const usernameInput = document.getElementById("edit-user-input-username");
            const passwordInput = document.getElementById("edit-user-input-password");
            const roleInput = document.getElementById("edit-user-input-role");

            if (idInput) idInput.value = user.id;
            if (nameInput) nameInput.value = user.name;
            if (usernameInput) usernameInput.value = user.username;
            if (passwordInput) passwordInput.value = ""; // Empty password input
            if (roleInput) roleInput.value = user.role;
            
            // Populate member id dropdown
            populateDropdowns();
            const memberSelect = document.getElementById("edit-user-input-member-id");
            if (memberSelect) memberSelect.value = user.member_id || '';
            toggleEditUserMemberSelect();
        }
    });
}

function submitEditUser(e) {
    e.preventDefault();
    const id = document.getElementById("edit-user-input-id").value;
    const name = document.getElementById("edit-user-input-name").value;
    const username = document.getElementById("edit-user-input-username").value;
    const password = document.getElementById("edit-user-input-password").value;
    const role = document.getElementById("edit-user-input-role").value;
    const memberId = document.getElementById("edit-user-input-member-id").value;

    fetch('/api/edit_user', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ id, name, username, password, role, memberId })
    })
    .then(r => r.json())
    .then(res => {
        if (res.success) {
            e.target.reset();
            document.getElementById("edit-user-member-group").style.display = "none";
            closeModal("modal-edit-user");
            showAppNotice("تم تحديث حساب المستخدم بنجاح!");
            renderUsers();
        } else {
            showAppNotice("خطأ: " + res.error);
        }
    });
}

function deleteUser(userId) {
    if (!confirm("هل أنت متأكد من حذف هذا المستخدم؟")) return;

    fetch('/api/delete_user', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ id: userId })
    })
    .then(r => r.json())
    .then(res => {
        if (res.success) {
            showAppNotice("تم حذف المستخدم بنجاح!");
            renderUsers();
        } else {
            showAppNotice("خطأ: " + res.error);
        }
    });
}

// ==================== ACTIVITY LOG SYSTEM ====================

function renderActivityLog() {
    fetch('/api/get_activity_log', { credentials: 'same-origin' })
    .then(async response => {
        const result = await response.json().catch(() => ({}));
        if (!response.ok) {
            throw new Error(result.error || 'تعذر تحميل سجل الحركات.');
        }
        return result;
    })
    .then(res => {
        const tbody = document.getElementById("activity-log-table-body");
        if (!tbody) return;

        if (res.success) {
            if (res.logs.length === 0) {
                tbody.innerHTML = `<tr><td colspan="5" class="text-center" style="color: var(--text-muted);">لا توجد حركات مسجلة حالياً في السجل</td></tr>`;
            } else {
                tbody.innerHTML = res.logs.map(l => `
                    <tr>
                        <td class="val-mono">${formatDateTime(l.created_at)}</td>
                        <td><strong>${escapeHtml(l.name || '—')}</strong> <span style="font-size: 11px; color: var(--text-muted);">(${escapeHtml(l.username || '—')})</span></td>
                        <td><span class="badge ${l.role === 'مدير النظام' ? 'badge-active' : 'badge-frozen'}">${escapeHtml(l.role || '—')}</span></td>
                        <td><strong>${escapeHtml(l.action || '—')}</strong></td>
                        <td><span style="font-size: 13px; color: var(--text-secondary);">${escapeHtml(l.details || '—')}</span></td>
                    </tr>
                `).join("");
            }
        } else {
            tbody.innerHTML = `<tr><td colspan="5" class="text-center" style="color: var(--accent-red);">${res.error || 'تعذر تحميل سجل الحركات.'}</td></tr>`;
        }
        applyTableFiltersForBody("activity-log-table-body");
    })
    .catch(error => {
        const tbody = document.getElementById("activity-log-table-body");
        if (tbody) {
            tbody.innerHTML = `<tr><td colspan="5" class="text-center" style="color: var(--accent-red);">${escapeHtml(error.message || 'تعذر تحميل سجل الحركات. أعد المحاولة لاحقاً.')}</td></tr>`;
        }
    });
}

// ==================== MEMBER PORTAL UI RENDERING ====================

function renderMemberPortal(data) {
    if (!data || !data.member) return;

    const m = data.member;
    const subs = data.subscriptions || [];
    const pays = data.payments || [];

    // 1. Profile section
    safeSetText("mp-member-id", m.id);
    safeSetText("mp-member-name", m.name);
    safeSetText("mp-member-phone", "جوال: " + m.phone);
    safeSetText("mp-member-reg-date", "تاريخ التسجيل: " + m.created_at);

    // Find active subscription plan
    const activeSub = subs.find(s => s.status === 'فعال') || subs.find(s => s.status === 'مجمد') || null;
    
    if (activeSub) {
        safeSetText("mp-plan-name", activeSub.plan_name);
        safeSetText("mp-plan-duration", `المدة: من ${activeSub.start_date} إلى ${activeSub.end_date}`);
        document.getElementById("mp-plan-name").style.color = activeSub.status === 'مجمد' ? 'var(--accent-blue)' : 'var(--accent-yellow)';
        
        // Sum total remaining debts from all active/frozen subscriptions
        let totalDebt = subs.reduce((sum, s) => sum + Number(s.remaining), 0);
        safeSetText("mp-remaining-debt", totalDebt.toFixed(2) + " ₪");
    } else {
        safeSetText("mp-plan-name", "لا يوجد اشتراك فعال حالياً");
        safeSetText("mp-plan-duration", "يرجى التوجه للاستقبال للاشتراك");
        document.getElementById("mp-plan-name").style.color = 'var(--text-muted)';
        safeSetText("mp-remaining-debt", "0.00 ₪");
    }

    // 2. Render subscriptions table
    const subsBody = document.getElementById("mp-subs-table-body");
    if (subsBody) {
        if (subs.length === 0) {
            subsBody.innerHTML = `<tr><td colspan="7" class="text-center" style="color: var(--text-muted);">لم تقم بالاشتراك في أي باقة حتى الآن</td></tr>`;
        } else {
            subsBody.innerHTML = subs.map(s => {
                let badgeClass = "badge-active";
                if (s.status === "منتهي") badgeClass = "badge-expired";
                if (s.status === "مجمد") badgeClass = "badge-frozen";

                return `
                    <tr>
                        <td><strong>${s.plan_name}</strong></td>
                        <td class="val-mono">${s.start_date}</td>
                        <td class="val-mono">${s.end_date}</td>
                        <td class="val-mono">${Number(s.amount).toFixed(2)} ₪</td>
                        <td class="val-mono val-positive">${Number(s.paid).toFixed(2)} ₪</td>
                        <td class="val-mono ${s.remaining > 0 ? 'val-negative' : 'val-positive'}">${Number(s.remaining).toFixed(2)} ₪</td>
                        <td><span class="badge ${badgeClass}">${s.status}</span></td>
                    </tr>
                `;
            }).join("");
        }
    }
    applyTableFiltersForBody("mp-subs-table-body");

    // 3. Render payments and product purchases table
    const purchasesBody = document.getElementById("mp-purchases-table-body");
    if (purchasesBody) {
        if (pays.length === 0) {
            purchasesBody.innerHTML = `<tr><td colspan="4" class="text-center" style="color: var(--text-muted);">لا توجد أي دفعة أو مبيعات مسجلة باسمك</td></tr>`;
        } else {
            purchasesBody.innerHTML = pays.map(p => {
                const isPurchase = p.note.startsWith("شراء:");
                return `
                    <tr>
                        <td class="val-mono">${p.date}</td>
                        <td class="val-mono ${isPurchase ? 'val-negative' : 'val-positive'}">${Number(p.amount).toFixed(2)} ₪</td>
                        <td>${p.method}</td>
                        <td>
                            <strong style="color: ${isPurchase ? 'var(--accent-green)' : 'var(--text-primary)'}">
                                ${p.note}
                            </strong>
                        </td>
                    </tr>
                `;
            }).join("");
        }
    }
    applyTableFiltersForBody("mp-purchases-table-body");
}

function renderTrainerPortal(data) {
    const trainer = data?.trainer || null;
    const assignments = Array.isArray(data?.assignments) ? data.assignments : [];
    const members = Array.isArray(data?.members) ? data.members : [];
    const subscriptions = Array.isArray(data?.subscriptions) ? data.subscriptions : [];
    const trainingPrograms = Array.isArray(data?.trainingPrograms) ? data.trainingPrograms : [];
    const nutritionPrograms = Array.isArray(data?.nutritionPrograms) ? data.nutritionPrograms : [];

    const meta = document.getElementById("trainer-portal-meta");
    const body = document.getElementById("trainer-portal-members-body");
    const programsBody = document.getElementById("trainer-portal-programs-body");
    if (!body) return;

    if (!trainer) {
        if (meta) meta.innerText = "لا يوجد ملف مدرب مرتبط بهذا الحساب";
        body.innerHTML = `<tr><td colspan="7" class="text-center" style="color: var(--text-muted);">يجب ربط المستخدم بملف مدرب من شاشة المدربين</td></tr>`;
        if (programsBody) {
            programsBody.innerHTML = `<tr><td colspan="6" class="text-center" style="color: var(--text-muted);">لا توجد بيانات برامج لعرضها</td></tr>`;
        }
        return;
    }

    const trainerName = trainer.user?.name || `مدرب #${trainer.id}`;
    if (meta) {
        meta.innerText = `${trainerName} • عدد المشتركين المرتبطين: ${assignments.length}`;
    }

    if (assignments.length === 0) {
        body.innerHTML = `<tr><td colspan="7" class="text-center" style="color: var(--text-muted);">لا يوجد مشتركين مرتبطين بهذا المدرب</td></tr>`;
    } else {
        body.innerHTML = assignments.map(a => {
            const member = members.find(m => String(m.id) === String(a.member_id));
            const memberName = member ? member.name : `#${a.member_id}`;
            const memberPhone = member ? (member.phone || '—') : '—';

            const latestSub = subscriptions
                .filter(s => String(s.member_id) === String(a.member_id))
                .sort((x, y) => String(y.end_date || '').localeCompare(String(x.end_date || '')))[0] || null;

            const planName = latestSub ? (latestSub.plan_name || '—') : '—';
            const subStart = latestSub ? normalizeDateForInput(latestSub.start_date) : '—';
            const subEnd = latestSub ? normalizeDateForInput(latestSub.end_date) : '—';
            const subStatus = latestSub ? (latestSub.status || '—') : '—';

            return `
                <tr>
                    <td><strong>${memberName}</strong></td>
                    <td class="val-mono">${memberPhone}</td>
                    <td>${planName}</td>
                    <td class="val-mono">${subStart}</td>
                    <td class="val-mono">${subEnd}</td>
                    <td><span class="badge ${subStatus === 'فعال' ? 'badge-active' : 'badge-frozen'}">${subStatus}</span></td>
                    <td>${a.note || '—'}</td>
                </tr>
            `;
        }).join("");
    }

    if (programsBody) {
        const allPrograms = [
            ...trainingPrograms.map(p => ({ ...p, _type: 'تدريبي' })),
            ...nutritionPrograms.map(p => ({ ...p, _type: 'غذائي' })),
        ].sort((a, b) => String(b.id || '').localeCompare(String(a.id || '')));

        if (allPrograms.length === 0) {
            programsBody.innerHTML = `<tr><td colspan="6" class="text-center" style="color: var(--text-muted);">لا توجد برامج مرتبطة بك حتى الآن</td></tr>`;
        } else {
            programsBody.innerHTML = allPrograms.map(p => {
                const assignment = assignments.find(a => Number(a.id) === Number(p.trainer_assignment_id));
                const member = assignment ? members.find(m => String(m.id) === String(assignment.member_id)) : null;
                const memberName = member ? `${member.name} (${member.id})` : '—';
                const contentLabel = p.content_json
                    ? (typeof p.content_json === 'string'
                        ? p.content_json
                        : (p.content_json.notes || `حقول: ${Object.keys(p.content_json || {}).join('، ')}`))
                    : '—';

                return `
                    <tr>
                        <td>${p._type}</td>
                        <td>${memberName}</td>
                        <td><strong>${p.title || '—'}</strong></td>
                        <td>${p.goal || '—'}</td>
                        <td>${contentLabel}</td>
                        <td>
                            <div style="display: flex; gap: 8px;">
                                <button class="btn btn-secondary btn-sm" onclick="${p._type === 'تدريبي' ? `openEditTrainingProgramModal('${p.id}')` : `openEditNutritionProgramModal('${p.id}')`}">تعديل</button>
                                <button class="btn btn-primary btn-sm" style="background-color: var(--accent-red);" onclick="${p._type === 'تدريبي' ? `deleteTrainingProgram('${p.id}')` : `deleteNutritionProgram('${p.id}')`}">حذف</button>
                            </div>
                        </td>
                    </tr>
                `;
            }).join("");
        }
    }
}

// ==================== MEMBER DETAIL SYSTEM ====================

function openMemberDetail(memberId) {
    state.selectedMemberId = memberId;
    switchView('member-detail');
}

function switchDetailTab(tabName, btnElement) {
    // Toggle active class on tab buttons
    const tabBtns = document.querySelectorAll(".md-tab-btn");
    tabBtns.forEach(btn => btn.classList.remove("active"));
    if (btnElement) btnElement.classList.add("active");

    // Hide all panel contents
    const panels = document.querySelectorAll(".md-tab-panel");
    panels.forEach(p => p.style.display = "none");

    // Show selected panel
    const activePanel = document.getElementById("md-tab-" + tabName);
    if (activePanel) {
        activePanel.style.display = "block";
    }
}

function renderMemberDetail(memberId) {
    const member = state.members.find(m => String(m.id) === String(memberId));
    if (!member) return;

    // 1. Info Header
    safeSetText("md-member-name", member.name);
    
    // Set phone and whatsapp
    safeSetText("md-member-phone", member.phone);
    
    const waContainer = document.getElementById("md-member-whatsapp-container");
    if (waContainer) {
        if (member.whatsapp) {
            waContainer.style.display = "flex";
            safeSetText("md-member-whatsapp", "واتساب: " + member.whatsapp);
        } else {
            waContainer.style.display = "none";
        }
    }
    
    safeSetText("md-member-gender", member.gender || 'ذكر');
    const cardCode = getActiveMembershipCardCode(member.id) || member.membership_number || member.id;
    safeSetText("md-qr-label", cardCode);
    const qrImage = document.getElementById("md-qr-code");
    if (qrImage) {
        qrImage.src = `https://api.qrserver.com/v1/create-qr-code/?size=100x100&data=${encodeURIComponent(cardCode)}`;
    }
    ensureMemberQrActions(cardCode);

    // Render photo in Avatar container if present
    const avatarContainer = document.getElementById("md-avatar-container");
    if (avatarContainer) {
        if (member.image_path) {
            avatarContainer.innerHTML = `<img src="${member.image_path}" style="width: 100%; height: 100%; object-fit: cover;">`;
        } else {
            avatarContainer.innerHTML = `<i data-lucide="user" style="width: 40px; height: 40px;"></i>`;
            lucide.createIcons();
        }
    }

    // Calculate and render overall remaining debt
    const memberSubs = state.subscriptions.filter(s => s.member_id === memberId);
    const totalRemainingDebt = memberSubs.reduce((sum, s) => sum + Number(s.remaining), 0);
    safeSetText("md-total-remaining-debt", totalRemainingDebt.toFixed(2) + " ₪");

    // 2. Subscriptions Tab Content
    const subsContainer = document.getElementById("md-subscriptions-card-container");
    if (subsContainer) {
        const subs = state.subscriptions.filter(s => s.member_id === memberId);
        if (subs.length === 0) {
            subsContainer.innerHTML = `<div style="text-align: center; padding: 20px; color: var(--text-muted);">لا توجد اشتراكات نشطة أو سابقة</div>`;
        } else {
            subsContainer.innerHTML = subs.map(s => {
                let badgeClass = "badge-active";
                if (s.status === "منتهي") badgeClass = "badge-expired";
                if (s.status === "مجمد") badgeClass = "badge-frozen";
                
                let actionBtnLabel = s.status === "مجمد" ? "استئناف" : "تجميد";
                let isBtnDisabled = s.status === "منتهي" ? 'disabled' : '';
                
                // Hide actions button for auditor or apply disabled style
                let btnStyle = '';
                if (state.currentUser && state.currentUser.role === 'المدقق المالي') {
                    btnStyle = 'display: none;';
                } else if (s.status === "منتهي") {
                    btnStyle = 'opacity: 0.5; cursor: not-allowed;';
                }

                return `
                    <div style="display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid var(--border-color); padding-bottom: 16px; margin-bottom: 16px;">
                        <div>
                            <h4 style="font-size: 18px; font-weight: 700; margin-bottom: 8px;">${s.plan_name}</h4>
                            <div style="font-size: 13px; color: var(--text-secondary); margin-bottom: 6px;">
                                <span>البداية: ${s.start_date}</span> &bull; <span>النهاية: ${s.end_date}</span>
                            </div>
                            <div style="font-size: 13px; font-weight: 600;">
                                <span>السعر: ${Number(s.amount).toFixed(2)} ₪</span> &bull;
                                <span class="val-positive">المدفوع: ${Number(s.paid).toFixed(2)} ₪</span> &bull;
                                <span class="${s.remaining > 0 ? 'val-negative' : 'val-positive'}">المتبقي: ${Number(s.remaining).toFixed(2)} ₪</span>
                            </div>
                        </div>
                        <div style="display: flex; align-items: center; gap: 12px;">
                            <span class="badge ${badgeClass}">${s.status}</span>
                            <button class="btn btn-secondary btn-sm" ${isBtnDisabled} style="${btnStyle}" onclick="toggleSubscriptionStatusDetail('${s.id}')">
                                ${actionBtnLabel}
                            </button>
                            ${state.currentUser && ['مدير النظام', 'المحاسب'].includes(state.currentUser.role) ? `
                                <button class="btn btn-secondary btn-sm btn-edit-sub" onclick="openEditSubscriptionModal('${s.id}')" title="تعديل">
                                    <i data-lucide="edit-2" style="width: 13px; height: 13px;"></i>
                                </button>
                            ` : ''}
                        </div>
                    </div>
                `;
            }).join("");
        }
    }

    // 3. Measurements Tab Content
    const measBody = document.getElementById("md-measurements-table-body");
    if (measBody) {
        const meas = state.measurements.filter(m => m.member_id === memberId);
        if (meas.length === 0) {
            measBody.innerHTML = `<tr><td colspan="5" class="text-center" style="color: var(--text-muted); padding: 20px;">لا توجد قياسات مسجلة بعد لهذا المشترك</td></tr>`;
        } else {
            measBody.innerHTML = meas.map(m => `
                <tr>
                    <td class="val-mono">${m.created_at}</td>
                    <td class="val-mono bold" style="color: var(--accent-cyan);">${m.weight}</td>
                    <td class="val-mono">${m.height}</td>
                    <td class="val-mono" style="color: var(--accent-red);">${m.fat_percentage}%</td>
                    <td class="val-mono" style="color: var(--accent-green);">${m.muscle_mass}</td>
                </tr>
            `).join("");
        }
    }
    applyTableFiltersForBody("md-measurements-table-body");

    // 4. Payments Tab Content
    const paysBody = document.getElementById("md-payments-table-body");
    if (paysBody) {
        const pays = state.payments.filter(p =>
            p.member_id === memberId || (!p.member_id && p.member_name === member.name)
        );
        if (pays.length === 0) {
            paysBody.innerHTML = `<tr><td colspan="5" class="text-center" style="color: var(--text-muted); padding: 20px;">لا توجد دفعات مالية مسجلة</td></tr>`;
        } else {
            paysBody.innerHTML = pays.map(p => {
                const editBtn = state.currentUser && ['مدير النظام', 'المحاسب'].includes(state.currentUser.role) ? `
                    <button class="btn btn-secondary btn-sm btn-edit-pay" onclick="openEditPaymentModal('${p.id}')" title="تعديل">
                        <i data-lucide="edit-2" style="width: 13px; height: 13px;"></i>
                    </button>
                ` : '';
                return `
                    <tr>
                        <td class="val-mono">${p.date}</td>
                        <td class="val-mono val-positive bold">${formatMoney(p.amount)}</td>
                        <td>${p.method}</td>
                        <td style="font-size: 13px; color: var(--text-secondary);">${p.note}</td>
                        <td>
                            <div style="display: flex; gap: 8px;">
                                ${editBtn}
                            </div>
                        </td>
                    </tr>
                `;
            }).join("");
        }
    }
    applyTableFiltersForBody("md-payments-table-body");
    
    applyTableFiltersForBody("md-payments-table-body");
    
    // 5. Programs Tab Content
    const programsBody = document.getElementById("md-programs-table-body");
    if (programsBody) {
        const assignments = (state.trainerAssignments || [])
            .filter(a => String(a.member_id) === String(memberId));

        const assignmentIds = assignments.map(a => Number(a.id));
        const trainingPrograms = (state.trainingPrograms || [])
            .filter(p => assignmentIds.includes(Number(p.trainer_assignment_id)))
            .map(p => ({ ...p, _type: 'تدريبي' }));
        const nutritionPrograms = (state.nutritionPrograms || [])
            .filter(p => assignmentIds.includes(Number(p.trainer_assignment_id)))
            .map(p => ({ ...p, _type: 'غذائي' }));

        const allPrograms = [...trainingPrograms, ...nutritionPrograms]
            .sort((a, b) => String(b.id || '').localeCompare(String(a.id || '')));

        if (allPrograms.length === 0) {
            programsBody.innerHTML = `<tr><td colspan="5" class="text-center" style="color: var(--text-muted);">لا توجد برامج مرتبطة بهذا المشترك حتى الآن</td></tr>`;
        } else {
            programsBody.innerHTML = allPrograms.map(p => {
                const assignment = assignments.find(a => Number(a.id) === Number(p.trainer_assignment_id));
                const trainer = assignment
                    ? (state.trainers || []).find(t => Number(t.id) === Number(assignment.trainer_id))
                    : null;
                const trainerName = trainer?.user?.name || (assignment ? `مدرب #${assignment.trainer_id}` : '—');

                let contentLabel = '—';
                if (p.content_json) {
                    if (typeof p.content_json === 'string') {
                        contentLabel = p.content_json;
                    } else if (p.content_json.notes) {
                        contentLabel = p.content_json.notes;
                    } else {
                        const keys = Object.keys(p.content_json || {});
                        contentLabel = keys.length ? `حقول: ${keys.join('، ')}` : '—';
                    }
                }

                return `
                    <tr>
                        <td>${p._type}</td>
                        <td>${trainerName}</td>
                        <td><strong>${p.title || '—'}</strong></td>
                        <td>${p.goal || '—'}</td>
                        <td>${contentLabel}</td>
                    </tr>
                `;
            }).join("");
        }
    }
    applyTableFiltersForBody("md-programs-table-body");
    // Hide add measurements form for Auditor
    const formMeas = document.getElementById("form-add-measurement");
    if (formMeas) {
        if (state.currentUser && state.currentUser.role === 'المدقق المالي') {
            formMeas.style.display = "none";
        } else {
            formMeas.style.display = "grid";
        }
    }
}

function toggleSubscriptionStatusDetail(subId) {
    fetch('/api/toggle_subscription', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ subId })
    })
    .then(r => r.json())
    .then(res => {
        if (res.success) {
            showAppNotice(`تم تغيير حالة الاشتراك إلى: ${res.newStatus}`);
            // Re-fetch state and refresh member details
            loadStateAndRender("member-detail");
        } else {
            showAppNotice("خطأ: " + res.error);
        }
    });
}

function submitAddMeasurement(e) {
    e.preventDefault();
    const memberId = state.selectedMemberId;
    if (!memberId) return;

    const weight = Number(document.getElementById("meas-input-weight").value);
    const height = Number(document.getElementById("meas-input-height").value);
    const fat = Number(document.getElementById("meas-input-fat").value);
    const muscle = Number(document.getElementById("meas-input-muscle").value);

    fetch(resolveApiUrl('add_measurement'), {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ memberId, weight, height, fat, muscle })
    })
    .then(r => r.json())
    .then(res => {
        if (res.success) {
            e.target.reset();
            showAppNotice("تم إضافة قياسات الجسم وحفظها بنجاح!");
            loadStateAndRender("member-detail");
        } else {
            showAppNotice("خطأ: " + res.error);
        }
    });
}

function openChangePasswordModal() {
    ensureFallbackModal("modal-change-password");
    const form = document.getElementById("form-change-password");
    if (form) form.reset();
    openModal("modal-change-password");
}

function submitChangePassword(e) {
    e.preventDefault();
    const currentPassword = document.getElementById("cp-input-current").value;
    const newPassword = document.getElementById("cp-input-new").value;
    const confirmPassword = document.getElementById("cp-input-confirm").value;

    if (newPassword !== confirmPassword) {
        showAppNotice("كلمة المرور الجديدة وتأكيدها غير متطابقين!");
        return;
    }

    fetch('/api/change_password', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ currentPassword, newPassword })
    })
    .then(r => r.json())
    .then(res => {
        if (res.success) {
            e.target.reset();
            closeModal("modal-change-password");
            showAppNotice("تم تغيير كلمة المرور بنجاح!");
        } else {
            showAppNotice("خطأ: " + res.error);
        }
    })
    .catch(err => {
        console.error("Change password error:", err);
        showAppNotice("حدث خطأ بالاتصال بالشبكة!");
    });
}

// ==================== SMART EXCEL IMPORT SYSTEM ====================

let parsedImportData = {
    members: [],
    subscriptions: [],
    payments: []
};

function renderImportView() {
    // Reset dropzone and results container
    document.getElementById("import-summary-container").style.display = "none";
    document.getElementById("import-file-input").value = "";
    
    // Reset parsed data
    parsedImportData = {
        members: [],
        subscriptions: [],
        payments: []
    };

    // Bind dropzone drag-and-drop events dynamically
    initImportDropzone();
}

function initImportDropzone() {
    const dropzone = document.getElementById("import-dropzone");
    if (!dropzone) return;

    // Prevent default drag behaviors
    ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
        dropzone.addEventListener(eventName, preventDefaults, false);
    });

    function preventDefaults(e) {
        e.preventDefault();
        e.stopPropagation();
    }

    // Highlight dropzone on drag over
    ['dragenter', 'dragover'].forEach(eventName => {
        dropzone.addEventListener(eventName, () => dropzone.classList.add('dragover'), false);
    });

    ['dragleave', 'drop'].forEach(eventName => {
        dropzone.addEventListener(eventName, () => dropzone.classList.remove('dragover'), false);
    });

    // Handle dropped files
    dropzone.addEventListener('drop', (e) => {
        const dt = e.dataTransfer;
        const files = dt.files;
        if (files.length > 0) {
            handleImportFile(files[0]);
        }
    }, false);
}

function onImportFileSelected(e) {
    const files = e.target.files;
    if (files.length > 0) {
        handleImportFile(files[0]);
    }
}

function handleImportFile(file) {
    if (!file) return;
    
    // Check file extension
    const ext = file.name.split('.').pop().toLowerCase();
    if (ext !== 'xlsx' && ext !== 'xls' && ext !== 'csv') {
        showAppNotice("ملف غير صالح! يرجى اختيار ملف إكسل (.xlsx, .xls) أو ملف CSV.");
        return;
    }

    const reader = new FileReader();
    reader.onload = function(e) {
        try {
            const data = new Uint8Array(e.target.result);
            const workbook = XLSX.read(data, { type: 'array' });
            
            // Temporary variables to parse
            let rawMembers = [];
            let rawSubs = [];
            let rawPays = [];

            // Parse Sheet 1: المشتركون (Members)
            const membersSheetName = workbook.SheetNames.find(name => name.includes("مشترك") || name.toLowerCase().includes("member"));
            if (membersSheetName) {
                const sheet = workbook.Sheets[membersSheetName];
                const json = XLSX.utils.sheet_to_json(sheet);
                rawMembers = json.map(row => {
                    // Match Arabic / English column headers
                    return {
                        id: String(row['رقم العضوية'] || row['رقم المشترك'] || row['id'] || row['ID'] || '').trim(),
                        name: String(row['الاسم الكامل'] || row['الاسم'] || row['name'] || row['Name'] || '').trim(),
                        phone: String(row['رقم الجوال'] || row['الجوال'] || row['phone'] || row['Phone'] || '').trim(),
                        whatsapp: String(row['رقم الواتساب'] || row['الواتساب'] || row['whatsapp'] || row['WhatsApp'] || '').trim(),
                        gender: String(row['الجنس'] || row['gender'] || row['Gender'] || 'ذكر').trim()
                    };
                }).filter(m => m.name !== "");
            }

            // Parse Sheet 2: الاشتراكات (Subscriptions)
            const subsSheetName = workbook.SheetNames.find(name => name.includes("اشتراك") || name.toLowerCase().includes("sub"));
            if (subsSheetName) {
                const sheet = workbook.Sheets[subsSheetName];
                const json = XLSX.utils.sheet_to_json(sheet);
                rawSubs = json.map(row => {
                    return {
                        member_id: String(row['رقم العضوية'] || row['رقم المشترك'] || row['member_id'] || row['Member ID'] || '').trim(),
                        plan_name: String(row['اسم الباقة'] || row['اسم الاشتراك'] || row['الخطة'] || row['plan_name'] || row['Plan Name'] || '').trim(),
                        amount: Number(row['القيمة الكلية'] || row['القيمة'] || row['السعر'] || row['amount'] || row['Amount'] || 0),
                        paid: Number(row['المدفوع'] || row['paid'] || row['Paid'] || 0),
                        remaining: Number(row['المتبقي'] || row['remaining'] || row['Remaining'] || 0),
                        status: String(row['الحالة'] || row['status'] || row['Status'] || 'فعال').trim(),
                        start_date: String(row['تاريخ البدء'] || row['البداية'] || row['start_date'] || row['Start Date'] || '').trim(),
                        end_date: String(row['تاريخ الانتهاء'] || row['النهاية'] || row['end_date'] || row['End Date'] || '').trim()
                    };
                }).filter(s => s.member_id !== "" && s.plan_name !== "");
            }

            // Parse Sheet 3: المدفوعات (Payments)
            const paysSheetName = workbook.SheetNames.find(name => name.includes("مدفوع") || name.toLowerCase().includes("pay"));
            if (paysSheetName) {
                const sheet = workbook.Sheets[paysSheetName];
                const json = XLSX.utils.sheet_to_json(sheet);
                rawPays = json.map(row => {
                    return {
                        member_id: String(row['رقم العضوية'] || row['رقم المشترك'] || row['member_id'] || row['Member ID'] || '').trim(),
                        member_name: String(row['اسم المشترك'] || row['الاسم'] || row['member_name'] || row['Member Name'] || '').trim(),
                        amount: Number(row['المبلغ المدفوع'] || row['المبلغ'] || row['amount'] || row['Amount'] || 0),
                        method: String(row['طريقة الدفع'] || row['الطريقة'] || row['method'] || row['Method'] || 'نقدي').trim(),
                        date: String(row['تاريخ الدفع'] || row['التاريخ'] || row['date'] || row['Date'] || '').trim(),
                        note: String(row['ملاحظة'] || row['ملاحظات'] || row['note'] || row['Note'] || '').trim()
                    };
                }).filter(p => (p.member_id !== "" || p.member_name !== "") && p.amount > 0);
            }

            // Assign to state variables
            parsedImportData.members = rawMembers;
            parsedImportData.subscriptions = rawSubs;
            parsedImportData.payments = rawPays;

            // Check if anything was parsed
            const totalRecords = rawMembers.length + rawSubs.length + rawPays.length;
            if (totalRecords === 0) {
                showAppNotice("تنبيه: لم يتم العثور على أي أوراق عمل مطابقة (المشتركون، الاشتراكات، المدفوعات) أو الملف فارغ!");
                return;
            }

            // Update DOM labels
            document.getElementById("import-filename-label").innerText = `${file.name} (${(file.size / 1024).toFixed(1)} KB)`;
            document.getElementById("import-count-members").innerText = rawMembers.length;
            document.getElementById("import-count-subscriptions").innerText = rawSubs.length;
            document.getElementById("import-count-payments").innerText = rawPays.length;

            // Render Preview Tables
            renderImportPreviewTables();

            // Toggle views
            document.getElementById("import-summary-container").style.display = "block";

            // Bind SVG icons
            lucide.createIcons();

        } catch (err) {
            console.error("Excel parse error:", err);
            showAppNotice("حدث خطأ أثناء قراءة ملف إكسل. تأكد من أن الملف غير معطوب وبصيغة صحيحة.");
        }
    };
    reader.readAsArrayBuffer(file);
}

function renderImportPreviewTables() {
    // 1. Members preview table (first 10 records)
    const membersBody = document.getElementById("import-preview-body-members");
    if (membersBody) {
        if (parsedImportData.members.length === 0) {
            membersBody.innerHTML = `<tr><td colspan="5" class="text-center" style="color: var(--text-muted);">لا توجد سجلات مشتركين بالملف</td></tr>`;
        } else {
            membersBody.innerHTML = parsedImportData.members.slice(0, 10).map(m => `
                <tr>
                    <td class="val-mono">${m.id || '— تلقائي —'}</td>
                    <td><strong>${m.name}</strong></td>
                    <td class="val-mono">${m.phone}</td>
                    <td class="val-mono">${m.whatsapp || '—'}</td>
                    <td><span class="badge ${m.gender === 'ذكر' ? 'badge-active' : 'badge-frozen'}">${m.gender}</span></td>
                </tr>
            `).join("") + (parsedImportData.members.length > 10 ? `<tr><td colspan="5" class="text-center" style="color: var(--text-muted); font-size: 11px;">... وتم إخفاء ${parsedImportData.members.length - 10} سجل متبقي من المعاينة ...</td></tr>` : '');
        }
    }

    // 2. Subscriptions preview table (first 10 records)
    const subsBody = document.getElementById("import-preview-body-subscriptions");
    if (subsBody) {
        if (parsedImportData.subscriptions.length === 0) {
            subsBody.innerHTML = `<tr><td colspan="8" class="text-center" style="color: var(--text-muted);">لا توجد سجلات اشتراكات بالملف</td></tr>`;
        } else {
            subsBody.innerHTML = parsedImportData.subscriptions.slice(0, 10).map(s => `
                <tr>
                    <td class="val-mono">${s.member_id}</td>
                    <td><strong>${s.plan_name}</strong></td>
                    <td class="val-mono">${formatMoney(s.amount)}</td>
                    <td class="val-mono val-positive">${formatMoney(s.paid)}</td>
                    <td class="val-mono ${toNumber(s.remaining) > 0 ? 'val-negative' : 'val-positive'}">${formatMoney(s.remaining)}</td>
                    <td><span class="badge ${s.status === 'منتهي' ? 'badge-expired' : (s.status === 'مجمد' ? 'badge-frozen' : 'badge-active')}">${s.status}</span></td>
                    <td class="val-mono">${s.start_date}</td>
                    <td class="val-mono">${s.end_date}</td>
                </tr>
            `).join("") + (parsedImportData.subscriptions.length > 10 ? `<tr><td colspan="8" class="text-center" style="color: var(--text-muted); font-size: 11px;">... وتم إخفاء ${parsedImportData.subscriptions.length - 10} سجل متبقي من المعاينة ...</td></tr>` : '');
        }
    }

    // 3. Payments preview table (first 10 records)
    const paysBody = document.getElementById("import-preview-body-payments");
    if (paysBody) {
        if (parsedImportData.payments.length === 0) {
            paysBody.innerHTML = `<tr><td colspan="5" class="text-center" style="color: var(--text-muted);">لا توجد سجلات مدفوعات بالملف</td></tr>`;
        } else {
            paysBody.innerHTML = parsedImportData.payments.slice(0, 10).map(p => `
                <tr>
                    <td><strong>${p.member_name || p.member_id}</strong></td>
                    <td class="val-mono val-positive">${formatMoney(p.amount)}</td>
                    <td>${p.method}</td>
                    <td class="val-mono">${p.date}</td>
                    <td><span style="font-size: 11px; color: var(--text-secondary);">${p.note || '—'}</span></td>
                </tr>
            `).join("") + (parsedImportData.payments.length > 10 ? `<tr><td colspan="5" class="text-center" style="color: var(--text-muted); font-size: 11px;">... وتم إخفاء ${parsedImportData.payments.length - 10} سجل متبقي من المعاينة ...</td></tr>` : '');
        }
    }
    applyTableFiltersForBody("import-preview-body-members");
    applyTableFiltersForBody("import-preview-body-subscriptions");
    applyTableFiltersForBody("import-preview-body-payments");
}

function switchImportPreviewTab(tabName, btn) {
    // Toggle active tab buttons
    const tabBtns = document.querySelectorAll(".import-tab-btn");
    tabBtns.forEach(b => b.classList.remove("active"));
    btn.classList.add("active");

    // Toggle preview panels
    const panels = document.querySelectorAll(".import-preview-panel");
    panels.forEach(p => p.style.display = "none");
    
    document.getElementById(`import-preview-panel-${tabName}`).style.display = "block";
}

function submitSmartImport() {
    const totalRecords = parsedImportData.members.length + parsedImportData.subscriptions.length + parsedImportData.payments.length;
    if (totalRecords === 0) {
        showAppNotice("لا توجد بيانات صالحة للاستيراد!");
        return;
    }

    if (!confirm(`هل أنت متأكد من بدء الاستيراد الذكي لعدد ${totalRecords} سجل وتصفية المكررات؟`)) {
        return;
    }

    const btn = document.getElementById("btn-execute-import");
    btn.disabled = true;
    btn.innerHTML = `<span>جاري استيراد وتصفية البيانات...</span>`;

    fetch('/api/import_data', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(parsedImportData),
        credentials: 'same-origin'
    })
    .then(r => r.json())
    .then(res => {
        btn.disabled = false;
        btn.innerHTML = `<i data-lucide="check"></i><span>بدء الاستيراد الذكي وتصفية المكررات</span>`;
        lucide.createIcons();

        if (res.success) {
            showAppNotice(`تم الانتهاء من الاستيراد الذكي وتصفية التكرارات بنجاح!\n\n` +
                  `المشتركين: تم إضافة ${res.imported_members_count} جديد وتحديث ${res.updated_members_count}.\n` +
                  `الاشتراكات: تم إضافة ${res.imported_subs_count} جديد (وتخطي ${res.skipped_subs_count} مكرر).\n` +
                  `المدفوعات: تم إضافة ${res.imported_pays_count} جديد (وتخطي ${res.skipped_pays_count} مكرر).`);
            
            // Reset page and reload state
            renderImportView();
            loadStateAndRender("dashboard");
            switchView("dashboard");
        } else {
            showAppNotice("فشل الاستيراد: " + res.error);
        }
    })
    .catch(err => {
        btn.disabled = false;
        btn.innerHTML = `<i data-lucide="check"></i><span>بدء الاستيراد الذكي وتصفية المكررات</span>`;
        lucide.createIcons();
        console.error("Import error:", err);
        showAppNotice("حدث خطأ بالاتصال أثناء إرسال البيانات!");
    });
}

// ==================== GLOBAL SEARCH WORKSPACE ====================
let globalSearchInitialized = false;
let gsearchTimeout = null;

function renderGlobalSearch() {
    const role = state.currentUser.role;
    const revSummary = document.getElementById("search-revenue-summary");
    if (revSummary) {
        if (['مدير النظام', 'المحاسب', 'المدقق المالي'].includes(role)) {
            revSummary.style.display = "grid";
        } else {
            revSummary.style.display = "none";
        }
    }
    
    setupGlobalSearchCategories();
    
    if (!globalSearchInitialized) {
        const inputEl = document.getElementById("gsearch-input");
        const catEl = document.getElementById("gsearch-category");
        const startEl = document.getElementById("gsearch-start-date");
        const endEl = document.getElementById("gsearch-end-date");
        const statusEl = document.getElementById("gsearch-status");
        
        if (inputEl) inputEl.addEventListener("input", debounceGlobalSearch);
        if (catEl) catEl.addEventListener("change", executeGlobalSearch);
        if (startEl) startEl.addEventListener("change", executeGlobalSearch);
        if (endEl) endEl.addEventListener("change", executeGlobalSearch);
        if (statusEl) statusEl.addEventListener("change", executeGlobalSearch);
        
        globalSearchInitialized = true;
    }
    
    executeGlobalSearch();
}

function setupGlobalSearchCategories() {
    const role = state.currentUser.role;
    const catSelect = document.getElementById("gsearch-category");
    if (!catSelect) return;
    
    const currentVal = catSelect.value;
    
    const allOpt = '<option value="all">الكل (بحث شامل)</option>';
    const membersOpt = '<option value="members">المشتركون</option>';
    const subsOpt = '<option value="subscriptions">الاشتراكات</option>';
    const paysOpt = '<option value="payments">المدفوعات</option>';
    const salesOpt = '<option value="sales">المبيعات</option>';
    const logsOpt = '<option value="logs">سجل الحركات</option>';
    
    if (role === 'موظف الاستقبال') {
        catSelect.innerHTML = allOpt + membersOpt;
    } else if (role === 'المحاسب') {
        catSelect.innerHTML = allOpt + membersOpt + subsOpt + paysOpt + salesOpt;
    } else {
        catSelect.innerHTML = allOpt + membersOpt + subsOpt + paysOpt + salesOpt + logsOpt;
    }
    
    // Restore value if still exists in the new options list
    const options = Array.from(catSelect.options).map(o => o.value);
    if (options.includes(currentVal)) {
        catSelect.value = currentVal;
    } else {
        catSelect.value = "all";
    }
}

function debounceGlobalSearch() {
    clearTimeout(gsearchTimeout);
    gsearchTimeout = setTimeout(executeGlobalSearch, 250);
}

function executeGlobalSearch() {
    const queryVal = (document.getElementById("gsearch-input")?.value || "").trim();
    const catVal = document.getElementById("gsearch-category")?.value || "all";
    const startVal = document.getElementById("gsearch-start-date")?.value || "";
    const endVal = document.getElementById("gsearch-end-date")?.value || "";
    const statusVal = document.getElementById("gsearch-status")?.value || "all";
    
    const resultsContainer = document.getElementById("gsearch-results-container");
    if (!resultsContainer) return;
    
    const paramsEmpty = !queryVal && !startVal && !endVal && statusVal === 'all';
    
    fetch('/api/global_search', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            query: queryVal,
            category: catVal,
            startDate: startVal,
            endDate: endVal,
            status: statusVal
        })
    })
    .then(res => res.json())
    .then(res => {
        if (res.success) {
            // Render Revenues
            if (res.revenues) {
                renderSearchRevenues(res.revenues);
            }
            
            // Render Search Results
            if (paramsEmpty) {
                resultsContainer.innerHTML = `
                    <div style="text-align: center; padding: 40px; color: var(--text-muted);">
                        <i data-lucide="search" style="width: 48px; height: 48px; margin-bottom: 16px; opacity: 0.5; display: inline-block;"></i>
                        <p>الرجاء كتابة كلمة بحث لبدء الاستعلام الشامل عن السجلات</p>
                    </div>
                `;
                lucide.createIcons();
            } else {
                renderSearchResults(res.results, queryVal);
            }
        } else {
            resultsContainer.innerHTML = `<div class="alert alert-danger" style="margin-top: 16px;">${res.error}</div>`;
        }
    })
    .catch(err => {
        console.error("Global search error:", err);
        resultsContainer.innerHTML = `<div class="alert alert-danger" style="margin-top: 16px;">فشل الاتصال بالخادم</div>`;
    });
}

function renderSearchRevenues(revs) {
    const periods = ['daily', 'weekly', 'monthly', 'yearly'];
    periods.forEach(p => {
        const data = revs[p];
        const valEl = document.getElementById(`search-rev-${p}`);
        const detEl = document.getElementById(`search-rev-${p}-detail`);
        
        if (valEl && data) {
            valEl.innerText = formatMoney(data.total);
        }
        if (detEl && data) {
            detEl.innerText = `نقدي: ${formatMoney(data.cash)} | تحويل: ${formatMoney(data.transfer)}`;
        }
    });
}

function highlightSearchMatch(text, query) {
    if (!text) return "";
    text = String(text);
    if (!query) return text;
    const escapedQuery = query.replace(/[-\/\\^$*+?.()|[\]{}]/g, '\\$&');
    const regex = new RegExp(`(${escapedQuery})`, 'gi');
    return text.replace(regex, `<span class="gsearch-highlight">$1</span>`);
}

function renderSearchResults(results, query) {
    const resultsContainer = document.getElementById("gsearch-results-container");
    if (!resultsContainer) return;
    
    let html = "";
    
    const hasResults = Object.values(results).some(arr => arr && arr.length > 0);
    if (!hasResults) {
        resultsContainer.innerHTML = `
            <div style="text-align: center; padding: 40px; color: var(--text-muted);">
                <i data-lucide="info" style="width: 48px; height: 48px; margin-bottom: 16px; opacity: 0.5; display: inline-block;"></i>
                <p>لم يتم العثور على أي نتائج تطابق البحث والخيارات المحددة</p>
            </div>
        `;
        lucide.createIcons();
        return;
    }
    
    // 1. Members
    if (results.members && results.members.length > 0) {
        html += `
            <div class="card gsearch-category-section">
                <div class="gsearch-category-header">
                    <span class="gsearch-category-title">
                        <i data-lucide="users" style="color: var(--accent-cyan);"></i>
                        المشتركون
                    </span>
                    <span class="gsearch-category-count">${results.members.length} سجل</span>
                </div>
                <div class="table-responsive">
                    <table class="custom-table">
                        <thead>
                            <tr>
                                <th>رقم العضوية</th>
                                <th>اسم المشترك</th>
                                <th>رقم الجوال</th>
                                <th>الجنس</th>
                                <th>الواتساب</th>
                                <th>تاريخ التسجيل</th>
                            </tr>
                        </thead>
                        <tbody>
                            ${results.members.map(m => `
                                <tr style="cursor: pointer;" onclick="openMemberDetail('${m.id}')">
                                    <td class="val-mono bold" style="color: var(--accent-cyan);">${highlightSearchMatch(m.id, query)}</td>
                                    <td class="bold">${highlightSearchMatch(m.name, query)}</td>
                                    <td>${highlightSearchMatch(m.phone, query)}</td>
                                    <td>${m.gender}</td>
                                    <td style="direction: ltr; text-align: right;">${m.whatsapp ? highlightSearchMatch(m.whatsapp, query) : '—'}</td>
                                    <td>${m.created_at}</td>
                                </tr>
                            `).join("")}
                        </tbody>
                    </table>
                </div>
            </div>
        `;
    }
    
    // 2. Subscriptions
    if (results.subscriptions && results.subscriptions.length > 0) {
        html += `
            <div class="card gsearch-category-section">
                <div class="gsearch-category-header">
                    <span class="gsearch-category-title">
                        <i data-lucide="calendar" style="color: var(--accent-yellow);"></i>
                        الاشتراكات
                    </span>
                    <span class="gsearch-category-count">${results.subscriptions.length} سجل</span>
                </div>
                <div class="table-responsive">
                    <table class="custom-table">
                        <thead>
                            <tr>
                                <th>رمز الاشتراك</th>
                                <th>اسم المشترك</th>
                                <th>الباقة</th>
                                <th>تاريخ البدء</th>
                                <th>تاريخ الانتهاء</th>
                                <th>المبلغ الإجمالي</th>
                                <th>المدفوع</th>
                                <th>المتبقي</th>
                                <th>الحالة</th>
                            </tr>
                        </thead>
                        <tbody>
                            ${results.subscriptions.map(s => `
                                <tr style="cursor: pointer;" onclick="openMemberDetail('${s.member_id}')">
                                    <td class="val-mono bold" style="color: var(--accent-yellow);">${highlightSearchMatch(s.id, query)}</td>
                                    <td class="bold">${highlightSearchMatch(s.member_name, query)} (${s.member_id})</td>
                                    <td>${highlightSearchMatch(s.plan_name, query)}</td>
                                    <td>${s.start_date}</td>
                                    <td>${s.end_date}</td>
                                    <td class="val-mono">${formatMoney(s.amount)}</td>
                                    <td class="val-positive">${formatMoney(s.paid)}</td>
                                    <td class="${toNumber(s.remaining) > 0 ? 'val-negative' : 'val-mono'}">${formatMoney(s.remaining)}</td>
                                    <td>
                                        <span class="badge ${s.status === 'فعال' ? 'badge-active' : s.status === 'منتهي' ? 'badge-expired' : 'badge-frozen'}">
                                            ${s.status}
                                        </span>
                                    </td>
                                </tr>
                            `).join("")}
                        </tbody>
                    </table>
                </div>
            </div>
        `;
    }
    
    // 3. Payments
    if (results.payments && results.payments.length > 0) {
        html += `
            <div class="card gsearch-category-section">
                <div class="gsearch-category-header">
                    <span class="gsearch-category-title">
                        <i data-lucide="coins" style="color: var(--accent-green);"></i>
                        المدفوعات وسندات القبض
                    </span>
                    <span class="gsearch-category-count">${results.payments.length} سجل</span>
                </div>
                <div class="table-responsive">
                    <table class="custom-table">
                        <thead>
                            <tr>
                                <th>رمز الدفعة</th>
                                <th>اسم المشترك / الوصف</th>
                                <th>التاريخ والوقت</th>
                                <th>المبلغ</th>
                                <th>طريقة الدفع</th>
                                <th>الملاحظات</th>
                            </tr>
                        </thead>
                        <tbody>
                            ${results.payments.map(p => `
                                <tr>
                                    <td class="val-mono" style="color: var(--accent-green);">${highlightSearchMatch(p.id, query)}</td>
                                    <td class="bold">${highlightSearchMatch(getPaymentMemberDisplay(p), query)}</td>
                                    <td>${p.date}</td>
                                    <td class="val-positive font-bold">${formatMoney(p.amount)}</td>
                                    <td>${highlightSearchMatch(p.method, query)}</td>
                                    <td>${p.note ? highlightSearchMatch(p.note, query) : '—'}</td>
                                </tr>
                            `).join("")}
                        </tbody>
                    </table>
                </div>
            </div>
        `;
    }
    
    // 4. Sales
    if (results.sales && results.sales.length > 0) {
        html += `
            <div class="card gsearch-category-section">
                <div class="gsearch-category-header">
                    <span class="gsearch-category-title">
                        <i data-lucide="shopping-cart" style="color: var(--accent-red);"></i>
                        مبيعات الكاشير
                    </span>
                    <span class="gsearch-category-count">${results.sales.length} سجل</span>
                </div>
                <div class="table-responsive">
                    <table class="custom-table">
                        <thead>
                            <tr>
                                <th>رقم العملية</th>
                                <th>التاريخ والوقت</th>
                                <th>المنتجات المباعة</th>
                                <th>المجموع الكلي</th>
                                <th>طريقة الدفع</th>
                            </tr>
                        </thead>
                        <tbody>
                            ${results.sales.map(s => `
                                <tr>
                                    <td class="val-mono">#${s.id}</td>
                                    <td>${s.date}</td>
                                    <td class="bold">${highlightSearchMatch(s.products, query)}</td>
                                    <td class="val-positive">${formatMoney(s.total)}</td>
                                    <td>${highlightSearchMatch(s.method, query)}</td>
                                </tr>
                            `).join("")}
                        </tbody>
                    </table>
                </div>
            </div>
        `;
    }
    
    // 5. Products
    if (results.products && results.products.length > 0) {
        html += `
            <div class="card gsearch-category-section">
                <div class="gsearch-category-header">
                    <span class="gsearch-category-title">
                        <i data-lucide="package" style="color: var(--accent-orange);"></i>
                        المنتجات والمخزون
                    </span>
                    <span class="gsearch-category-count">${results.products.length} سجل</span>
                </div>
                <div class="table-responsive">
                    <table class="custom-table">
                        <thead>
                            <tr>
                                <th>رمز المنتج</th>
                                <th>اسم المنتج</th>
                                <th>سعر البيع</th>
                                <th>الكمية المتوفرة</th>
                            </tr>
                        </thead>
                        <tbody>
                            ${results.products.map(pr => `
                                <tr>
                                    <td class="val-mono">${highlightSearchMatch(pr.id, query)}</td>
                                    <td class="bold">${highlightSearchMatch(pr.name, query)}</td>
                                    <td class="val-positive">${formatMoney(pr.price)}</td>
                                    <td class="${pr.stock <= 3 ? 'val-negative' : 'val-mono'}">${pr.stock} وحدات</td>
                                </tr>
                            `).join("")}
                        </tbody>
                    </table>
                </div>
            </div>
        `;
    }
    
    // 6. Checkins
    if (results.checkins && results.checkins.length > 0) {
        html += `
            <div class="card gsearch-category-section">
                <div class="gsearch-category-header">
                    <span class="gsearch-category-title">
                        <i data-lucide="check-square" style="color: var(--accent-cyan);"></i>
                        سجل الحضور اليومي
                    </span>
                    <span class="gsearch-category-count">${results.checkins.length} سجل</span>
                </div>
                <div class="table-responsive">
                    <table class="custom-table">
                        <thead>
                            <tr>
                                <th>رقم العضوية</th>
                                <th>اسم المشترك</th>
                                <th>وقت الحضور</th>
                                <th>التاريخ والوقت الكامل</th>
                            </tr>
                        </thead>
                        <tbody>
                            ${results.checkins.map(chk => `
                                <tr style="cursor: pointer;" onclick="openMemberDetail('${chk.member_id}')">
                                    <td class="val-mono bold">${highlightSearchMatch(chk.member_id, query)}</td>
                                    <td class="bold">${highlightSearchMatch(chk.member_name, query)}</td>
                                    <td>${chk.time}</td>
                                    <td>${chk.created_at}</td>
                                </tr>
                            `).join("")}
                        </tbody>
                    </table>
                </div>
            </div>
        `;
    }
    
    // 7. Activity Logs
    if (results.logs && results.logs.length > 0) {
        html += `
            <div class="card gsearch-category-section">
                <div class="gsearch-category-header">
                    <span class="gsearch-category-title">
                        <i data-lucide="scroll" style="color: var(--text-muted);"></i>
                        سجل الحركات على النظام
                    </span>
                    <span class="gsearch-category-count">${results.logs.length} سجل</span>
                </div>
                <div class="table-responsive">
                    <table class="custom-table">
                        <thead>
                            <tr>
                                <th>المستخدم</th>
                                <th>الاسم الكامل</th>
                                <th>الدور</th>
                                <th>نوع الحركة</th>
                                <th>التفاصيل والبيانات</th>
                                <th>الوقت</th>
                            </tr>
                        </thead>
                        <tbody>
                            ${results.logs.map(log => `
                                <tr>
                                    <td class="bold">${highlightSearchMatch(log.username, query)}</td>
                                    <td>${highlightSearchMatch(log.name, query)}</td>
                                    <td>${log.role}</td>
                                    <td class="val-positive">${highlightSearchMatch(log.action, query)}</td>
                                    <td style="max-width: 300px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;" title="${log.details}">
                                        ${highlightSearchMatch(log.details, query)}
                                    </td>
                                    <td>${log.created_at}</td>
                                </tr>
                            `).join("")}
                        </tbody>
                    </table>
                </div>
            </div>
        `;
    }
    
    resultsContainer.innerHTML = html;
    lucide.createIcons();
    initAllTableFilters();
}

// ==================== EDIT SUBSCRIPTION WORKSPACE ====================
function openEditSubscriptionModal(subId) {
    const sub = state.subscriptions.find(s => s.id === subId);
    if (!sub) {
        showAppNotice("الاشتراك غير موجود!");
        return;
    }

    // Ensure the lazily-created modal fields exist before populating them.
    openModal("modal-edit-subscription");

    const member = state.members.find(m => m.id === sub.member_id) || { name: "مجهول" };
    const idInput = document.getElementById("edit-sub-id");
    const memberNameInput = document.getElementById("edit-sub-member-name");
    const startDateInput = document.getElementById("edit-sub-start-date");
    const endDateInput = document.getElementById("edit-sub-end-date");
    const amountInput = document.getElementById("edit-sub-amount");
    const paidInput = document.getElementById("edit-sub-paid");
    const statusInput = document.getElementById("edit-sub-status");
    if (![idInput, memberNameInput, startDateInput, endDateInput, amountInput, paidInput, statusInput].every(Boolean)) {
        showAppNotice("تعذر فتح نموذج تعديل الاشتراك. أعد تحميل الصفحة ثم حاول مجدداً.", 'error');
        return;
    }

    idInput.value = sub.id || '';
    memberNameInput.value = `${member.name} (${sub.member_id || '—'})`;
    ensureEditSubscriptionPlanSelect(sub.plan_id, sub.plan_name);
    startDateInput.value = String(sub.start_date || '').slice(0, 10);
    endDateInput.value = String(sub.end_date || '').slice(0, 10);
    amountInput.value = sub.amount ?? '';
    paidInput.value = sub.paid ?? '';
    statusInput.value = sub.status || 'فعال';

    const editSubAmountInput = document.getElementById("edit-sub-amount");
    const editSubPaidInput = document.getElementById("edit-sub-paid");
    const editSubPlanControl = document.getElementById("edit-sub-plan-name");

    if (editSubAmountInput && editSubPaidInput && editSubPlanControl) {
        editSubAmountInput.dataset.userEdited = "0";
        editSubPaidInput.dataset.userEdited = "0";
        setEditSubscriptionHint("تغيير الخطة يمكنه تعبئة المبلغ والمدفوع تلقائيا.", "info");

        editSubAmountInput.oninput = () => {
            editSubAmountInput.dataset.userEdited = "1";
        };
        editSubPaidInput.oninput = () => {
            editSubPaidInput.dataset.userEdited = "1";
        };
        editSubPlanControl.onchange = () => {
            const syncResult = syncEditSubscriptionAmountFromPlan(false);
            if (syncResult.updatedAmount || syncResult.updatedPaid) {
                setEditSubscriptionHint("تم تحديث المبلغ والمدفوع تلقائيا حسب الخطة المختارة.", "success");
            } else if (syncResult.skippedByManual) {
                setEditSubscriptionHint("تم الحفاظ على القيم لأنك عدلتها يدويا.", "warning");
            }
        };
    }
    
}

async function submitEditSubscription(event) {
    event.preventDefault();
    
    const subId = document.getElementById("edit-sub-id").value;
    const planInputRaw = (document.getElementById("edit-sub-plan-name").value || "").trim();
    const matchedPlan = state.plans.find(p => p.id === planInputRaw || p.name === planInputRaw);
    const planId = matchedPlan ? matchedPlan.id : null;
    const planName = matchedPlan ? matchedPlan.name : planInputRaw;
    const startDate = document.getElementById("edit-sub-start-date").value;
    const endDate = document.getElementById("edit-sub-end-date").value;
    const amount = parseFloat(document.getElementById("edit-sub-amount").value);
    const paid = parseFloat(document.getElementById("edit-sub-paid").value);
    const status = document.getElementById("edit-sub-status").value;
    
    if (!planName || !startDate || !endDate || isNaN(amount) || isNaN(paid)) {
        showAppNotice("يرجى ملء جميع الحقول بشكل صحيح!");
        return;
    }
    
    try {
        const res = await postApi('edit_subscription', {
            subId: subId,
            planId: planId,
            planName: planName,
            startDate: startDate,
            endDate: endDate,
            amount: amount,
            paid: paid,
            status: status
        });
        if (res.success) {
            closeModal("modal-edit-subscription");
            showAppNotice("تم تعديل الاشتراك وحفظ البيانات بنجاح!");
            
            // Reload state from DB and refresh current view panel
            if (currentView === "member-detail") {
                loadStateAndRender("member-detail");
            } else {
                loadStateAndRender(currentView);
            }
        } else {
            showAppNotice("خطأ أثناء الحفظ: " + (res.error || 'تعذر حفظ التعديلات.'));
        }
    } catch (err) {
        console.error("Edit subscription error:", err);
        showAppNotice("حدث خطأ بالاتصال أثناء إرسال البيانات!");
    }
}

// ==================== EDIT PAYMENT WORKSPACE ====================
function openEditPaymentModal(payId) {
    const pay = state.payments.find(p => p.id === payId);
    if (!pay) {
        showAppNotice("الدفعة غير موجودة!");
        return;
    }

    // The modal may be created lazily, so it must exist before querying its fields.
    openModal("modal-edit-payment");

    const fields = {
        id: document.getElementById("edit-pay-id"),
        memberName: document.getElementById("edit-pay-member-name"),
        date: document.getElementById("edit-pay-date"),
        amount: document.getElementById("edit-pay-amount"),
        method: document.getElementById("edit-pay-method"),
        transferFromAccount: document.getElementById("edit-pay-transfer-from-account"),
        note: document.getElementById("edit-pay-note"),
    };

    if (!Object.values(fields).every(Boolean)) {
        showAppNotice("تعذر فتح نموذج تعديل الدفعة. أعد تحميل الصفحة ثم حاول مجدداً.", 'error');
        return;
    }

    fields.id.value = pay.id || '';
    fields.memberName.value = getPaymentMemberDisplay(pay);
    fields.date.value = String(pay.date || '').slice(0, 16);
    fields.amount.value = pay.amount ?? '';
    fields.method.value = pay.method || 'نقدي';
    fields.transferFromAccount.value = pay.transfer_from_account || '';
    fields.note.value = pay.note || '';
    togglePaymentTransferAccountField('edit-pay-method', 'edit-pay-transfer-account-group', 'edit-pay-transfer-from-account');
}

function submitEditPayment(event) {
    event.preventDefault();
    
    const payId = document.getElementById("edit-pay-id").value;
    const date = document.getElementById("edit-pay-date").value.trim();
    const amount = parseFloat(document.getElementById("edit-pay-amount").value);
    const method = document.getElementById("edit-pay-method").value;
    const transferFromAccount = document.getElementById("edit-pay-transfer-from-account")?.value.trim() || "";
    const note = document.getElementById("edit-pay-note").value.trim();
    
    if (!date || isNaN(amount) || amount < 0) {
        showAppNotice("يرجى ملء جميع الحقول بشكل صحيح!");
        return;
    }
    
    if (method === 'تحويل' && !transferFromAccount) {
        showAppNotice("يرجى إدخال اسم الحساب المحول منه عند اختيار التحويل.", 'warning', 5200);
        return;
    }

    fetch('/api/edit_payment', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            payId: payId,
            date: date,
            amount: amount,
            method: method,
            transferFromAccount: transferFromAccount,
            note: note
        })
    })
    .then(r => r.json())
    .then(res => {
        if (res.success) {
            closeModal("modal-edit-payment");
            showAppNotice("تم تعديل الحركة المالية وحفظ التغييرات بنجاح!");
            
            // Reload state from DB and refresh current view panel
            if (currentView === "member-detail") {
                loadStateAndRender("member-detail");
            } else {
                loadStateAndRender(currentView);
            }
        } else {
            showAppNotice("خطأ أثناء الحفظ: " + res.error);
        }
    })
    .catch(err => {
        console.error("Edit payment error:", err);
        showAppNotice("حدث خطأ بالاتصال أثناء إرسال البيانات!");
    });
}

// ==================== COLUMN-LEVEL TABLE FILTERS WORKSPACE ====================
function initTableFilters(table) {
    const thead = table.querySelector("thead");
    if (!thead) return;
    
    // Check if filter row already exists
    if (thead.querySelector(".filter-row")) return;
    
    const headerRow = thead.querySelector("tr");
    if (!headerRow) return;
    
    const filterRow = document.createElement("tr");
    filterRow.className = "filter-row";
    
    Array.from(headerRow.cells).forEach((cell, index) => {
        const th = document.createElement("th");
        
        const text = cell.textContent.trim();
        if (text === "الإجراءات" || text === "إجراءات") {
            th.innerHTML = ""; // No filter input for Action columns
        } else {
            const input = document.createElement("input");
            input.type = "text";
            input.className = "table-filter-input";
            input.placeholder = `فلترة...`;
            input.setAttribute("data-col", index);
            
            input.addEventListener("input", () => {
                applyTableFilters(table);
            });
            
            th.appendChild(input);
        }
        filterRow.appendChild(th);
    });
    
    thead.appendChild(filterRow);
}

function initAllTableFilters() {
    const tables = document.querySelectorAll("table.custom-table");
    tables.forEach(table => {
        initTableFilters(table);
    });
}

function applyTableFilters(table) {
    const tbody = table.querySelector("tbody");
    if (!tbody) return;
    
    const rows = Array.from(tbody.querySelectorAll("tr"));
    const filterRow = table.querySelector(".filter-row");
    if (!filterRow) return;
    
    const inputs = Array.from(filterRow.querySelectorAll("input.table-filter-input"));
    
    rows.forEach(row => {
        // Skip warning/limit text rows
        if (row.cells.length === 1 && row.cells[0].colSpan > 1) {
            row.style.display = "";
            return;
        }
        
        let matches = true;
        inputs.forEach(input => {
            const colIndex = parseInt(input.getAttribute("data-col"));
            const query = input.value.trim().toLowerCase();
            if (!query) return;
            
            const cell = row.cells[colIndex];
            if (!cell) return;
            
            const cellText = cell.textContent.trim().toLowerCase();
            if (cellText.indexOf(query) === -1) {
                matches = false;
            }
        });
        
        row.style.display = matches ? "" : "none";
    });
}

function applyTableFiltersForBody(tbodyId) {
    const tbody = document.getElementById(tbodyId);
    if (!tbody) return;
    const table = tbody.closest("table");
    if (table) {
        applyTableFilters(table);
    }
}
