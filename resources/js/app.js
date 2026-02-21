import './bootstrap';

const authKey = 'auth_token';
const themeKey = 'theme_preference';

const dom = {
    body: document.body,
    themeToggles: document.querySelectorAll('[data-theme-toggle]'),
    themeLabels: document.querySelectorAll('.theme-label'),
    loginForm: document.getElementById('login-form'),
    loginError: document.getElementById('login-error'),
    userName: document.getElementById('user-name'),
    userRole: document.getElementById('user-role'),
    brandLogo: document.getElementById('brand-logo'),
    logoutButton: document.getElementById('logout-button'),
    logoutButtonMobile: document.getElementById('logout-button-mobile'),
    syncStatus: document.getElementById('sync-status'),
    pageTitle: document.getElementById('page-title'),
    pageSubtitle: document.getElementById('page-subtitle'),
    dashboardRevenue: document.getElementById('dashboard-revenue'),
    dashboardCosts: document.getElementById('dashboard-costs'),
    dashboardProfit: document.getElementById('dashboard-profit'),
    dashboardProfitChart: document.getElementById('dashboard-profit-chart'),
    dashboardProfitChartRange: document.getElementById('dashboard-profit-chart-range'),
    mobileMenuToggle: document.getElementById('mobile-menu-toggle'),
    navItems: document.querySelectorAll('.nav-item[data-view]'),
    views: document.querySelectorAll('.view'),
    quickLinks: document.querySelectorAll('[data-go-view]'),
    logoUploadForm: document.getElementById('logo-upload-form'),
    logoUploadStatus: document.getElementById('logo-upload-status'),
    staffUserForm: document.getElementById('staff-user-form'),
    staffUserFormStatus: document.getElementById('staff-user-form-status'),
    staffUsersTable: document.getElementById('staff-users-table'),
    staffUsersRefresh: document.getElementById('staff-users-refresh'),
    profileForm: document.getElementById('profile-form'),
    profileFormStatus: document.getElementById('profile-form-status'),
    profileName: document.getElementById('profile-name'),
    profileEmail: document.getElementById('profile-email'),
    passwordForm: document.getElementById('password-form'),
    passwordFormStatus: document.getElementById('password-form-status'),
    portalDownloadLatest: document.getElementById('portal-download-latest'),
    portalWebsites: document.getElementById('portal-websites'),
    customerDetailTitle: document.getElementById('customer-detail-title'),
    customerDetailEmail: document.getElementById('customer-detail-email'),
    customerDetailBilling: document.getElementById('customer-detail-billing'),
    customerDetailNotes: document.getElementById('customer-detail-notes'),
    customerTotalSpent: document.getElementById('customer-total-spent'),
    customerMRR: document.getElementById('customer-mrr'),
    customerSubscriptionCount: document.getElementById('customer-subscription-count'),
    customerJobsTable: document.getElementById('customer-jobs-table'),
    customerSubscriptionsTable: document.getElementById('customer-subscriptions-table'),
    customerWebsitesList: document.getElementById('customer-websites-list'),
    customerWebsiteForm: document.getElementById('customer-website-form'),
    customerWebsiteStatus: document.getElementById('customer-website-status'),
    customerWebsiteCancel: document.getElementById('customer-website-cancel'),
    customerWebsiteTitle: document.getElementById('customer-website-title'),
    customerDetailBack: document.getElementById('customer-detail-back'),
    customersSearch: document.getElementById('customers-search'),
    customersClear: document.getElementById('customers-clear'),
    customersLoadMore: document.getElementById('customers-load-more'),
    jobsFilterStatus: document.getElementById('jobs-filter-status'),
    jobsFilterCustomer: document.getElementById('jobs-filter-customer'),
    jobsClear: document.getElementById('jobs-clear'),
    jobsLoadMore: document.getElementById('jobs-load-more'),
    costsLoadMore: document.getElementById('costs-load-more'),
    subscriptionsFilterStatus: document.getElementById('subscriptions-filter-status'),
    subscriptionsFilterCustomer: document.getElementById('subscriptions-filter-customer'),
    subscriptionsClear: document.getElementById('subscriptions-clear'),
    subscriptionsLoadMore: document.getElementById('subscriptions-load-more'),
    invoicesFilterStatus: document.getElementById('invoices-filter-status'),
    invoicesFilterCustomer: document.getElementById('invoices-filter-customer'),
    invoicesClear: document.getElementById('invoices-clear'),
    invoicesLoadMore: document.getElementById('invoices-load-more'),
    customersTable: document.getElementById('customers-table'),
    customerForm: document.getElementById('customer-form'),
    customerFormTitle: document.getElementById('customer-form-title'),
    customerFormStatus: document.getElementById('customer-form-status'),
    customerFormCancel: document.getElementById('customer-form-cancel'),
    customersRefresh: document.getElementById('customers-refresh'),
    jobsTable: document.getElementById('jobs-table'),
    costsTable: document.getElementById('costs-table'),
    costForm: document.getElementById('cost-form'),
    costFormTitle: document.getElementById('cost-form-title'),
    costFormStatus: document.getElementById('cost-form-status'),
    costFormCancel: document.getElementById('cost-form-cancel'),
    costsRefresh: document.getElementById('costs-refresh'),
    jobForm: document.getElementById('job-form'),
    jobFormTitle: document.getElementById('job-form-title'),
    jobFormStatus: document.getElementById('job-form-status'),
    jobFormCancel: document.getElementById('job-form-cancel'),
    jobCustomerSelect: document.getElementById('job-customer-select'),
    jobsRefresh: document.getElementById('jobs-refresh'),
    subscriptionsTable: document.getElementById('subscriptions-table'),
    subscriptionForm: document.getElementById('subscription-form'),
    subscriptionFormTitle: document.getElementById('subscription-form-title'),
    subscriptionFormStatus: document.getElementById('subscription-form-status'),
    subscriptionMonthsStatus: document.getElementById('subscription-months-status'),
    subscriptionFormCancel: document.getElementById('subscription-form-cancel'),
    subscriptionMonthsTable: document.getElementById('subscription-months-table'),
    subscriptionMonthsRefresh: document.getElementById('subscription-months-refresh'),
    subscriptionCustomerSelect: document.getElementById('subscription-customer-select'),
    subscriptionsRefresh: document.getElementById('subscriptions-refresh'),
    invoicesTable: document.getElementById('invoices-table'),
    invoiceForm: document.getElementById('invoice-form'),
    invoiceFormTitle: document.getElementById('invoice-form-title'),
    invoiceFormStatus: document.getElementById('invoice-form-status'),
    invoiceFormCancel: document.getElementById('invoice-form-cancel'),
    invoiceCustomerSelect: document.getElementById('invoice-customer-select'),
    invoiceLineItems: document.getElementById('invoice-line-items'),
    invoiceAddLineItem: document.getElementById('invoice-add-line-item'),
    invoicesRefresh: document.getElementById('invoices-refresh'),
};

const statTargets = {
    jobs: document.querySelector('[data-stat="jobs"]'),
    subscriptions: document.querySelector('[data-stat="subscriptions"]'),
};

const invoiceTables = {
    dashboard: document.getElementById('recent-invoices'),
    portal: document.getElementById('portal-invoices'),
};

const api = window.axios;
const dashboardProfitYear = 2026;

const state = {
    view: 'dashboard',
    role: 'guest',
    user: null,
    customers: [],
    customerOptions: [],
    jobs: [],
    costs: [],
    subscriptions: [],
    subscriptionMonths: [],
    invoices: [],
    staffUsers: [],
    portalInvoices: [],
    currentCustomer: null,
    filters: {
        customers: {
            search: '',
        },
        jobs: {
            status: 'all',
            customer: 'all',
        },
        subscriptions: {
            status: 'all',
            customer: 'all',
        },
        invoices: {
            status: 'all',
            customer: 'all',
        },
    },
    pagination: {
        customers: { page: 1, lastPage: 1 },
        jobs: { page: 1, lastPage: 1 },
        costs: { page: 1, lastPage: 1 },
        subscriptions: { page: 1, lastPage: 1 },
        invoices: { page: 1, lastPage: 1 },
    },
    editing: {
        customer: null,
        job: null,
        cost: null,
        subscription: null,
        invoice: null,
        website: null,
    },
};

const viewMeta = {
    dashboard: {
        title: 'Dashboard',
        subtitle: 'Overview and performance snapshots.',
    },
    customers: {
        title: 'Customers',
        subtitle: 'Manage customer profiles and portal access.',
    },
    jobs: {
        title: 'Jobs',
        subtitle: 'Track one-off work and invoicing status.',
    },
    subscriptions: {
        title: 'Subscriptions',
        subtitle: 'Recurring services and billing cadence.',
    },
    costs: {
        title: 'Costs',
        subtitle: 'Track expenses and receipt uploads.',
    },
    invoices: {
        title: 'Invoices',
        subtitle: 'Create, send, and download invoices.',
    },
    admin: {
        title: 'Admin',
        subtitle: 'Brand settings and configuration.',
    },
    'customer-detail': {
        title: 'Customer overview',
        subtitle: 'Jobs, subscriptions, and websites for this customer.',
    },
    portal: {
        title: 'Customer Portal',
        subtitle: 'Review invoices and quick-login links.',
    },
};

function setAuthState(isAuthenticated) {
    dom.body.dataset.auth = isAuthenticated ? 'authenticated' : 'guest';
}

function setRole(role) {
    state.role = role;
    dom.body.dataset.role = role;
}

function setNavOpen(isOpen) {
    if (isOpen) {
        dom.body.dataset.nav = 'open';
    } else {
        delete dom.body.dataset.nav;
    }
    if (dom.mobileMenuToggle) {
        dom.mobileMenuToggle.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
    }
}

function toggleNav() {
    const isOpen = dom.body.dataset.nav === 'open';
    setNavOpen(!isOpen);
}

function populateProfileForm(user) {
    if (!dom.profileForm || !user) return;
    if (dom.profileName) dom.profileName.value = user.name || '';
    if (dom.profileEmail) dom.profileEmail.value = user.email || '';
    setFormStatus(dom.profileFormStatus, '');
}

function setTheme(theme) {
    document.documentElement.dataset.theme = theme;
    dom.themeLabels.forEach((label) => {
        label.textContent = theme === 'dark' ? 'Dark' : 'Light';
    });
}

function applyStoredTheme() {
    const storedTheme = localStorage.getItem(themeKey);
    if (storedTheme) {
        setTheme(storedTheme);
    }
}

function setToken(token) {
    if (token) {
        localStorage.setItem(authKey, token);
        api.defaults.headers.common.Authorization = `Bearer ${token}`;
        setAuthState(true);
    } else {
        localStorage.removeItem(authKey);
        delete api.defaults.headers.common.Authorization;
        setAuthState(false);
    }
}

function updateSyncStatus(status) {
    if (dom.syncStatus) {
        dom.syncStatus.textContent = status;
    }
}

function setActiveView(view) {
    const meta = viewMeta[view] || viewMeta.dashboard;
    state.view = view;
    const navView = view === 'customer-detail' ? 'customers' : view;

    dom.views.forEach((section) => {
        section.classList.toggle('active', section.dataset.view === view);
    });

    dom.navItems.forEach((item) => {
        item.classList.toggle('active', item.dataset.view === navView);
    });

    if (dom.pageTitle) dom.pageTitle.textContent = meta.title;
    if (dom.pageSubtitle) dom.pageSubtitle.textContent = meta.subtitle;

    if (view === 'customers') {
        loadCustomers();
    }
    if (view === 'jobs') {
        ensureCustomersLoaded().then(loadJobs);
    }
    if (view === 'subscriptions') {
        ensureCustomersLoaded().then(loadSubscriptions);
    }
    if (view === 'costs') {
        loadCosts();
    }
    if (view === 'invoices') {
        ensureCustomersLoaded().then(loadInvoices);
    }
    if (view === 'customer-detail' && state.currentCustomer?.id) {
        loadCustomerDetail(state.currentCustomer.id);
    }
    if (view === 'portal') {
        loadPortalInvoices();
        loadPortalWebsites();
    }
    if (view === 'admin' && state.role === 'admin') {
        loadStaffUsers();
    }
}

function escapeHtml(value) {
    return String(value ?? '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}

function formatCurrency(amount) {
    if (typeof amount !== 'number' || Number.isNaN(amount)) return '£0.00';
    return amount.toLocaleString('en-GB', { style: 'currency', currency: 'GBP' });
}

function formatDate(value) {
    if (!value) return '';
    const date = new Date(value);
    if (Number.isNaN(date.getTime())) return '';
    return date.toLocaleDateString('en-US', { month: 'short', day: 'numeric' });
}

function formatDateWithYear(value) {
    if (!value) return '';
    const date = new Date(value);
    if (Number.isNaN(date.getTime())) return '';
    return date.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
}

function formatMonth(value) {
    if (!value) return '';
    const date = new Date(value);
    if (Number.isNaN(date.getTime())) return '';
    return date.toLocaleDateString('en-GB', { month: 'short', year: 'numeric' });
}

function formatDateInput(value) {
    if (!value) return '';
    return String(value).slice(0, 10);
}

function truncate(value, length = 32) {
    const text = String(value ?? '');
    if (text.length <= length) return text;
    return `${text.slice(0, length)}...`;
}

function buildQuery(params) {
    const searchParams = new URLSearchParams();
    Object.entries(params).forEach(([key, value]) => {
        if (value === null || value === undefined || value === '' || value === 'all') return;
        searchParams.append(key, value);
    });
    const queryString = searchParams.toString();
    return queryString ? `?${queryString}` : '';
}

function debounce(fn, delay = 300) {
    let timeoutId;
    return (...args) => {
        clearTimeout(timeoutId);
        timeoutId = setTimeout(() => fn(...args), delay);
    };
}

const loadMoreButtons = {
    customers: dom.customersLoadMore,
    jobs: dom.jobsLoadMore,
    costs: dom.costsLoadMore,
    subscriptions: dom.subscriptionsLoadMore,
    invoices: dom.invoicesLoadMore,
};

function updateLoadMoreVisibility(key) {
    const button = loadMoreButtons[key];
    if (!button) return;
    const pagination = state.pagination[key];
    if (!pagination) return;
    button.style.display = pagination.page < pagination.lastPage ? 'inline-flex' : 'none';
    button.disabled = false;
}

function updatePagination(key, response, append) {
    const meta = response?.data?.meta || {};
    const currentPage = meta.current_page ?? (append ? state.pagination[key].page + 1 : 1);
    const lastPage = meta.last_page ?? currentPage;
    state.pagination[key] = { page: currentPage, lastPage };
    updateLoadMoreVisibility(key);
}

function resetPagination(key) {
    state.pagination[key] = { page: 1, lastPage: 1 };
    updateLoadMoreVisibility(key);
}

function setLoadMoreLoading(key, isLoading) {
    const button = loadMoreButtons[key];
    if (!button) return;
    button.disabled = isLoading;
}

function resetTable(container) {
    if (!container) return null;
    const header = container.querySelector('.table-header');
    const headerClone = header ? header.cloneNode(true) : null;
    container.innerHTML = '';
    if (headerClone) {
        container.appendChild(headerClone);
    }
    return container;
}

function setFormStatus(element, message, isError = false) {
    if (!element) return;
    element.textContent = message;
    element.style.color = isError ? '#ef4444' : '';
}

function getErrorMessage(error, fallback = 'Request failed.') {
    const validationErrors = error?.response?.data?.errors;
    if (validationErrors && typeof validationErrors === 'object') {
        const firstField = Object.keys(validationErrors)[0];
        const firstMessage = validationErrors[firstField]?.[0];
        if (firstMessage) return String(firstMessage);
    }

    const message = error?.response?.data?.message;
    if (message) return String(message);

    return fallback;
}

async function loadPreferences() {
    try {
        const response = await api.get('/api/preferences');
        const theme = response.data?.theme || 'light';
        localStorage.setItem(themeKey, theme);
        setTheme(theme);
    } catch (error) {
        applyStoredTheme();
    }
}

async function saveTheme(theme) {
    setTheme(theme);
    localStorage.setItem(themeKey, theme);
    try {
        await api.put('/api/preferences', { theme });
    } catch (error) {
        // Ignore preference errors for guests.
    }
}

async function loadBrand() {
    try {
        const response = await api.get('/api/brand');
        const payload = response.data?.data ?? response.data;
        if (dom.brandLogo) {
            if (payload?.logo_file_id) {
                dom.brandLogo.src = `/api/brand/logo?ts=${Date.now()}`;
                dom.brandLogo.style.display = 'block';
            } else {
                dom.brandLogo.style.display = 'none';
            }
        }
    } catch (error) {
        if (dom.brandLogo) {
            dom.brandLogo.style.display = 'none';
        }
    }
}

function parseTotal(response) {
    return response?.data?.meta?.total ?? response?.data?.data?.length ?? 0;
}

async function calculateDashboardMetrics() {
    const response = await api.get('/api/stats/revenue');
    return {
        revenue: Number(response?.data?.total ?? 0),
        costs: Number(response?.data?.costs_total ?? 0),
        profit: Number(response?.data?.profit_total ?? 0),
    };
}

async function calculateWeeklyProfit(year = dashboardProfitYear) {
    const response = await api.get(`/api/stats/profit-weekly?year=${year}`);
    return response?.data ?? {};
}

function renderProfitChart(payload = null) {
    if (!dom.dashboardProfitChart) return;

    const startDate = payload?.start_date ?? `${dashboardProfitYear}-01-01`;
    const endDate = payload?.end_date ?? `${dashboardProfitYear}-12-31`;
    if (dom.dashboardProfitChartRange) {
        dom.dashboardProfitChartRange.textContent = `${formatDateWithYear(startDate)} to ${formatDateWithYear(endDate)}`;
    }

    const weeks = Array.isArray(payload?.weeks) ? payload.weeks : [];
    if (!weeks.length) {
        dom.dashboardProfitChart.innerHTML = '<div class="profit-chart-empty">No weekly profit data yet.</div>';
        return;
    }

    const chartWidth = 900;
    const chartHeight = 240;
    const padding = {
        top: 16,
        right: 14,
        bottom: 26,
        left: 14,
    };
    const plotWidth = chartWidth - padding.left - padding.right;
    const plotHeight = chartHeight - padding.top - padding.bottom;
    const clampedProfits = weeks.map((week) => Math.max(Number(week?.profit ?? 0), 0));
    const maxProfit = Math.max(...clampedProfits, 0);
    const pointCount = weeks.length;
    const denominator = maxProfit > 0 ? maxProfit : 1;
    const bottomY = padding.top + plotHeight;

    const points = clampedProfits.map((profit, index) => {
        const x = pointCount > 1
            ? padding.left + (index * plotWidth) / (pointCount - 1)
            : padding.left + plotWidth / 2;
        const y = bottomY - (profit / denominator) * plotHeight;
        return { x, y };
    });

    const pointsText = points.map((point) => `${point.x},${point.y}`).join(' ');
    const firstPoint = points[0];
    const lastPoint = points[points.length - 1];
    const areaPoints = `${firstPoint.x},${bottomY} ${pointsText} ${lastPoint.x},${bottomY}`;
    const startLabel = formatDate(weeks[0]?.week_start);
    const endLabel = formatDate(weeks[weeks.length - 1]?.week_end);

    dom.dashboardProfitChart.innerHTML = `
        <svg class="profit-chart-svg" viewBox="0 0 ${chartWidth} ${chartHeight}" role="img" aria-label="Weekly profit chart for ${dashboardProfitYear}">
            <line class="profit-chart-baseline" x1="${padding.left}" y1="${bottomY}" x2="${chartWidth - padding.right}" y2="${bottomY}"></line>
            <polygon class="profit-chart-area" points="${areaPoints}"></polygon>
            <polyline class="profit-chart-line" points="${pointsText}"></polyline>
        </svg>
        <div class="profit-chart-axis">
            <span>${escapeHtml(startLabel)}</span>
            <span>Peak ${escapeHtml(formatCurrency(maxProfit))}</span>
            <span>${escapeHtml(endLabel)}</span>
        </div>
    `;
}

function renderInvoiceRows(container, invoices, emptyMessage) {
    if (!container) return;
    resetTable(container);

    if (!invoices.length) {
        const emptyRow = document.createElement('div');
        emptyRow.className = 'table-row table-empty';
        emptyRow.innerHTML = `<span>${emptyMessage}</span><span></span><span></span><span></span>`;
        container.appendChild(emptyRow);
        return;
    }

    invoices.forEach((invoice) => {
        const row = document.createElement('div');
        row.className = 'table-row';

        const status = invoice.status || 'draft';
        let pillClass = 'pill';
        if (status === 'paid') {
            pillClass = 'pill success';
        } else if (status === 'draft') {
            pillClass = 'pill outline';
        }

        row.innerHTML = `
            <span>#${escapeHtml(invoice.invoice_number)}</span>
            <span class="${pillClass}">${escapeHtml(status)}</span>
            <span>${formatCurrency(Number(invoice.total))}</span>
            <span>${formatDate(invoice.due_date)}</span>
        `;

        container.appendChild(row);
    });
}

async function loadStaffStats() {
    const results = await Promise.allSettled([
        api.get('/api/jobs?per_page=1'),
        api.get('/api/subscriptions?per_page=1'),
        api.get('/api/invoices?per_page=3'),
        calculateDashboardMetrics(),
        calculateWeeklyProfit(),
    ]);

    const [jobsResult, subscriptionsResult, invoicesResult, metricsResult, weeklyProfitResult] = results;

    if (statTargets.jobs) {
        statTargets.jobs.textContent =
            jobsResult.status === 'fulfilled' ? parseTotal(jobsResult.value) : '--';
    }
    if (statTargets.subscriptions) {
        statTargets.subscriptions.textContent =
            subscriptionsResult.status === 'fulfilled' ? parseTotal(subscriptionsResult.value) : '--';
    }

    if (invoicesResult.status === 'fulfilled') {
        renderInvoiceRows(invoiceTables.dashboard, invoicesResult.value?.data?.data ?? [], 'No invoices yet.');
    } else {
        renderInvoiceRows(invoiceTables.dashboard, [], 'Unable to load invoices.');
    }

    if (dom.dashboardRevenue) {
        dom.dashboardRevenue.textContent =
            metricsResult.status === 'fulfilled' ? formatCurrency(metricsResult.value.revenue) : '--';
    }
    if (dom.dashboardCosts) {
        dom.dashboardCosts.textContent =
            metricsResult.status === 'fulfilled' ? formatCurrency(metricsResult.value.costs) : '--';
    }
    if (dom.dashboardProfit) {
        dom.dashboardProfit.textContent =
            metricsResult.status === 'fulfilled' ? formatCurrency(metricsResult.value.profit) : '--';
    }

    if (weeklyProfitResult.status === 'fulfilled') {
        renderProfitChart(weeklyProfitResult.value);
    } else {
        renderProfitChart(null);
    }

    const failures = results.filter((result) => result.status === 'rejected').length;
    updateSyncStatus(failures === results.length ? 'Offline' : failures ? 'Partial' : 'Connected');
}

async function loadPortalInvoices() {
    try {
        const invoices = await api.get('/api/portal/invoices?per_page=6');
        const items = invoices?.data?.data ?? [];
        state.portalInvoices = items;
        renderInvoiceRows(invoiceTables.portal, items, 'No invoices available.');
    } catch (error) {
        renderInvoiceRows(invoiceTables.portal, [], 'Unable to load invoices.');
    }
}

async function loadPortalWebsites() {
    if (!dom.portalWebsites) return;
    dom.portalWebsites.innerHTML = '';

    try {
        const response = await api.get('/api/portal/websites?per_page=20');
        const websites = response?.data?.data ?? [];

        if (!websites.length) {
            const emptyCard = document.createElement('div');
            emptyCard.className = 'site-card';
            emptyCard.innerHTML = `
                <div>
                    <div class="site-name">No websites yet</div>
                    <div class="site-url">Ask support to add one.</div>
                </div>
            `;
            dom.portalWebsites.appendChild(emptyCard);
            return;
        }

        websites.forEach((website) => {
            const card = document.createElement('div');
            card.className = 'site-card';
            card.innerHTML = `
                <div>
                    <div class="site-name">${escapeHtml(website.name)}</div>
                    <div class="site-url">${escapeHtml(website.login_url)}</div>
                </div>
                <a class="btn btn-primary btn-small" href="${escapeHtml(website.login_url)}" target="_blank" rel="noopener">Quick login</a>
            `;
            dom.portalWebsites.appendChild(card);
        });
    } catch (error) {
        const errorCard = document.createElement('div');
        errorCard.className = 'site-card';
        errorCard.innerHTML = `
            <div>
                <div class="site-name">Unable to load websites</div>
                <div class="site-url">Please try again later.</div>
            </div>
        `;
        dom.portalWebsites.appendChild(errorCard);
    }
}

function resolveRole(user) {
    const roles = user?.roles?.map((role) => role.slug) ?? [];
    if (roles.includes('admin')) {
        return 'admin';
    }
    if (roles.includes('staff')) {
        return 'staff';
    }
    if (roles.includes('customer')) {
        return 'customer';
    }
    return 'staff';
}

async function loadSession() {
    const token = localStorage.getItem(authKey);
    if (!token) {
        setAuthState(false);
        setRole('guest');
        applyStoredTheme();
        return;
    }

    setToken(token);

    try {
        const response = await api.get('/api/auth/me');
        const user = response.data;
        state.user = user;
        const role = resolveRole(user);
        setRole(role);
        if (dom.userName) dom.userName.textContent = user?.name || 'User';
        if (dom.userRole) dom.userRole.textContent = role;
        populateProfileForm(user);

        await Promise.all([loadPreferences(), loadBrand()]);

        if (role === 'customer') {
            setActiveView('portal');
            loadPortalWebsites();
        } else {
            loadCustomerOptions();
            setActiveView('dashboard');
            loadStaffStats();
        }
    } catch (error) {
        setToken(null);
        setAuthState(false);
        setRole('guest');
    }
}

async function handleLogin(event) {
    event.preventDefault();
    if (!dom.loginForm) return;

    const submitButton = dom.loginForm.querySelector('button[type="submit"]');
    if (dom.loginError) {
        dom.loginError.textContent = '';
    }

    if (submitButton) submitButton.disabled = true;

    const formData = new FormData(dom.loginForm);
    const payload = {
        email: formData.get('email'),
        password: formData.get('password'),
        device_name: 'web',
    };

    try {
        const response = await api.post('/api/auth/login', payload);
        setToken(response.data.token);
        const role = resolveRole(response.data.user);
        setRole(role);
        if (dom.userName) dom.userName.textContent = response.data.user?.name || 'User';
        if (dom.userRole) dom.userRole.textContent = role;
        await Promise.all([loadPreferences(), loadBrand()]);
        if (role === 'customer') {
            setActiveView('portal');
            loadPortalWebsites();
        } else {
            setActiveView('dashboard');
            loadStaffStats();
        }
    } catch (error) {
        if (dom.loginError) {
            dom.loginError.textContent = 'Invalid credentials. Please try again.';
        }
    } finally {
        if (submitButton) submitButton.disabled = false;
    }
}

async function handleProfileSubmit(event) {
    event.preventDefault();
    if (!dom.profileForm) return;

    const formData = new FormData(dom.profileForm);
    const payload = {
        name: String(formData.get('name') || '').trim(),
        email: String(formData.get('email') || '').trim(),
    };

    if (!payload.name || !payload.email) {
        setFormStatus(dom.profileFormStatus, 'Name and email are required.', true);
        return;
    }

    try {
        const response = await api.put('/api/account/profile', payload);
        const user = response?.data?.user ?? response?.data ?? null;
        if (user) {
            state.user = user;
            if (dom.userName) dom.userName.textContent = user?.name || 'User';
            populateProfileForm(user);
        }
        setFormStatus(dom.profileFormStatus, 'Profile updated.');
    } catch (error) {
        setFormStatus(dom.profileFormStatus, 'Unable to update profile.', true);
    }
}

async function handlePasswordSubmit(event) {
    event.preventDefault();
    if (!dom.passwordForm) return;

    const formData = new FormData(dom.passwordForm);
    const payload = {
        current_password: String(formData.get('current_password') || ''),
        password: String(formData.get('password') || ''),
        password_confirmation: String(formData.get('password_confirmation') || ''),
    };

    if (!payload.current_password || !payload.password || !payload.password_confirmation) {
        setFormStatus(dom.passwordFormStatus, 'All password fields are required.', true);
        return;
    }
    if (payload.password !== payload.password_confirmation) {
        setFormStatus(dom.passwordFormStatus, 'New passwords do not match.', true);
        return;
    }

    try {
        await api.put('/api/account/password', payload);
        setFormStatus(dom.passwordFormStatus, 'Password updated.');
        dom.passwordForm.reset();
    } catch (error) {
        setFormStatus(dom.passwordFormStatus, 'Unable to update password.', true);
    }
}

async function handleLogout() {
    try {
        await api.post('/api/auth/logout');
    } catch (error) {
        // ignore logout errors
    }
    setToken(null);
    setRole('guest');
    setAuthState(false);
    applyStoredTheme();
}

async function handleLogoUpload(event) {
    event.preventDefault();
    if (!dom.logoUploadForm) return;

    const formData = new FormData(dom.logoUploadForm);
    const file = formData.get('logo');
    if (!file) return;

    if (dom.logoUploadStatus) {
        dom.logoUploadStatus.textContent = 'Uploading...';
    }

    try {
        await api.post('/api/brand/logo', formData, {
            headers: { 'Content-Type': 'multipart/form-data' },
        });
        if (dom.logoUploadStatus) {
            dom.logoUploadStatus.textContent = 'Logo updated.';
        }
        dom.logoUploadForm.reset();
        await loadBrand();
    } catch (error) {
        if (dom.logoUploadStatus) {
            dom.logoUploadStatus.textContent = 'Upload failed. Please try again.';
        }
    }
}

function renderStaffUsers() {
    if (!dom.staffUsersTable) return;
    resetTable(dom.staffUsersTable);

    if (!state.staffUsers.length) {
        const emptyRow = document.createElement('div');
        emptyRow.className = 'table-row table-empty staff-users';
        emptyRow.innerHTML = '<span>No staff users yet.</span><span></span><span></span>';
        dom.staffUsersTable.appendChild(emptyRow);
        return;
    }

    state.staffUsers.forEach((user) => {
        const row = document.createElement('div');
        row.className = 'table-row staff-users';
        row.innerHTML = `
            <span>${escapeHtml(user.name || '')}</span>
            <span>${escapeHtml(user.email || '')}</span>
            <span>${formatDate(user.created_at)}</span>
        `;
        dom.staffUsersTable.appendChild(row);
    });
}

async function loadStaffUsers() {
    if (!dom.staffUsersTable || state.role !== 'admin') return;
    setFormStatus(dom.staffUserFormStatus, '');
    resetTable(dom.staffUsersTable);

    const loadingRow = document.createElement('div');
    loadingRow.className = 'table-row table-empty staff-users';
    loadingRow.innerHTML = '<span>Loading staff users...</span><span></span><span></span>';
    dom.staffUsersTable.appendChild(loadingRow);

    try {
        const response = await api.get('/api/admin/staff-users');
        state.staffUsers = response?.data?.data ?? [];
        renderStaffUsers();
    } catch (error) {
        state.staffUsers = [];
        const emptyRow = document.createElement('div');
        emptyRow.className = 'table-row table-empty staff-users';
        emptyRow.innerHTML = '<span>Unable to load staff users.</span><span></span><span></span>';
        resetTable(dom.staffUsersTable);
        dom.staffUsersTable.appendChild(emptyRow);
    }
}

async function handleStaffUserSubmit(event) {
    event.preventDefault();
    if (!dom.staffUserForm) return;

    const formData = new FormData(dom.staffUserForm);
    const payload = {
        name: String(formData.get('name') || '').trim(),
        email: String(formData.get('email') || '').trim(),
        password: String(formData.get('password') || ''),
    };

    if (!payload.name || !payload.email || !payload.password) {
        setFormStatus(dom.staffUserFormStatus, 'Name, email, and password are required.', true);
        return;
    }

    try {
        await api.post('/api/admin/staff-users', payload);
        dom.staffUserForm.reset();
        setFormStatus(dom.staffUserFormStatus, 'Staff user created.');
        await loadStaffUsers();
    } catch (error) {
        setFormStatus(dom.staffUserFormStatus, getErrorMessage(error, 'Unable to create staff user.'), true);
    }
}

function populateCustomerSelects(customers) {
    const selects = [dom.jobCustomerSelect, dom.subscriptionCustomerSelect, dom.invoiceCustomerSelect];
    selects.forEach((select) => {
        if (!select) return;
        const currentValue = select.value;
        select.innerHTML = '';
        const placeholder = document.createElement('option');
        placeholder.value = '';
        placeholder.textContent = 'Select customer';
        placeholder.disabled = true;
        placeholder.selected = true;
        select.appendChild(placeholder);
        customers.forEach((customer) => {
            const option = document.createElement('option');
            option.value = customer.id;
            option.textContent = customer.name;
            select.appendChild(option);
        });
        if (currentValue) {
            select.value = currentValue;
        }
    });
}

function populateCustomerFilterSelects(customers) {
    const selects = [dom.jobsFilterCustomer, dom.subscriptionsFilterCustomer, dom.invoicesFilterCustomer];
    selects.forEach((select) => {
        if (!select) return;
        const currentValue = select.value || 'all';
        select.innerHTML = '';
        const placeholder = document.createElement('option');
        placeholder.value = 'all';
        placeholder.textContent = 'All customers';
        select.appendChild(placeholder);
        customers.forEach((customer) => {
            const option = document.createElement('option');
            option.value = customer.id;
            option.textContent = customer.name;
            select.appendChild(option);
        });
        select.value = currentValue;
    });
}

function getCustomerName(id) {
    const source = state.customerOptions.length ? state.customerOptions : state.customers;
    return source.find((customer) => customer.id === id)?.name || 'Unknown';
}

async function loadCustomerOptions() {
    try {
        const perPage = 200;
        let page = 1;
        let lastPage = 1;
        const customers = [];

        do {
            const query = buildQuery({ per_page: perPage, page });
            const response = await api.get(`/api/customers${query}`);
            const items = response?.data?.data ?? [];
            customers.push(...items);
            const meta = response?.data?.meta || {};
            lastPage = meta.last_page ?? page;
            page += 1;
        } while (page <= lastPage);

        state.customerOptions = customers;
        populateCustomerSelects(customers);
        populateCustomerFilterSelects(customers);
    } catch (error) {
        // Keep existing options if the full list cannot be loaded.
    }
}

async function ensureCustomersLoaded() {
    if (!state.customerOptions.length) {
        await loadCustomerOptions();
    }
    if (!state.customers.length) {
        await loadCustomers();
    }
}

async function loadCustomers(append = false) {
    if (!dom.customersTable) return;
    setFormStatus(dom.customerFormStatus, '');
    setLoadMoreLoading('customers', true);
    if (!append) {
        resetPagination('customers');
        resetTable(dom.customersTable);
        const loadingRow = document.createElement('div');
        loadingRow.className = 'table-row table-empty customers';
        loadingRow.innerHTML = '<span>Loading customers...</span><span></span><span></span><span></span><span></span>';
        dom.customersTable.appendChild(loadingRow);
    }

    try {
        const page = append ? state.pagination.customers.page + 1 : 1;
        const query = buildQuery({
            per_page: 20,
            page,
            search: state.filters.customers.search || undefined,
        });
        const response = await api.get(`/api/customers${query}`);
        const items = response?.data?.data ?? [];
        state.customers = append ? [...state.customers, ...items] : items;
        const optionsSource = state.customerOptions.length ? state.customerOptions : state.customers;
        populateCustomerSelects(optionsSource);
        populateCustomerFilterSelects(optionsSource);
        updatePagination('customers', response, append);
        renderCustomers();
    } catch (error) {
        resetTable(dom.customersTable);
        const emptyRow = document.createElement('div');
        emptyRow.className = 'table-row table-empty customers';
        emptyRow.innerHTML = '<span>Unable to load customers.</span><span></span><span></span><span></span><span></span>';
        dom.customersTable.appendChild(emptyRow);
    } finally {
        setLoadMoreLoading('customers', false);
    }
}

function renderCustomers() {
    if (!dom.customersTable) return;
    resetTable(dom.customersTable);

    if (!state.customers.length) {
        const emptyRow = document.createElement('div');
        emptyRow.className = 'table-row table-empty customers';
        emptyRow.innerHTML = '<span>No customers yet.</span><span></span><span></span><span></span><span></span>';
        dom.customersTable.appendChild(emptyRow);
        return;
    }

    state.customers.forEach((customer) => {
        const totalSpent = Number(customer.jobs_sum_cost || 0);
        const mrr = Number(customer.subscriptions_sum_monthly_cost || 0);
        const row = document.createElement('div');
        row.className = 'table-row customers clickable';
        row.dataset.id = customer.id;
        row.innerHTML = `
            <span>${escapeHtml(customer.name)}</span>
            <span>${escapeHtml(customer.email)}</span>
            <span>${escapeHtml(truncate(customer.billing_address, 38))}</span>
            <span>
                <span class="metric-pill">Spent <span>${formatCurrency(totalSpent)}</span></span>
                <span class="metric-pill">MRR <span>${formatCurrency(mrr)}</span></span>
            </span>
            <div class="row-actions">
                <button class="btn btn-outline btn-small" data-action="edit" data-id="${customer.id}">Edit</button>
                <button class="btn btn-outline btn-small" data-action="delete" data-id="${customer.id}">Delete</button>
            </div>
        `;
        dom.customersTable.appendChild(row);
    });
}

function renderCustomerJobs(jobs = []) {
    if (!dom.customerJobsTable) return;
    resetTable(dom.customerJobsTable);

    if (!jobs.length) {
        const emptyRow = document.createElement('div');
        emptyRow.className = 'table-row table-empty jobs-detail';
        emptyRow.innerHTML = '<span>No jobs yet.</span><span></span><span></span><span></span>';
        dom.customerJobsTable.appendChild(emptyRow);
        return;
    }

    jobs.forEach((job) => {
        const row = document.createElement('div');
        row.className = 'table-row jobs-detail';
        row.innerHTML = `
            <span>${escapeHtml(truncate(job.description, 50))}</span>
            <span>${formatCurrency(Number(job.cost))}</span>
            <span>${escapeHtml(job.status)}</span>
            <span>${formatDate(job.completed_at)}</span>
        `;
        dom.customerJobsTable.appendChild(row);
    });
}

function renderCustomerSubscriptions(subscriptions = []) {
    if (!dom.customerSubscriptionsTable) return;
    resetTable(dom.customerSubscriptionsTable);

    if (!subscriptions.length) {
        const emptyRow = document.createElement('div');
        emptyRow.className = 'table-row table-empty subscriptions-detail';
        emptyRow.innerHTML = '<span>No subscriptions yet.</span><span></span><span></span><span></span>';
        dom.customerSubscriptionsTable.appendChild(emptyRow);
        return;
    }

    subscriptions.forEach((subscription) => {
        const row = document.createElement('div');
        row.className = 'table-row subscriptions-detail';
        row.innerHTML = `
            <span>${escapeHtml(truncate(subscription.description, 45))}</span>
            <span>${formatCurrency(Number(subscription.monthly_cost))}</span>
            <span>${escapeHtml(subscription.status)}</span>
            <span>${formatDate(subscription.next_invoice_date)}</span>
        `;
        dom.customerSubscriptionsTable.appendChild(row);
    });
}

function renderCustomerWebsites(websites = []) {
    if (!dom.customerWebsitesList) return;
    dom.customerWebsitesList.innerHTML = '';

    if (!websites.length) {
        const emptyCard = document.createElement('div');
        emptyCard.className = 'site-card';
        emptyCard.innerHTML = `
            <div>
                <div class="site-name">No websites yet</div>
                <div class="site-url">Add one to enable quick login.</div>
            </div>
        `;
        dom.customerWebsitesList.appendChild(emptyCard);
        return;
    }

    websites.forEach((website) => {
        const card = document.createElement('div');
        card.className = 'site-card';
        card.innerHTML = `
            <div>
                <div class="site-name">${escapeHtml(website.name)}</div>
                <div class="site-url">${escapeHtml(website.login_url)}</div>
            </div>
            <div class="site-actions">
                <a class="btn btn-primary btn-small" href="${escapeHtml(website.login_url)}" target="_blank" rel="noopener">Quick login</a>
                <button class="btn btn-outline btn-small" data-action="edit" data-id="${website.id}">Edit</button>
                <button class="btn btn-outline btn-small" data-action="delete" data-id="${website.id}">Delete</button>
            </div>
        `;
        dom.customerWebsitesList.appendChild(card);
    });
}

async function loadCustomerDetail(customerId) {
    if (!customerId) return;
    setFormStatus(dom.customerWebsiteStatus, '');

    try {
        const response = await api.get(`/api/customers/${customerId}`);
        const customer = response?.data?.data ?? response?.data ?? null;
        if (!customer) return;

        state.currentCustomer = customer;

        if (dom.customerDetailTitle) dom.customerDetailTitle.textContent = customer.name || 'Customer';
        if (dom.customerDetailEmail) dom.customerDetailEmail.textContent = customer.email || '';
        if (dom.customerDetailBilling) dom.customerDetailBilling.textContent = customer.billing_address || '--';
        if (dom.customerDetailNotes) dom.customerDetailNotes.textContent = customer.notes || '--';
        if (dom.pageTitle) dom.pageTitle.textContent = customer.name || 'Customer';
        if (dom.pageSubtitle) dom.pageSubtitle.textContent = 'Customer overview';

        const jobs = customer.jobs || [];
        const subscriptions = customer.subscriptions || [];
        const websites = customer.websites || [];

        const totalSpent = jobs.reduce((sum, job) => sum + Number(job.cost || 0), 0);
        const monthlyRecurring = subscriptions.reduce((sum, sub) => sum + Number(sub.monthly_cost || 0), 0);
        const activeCount = subscriptions.filter((sub) => sub.status === 'active').length;

        if (dom.customerTotalSpent) dom.customerTotalSpent.textContent = formatCurrency(totalSpent);
        if (dom.customerMRR) dom.customerMRR.textContent = formatCurrency(monthlyRecurring);
        if (dom.customerSubscriptionCount) dom.customerSubscriptionCount.textContent = String(activeCount);

        renderCustomerJobs(jobs);
        renderCustomerSubscriptions(subscriptions);
        renderCustomerWebsites(websites);
    } catch (error) {
        setFormStatus(dom.customerWebsiteStatus, 'Unable to load customer.', true);
    }
}

function openCustomerDetail(customerId) {
    if (!customerId) return;
    resetCustomerWebsiteForm();
    state.currentCustomer = { id: customerId };
    setActiveView('customer-detail');
    loadCustomerDetail(customerId);
}

function resetCustomerForm() {
    if (!dom.customerForm) return;
    dom.customerForm.reset();
    dom.customerForm.querySelector('input[name="id"]').value = '';
    state.editing.customer = null;
    if (dom.customerFormTitle) dom.customerFormTitle.textContent = 'New customer';
    setFormStatus(dom.customerFormStatus, '');
}

function resetCustomerWebsiteForm() {
    if (!dom.customerWebsiteForm) return;
    dom.customerWebsiteForm.reset();
    const idField = dom.customerWebsiteForm.querySelector('input[name="id"]');
    if (idField) idField.value = '';
    state.editing.website = null;
    if (dom.customerWebsiteTitle) dom.customerWebsiteTitle.textContent = 'Add website';
    setFormStatus(dom.customerWebsiteStatus, '');
}

async function handleCustomerSubmit(event) {
    event.preventDefault();
    if (!dom.customerForm) return;

    const formData = new FormData(dom.customerForm);
    const payload = {
        name: String(formData.get('name') || '').trim(),
        email: String(formData.get('email') || '').trim(),
        billing_address: String(formData.get('billing_address') || '').trim(),
        notes: String(formData.get('notes') || '').trim() || null,
    };

    try {
        let response;
        if (state.editing.customer) {
            response = await api.put(`/api/customers/${state.editing.customer}`, payload);
            setFormStatus(dom.customerFormStatus, 'Customer updated.');
        } else {
            response = await api.post('/api/customers', payload);
            setFormStatus(dom.customerFormStatus, 'Customer created. Portal password: WebStamp123');
        }
        const saved = response?.data?.data ?? response?.data;
        if (saved) {
            const index = state.customerOptions.findIndex((item) => item.id === saved.id);
            if (index >= 0) {
                state.customerOptions[index] = saved;
            } else {
                state.customerOptions.push(saved);
            }
            populateCustomerSelects(state.customerOptions);
            populateCustomerFilterSelects(state.customerOptions);
        }
        await loadCustomers();
        resetCustomerForm();
    } catch (error) {
        setFormStatus(dom.customerFormStatus, getErrorMessage(error, 'Unable to save customer.'), true);
    }
}

async function handleCustomerAction(event) {
    const actionButton = event.target.closest('[data-action]');
    if (!actionButton) {
        const row = event.target.closest('.table-row.customers');
        if (row?.dataset?.id) {
            openCustomerDetail(Number(row.dataset.id));
        }
        return;
    }

    const id = Number(actionButton.dataset.id);
    const action = actionButton.dataset.action;
    const customer = state.customers.find((item) => item.id === id);

    if (action === 'edit' && customer) {
        state.editing.customer = id;
        if (dom.customerFormTitle) dom.customerFormTitle.textContent = 'Edit customer';
        dom.customerForm.querySelector('input[name="id"]').value = customer.id;
        dom.customerForm.querySelector('input[name="name"]').value = customer.name || '';
        dom.customerForm.querySelector('input[name="email"]').value = customer.email || '';
        dom.customerForm.querySelector('textarea[name="billing_address"]').value = customer.billing_address || '';
        dom.customerForm.querySelector('textarea[name="notes"]').value = customer.notes || '';
        setFormStatus(dom.customerFormStatus, 'Editing customer.');
    }

    if (action === 'delete' && id) {
        if (!window.confirm('Delete this customer?')) return;
        try {
            await api.delete(`/api/customers/${id}`);
            if (state.customerOptions.length) {
                state.customerOptions = state.customerOptions.filter((item) => item.id !== id);
                populateCustomerSelects(state.customerOptions);
                populateCustomerFilterSelects(state.customerOptions);
            }
            await loadCustomers();
        } catch (error) {
            setFormStatus(dom.customerFormStatus, 'Unable to delete customer.', true);
        }
    }
}

async function handleCustomerWebsiteSubmit(event) {
    event.preventDefault();
    if (!dom.customerWebsiteForm || !state.currentCustomer?.id) return;

    const formData = new FormData(dom.customerWebsiteForm);
    const editingId = state.editing.website;
    const payload = {
        name: String(formData.get('name') || '').trim(),
        login_url: String(formData.get('login_url') || '').trim(),
        notes: String(formData.get('notes') || '').trim() || null,
    };

    if (!editingId) {
        payload.customer_id = state.currentCustomer.id;
    }

    if (!payload.name || !payload.login_url) {
        setFormStatus(dom.customerWebsiteStatus, 'Name and URL are required.', true);
        return;
    }

    try {
        if (editingId) {
            await api.put(`/api/websites/${editingId}`, payload);
            setFormStatus(dom.customerWebsiteStatus, 'Website updated.');
        } else {
            await api.post('/api/websites', payload);
            setFormStatus(dom.customerWebsiteStatus, 'Website saved.');
        }
        await loadCustomerDetail(state.currentCustomer.id);
        resetCustomerWebsiteForm();
    } catch (error) {
        setFormStatus(dom.customerWebsiteStatus, 'Unable to save website.', true);
    }
}

async function handleCustomerWebsiteAction(event) {
    const actionButton = event.target.closest('[data-action]');
    if (!actionButton) return;
    const id = Number(actionButton.dataset.id);
    const action = actionButton.dataset.action;
    const websites = state.currentCustomer?.websites || [];
    const website = websites.find((item) => item.id === id);

    if (action === 'edit' && website && dom.customerWebsiteForm) {
        state.editing.website = id;
        if (dom.customerWebsiteTitle) dom.customerWebsiteTitle.textContent = 'Edit website';
        const idField = dom.customerWebsiteForm.querySelector('input[name="id"]');
        if (idField) idField.value = website.id;
        dom.customerWebsiteForm.querySelector('input[name="name"]').value = website.name || '';
        dom.customerWebsiteForm.querySelector('input[name="login_url"]').value = website.login_url || '';
        dom.customerWebsiteForm.querySelector('textarea[name="notes"]').value = website.notes || '';
        setFormStatus(dom.customerWebsiteStatus, 'Editing website.');
    }

    if (action === 'delete' && id) {
        if (!window.confirm('Delete this website?')) return;
        try {
            await api.delete(`/api/websites/${id}`);
            if (state.editing.website === id) {
                resetCustomerWebsiteForm();
            }
            await loadCustomerDetail(state.currentCustomer?.id);
        } catch (error) {
            setFormStatus(dom.customerWebsiteStatus, 'Unable to delete website.', true);
        }
    }
}

async function loadJobs(append = false) {
    if (!dom.jobsTable) return;
    setFormStatus(dom.jobFormStatus, '');
    setLoadMoreLoading('jobs', true);
    if (!append) {
        resetPagination('jobs');
        resetTable(dom.jobsTable);
        const loadingRow = document.createElement('div');
        loadingRow.className = 'table-row table-empty jobs';
        loadingRow.innerHTML = '<span>Loading jobs...</span><span></span><span></span><span></span><span></span>';
        dom.jobsTable.appendChild(loadingRow);
    }

    try {
        const page = append ? state.pagination.jobs.page + 1 : 1;
        const query = buildQuery({
            per_page: 20,
            page,
            status: state.filters.jobs.status,
            customer_id: state.filters.jobs.customer,
        });
        const response = await api.get(`/api/jobs${query}`);
        const items = response?.data?.data ?? [];
        state.jobs = append ? [...state.jobs, ...items] : items;
        updatePagination('jobs', response, append);
        renderJobs();
    } catch (error) {
        resetTable(dom.jobsTable);
        const emptyRow = document.createElement('div');
        emptyRow.className = 'table-row table-empty jobs';
        emptyRow.innerHTML = '<span>Unable to load jobs.</span><span></span><span></span><span></span><span></span>';
        dom.jobsTable.appendChild(emptyRow);
    } finally {
        setLoadMoreLoading('jobs', false);
    }
}

function renderJobs() {
    if (!dom.jobsTable) return;
    resetTable(dom.jobsTable);

    if (!state.jobs.length) {
        const emptyRow = document.createElement('div');
        emptyRow.className = 'table-row table-empty jobs';
        emptyRow.innerHTML = '<span>No jobs yet.</span><span></span><span></span><span></span><span></span>';
        dom.jobsTable.appendChild(emptyRow);
        return;
    }

    state.jobs.forEach((job) => {
        const row = document.createElement('div');
        row.className = 'table-row jobs';
        row.innerHTML = `
            <span>${escapeHtml(truncate(job.description, 40))}</span>
            <span>${escapeHtml(job.customer?.name || getCustomerName(job.customer_id))}</span>
            <span>${formatCurrency(Number(job.cost))}</span>
            <span>${escapeHtml(job.status)}</span>
            <div class="row-actions">
                <button class="btn btn-outline btn-small" data-action="edit" data-id="${job.id}">Edit</button>
                <button class="btn btn-outline btn-small" data-action="delete" data-id="${job.id}">Delete</button>
            </div>
        `;
        dom.jobsTable.appendChild(row);
    });
}

function resetJobForm() {
    if (!dom.jobForm) return;
    dom.jobForm.reset();
    dom.jobForm.querySelector('input[name="id"]').value = '';
    state.editing.job = null;
    if (dom.jobFormTitle) dom.jobFormTitle.textContent = 'New job';
    setFormStatus(dom.jobFormStatus, '');
}

async function handleJobSubmit(event) {
    event.preventDefault();
    if (!dom.jobForm) return;

    const formData = new FormData(dom.jobForm);
    const payload = {
        customer_id: Number(formData.get('customer_id')),
        description: String(formData.get('description') || '').trim(),
        cost: Number(formData.get('cost')),
        status: formData.get('status') || 'draft',
    };

    try {
        if (state.editing.job) {
            await api.put(`/api/jobs/${state.editing.job}`, payload);
            setFormStatus(dom.jobFormStatus, 'Job updated.');
        } else {
            await api.post('/api/jobs', payload);
            setFormStatus(dom.jobFormStatus, 'Job created.');
        }
        await loadJobs();
        resetJobForm();
    } catch (error) {
        setFormStatus(dom.jobFormStatus, 'Unable to save job.', true);
    }
}

async function handleJobAction(event) {
    const actionButton = event.target.closest('[data-action]');
    if (!actionButton) return;
    const id = Number(actionButton.dataset.id);
    const action = actionButton.dataset.action;
    const job = state.jobs.find((item) => item.id === id);

    if (action === 'edit' && job) {
        state.editing.job = id;
        if (dom.jobFormTitle) dom.jobFormTitle.textContent = 'Edit job';
        dom.jobForm.querySelector('input[name="id"]').value = job.id;
        dom.jobForm.querySelector('select[name="customer_id"]').value = job.customer_id;
        dom.jobForm.querySelector('textarea[name="description"]').value = job.description || '';
        dom.jobForm.querySelector('input[name="cost"]').value = job.cost || '';
        dom.jobForm.querySelector('select[name="status"]').value = job.status || 'draft';
        setFormStatus(dom.jobFormStatus, 'Editing job.');
    }

    if (action === 'delete' && id) {
        if (!window.confirm('Delete this job?')) return;
        try {
            await api.delete(`/api/jobs/${id}`);
            await loadJobs();
        } catch (error) {
            setFormStatus(dom.jobFormStatus, 'Unable to delete job.', true);
        }
    }
}

async function loadCosts(append = false) {
    if (!dom.costsTable) return;
    setFormStatus(dom.costFormStatus, '');
    setLoadMoreLoading('costs', true);
    if (!append) {
        resetPagination('costs');
        resetTable(dom.costsTable);
        const loadingRow = document.createElement('div');
        loadingRow.className = 'table-row table-empty costs';
        loadingRow.innerHTML = '<span>Loading costs...</span><span></span><span></span><span></span><span></span>';
        dom.costsTable.appendChild(loadingRow);
    }

    try {
        const page = append ? state.pagination.costs.page + 1 : 1;
        const response = await api.get(`/api/costs${buildQuery({ page, per_page: 15 })}`);
        const items = response?.data?.data ?? [];
        state.costs = append ? [...state.costs, ...items] : items;
        updatePagination('costs', response, append);
        renderCosts();
    } catch (error) {
        resetTable(dom.costsTable);
        const emptyRow = document.createElement('div');
        emptyRow.className = 'table-row table-empty costs';
        emptyRow.innerHTML = '<span>Unable to load costs.</span><span></span><span></span><span></span><span></span>';
        dom.costsTable.appendChild(emptyRow);
    } finally {
        setLoadMoreLoading('costs', false);
    }
}

function renderCosts() {
    if (!dom.costsTable) return;
    resetTable(dom.costsTable);

    if (!state.costs.length) {
        const emptyRow = document.createElement('div');
        emptyRow.className = 'table-row table-empty costs';
        emptyRow.innerHTML = '<span>No costs yet.</span><span></span><span></span><span></span><span></span>';
        dom.costsTable.appendChild(emptyRow);
        return;
    }

    state.costs.forEach((cost) => {
        const row = document.createElement('div');
        row.className = 'table-row costs';
        const receiptButton = cost.receipt_file_id
            ? `<button class="btn btn-outline btn-small" data-action="receipt" data-id="${cost.id}">Download</button>`
            : '<span class="muted">—</span>';

        row.innerHTML = `
            <span>${formatDate(cost.incurred_on)}</span>
            <span>${escapeHtml(truncate(cost.description, 42))}</span>
            <span>${formatCurrency(Number(cost.amount))}</span>
            <span>${receiptButton}</span>
            <div class="row-actions">
                <button class="btn btn-outline btn-small" data-action="edit" data-id="${cost.id}">Edit</button>
                <button class="btn btn-outline btn-small" data-action="delete" data-id="${cost.id}">Delete</button>
            </div>
        `;
        dom.costsTable.appendChild(row);
    });
}

function resetCostForm() {
    if (!dom.costForm) return;
    dom.costForm.reset();
    dom.costForm.querySelector('input[name="id"]').value = '';
    state.editing.cost = null;
    if (dom.costFormTitle) dom.costFormTitle.textContent = 'New cost';
    setFormStatus(dom.costFormStatus, '');
}

async function handleCostSubmit(event) {
    event.preventDefault();
    if (!dom.costForm) return;

    const formData = new FormData(dom.costForm);

    try {
        if (state.editing.cost) {
            formData.append('_method', 'PUT');
            await api.post(`/api/costs/${state.editing.cost}`, formData, {
                headers: { 'Content-Type': 'multipart/form-data' },
            });
            setFormStatus(dom.costFormStatus, 'Cost updated.');
        } else {
            await api.post('/api/costs', formData, {
                headers: { 'Content-Type': 'multipart/form-data' },
            });
            setFormStatus(dom.costFormStatus, 'Cost created.');
        }
        await loadCosts();
        resetCostForm();
    } catch (error) {
        setFormStatus(dom.costFormStatus, 'Unable to save cost.', true);
    }
}

async function downloadReceipt(id, filename) {
    try {
        const response = await api.get(`/api/costs/${id}/receipt`, { responseType: 'blob' });
        const blobUrl = window.URL.createObjectURL(new Blob([response.data]));
        const link = document.createElement('a');
        link.href = blobUrl;
        link.download = filename;
        document.body.appendChild(link);
        link.click();
        link.remove();
        window.URL.revokeObjectURL(blobUrl);
    } catch (error) {
        setFormStatus(dom.costFormStatus, 'Unable to download receipt.', true);
    }
}

async function handleCostAction(event) {
    const actionButton = event.target.closest('[data-action]');
    if (!actionButton) return;
    const id = Number(actionButton.dataset.id);
    const action = actionButton.dataset.action;
    const cost = state.costs.find((item) => item.id === id);

    if (action === 'edit' && cost) {
        state.editing.cost = id;
        if (dom.costFormTitle) dom.costFormTitle.textContent = 'Edit cost';
        dom.costForm.querySelector('input[name="id"]').value = cost.id;
        dom.costForm.querySelector('textarea[name="description"]').value = cost.description || '';
        dom.costForm.querySelector('input[name="amount"]').value = cost.amount || '';
        dom.costForm.querySelector('input[name="incurred_on"]').value = cost.incurred_on || '';
        dom.costForm.querySelector('textarea[name="notes"]').value = cost.notes || '';
        dom.costForm.querySelector('input[name="receipt"]').value = '';
        setFormStatus(dom.costFormStatus, 'Editing cost.');
    }

    if (action === 'receipt' && cost && cost.receipt_file_id) {
        const filename = cost.receipt_file?.original_name || `receipt-${cost.id}`;
        await downloadReceipt(cost.id, filename);
    }

    if (action === 'delete' && id) {
        if (!window.confirm('Delete this cost?')) return;
        try {
            await api.delete(`/api/costs/${id}`);
            await loadCosts();
        } catch (error) {
            setFormStatus(dom.costFormStatus, 'Unable to delete cost.', true);
        }
    }
}

async function loadSubscriptions(append = false) {
    if (!dom.subscriptionsTable) return;
    setFormStatus(dom.subscriptionFormStatus, '');
    setLoadMoreLoading('subscriptions', true);
    if (!append) {
        resetPagination('subscriptions');
        resetTable(dom.subscriptionsTable);
        const loadingRow = document.createElement('div');
        loadingRow.className = 'table-row table-empty subscriptions';
        loadingRow.innerHTML = '<span>Loading subscriptions...</span><span></span><span></span><span></span><span></span><span></span>';
        dom.subscriptionsTable.appendChild(loadingRow);
    }

    try {
        const page = append ? state.pagination.subscriptions.page + 1 : 1;
        const query = buildQuery({
            per_page: 20,
            page,
            status: state.filters.subscriptions.status,
            customer_id: state.filters.subscriptions.customer,
        });
        const response = await api.get(`/api/subscriptions${query}`);
        const items = response?.data?.data ?? [];
        state.subscriptions = append ? [...state.subscriptions, ...items] : items;
        updatePagination('subscriptions', response, append);
        renderSubscriptions();
    } catch (error) {
        resetTable(dom.subscriptionsTable);
        const emptyRow = document.createElement('div');
        emptyRow.className = 'table-row table-empty subscriptions';
        emptyRow.innerHTML = '<span>Unable to load subscriptions.</span><span></span><span></span><span></span><span></span><span></span>';
        dom.subscriptionsTable.appendChild(emptyRow);
    } finally {
        setLoadMoreLoading('subscriptions', false);
    }
}

function renderSubscriptions() {
    if (!dom.subscriptionsTable) return;
    resetTable(dom.subscriptionsTable);

    if (!state.subscriptions.length) {
        const emptyRow = document.createElement('div');
        emptyRow.className = 'table-row table-empty subscriptions';
        emptyRow.innerHTML = '<span>No subscriptions yet.</span><span></span><span></span><span></span><span></span><span></span>';
        dom.subscriptionsTable.appendChild(emptyRow);
        return;
    }

    state.subscriptions.forEach((subscription) => {
        const row = document.createElement('div');
        row.className = 'table-row subscriptions';
        row.innerHTML = `
            <span>${escapeHtml(truncate(subscription.description, 36))}</span>
            <span>${escapeHtml(subscription.customer?.name || getCustomerName(subscription.customer_id))}</span>
            <span>${formatCurrency(Number(subscription.monthly_cost))}</span>
            <span>${escapeHtml(subscription.status)}</span>
            <span>${formatDate(subscription.next_invoice_date)}</span>
            <div class="row-actions">
                <button class="btn btn-outline btn-small" data-action="edit" data-id="${subscription.id}">Edit</button>
                <button class="btn btn-outline btn-small" data-action="delete" data-id="${subscription.id}">Delete</button>
            </div>
        `;
        dom.subscriptionsTable.appendChild(row);
    });
}

function renderSubscriptionMonths(errorMessage = '') {
    if (!dom.subscriptionMonthsTable) return;
    resetTable(dom.subscriptionMonthsTable);

    if (!state.editing.subscription) {
        const emptyRow = document.createElement('div');
        emptyRow.className = 'table-row table-empty subscription-months';
        emptyRow.innerHTML = '<span>Select a subscription to track months.</span><span></span><span></span><span></span>';
        dom.subscriptionMonthsTable.appendChild(emptyRow);
        return;
    }

    if (errorMessage) {
        const emptyRow = document.createElement('div');
        emptyRow.className = 'table-row table-empty subscription-months';
        emptyRow.innerHTML = `<span>${escapeHtml(errorMessage)}</span><span></span><span></span><span></span>`;
        dom.subscriptionMonthsTable.appendChild(emptyRow);
        return;
    }

    if (!state.subscriptionMonths.length) {
        const emptyRow = document.createElement('div');
        emptyRow.className = 'table-row table-empty subscription-months';
        emptyRow.innerHTML = '<span>No monthly entries yet.</span><span></span><span></span><span></span>';
        dom.subscriptionMonthsTable.appendChild(emptyRow);
        return;
    }

    state.subscriptionMonths.forEach((month) => {
        const row = document.createElement('div');
        row.className = 'table-row subscription-months';
        const paymentStatus = month.payment_status === 'paid' ? 'paid' : 'unpaid';
        const nextPaymentStatus = paymentStatus === 'paid' ? 'unpaid' : 'paid';
        const toggleLabel = paymentStatus === 'paid' ? 'Mark unpaid' : 'Mark paid';
        const paymentClass = paymentStatus === 'paid' ? 'payment-status payment-status-paid' : 'payment-status payment-status-unpaid';
        row.innerHTML = `
            <span>${formatMonth(month.month_start)}</span>
            <span>${escapeHtml(month.subscription_status || 'active')}</span>
            <span class="${paymentClass}">${escapeHtml(paymentStatus)}</span>
            <div class="row-actions">
                <button type="button" class="btn btn-outline btn-small" data-action="toggle-payment" data-id="${month.id}" data-next-status="${nextPaymentStatus}">${toggleLabel}</button>
            </div>
        `;
        dom.subscriptionMonthsTable.appendChild(row);
    });
}

async function loadSubscriptionMonths(subscriptionId = state.editing.subscription) {
    if (!dom.subscriptionMonthsTable) return;

    if (!subscriptionId) {
        state.subscriptionMonths = [];
        setFormStatus(dom.subscriptionMonthsStatus, '');
        renderSubscriptionMonths();
        return;
    }

    setFormStatus(dom.subscriptionMonthsStatus, '');
    resetTable(dom.subscriptionMonthsTable);
    const loadingRow = document.createElement('div');
    loadingRow.className = 'table-row table-empty subscription-months';
    loadingRow.innerHTML = '<span>Loading monthly tracking...</span><span></span><span></span><span></span>';
    dom.subscriptionMonthsTable.appendChild(loadingRow);

    try {
        const response = await api.get(`/api/subscriptions/${subscriptionId}/months`);
        state.subscriptionMonths = response?.data?.data ?? [];
        renderSubscriptionMonths();
    } catch (error) {
        state.subscriptionMonths = [];
        renderSubscriptionMonths('Unable to load monthly tracking.');
    }
}

async function handleSubscriptionMonthAction(event) {
    event.preventDefault();

    const actionButton = event.target.closest('[data-action]');
    if (!actionButton) return;

    const action = actionButton.dataset.action;
    const monthId = Number(actionButton.dataset.id);
    const nextStatus = actionButton.dataset.nextStatus;
    const subscriptionId = state.editing.subscription;

    if (action !== 'toggle-payment' || !monthId || !subscriptionId) {
        return;
    }

    actionButton.disabled = true;

    try {
        const payload = {
            payment_status: nextStatus === 'paid' ? 'paid' : 'unpaid',
        };

        try {
            await api.post(`/api/subscription-months/${monthId}/payment`, payload);
        } catch (error) {
            const statusCode = Number(error?.response?.status || 0);

            if (statusCode !== 404 && statusCode !== 405) {
                throw error;
            }

            try {
                await api.post(`/api/subscriptions/${subscriptionId}/months/${monthId}/payment`, payload);
            } catch (nestedError) {
                const nestedStatusCode = Number(nestedError?.response?.status || 0);

                if (nestedStatusCode !== 404 && nestedStatusCode !== 405) {
                    throw nestedError;
                }

                // Backward compatibility for servers that only have the older PATCH route.
                await api.patch(`/api/subscriptions/${subscriptionId}/months/${monthId}`, payload);
            }
        }

        setFormStatus(dom.subscriptionMonthsStatus, 'Monthly payment status updated.');
        await loadSubscriptionMonths(subscriptionId);
    } catch (error) {
        setFormStatus(dom.subscriptionMonthsStatus, 'Unable to update monthly payment status.', true);
    } finally {
        actionButton.disabled = false;
    }
}

function resetSubscriptionForm() {
    if (!dom.subscriptionForm) return;
    dom.subscriptionForm.reset();
    dom.subscriptionForm.querySelector('input[name="id"]').value = '';
    state.editing.subscription = null;
    state.subscriptionMonths = [];
    if (dom.subscriptionFormTitle) dom.subscriptionFormTitle.textContent = 'New subscription';
    setFormStatus(dom.subscriptionFormStatus, '');
    setFormStatus(dom.subscriptionMonthsStatus, '');
    renderSubscriptionMonths();
}

async function handleSubscriptionSubmit(event) {
    event.preventDefault();
    if (!dom.subscriptionForm) return;

    const formData = new FormData(dom.subscriptionForm);
    const payload = {
        customer_id: Number(formData.get('customer_id')),
        description: String(formData.get('description') || '').trim(),
        monthly_cost: Number(formData.get('monthly_cost')),
        start_date: formData.get('start_date'),
        status: formData.get('status') || 'active',
    };

    try {
        if (state.editing.subscription) {
            await api.put(`/api/subscriptions/${state.editing.subscription}`, payload);
            setFormStatus(dom.subscriptionFormStatus, 'Subscription updated.');
            await loadSubscriptionMonths(state.editing.subscription);
        } else {
            await api.post('/api/subscriptions', payload);
            setFormStatus(dom.subscriptionFormStatus, 'Subscription created.');
        }
        await loadSubscriptions();
        resetSubscriptionForm();
    } catch (error) {
        setFormStatus(dom.subscriptionFormStatus, 'Unable to save subscription.', true);
    }
}

async function handleSubscriptionAction(event) {
    const actionButton = event.target.closest('[data-action]');
    if (!actionButton) return;
    const id = Number(actionButton.dataset.id);
    const action = actionButton.dataset.action;
    const subscription = state.subscriptions.find((item) => item.id === id);

    if (action === 'edit' && subscription) {
        state.editing.subscription = id;
        if (dom.subscriptionFormTitle) dom.subscriptionFormTitle.textContent = 'Edit subscription';
        dom.subscriptionForm.querySelector('input[name="id"]').value = subscription.id;
        dom.subscriptionForm.querySelector('select[name="customer_id"]').value = subscription.customer_id;
        dom.subscriptionForm.querySelector('textarea[name="description"]').value = subscription.description || '';
        dom.subscriptionForm.querySelector('input[name="monthly_cost"]').value = subscription.monthly_cost || '';
        dom.subscriptionForm.querySelector('input[name="start_date"]').value = formatDateInput(subscription.start_date);
        dom.subscriptionForm.querySelector('select[name="status"]').value = subscription.status || 'active';
        setFormStatus(dom.subscriptionFormStatus, 'Editing subscription.');
        await loadSubscriptionMonths(id);
    }

    if (action === 'delete' && id) {
        if (!window.confirm('Delete this subscription?')) return;
        try {
            await api.delete(`/api/subscriptions/${id}`);
            if (state.editing.subscription === id) {
                resetSubscriptionForm();
            }
            await loadSubscriptions();
        } catch (error) {
            setFormStatus(dom.subscriptionFormStatus, 'Unable to delete subscription.', true);
        }
    }
}

function clearInvoiceLineItems() {
    if (dom.invoiceLineItems) {
        dom.invoiceLineItems.innerHTML = '';
    }
}

function addInvoiceLineItem(item = {}) {
    const template = document.getElementById('invoice-line-item-template');
    if (!template || !dom.invoiceLineItems) return;
    const clone = template.content.cloneNode(true);
    const row = clone.querySelector('.line-item');
    if (!row) return;

    row.querySelector('input[name="description"]').value = item.description || '';
    row.querySelector('input[name="quantity"]').value = item.quantity || 1;
    row.querySelector('input[name="unit_price"]').value = item.unit_price || '';
    const billableTypeInput = row.querySelector('select[name="billable_type"]');
    const billableIdInput = row.querySelector('input[name="billable_id"]');
    billableTypeInput.value = item.billable_type || '';
    billableIdInput.value = item.billable_id || '';

    const syncBillableIdState = () => {
        const requiresId = billableTypeInput.value === 'job' || billableTypeInput.value === 'subscription';
        billableIdInput.disabled = !requiresId;
        if (!requiresId) {
            billableIdInput.value = '';
        }
    };

    syncBillableIdState();
    billableTypeInput.addEventListener('change', syncBillableIdState);

    row.addEventListener('click', (event) => {
        const button = event.target.closest('[data-action="remove-line-item"]');
        if (!button) return;
        row.remove();
    });

    dom.invoiceLineItems.appendChild(clone);
}

function mapBillableType(type) {
    if (!type) return '';
    if (type.toLowerCase().includes('job')) return 'job';
    if (type.toLowerCase().includes('subscription')) return 'subscription';
    return '';
}

function collectInvoiceLineItems() {
    if (!dom.invoiceLineItems) return [];
    const rows = Array.from(dom.invoiceLineItems.querySelectorAll('.line-item'));
    const items = [];

    for (const row of rows) {
        const description = row.querySelector('input[name="description"]').value.trim();
        const quantity = Number(row.querySelector('input[name="quantity"]').value);
        const unitPrice = Number(row.querySelector('input[name="unit_price"]').value);
        const billableType = row.querySelector('select[name="billable_type"]').value;
        const billableIdRaw = row.querySelector('input[name="billable_id"]').value;
        const billableId = billableType ? (billableIdRaw ? Number(billableIdRaw) : null) : null;

        if (!description) {
            continue;
        }
        if (!quantity || quantity < 1 || Number.isNaN(quantity)) {
            throw new Error('Line item quantity is invalid.');
        }
        if (Number.isNaN(unitPrice) || unitPrice < 0) {
            throw new Error('Line item unit price is invalid.');
        }
        if (billableType && (!billableId || Number.isNaN(billableId) || billableId < 1)) {
            throw new Error('Select a valid Billable ID when using Job or Subscription.');
        }

        items.push({
            description,
            quantity,
            unit_price: unitPrice,
            billable_type: billableType || null,
            billable_id: billableId || null,
        });
    }

    return items;
}

async function loadInvoices(append = false) {
    if (!dom.invoicesTable) return;
    setFormStatus(dom.invoiceFormStatus, '');
    setLoadMoreLoading('invoices', true);
    if (!append) {
        resetPagination('invoices');
        resetTable(dom.invoicesTable);
        const loadingRow = document.createElement('div');
        loadingRow.className = 'table-row table-empty invoices';
        loadingRow.innerHTML = '<span>Loading invoices...</span><span></span><span></span><span></span><span></span><span></span>';
        dom.invoicesTable.appendChild(loadingRow);
    }

    try {
        const page = append ? state.pagination.invoices.page + 1 : 1;
        const query = buildQuery({
            per_page: 20,
            page,
            status: state.filters.invoices.status,
            customer_id: state.filters.invoices.customer,
        });
        const response = await api.get(`/api/invoices${query}`);
        const items = response?.data?.data ?? [];
        state.invoices = append ? [...state.invoices, ...items] : items;
        updatePagination('invoices', response, append);
        renderInvoices();
    } catch (error) {
        resetTable(dom.invoicesTable);
        const emptyRow = document.createElement('div');
        emptyRow.className = 'table-row table-empty invoices';
        emptyRow.innerHTML = '<span>Unable to load invoices.</span><span></span><span></span><span></span><span></span><span></span>';
        dom.invoicesTable.appendChild(emptyRow);
    } finally {
        setLoadMoreLoading('invoices', false);
    }
}

function renderInvoices() {
    if (!dom.invoicesTable) return;
    resetTable(dom.invoicesTable);

    if (!state.invoices.length) {
        const emptyRow = document.createElement('div');
        emptyRow.className = 'table-row table-empty invoices';
        emptyRow.innerHTML = '<span>No invoices yet.</span><span></span><span></span><span></span><span></span><span></span>';
        dom.invoicesTable.appendChild(emptyRow);
        return;
    }

    state.invoices.forEach((invoice) => {
        const row = document.createElement('div');
        row.className = 'table-row invoices';
        row.innerHTML = `
            <span>#${escapeHtml(invoice.invoice_number)}</span>
            <span>${escapeHtml(invoice.customer?.name || getCustomerName(invoice.customer_id))}</span>
            <span>${formatCurrency(Number(invoice.total))}</span>
            <span>${escapeHtml(invoice.status)}</span>
            <span>${formatDate(invoice.due_date)}</span>
            <div class="row-actions">
                <button class="btn btn-outline btn-small" data-action="edit" data-id="${invoice.id}">Edit</button>
                <button class="btn btn-outline btn-small" data-action="send" data-id="${invoice.id}">Send</button>
                <button class="btn btn-outline btn-small" data-action="download" data-id="${invoice.id}">Download</button>
                <button class="btn btn-outline btn-small" data-action="delete" data-id="${invoice.id}">Delete</button>
            </div>
        `;
        dom.invoicesTable.appendChild(row);
    });
}

function resetInvoiceForm() {
    if (!dom.invoiceForm) return;
    dom.invoiceForm.reset();
    dom.invoiceForm.querySelector('input[name="id"]').value = '';
    state.editing.invoice = null;
    if (dom.invoiceFormTitle) dom.invoiceFormTitle.textContent = 'New invoice';
    clearInvoiceLineItems();
    addInvoiceLineItem();
    setFormStatus(dom.invoiceFormStatus, '');
}

async function handleInvoiceSubmit(event) {
    event.preventDefault();
    if (!dom.invoiceForm) return;

    const formData = new FormData(dom.invoiceForm);
    let lineItems;
    try {
        lineItems = collectInvoiceLineItems();
    } catch (error) {
        setFormStatus(dom.invoiceFormStatus, error.message, true);
        return;
    }

    if (!lineItems.length) {
        setFormStatus(dom.invoiceFormStatus, 'Add at least one line item.', true);
        return;
    }

    const customerId = Number(formData.get('customer_id'));
    if (!customerId || Number.isNaN(customerId)) {
        setFormStatus(dom.invoiceFormStatus, 'Select a customer.', true);
        return;
    }

    const payload = {
        customer_id: customerId,
        issue_date: formData.get('issue_date'),
        due_date: formData.get('due_date'),
        tax_amount: formData.get('tax_amount') ? Number(formData.get('tax_amount')) : 0,
        status: formData.get('status') || 'draft',
        line_items: lineItems,
    };

    try {
        if (state.editing.invoice) {
            await api.put(`/api/invoices/${state.editing.invoice}`, payload);
            setFormStatus(dom.invoiceFormStatus, 'Invoice updated.');
        } else {
            await api.post('/api/invoices', payload);
            setFormStatus(dom.invoiceFormStatus, 'Invoice created.');
        }
        await loadInvoices();
        resetInvoiceForm();
    } catch (error) {
        setFormStatus(dom.invoiceFormStatus, getErrorMessage(error, 'Unable to save invoice.'), true);
    }
}

async function downloadInvoice(id, filename, portal = false) {
    try {
        const url = portal ? `/api/portal/invoices/${id}/download` : `/api/invoices/${id}/download`;
        const response = await api.get(url, { responseType: 'blob' });
        const blobUrl = window.URL.createObjectURL(new Blob([response.data]));
        const link = document.createElement('a');
        link.href = blobUrl;
        link.download = filename;
        document.body.appendChild(link);
        link.click();
        link.remove();
        window.URL.revokeObjectURL(blobUrl);
    } catch (error) {
        setFormStatus(dom.invoiceFormStatus, 'Unable to download invoice.', true);
    }
}

async function handleInvoiceAction(event) {
    const actionButton = event.target.closest('[data-action]');
    if (!actionButton) return;
    const id = Number(actionButton.dataset.id);
    const action = actionButton.dataset.action;
    const invoice = state.invoices.find((item) => item.id === id);

    if (action === 'edit' && invoice) {
        state.editing.invoice = id;
        if (dom.invoiceFormTitle) dom.invoiceFormTitle.textContent = `Edit ${invoice.invoice_number}`;
        dom.invoiceForm.querySelector('input[name="id"]').value = invoice.id;
        dom.invoiceForm.querySelector('select[name="customer_id"]').value = invoice.customer_id;
        dom.invoiceForm.querySelector('input[name="issue_date"]').value = invoice.issue_date || '';
        dom.invoiceForm.querySelector('input[name="due_date"]').value = invoice.due_date || '';
        dom.invoiceForm.querySelector('input[name="tax_amount"]').value = invoice.tax_amount || 0;
        dom.invoiceForm.querySelector('select[name="status"]').value = invoice.status || 'draft';
        clearInvoiceLineItems();
        (invoice.line_items || []).forEach((item) => {
            addInvoiceLineItem({
                description: item.description,
                quantity: item.quantity,
                unit_price: item.unit_price,
                billable_type: mapBillableType(item.billable_type),
                billable_id: item.billable_id,
            });
        });
        if (!(invoice.line_items || []).length) {
            addInvoiceLineItem();
        }
        setFormStatus(dom.invoiceFormStatus, 'Editing invoice.');
    }

    if (action === 'send' && id) {
        try {
            await api.post(`/api/invoices/${id}/send`);
            await loadInvoices();
        } catch (error) {
            setFormStatus(dom.invoiceFormStatus, 'Unable to send invoice.', true);
        }
    }

    if (action === 'download' && invoice) {
        await downloadInvoice(id, `Invoice-${invoice.invoice_number}.pdf`);
    }

    if (action === 'delete' && id) {
        if (!window.confirm('Delete this invoice?')) return;
        try {
            await api.delete(`/api/invoices/${id}`);
            await loadInvoices();
        } catch (error) {
            setFormStatus(dom.invoiceFormStatus, 'Unable to delete invoice.', true);
        }
    }
}

function initializeInvoiceForm() {
    clearInvoiceLineItems();
    addInvoiceLineItem();
}

function handlePortalDownloadLatest() {
    if (!state.portalInvoices.length) return;
    const latest = state.portalInvoices[0];
    downloadInvoice(latest.id, `Invoice-${latest.invoice_number}.pdf`, true);
}

function initializeNavigation() {
    dom.navItems.forEach((item) => {
        item.addEventListener('click', (event) => {
            event.preventDefault();
            if (state.role === 'customer' && item.dataset.view !== 'portal') return;
            setActiveView(item.dataset.view);
            setNavOpen(false);
        });
    });

    dom.quickLinks.forEach((button) => {
        button.addEventListener('click', () => {
            if (button.dataset.goView) {
                if (button.dataset.goView === 'admin' && state.role === 'customer') {
                    return;
                }
                setActiveView(button.dataset.goView);
            }
        });
    });
}

if (dom.themeToggles.length) {
    dom.themeToggles.forEach((button) => {
        button.addEventListener('click', () => {
            const current = document.documentElement.dataset.theme === 'dark' ? 'dark' : 'light';
            const next = current === 'dark' ? 'light' : 'dark';
            saveTheme(next);
        });
    });
}

if (dom.loginForm) {
    dom.loginForm.addEventListener('submit', handleLogin);
}

if (dom.logoutButton) {
    dom.logoutButton.addEventListener('click', handleLogout);
}

if (dom.logoutButtonMobile) {
    dom.logoutButtonMobile.addEventListener('click', handleLogout);
}

if (dom.mobileMenuToggle) {
    dom.mobileMenuToggle.addEventListener('click', toggleNav);
}

window.addEventListener('resize', () => {
    if (window.innerWidth > 964) {
        setNavOpen(false);
    }
});

if (dom.logoUploadForm) {
    dom.logoUploadForm.addEventListener('submit', handleLogoUpload);
}

if (dom.profileForm) {
    dom.profileForm.addEventListener('submit', handleProfileSubmit);
}

if (dom.passwordForm) {
    dom.passwordForm.addEventListener('submit', handlePasswordSubmit);
}

if (dom.staffUserForm) {
    dom.staffUserForm.addEventListener('submit', handleStaffUserSubmit);
}

if (dom.staffUsersRefresh) {
    dom.staffUsersRefresh.addEventListener('click', loadStaffUsers);
}

if (dom.customerForm) {
    dom.customerForm.addEventListener('submit', handleCustomerSubmit);
}

if (dom.customerFormCancel) {
    dom.customerFormCancel.addEventListener('click', resetCustomerForm);
}

if (dom.customersTable) {
    dom.customersTable.addEventListener('click', handleCustomerAction);
}

if (dom.customersRefresh) {
    dom.customersRefresh.addEventListener('click', loadCustomers);
}

if (dom.customersLoadMore) {
    dom.customersLoadMore.addEventListener('click', () => loadCustomers(true));
}

if (dom.customersSearch) {
    const debouncedSearch = debounce(() => {
        state.filters.customers.search = dom.customersSearch.value.trim();
        loadCustomers();
    }, 300);
    dom.customersSearch.addEventListener('input', debouncedSearch);
}

if (dom.customersClear) {
    dom.customersClear.addEventListener('click', () => {
        if (dom.customersSearch) dom.customersSearch.value = '';
        state.filters.customers.search = '';
        loadCustomers();
    });
}

if (dom.customerWebsiteForm) {
    dom.customerWebsiteForm.addEventListener('submit', handleCustomerWebsiteSubmit);
}

if (dom.customerWebsiteCancel) {
    dom.customerWebsiteCancel.addEventListener('click', resetCustomerWebsiteForm);
}

if (dom.customerWebsitesList) {
    dom.customerWebsitesList.addEventListener('click', handleCustomerWebsiteAction);
}

if (dom.customerDetailBack) {
    dom.customerDetailBack.addEventListener('click', () => setActiveView('customers'));
}

if (dom.jobForm) {
    dom.jobForm.addEventListener('submit', handleJobSubmit);
}

if (dom.jobFormCancel) {
    dom.jobFormCancel.addEventListener('click', resetJobForm);
}

if (dom.jobsTable) {
    dom.jobsTable.addEventListener('click', handleJobAction);
}

if (dom.jobsRefresh) {
    dom.jobsRefresh.addEventListener('click', loadJobs);
}

if (dom.jobsLoadMore) {
    dom.jobsLoadMore.addEventListener('click', () => loadJobs(true));
}

if (dom.costForm) {
    dom.costForm.addEventListener('submit', handleCostSubmit);
}

if (dom.costFormCancel) {
    dom.costFormCancel.addEventListener('click', resetCostForm);
}

if (dom.costsTable) {
    dom.costsTable.addEventListener('click', handleCostAction);
}

if (dom.costsRefresh) {
    dom.costsRefresh.addEventListener('click', loadCosts);
}

if (dom.costsLoadMore) {
    dom.costsLoadMore.addEventListener('click', () => loadCosts(true));
}

if (dom.jobsFilterStatus) {
    dom.jobsFilterStatus.addEventListener('change', () => {
        state.filters.jobs.status = dom.jobsFilterStatus.value;
        loadJobs();
    });
}

if (dom.jobsFilterCustomer) {
    dom.jobsFilterCustomer.addEventListener('change', () => {
        state.filters.jobs.customer = dom.jobsFilterCustomer.value;
        loadJobs();
    });
}

if (dom.jobsClear) {
    dom.jobsClear.addEventListener('click', () => {
        if (dom.jobsFilterStatus) dom.jobsFilterStatus.value = 'all';
        if (dom.jobsFilterCustomer) dom.jobsFilterCustomer.value = 'all';
        state.filters.jobs.status = 'all';
        state.filters.jobs.customer = 'all';
        loadJobs();
    });
}

if (dom.subscriptionForm) {
    dom.subscriptionForm.addEventListener('submit', handleSubscriptionSubmit);
}

if (dom.subscriptionFormCancel) {
    dom.subscriptionFormCancel.addEventListener('click', resetSubscriptionForm);
}

if (dom.subscriptionsTable) {
    dom.subscriptionsTable.addEventListener('click', handleSubscriptionAction);
}

if (dom.subscriptionMonthsTable) {
    dom.subscriptionMonthsTable.addEventListener('click', handleSubscriptionMonthAction);
}

if (dom.subscriptionsRefresh) {
    dom.subscriptionsRefresh.addEventListener('click', loadSubscriptions);
}

if (dom.subscriptionMonthsRefresh) {
    dom.subscriptionMonthsRefresh.addEventListener('click', () => loadSubscriptionMonths(state.editing.subscription));
}

if (dom.subscriptionsLoadMore) {
    dom.subscriptionsLoadMore.addEventListener('click', () => loadSubscriptions(true));
}

if (dom.subscriptionsFilterStatus) {
    dom.subscriptionsFilterStatus.addEventListener('change', () => {
        state.filters.subscriptions.status = dom.subscriptionsFilterStatus.value;
        loadSubscriptions();
    });
}

if (dom.subscriptionsFilterCustomer) {
    dom.subscriptionsFilterCustomer.addEventListener('change', () => {
        state.filters.subscriptions.customer = dom.subscriptionsFilterCustomer.value;
        loadSubscriptions();
    });
}

if (dom.subscriptionsClear) {
    dom.subscriptionsClear.addEventListener('click', () => {
        if (dom.subscriptionsFilterStatus) dom.subscriptionsFilterStatus.value = 'all';
        if (dom.subscriptionsFilterCustomer) dom.subscriptionsFilterCustomer.value = 'all';
        state.filters.subscriptions.status = 'all';
        state.filters.subscriptions.customer = 'all';
        loadSubscriptions();
    });
}

if (dom.invoiceForm) {
    dom.invoiceForm.addEventListener('submit', handleInvoiceSubmit);
}

if (dom.invoiceFormCancel) {
    dom.invoiceFormCancel.addEventListener('click', resetInvoiceForm);
}

if (dom.invoiceAddLineItem) {
    dom.invoiceAddLineItem.addEventListener('click', () => addInvoiceLineItem());
}

if (dom.invoicesTable) {
    dom.invoicesTable.addEventListener('click', handleInvoiceAction);
}

if (dom.invoicesRefresh) {
    dom.invoicesRefresh.addEventListener('click', loadInvoices);
}

if (dom.invoicesLoadMore) {
    dom.invoicesLoadMore.addEventListener('click', () => loadInvoices(true));
}

if (dom.invoicesFilterStatus) {
    dom.invoicesFilterStatus.addEventListener('change', () => {
        state.filters.invoices.status = dom.invoicesFilterStatus.value;
        loadInvoices();
    });
}

if (dom.invoicesFilterCustomer) {
    dom.invoicesFilterCustomer.addEventListener('change', () => {
        state.filters.invoices.customer = dom.invoicesFilterCustomer.value;
        loadInvoices();
    });
}

if (dom.invoicesClear) {
    dom.invoicesClear.addEventListener('click', () => {
        if (dom.invoicesFilterStatus) dom.invoicesFilterStatus.value = 'all';
        if (dom.invoicesFilterCustomer) dom.invoicesFilterCustomer.value = 'all';
        state.filters.invoices.status = 'all';
        state.filters.invoices.customer = 'all';
        loadInvoices();
    });
}

if (dom.portalDownloadLatest) {
    dom.portalDownloadLatest.addEventListener('click', handlePortalDownloadLatest);
}

initializeInvoiceForm();
initializeNavigation();
applyStoredTheme();
renderSubscriptionMonths();
loadSession();
