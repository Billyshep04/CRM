import './bootstrap';

const authKey = 'auth_token';
const themeKey = 'theme_preference';

const dom = {
    body: document.body,
    themeToggles: document.querySelectorAll('[data-theme-toggle]'),
    themeLabels: document.querySelectorAll('.theme-label'),
    loginIntro: document.getElementById('login-intro'),
    forgotPasswordIntro: document.getElementById('forgot-password-intro'),
    resetPasswordIntro: document.getElementById('reset-password-intro'),
    loginForm: document.getElementById('login-form'),
    loginError: document.getElementById('login-error'),
    forgotPasswordForm: document.getElementById('forgot-password-form'),
    forgotPasswordLink: document.getElementById('forgot-password-link'),
    forgotPasswordBack: document.getElementById('forgot-password-back'),
    forgotPasswordStatus: document.getElementById('forgot-password-status'),
    resetPasswordForm: document.getElementById('reset-password-form'),
    resetPasswordBack: document.getElementById('reset-password-back'),
    resetPasswordStatus: document.getElementById('reset-password-status'),
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
    dashboardPotentialMrr: document.getElementById('dashboard-potential-mrr'),
    dashboardOpportunityValue: document.getElementById('dashboard-opportunity-value'),
    dashboardOpportunityCount: document.getElementById('dashboard-opportunity-count'),
    opportunityPotentialMrr: document.getElementById('opportunity-potential-mrr'),
    opportunityWeightedMrr: document.getElementById('opportunity-weighted-mrr'),
    opportunityProjectValue: document.getElementById('opportunity-project-value'),
    opportunityRenewals: document.getElementById('opportunity-renewals'),
    opportunitiesTable: document.getElementById('opportunities-table'),
    opportunitiesRefresh: document.getElementById('opportunities-refresh'),
    opportunitiesRecommend: document.getElementById('opportunities-recommend'),
    opportunitiesBulkEdit: document.getElementById('opportunities-bulk-edit'),
    opportunitiesBulkDelete: document.getElementById('opportunities-bulk-delete'),
    opportunitiesFilterStatus: document.getElementById('opportunities-filter-status'),
    opportunitiesFilterType: document.getElementById('opportunities-filter-type'),
    opportunityForm: document.getElementById('opportunity-form'),
    opportunityFormTitle: document.getElementById('opportunity-form-title'),
    opportunityFormStatus: document.getElementById('opportunity-form-status'),
    opportunityFormCancel: document.getElementById('opportunity-form-cancel'),
    opportunityCustomerSelect: document.getElementById('opportunity-customer-select'),
    opportunityFollowUpModal: document.getElementById('opportunity-follow-up-modal'),
    opportunityFollowUpForm: document.getElementById('opportunity-follow-up-form'),
    opportunityFollowUpDate: document.getElementById('opportunity-follow-up-date'),
    opportunityFollowUpSubtitle: document.getElementById('opportunity-follow-up-subtitle'),
    opportunityFollowUpStatus: document.getElementById('opportunity-follow-up-status'),
    opportunityFollowUpClose: document.getElementById('opportunity-follow-up-close'),
    leadDiscoveryForm: document.getElementById('lead-discovery-form'),
    leadDiscoveryStatus: document.getElementById('lead-discovery-status'),
    leadDiscoveryRefresh: document.getElementById('lead-discovery-refresh'),
    leadDiscoveryContacted: document.getElementById('lead-discovery-contacted'),
    discoveredLeadsTitle: document.getElementById('discovered-leads-title'),
    discoveredLeadsSubtitle: document.getElementById('discovered-leads-subtitle'),
    discoveredLeadsTable: document.getElementById('discovered-leads-table'),
    leadDiscoveryRuns: document.getElementById('lead-discovery-runs'),
    leadDetailTitle: document.getElementById('lead-detail-title'),
    leadDetailCategory: document.getElementById('lead-detail-category'),
    leadDetailWebsite: document.getElementById('lead-detail-website'),
    leadDetailPhone: document.getElementById('lead-detail-phone'),
    leadDetailAddress: document.getElementById('lead-detail-address'),
    leadDetailGoogle: document.getElementById('lead-detail-google'),
    leadDetailScores: document.getElementById('lead-detail-scores'),
    leadDetailAuditDate: document.getElementById('lead-detail-audit-date'),
    leadDetailFacts: document.getElementById('lead-detail-facts'),
    leadDetailFindings: document.getElementById('lead-detail-findings'),
    leadDetailHistory: document.getElementById('lead-detail-history'),
    leadDetailContacted: document.getElementById('lead-detail-contacted'),
    leadDetailConvert: document.getElementById('lead-detail-convert'),
    leadDetailAudit: document.getElementById('lead-detail-audit'),
    leadDetailDelete: document.getElementById('lead-detail-delete'),
    leadDetailBack: document.getElementById('lead-detail-back'),
    monthlyFinanceMonths: document.getElementById('monthly-finance-months'),
    monthlyFinanceRefresh: document.getElementById('monthly-finance-refresh'),
    monthlyFinanceSelectedMonth: document.getElementById('monthly-finance-selected-month'),
    monthlyFinanceRevenue: document.getElementById('monthly-finance-revenue'),
    monthlyFinanceRevenueMeta: document.getElementById('monthly-finance-revenue-meta'),
    monthlyFinanceCosts: document.getElementById('monthly-finance-costs'),
    monthlyFinanceCostsMeta: document.getElementById('monthly-finance-costs-meta'),
    monthlyFinanceProfit: document.getElementById('monthly-finance-profit'),
    monthlyFinanceProfitMeta: document.getElementById('monthly-finance-profit-meta'),
    monthlyFinanceTax: document.getElementById('monthly-finance-tax'),
    monthlyFinanceTaxMeta: document.getElementById('monthly-finance-tax-meta'),
    monthlyFinanceOwed: document.getElementById('monthly-finance-owed'),
    monthlyFinanceOwedMeta: document.getElementById('monthly-finance-owed-meta'),
    monthlyFinanceCardRevenue: document.getElementById('monthly-finance-card-revenue'),
    monthlyFinanceCardCosts: document.getElementById('monthly-finance-card-costs'),
    monthlyFinanceCardProfit: document.getElementById('monthly-finance-card-profit'),
    monthlyFinanceCardTax: document.getElementById('monthly-finance-card-tax'),
    monthlyFinanceCardOwed: document.getElementById('monthly-finance-card-owed'),
    monthlyFinanceSettingsToggle: document.getElementById('monthly-finance-settings-toggle'),
    monthlyFinanceSettingsPopover: document.getElementById('monthly-finance-settings-popover'),
    monthlyFinanceToggleRevenue: document.getElementById('monthly-finance-toggle-revenue'),
    monthlyFinanceToggleCosts: document.getElementById('monthly-finance-toggle-costs'),
    monthlyFinanceToggleProfit: document.getElementById('monthly-finance-toggle-profit'),
    monthlyFinanceToggleTax: document.getElementById('monthly-finance-toggle-tax'),
    monthlyFinanceToggleOwed: document.getElementById('monthly-finance-toggle-owed'),
    dashboardSettingsToggle: document.getElementById('dashboard-settings-toggle'),
    dashboardSettingsPopover: document.getElementById('dashboard-settings-popover'),
    dashboardTileRevenue: document.getElementById('dashboard-tile-revenue'),
    dashboardTileCosts: document.getElementById('dashboard-tile-costs'),
    dashboardTileProfit: document.getElementById('dashboard-tile-profit'),
    dashboardTileJobs: document.getElementById('dashboard-tile-jobs'),
    dashboardTileSubscriptions: document.getElementById('dashboard-tile-subscriptions'),
    dashboardTilePotentialMrr: document.getElementById('dashboard-tile-potential-mrr'),
    dashboardTilePipelineValue: document.getElementById('dashboard-tile-pipeline-value'),
    dashboardTileOpenOpportunities: document.getElementById('dashboard-tile-open-opportunities'),
    dashboardToggleRevenue: document.getElementById('dashboard-toggle-revenue'),
    dashboardToggleCosts: document.getElementById('dashboard-toggle-costs'),
    dashboardToggleProfit: document.getElementById('dashboard-toggle-profit'),
    dashboardToggleJobs: document.getElementById('dashboard-toggle-jobs'),
    dashboardToggleSubscriptions: document.getElementById('dashboard-toggle-subscriptions'),
    dashboardTogglePotentialMrr: document.getElementById('dashboard-toggle-potential-mrr'),
    dashboardTogglePipelineValue: document.getElementById('dashboard-toggle-pipeline-value'),
    dashboardToggleOpenOpportunities: document.getElementById('dashboard-toggle-open-opportunities'),
    monthlyTasksMonths: document.getElementById('monthly-tasks-months'),
    monthlyTasksRefresh: document.getElementById('monthly-tasks-refresh'),
    monthlyTasksSelectedMonth: document.getElementById('monthly-tasks-selected-month'),
    monthlyTasksCompleted: document.getElementById('monthly-tasks-completed'),
    monthlyTasksHours: document.getElementById('monthly-tasks-hours'),
    monthlyTasksTaskChange: document.getElementById('monthly-tasks-task-change'),
    monthlyTasksHourChange: document.getElementById('monthly-tasks-hour-change'),
    monthlyTasksCardTaskChange: document.getElementById('monthly-tasks-card-task-change'),
    monthlyTasksCardHourChange: document.getElementById('monthly-tasks-card-hour-change'),
    mobileMenuToggle: document.getElementById('mobile-menu-toggle'),
    mobileMenuNotification: document.getElementById('mobile-menu-notification'),
    navItems: document.querySelectorAll('.nav-item[data-view]'),
    views: document.querySelectorAll('.view'),
    quickLinks: document.querySelectorAll('[data-go-view]'),
    logoUploadForm: document.getElementById('logo-upload-form'),
    logoUploadStatus: document.getElementById('logo-upload-status'),
    smtp2goSettingsForm: document.getElementById('smtp2go-settings-form'),
    smtp2goSettingsStatus: document.getElementById('smtp2go-settings-status'),
    smtp2goEnabled: document.getElementById('smtp2go-enabled'),
    smtp2goApiKey: document.getElementById('smtp2go-api-key'),
    smtp2goApiKeyMask: document.getElementById('smtp2go-api-key-mask'),
    invoiceSettingsForm: document.getElementById('invoice-settings-form'),
    invoiceSettingsStatus: document.getElementById('invoice-settings-status'),
    invoiceAccountName: document.getElementById('invoice-account-name'),
    invoiceSortCode: document.getElementById('invoice-sort-code'),
    invoiceAccountNumber: document.getElementById('invoice-account-number'),
    proposalFormsSettingsForm: document.getElementById('proposal-forms-settings-form'),
    proposalFormsSettingsStatus: document.getElementById('proposal-forms-settings-status'),
    proposalFormsEditor: document.getElementById('proposal-forms-editor'),
    proposalFormsAddType: document.getElementById('proposal-forms-add-type'),
    proposalFormEditTitle: document.getElementById('proposal-form-edit-title'),
    proposalFormEditLabel: document.getElementById('proposal-form-edit-label'),
    proposalFormEditEditor: document.getElementById('proposal-form-edit-editor'),
    proposalFormEditStatus: document.getElementById('proposal-form-edit-status'),
    proposalFormEditBack: document.getElementById('proposal-form-edit-back'),
    proposalFormEditAddQuestion: document.getElementById('proposal-form-edit-add-question'),
    proposalFormEditDelete: document.getElementById('proposal-form-edit-delete'),
    customerFormsSettingsForm: document.getElementById('customer-forms-settings-form'),
    customerFormsSettingsStatus: document.getElementById('customer-forms-settings-status'),
    customerFormsEditor: document.getElementById('customer-forms-editor'),
    customerFormsAddType: document.getElementById('customer-forms-add-type'),
    customerFormEditTitle: document.getElementById('customer-form-edit-title'),
    customerFormEditLabel: document.getElementById('customer-form-edit-label'),
    customerFormEditEditor: document.getElementById('customer-form-edit-editor'),
    customerFormEditStatus: document.getElementById('customer-form-edit-status'),
    customerFormEditBack: document.getElementById('customer-form-edit-back'),
    customerFormEditAddQuestion: document.getElementById('customer-form-edit-add-question'),
    customerFormEditDelete: document.getElementById('customer-form-edit-delete'),
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
    portalJobs: document.getElementById('portal-jobs'),
    portalSubscriptions: document.getElementById('portal-subscriptions'),
    portalWebsites: document.getElementById('portal-websites'),
    portalProfileForm: document.getElementById('portal-profile-form'),
    portalProfileStatus: document.getElementById('portal-profile-status'),
    portalProfileName: document.getElementById('portal-profile-name'),
    portalProfileEmail: document.getElementById('portal-profile-email'),
    portalProfilePhone: document.getElementById('portal-profile-phone'),
    portalProfileBillingAddress: document.getElementById('portal-profile-billing-address'),
    portalSupportForm: document.getElementById('portal-support-form'),
    portalSupportStatus: document.getElementById('portal-support-status'),
    portalPasswordForm: document.getElementById('portal-password-form'),
    portalPasswordStatus: document.getElementById('portal-password-status'),
    toast: document.getElementById('app-toast'),
    customerDetailTitle: document.getElementById('customer-detail-title'),
    customerDetailEmail: document.getElementById('customer-detail-email'),
    customerDetailPhone: document.getElementById('customer-detail-phone'),
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
    customerFormRequestForm: document.getElementById('customer-form-request-form'),
    customerFormTemplate: document.getElementById('customer-form-template'),
    customerFormRequestStatus: document.getElementById('customer-form-request-status'),
    customerFormsTable: document.getElementById('customer-forms-table'),
    customerFormReview: document.getElementById('customer-form-review'),
    customerDetailBack: document.getElementById('customer-detail-back'),
    customerDetailArchive: document.getElementById('customer-detail-archive'),
    customersSearch: document.getElementById('customers-search'),
    customersClear: document.getElementById('customers-clear'),
    customersLoadMore: document.getElementById('customers-load-more'),
    customersArchivedToggle: document.getElementById('customers-archived-toggle'),
    jobsFilterStatus: document.getElementById('jobs-filter-status'),
    jobsFilterCustomer: document.getElementById('jobs-filter-customer'),
    jobsClear: document.getElementById('jobs-clear'),
    jobsLoadMore: document.getElementById('jobs-load-more'),
    jobsArchivedToggle: document.getElementById('jobs-archived-toggle'),
    costsLoadMore: document.getElementById('costs-load-more'),
    subscriptionsFilterStatus: document.getElementById('subscriptions-filter-status'),
    subscriptionsFilterCustomer: document.getElementById('subscriptions-filter-customer'),
    subscriptionsClear: document.getElementById('subscriptions-clear'),
    subscriptionsLoadMore: document.getElementById('subscriptions-load-more'),
    proposalsFilterStatus: document.getElementById('proposals-filter-status'),
    proposalsFilterCustomer: document.getElementById('proposals-filter-customer'),
    proposalsClear: document.getElementById('proposals-clear'),
    proposalsLoadMore: document.getElementById('proposals-load-more'),
    invoicesFilterStatus: document.getElementById('invoices-filter-status'),
    invoicesFilterCustomer: document.getElementById('invoices-filter-customer'),
    invoicesClear: document.getElementById('invoices-clear'),
    invoicesLoadMore: document.getElementById('invoices-load-more'),
    invoicesPaidToggle: document.getElementById('invoices-paid-toggle'),
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
    costRecurringSelect: document.getElementById('cost-is-recurring'),
    costRecurringFrequencyField: document.getElementById('cost-frequency-field'),
    costRecurringFrequencySelect: document.getElementById('cost-recurring-frequency'),
    costsRefresh: document.getElementById('costs-refresh'),
    jobForm: document.getElementById('job-form'),
    jobFormTitle: document.getElementById('job-form-title'),
    jobFormStatus: document.getElementById('job-form-status'),
    jobFormCancel: document.getElementById('job-form-cancel'),
    jobCustomerSelect: document.getElementById('job-customer-select'),
    jobPhotoUploadForm: document.getElementById('job-photo-upload-form'),
    jobPhotoUploadStatus: document.getElementById('job-photo-upload-status'),
    jobPhotoFilesInput: document.getElementById('job-photo-files'),
    jobPhotoJobSelect: document.getElementById('job-photo-job-select'),
    jobPhotosTable: document.getElementById('job-photos-table'),
    jobPhotosRefresh: document.getElementById('job-photos-refresh'),
    jobPhotosDownloadAll: document.getElementById('job-photos-download-all'),
    jobsRefresh: document.getElementById('jobs-refresh'),
    tasksTable: document.getElementById('tasks-table'),
    tasksRefresh: document.getElementById('tasks-refresh'),
    tasksFilterStatus: document.getElementById('tasks-filter-status'),
    tasksFilterStaff: document.getElementById('tasks-filter-staff'),
    tasksClear: document.getElementById('tasks-clear'),
    taskForm: document.getElementById('task-form'),
    taskFormTitle: document.getElementById('task-form-title'),
    taskFormStatus: document.getElementById('task-form-status'),
    taskFormCancel: document.getElementById('task-form-cancel'),
    taskStaffSelect: document.getElementById('task-staff-select'),
    taskJobSelect: document.getElementById('task-job-select'),
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
    proposalsTable: document.getElementById('proposals-table'),
    proposalForm: document.getElementById('proposal-form'),
    proposalFormTitle: document.getElementById('proposal-form-title'),
    proposalFormStatus: document.getElementById('proposal-form-status'),
    proposalFormCancel: document.getElementById('proposal-form-cancel'),
    proposalCustomerSelect: document.getElementById('proposal-customer-select'),
    proposalTypeSelect: document.getElementById('proposal-type-select'),
    proposalFormAnswers: document.getElementById('proposal-form-answers'),
    proposalTitle: document.getElementById('proposal-title'),
    proposalLineItemDescription: document.getElementById('proposal-line-item-description'),
    proposalsRefresh: document.getElementById('proposals-refresh'),
    invoicesTable: document.getElementById('invoices-table'),
    invoiceForm: document.getElementById('invoice-form'),
    invoiceFormTitle: document.getElementById('invoice-form-title'),
    invoiceFormStatus: document.getElementById('invoice-form-status'),
    invoiceFormCancel: document.getElementById('invoice-form-cancel'),
    invoiceCustomerSelect: document.getElementById('invoice-customer-select'),
    invoiceLineItems: document.getElementById('invoice-line-items'),
    invoiceAddLineItem: document.getElementById('invoice-add-line-item'),
    invoicesRefresh: document.getElementById('invoices-refresh'),
    portalProposals: document.getElementById('portal-proposals'),
    portalProposalsRefresh: document.getElementById('portal-proposals-refresh'),
    portalFormsTable: document.getElementById('portal-forms-table'),
    portalFormsRefresh: document.getElementById('portal-forms-refresh'),
    portalFormsNav: document.getElementById('portal-forms-nav'),
    portalFormsNotification: document.getElementById('portal-forms-notification'),
    portalFormPanel: document.getElementById('portal-form-panel'),
    portalCustomerForm: document.getElementById('portal-customer-form'),
    portalFormTitle: document.getElementById('portal-form-title'),
    portalFormSubtitle: document.getElementById('portal-form-subtitle'),
    portalFormFields: document.getElementById('portal-form-fields'),
    portalFormStatus: document.getElementById('portal-form-status'),
    portalFormSubmit: document.getElementById('portal-form-submit'),
    portalFormClose: document.getElementById('portal-form-close'),
    staffTrackingTable: document.getElementById('staff-tracking-table'),
    staffTrackingRefresh: document.getElementById('staff-tracking-refresh'),
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
let toastTimer = null;

const monthlyFinanceBoxDefaults = {
    revenue: true,
    costs: true,
    profit: true,
    tax: true,
    owed: true,
};

const dashboardTileDefaults = {
    revenue: true,
    costs: true,
    profit: true,
    jobs: true,
    subscriptions: true,
    potential_mrr: true,
    pipeline_value: true,
    open_opportunities: true,
};

const state = {
    view: 'dashboard',
    role: 'guest',
    user: null,
    customers: [],
    customerOptions: [],
    jobs: [],
    jobPhotos: [],
    costs: [],
    subscriptions: [],
    proposals: [],
    tasks: [],
    monthlyTasks: [],
    monthlyTasksSelectedMonth: null,
    staffTracking: [],
    subscriptionMonths: [],
    invoices: [],
    revenueOpportunities: [],
    opportunityBulkEdit: false,
    selectedOpportunityIds: new Set(),
    discoveredLeads: [],
    showingContactedLeads: false,
    leadDiscoveryRuns: [],
    staffUsers: [],
    monthlyFinance: [],
    monthlyFinanceSelectedMonth: null,
    monthlyFinanceBoxVisibility: { ...monthlyFinanceBoxDefaults },
    dashboardTileVisibility: { ...dashboardTileDefaults },
    mailSettings: null,
    invoiceSettings: null,
    proposalFormSettings: { types: [] },
    customerFormSettings: { types: [] },
    portalInvoices: [],
    portalProposals: [],
    portalJobs: [],
    portalSubscriptions: [],
    customerForms: [],
    customerFormTemplates: [],
    portalForms: [],
    currentPortalForm: null,
    currentCustomer: null,
    currentLead: null,
    filters: {
        customers: {
            search: '',
            archived: false,
        },
        jobs: {
            status: 'all',
            customer: 'all',
            archived: false,
        },
        subscriptions: {
            status: 'all',
            customer: 'all',
        },
        proposals: {
            status: 'all',
            customer: 'all',
        },
        invoices: {
            status: 'all',
            customer: 'all',
            paid: false,
        },
        tasks: {
            status: 'all',
            staff: 'all',
        },
        revenueOpportunities: { status: 'all', type: 'all' },
    },
    pagination: {
        customers: { page: 1, lastPage: 1 },
        jobs: { page: 1, lastPage: 1 },
        costs: { page: 1, lastPage: 1 },
        subscriptions: { page: 1, lastPage: 1 },
        proposals: { page: 1, lastPage: 1 },
        invoices: { page: 1, lastPage: 1 },
        tasks: { page: 1, lastPage: 1 },
    },
    editing: {
        customer: null,
        job: null,
        jobPhotoJobId: null,
        cost: null,
        subscription: null,
        proposal: null,
        proposalFormTypeIndex: null,
        customerFormTypeIndex: null,
        task: null,
        invoice: null,
        website: null,
        revenueOpportunity: null,
        opportunityFollowUp: null,
    },
    invoiceBillables: {
        jobsByCustomer: {},
        subscriptionsByCustomer: {},
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
    'revenue-opportunities': {
        title: 'Revenue Opportunities',
        subtitle: 'Grow recurring revenue, retain customers, and manage upsells.',
    },
    'lead-discovery': {
        title: 'Lead Discovery',
        subtitle: 'Find external businesses, audit their websites, and build a qualified lead pipeline.',
    },
    'lead-detail': {
        title: 'Lead Intelligence',
        subtitle: 'Business details, website issues, recommendations, and audit history.',
    },
    jobs: {
        title: 'Jobs',
        subtitle: 'Track one-off work and invoicing status.',
    },
    tasks: {
        title: 'Tasks',
        subtitle: 'Assigned work and completion tracking.',
    },
    subscriptions: {
        title: 'Subscriptions',
        subtitle: 'Recurring services and billing cadence.',
    },
    proposals: {
        title: 'Proposals',
        subtitle: 'Create and send customer proposals.',
    },
    costs: {
        title: 'Costs',
        subtitle: 'Track expenses and receipt uploads.',
    },
    'monthly-finance': {
        title: 'Monthly Finance',
        subtitle: 'Revenue, costs, profits, and tax by month.',
    },
    'monthly-tasks': {
        title: 'Monthly Tasks',
        subtitle: 'Completed tasks and logged hours by month.',
    },
    'staff-tracking': {
        title: 'Staff',
        subtitle: 'Track staff task completion and logged hours.',
    },
    invoices: {
        title: 'Invoices',
        subtitle: 'Create, send, and download invoices.',
    },
    admin: {
        title: 'Admin',
        subtitle: 'Brand settings and configuration.',
    },
    'proposal-form-edit': {
        title: 'Edit Proposal Form',
        subtitle: 'Manage the questions for one proposal type.',
    },
    'customer-form-edit': {
        title: 'Edit Customer Form',
        subtitle: 'Manage the questions customers complete in their portal.',
    },
    'customer-detail': {
        title: 'Customer overview',
        subtitle: 'Jobs, subscriptions, and websites for this customer.',
    },
    portal: {
        title: 'Customer Portal',
        subtitle: 'Review invoices and quick-login links.',
    },
    'portal-proposals': {
        title: 'Proposals',
        subtitle: 'Review, approve, decline, and download your proposals.',
    },
    'portal-forms': {
        title: 'Forms',
        subtitle: 'Complete requested forms and review previous submissions.',
    },
    'portal-support': {
        title: 'Support',
        subtitle: 'Contact support about an issue in your portal.',
    },
    'portal-admin': {
        title: 'Admin',
        subtitle: 'Manage your account details and password.',
    },
};

function setAuthState(isAuthenticated) {
    dom.body.dataset.auth = isAuthenticated ? 'authenticated' : 'guest';
}

function setRole(role) {
    state.role = role;
    dom.body.dataset.role = role;
    if (role !== 'customer') updatePortalFormsNotification([]);
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

function populatePortalProfileForm(user) {
    if (!dom.portalProfileForm || !user) return;

    const customerProfile = user.customer_profile || null;
    if (dom.portalProfileName) {
        dom.portalProfileName.value = customerProfile?.name || user.name || '';
    }
    if (dom.portalProfileEmail) {
        dom.portalProfileEmail.value = customerProfile?.email || user.email || '';
    }
    if (dom.portalProfilePhone) {
        dom.portalProfilePhone.value = customerProfile?.phone || '';
    }
    if (dom.portalProfileBillingAddress) {
        dom.portalProfileBillingAddress.value = customerProfile?.billing_address || '';
    }
    setFormStatus(dom.portalProfileStatus, '');
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
    if (['monthly-finance', 'proposal-form-edit', 'customer-form-edit', 'staff-tracking'].includes(view) && state.role !== 'admin') {
        return;
    }

    if (view === 'monthly-tasks' && state.role !== 'staff') {
        return;
    }

    const meta = viewMeta[view] || viewMeta.dashboard;
    state.view = view;
    const navView = view === 'customer-detail' ? 'customers' : (view === 'lead-detail' ? 'lead-discovery' : view);

    dom.views.forEach((section) => {
        section.classList.toggle('active', section.dataset.view === view);
    });

    dom.navItems.forEach((item) => {
        item.classList.toggle('active', item.dataset.view === navView);
    });

    if (dom.pageTitle) dom.pageTitle.textContent = meta.title;
    if (dom.pageSubtitle) {
        dom.pageSubtitle.textContent = view === 'admin' && state.role === 'staff'
            ? 'Manage your account details and password.'
            : meta.subtitle;
    }

    if (view !== 'monthly-finance') {
        setMonthlyFinanceSettingsOpen(false);
    }

    if (view !== 'dashboard') {
        setDashboardSettingsOpen(false);
    }

    if (view === 'customers') {
        loadCustomers();
    }
    if (view === 'jobs') {
        ensureCustomersLoaded().then(loadJobs);
    }
    if (view === 'revenue-opportunities') {
        ensureCustomersLoaded().then(() => {
            populateOpportunityCustomerSelect();
            loadRevenueOpportunities();
        });
    }
    if (view === 'lead-discovery') {
        loadLeadDiscoveryData();
    }
    if (view === 'lead-detail' && state.currentLead?.id) loadLeadDetail(state.currentLead.id);
    if (view === 'tasks') {
        Promise.all([loadStaffUsers(), ensureJobsLoaded()])
            .then(() => {
                populateTaskSelects();
                loadTasks();
            });
    }
    if (view === 'subscriptions') {
        ensureCustomersLoaded().then(loadSubscriptions);
    }
    if (view === 'proposals') {
        ensureCustomersLoaded()
            .then(loadAdminProposalForms)
            .then(loadProposals);
    }
    if (view === 'costs') {
        loadCosts();
    }
    if (view === 'monthly-finance' && state.role === 'admin') {
        loadMonthlyFinance();
    }
    if (view === 'monthly-tasks' && state.role === 'staff') {
        loadMonthlyTasks();
    }
    if (view === 'staff-tracking' && state.role === 'admin') {
        loadStaffTracking();
        loadStaffUsers();
    }
    if (view === 'invoices') {
        ensureCustomersLoaded().then(loadInvoices);
    }
    if (view === 'customer-detail' && state.currentCustomer?.id) {
        loadCustomerDetail(state.currentCustomer.id);
    }
    if (view === 'portal') {
        loadPortalInvoices();
        loadPortalJobs();
        loadPortalSubscriptions();
        loadPortalWebsites();
        loadPortalForms();
    }
    if (view === 'portal-proposals') {
        loadPortalProposals();
    }
    if (view === 'portal-forms') {
        loadPortalForms();
    }
    if (view === 'portal-admin') {
        populatePortalProfileForm(state.user);
    }
    if (view === 'portal-support') {
        setFormStatus(dom.portalSupportStatus, '');
    }
    if (view === 'admin') {
        populateProfileForm(state.user);
        if (state.role === 'admin') {
            loadAdminMailSettings();
            loadAdminInvoiceSettings();
            loadAdminProposalForms();
            loadAdminCustomerForms();
        }
    }
    if (view === 'proposal-form-edit' && state.role === 'admin') {
        if ((state.proposalFormSettings.types || []).length) {
            renderProposalFormEdit();
        } else {
            loadAdminProposalForms().then(renderProposalFormEdit);
        }
    }
    if (view === 'customer-form-edit' && state.role === 'admin') {
        if ((state.customerFormSettings.types || []).length) {
            renderCustomerFormEdit();
        } else {
            loadAdminCustomerForms().then(renderCustomerFormEdit);
        }
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

function formatEmailLink(value, fallback = '--') {
    const email = String(value ?? '').trim();
    if (!email) return escapeHtml(fallback);
    const safeEmail = escapeHtml(email);
    return `<a class="email-link" href="mailto:${safeEmail}">${safeEmail}</a>`;
}

function formatPhoneLink(value, fallback = '--') {
    const phone = String(value ?? '').trim();
    if (!phone) return escapeHtml(fallback);
    const dialNumber = phone.replace(/[^\d+]/g, '').replace(/(?!^)\+/g, '');
    if (!dialNumber) return escapeHtml(phone);
    return `<a class="phone-link" href="tel:${escapeHtml(dialNumber)}">${escapeHtml(phone)}</a>`;
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
    proposals: dom.proposalsLoadMore,
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

function setAuthMode(mode) {
    const isForgot = mode === 'forgot';
    const isReset = mode === 'reset';

    if (dom.loginIntro) dom.loginIntro.hidden = isForgot || isReset;
    if (dom.forgotPasswordIntro) dom.forgotPasswordIntro.hidden = !isForgot;
    if (dom.resetPasswordIntro) dom.resetPasswordIntro.hidden = !isReset;
    if (dom.loginForm) dom.loginForm.hidden = isForgot || isReset;
    if (dom.forgotPasswordForm) dom.forgotPasswordForm.hidden = !isForgot;
    if (dom.resetPasswordForm) dom.resetPasswordForm.hidden = !isReset;

    if (mode === 'login') {
        if (dom.loginError) dom.loginError.textContent = '';
        setFormStatus(dom.forgotPasswordStatus, '');
        setFormStatus(dom.resetPasswordStatus, '');
    }
}

function initializePasswordResetMode() {
    if (!dom.resetPasswordForm) return;

    const params = new URLSearchParams(window.location.search);
    const token = params.get('reset_token') || '';
    const email = params.get('email') || '';

    if (!token || !email) {
        setAuthMode('login');
        return;
    }

    const tokenInput = dom.resetPasswordForm.querySelector('input[name="token"]');
    const emailInput = dom.resetPasswordForm.querySelector('input[name="email"]');

    if (tokenInput) tokenInput.value = token;
    if (emailInput) emailInput.value = email;
    setAuthMode('reset');
}

function showToast(message, isError = false) {
    if (!dom.toast) return;

    dom.toast.textContent = message;
    dom.toast.classList.remove('toast-error', 'toast-success', 'show');
    dom.toast.classList.add(isError ? 'toast-error' : 'toast-success');

    // Force reflow so repeated toasts animate reliably.
    // eslint-disable-next-line no-unused-expressions
    dom.toast.offsetHeight;
    dom.toast.classList.add('show');

    if (toastTimer) {
        window.clearTimeout(toastTimer);
    }

    toastTimer = window.setTimeout(() => {
        if (!dom.toast) return;
        dom.toast.classList.remove('show');
    }, 3000);
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

function normalizeMonthlyFinanceBoxVisibility(value) {
    const normalized = { ...monthlyFinanceBoxDefaults };
    if (!value || typeof value !== 'object') {
        return normalized;
    }

    Object.keys(monthlyFinanceBoxDefaults).forEach((key) => {
        if (Object.prototype.hasOwnProperty.call(value, key)) {
            normalized[key] = Boolean(value[key]);
        }
    });

    return normalized;
}

function applyMonthlyFinanceBoxVisibility() {
    const visibility = normalizeMonthlyFinanceBoxVisibility(state.monthlyFinanceBoxVisibility);
    state.monthlyFinanceBoxVisibility = visibility;

    const cardMap = {
        revenue: dom.monthlyFinanceCardRevenue,
        costs: dom.monthlyFinanceCardCosts,
        profit: dom.monthlyFinanceCardProfit,
        tax: dom.monthlyFinanceCardTax,
        owed: dom.monthlyFinanceCardOwed,
    };

    const toggleMap = {
        revenue: dom.monthlyFinanceToggleRevenue,
        costs: dom.monthlyFinanceToggleCosts,
        profit: dom.monthlyFinanceToggleProfit,
        tax: dom.monthlyFinanceToggleTax,
        owed: dom.monthlyFinanceToggleOwed,
    };

    Object.keys(visibility).forEach((key) => {
        const isVisible = visibility[key] !== false;
        const card = cardMap[key];
        const toggle = toggleMap[key];
        if (card) {
            card.classList.toggle('hidden', !isVisible);
        }
        if (toggle) {
            toggle.checked = isVisible;
        }
    });
}

function setMonthlyFinanceSettingsOpen(isOpen) {
    if (!dom.monthlyFinanceSettingsPopover || !dom.monthlyFinanceSettingsToggle) return;
    dom.monthlyFinanceSettingsPopover.hidden = !isOpen;
    dom.monthlyFinanceSettingsToggle.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
}

function normalizeDashboardTileVisibility(value) {
    const normalized = { ...dashboardTileDefaults };
    if (!value || typeof value !== 'object') {
        return normalized;
    }

    Object.keys(dashboardTileDefaults).forEach((key) => {
        if (Object.prototype.hasOwnProperty.call(value, key)) {
            normalized[key] = Boolean(value[key]);
        }
    });

    return normalized;
}

function dashboardTileStorageKey() {
    return `dashboard_tile_preferences_${state.user?.id || 'guest'}`;
}

function readStoredDashboardTileVisibility() {
    try {
        const stored = JSON.parse(localStorage.getItem(dashboardTileStorageKey()) || 'null');
        if (!stored || typeof stored !== 'object' || !stored.tiles) return null;
        return {
            tiles: normalizeDashboardTileVisibility(stored.tiles),
            pending: stored.pending === true,
        };
    } catch (error) {
        return null;
    }
}

function storeDashboardTileVisibility(tiles, pending = false) {
    localStorage.setItem(dashboardTileStorageKey(), JSON.stringify({
        tiles: normalizeDashboardTileVisibility(tiles),
        pending,
    }));
}

function applyDashboardTileVisibility() {
    const visibility = normalizeDashboardTileVisibility(state.dashboardTileVisibility);
    state.dashboardTileVisibility = visibility;

    const cardMap = {
        revenue: dom.dashboardTileRevenue,
        costs: dom.dashboardTileCosts,
        profit: dom.dashboardTileProfit,
        jobs: dom.dashboardTileJobs,
        subscriptions: dom.dashboardTileSubscriptions,
        potential_mrr: dom.dashboardTilePotentialMrr,
        pipeline_value: dom.dashboardTilePipelineValue,
        open_opportunities: dom.dashboardTileOpenOpportunities,
    };

    const toggleMap = {
        revenue: dom.dashboardToggleRevenue,
        costs: dom.dashboardToggleCosts,
        profit: dom.dashboardToggleProfit,
        jobs: dom.dashboardToggleJobs,
        subscriptions: dom.dashboardToggleSubscriptions,
        potential_mrr: dom.dashboardTogglePotentialMrr,
        pipeline_value: dom.dashboardTogglePipelineValue,
        open_opportunities: dom.dashboardToggleOpenOpportunities,
    };

    Object.keys(visibility).forEach((key) => {
        const isVisible = visibility[key] !== false;
        const card = cardMap[key];
        const toggle = toggleMap[key];
        if (card) {
            card.classList.toggle('hidden', !isVisible);
        }
        if (toggle) {
            toggle.checked = isVisible;
        }
    });
}

function setDashboardSettingsOpen(isOpen) {
    if (!dom.dashboardSettingsPopover || !dom.dashboardSettingsToggle) return;
    dom.dashboardSettingsPopover.hidden = !isOpen;
    dom.dashboardSettingsToggle.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
}

async function saveDashboardTileVisibility() {
    storeDashboardTileVisibility(state.dashboardTileVisibility, true);
    try {
        const response = await api.put('/api/preferences', {
            dashboard_tiles: state.dashboardTileVisibility,
        });
        state.dashboardTileVisibility = normalizeDashboardTileVisibility(response?.data?.dashboard_tiles);
        storeDashboardTileVisibility(state.dashboardTileVisibility, false);
        applyDashboardTileVisibility();
    } catch (error) {
        showToast('Dashboard settings saved on this device. Account sync is temporarily unavailable.', true);
    }
}

async function saveMonthlyFinanceBoxVisibility() {
    try {
        const response = await api.put('/api/preferences', {
            monthly_finance_boxes: state.monthlyFinanceBoxVisibility,
        });
        state.monthlyFinanceBoxVisibility = normalizeMonthlyFinanceBoxVisibility(
            response?.data?.monthly_finance_boxes
        );
        applyMonthlyFinanceBoxVisibility();
    } catch (error) {
        showToast('Unable to save monthly finance settings.', true);
    }
}

async function loadPreferences() {
    const storedDashboardTiles = readStoredDashboardTileVisibility();
    try {
        const response = await api.get('/api/preferences');
        const theme = response.data?.theme || 'light';
        state.monthlyFinanceBoxVisibility = normalizeMonthlyFinanceBoxVisibility(
            response?.data?.monthly_finance_boxes
        );
        const serverDashboardTiles = normalizeDashboardTileVisibility(response?.data?.dashboard_tiles);
        state.dashboardTileVisibility = storedDashboardTiles?.pending
            ? storedDashboardTiles.tiles
            : serverDashboardTiles;
        storeDashboardTileVisibility(state.dashboardTileVisibility, storedDashboardTiles?.pending === true);
        localStorage.setItem(themeKey, theme);
        setTheme(theme);
        applyMonthlyFinanceBoxVisibility();
        applyDashboardTileVisibility();
    } catch (error) {
        applyStoredTheme();
        state.monthlyFinanceBoxVisibility = { ...monthlyFinanceBoxDefaults };
        state.dashboardTileVisibility = storedDashboardTiles?.tiles ?? { ...dashboardTileDefaults };
        applyMonthlyFinanceBoxVisibility();
        applyDashboardTileVisibility();
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

        const status = invoice.effective_status || invoice.status || 'draft';
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
    loadOpportunitySummary();
    if (state.role === 'staff') {
        const result = await api.get('/api/tasks/dashboard');
        const data = result?.data ?? {};

        if (dom.dashboardRevenue) dom.dashboardRevenue.textContent = String(data.pending_tasks ?? 0);
        if (dom.dashboardCosts) dom.dashboardCosts.textContent = String(data.completed_this_month ?? 0);
        if (dom.dashboardProfit) dom.dashboardProfit.textContent = `${Number(data.hours_this_month ?? 0).toFixed(2)}h`;
        if (statTargets.jobs) statTargets.jobs.textContent = String(data.overdue_tasks ?? 0);
        if (statTargets.subscriptions) statTargets.subscriptions.textContent = String(data.total_tasks ?? 0);

        const cards = document.querySelectorAll('[data-view="dashboard"] .panel-grid .card');
        const labels = ['Pending tasks', 'Completed this month', 'Hours this month', 'Overdue tasks', 'Total tasks'];
        const metas = ['Assigned to you', 'Completed by you', 'Logged by you', 'Past due and incomplete', 'All assigned tasks'];
        cards.forEach((card, index) => {
            const label = card.querySelector('.card-label');
            const meta = card.querySelector('.card-meta');
            if (label && labels[index]) label.textContent = labels[index];
            if (meta && metas[index]) meta.textContent = metas[index];
        });

        const chartCard = dom.dashboardProfitChart?.closest('.card');
        const invoiceCard = invoiceTables.dashboard?.closest('.card');
        if (chartCard) chartCard.style.display = 'none';
        if (invoiceCard) invoiceCard.style.display = 'none';
        updateSyncStatus('Connected');
        return;
    }

    const chartCard = dom.dashboardProfitChart?.closest('.card');
    const invoiceCard = invoiceTables.dashboard?.closest('.card');
    if (chartCard) chartCard.style.display = '';
    if (invoiceCard) invoiceCard.style.display = '';
    const cards = document.querySelectorAll('[data-view="dashboard"] .panel-grid .card');
    const labels = ['Revenue this month', 'Costs this month', 'Profit this month', 'Jobs', 'Subscriptions'];
    const metas = [
        'Completed jobs this month + paid subscriptions',
        'Total incurred costs this month',
        'Revenue this month minus costs this month',
        'Open or invoiced',
        'Recurring monthly',
    ];
    cards.forEach((card, index) => {
        const label = card.querySelector('.card-label');
        const meta = card.querySelector('.card-meta');
        if (label && labels[index]) label.textContent = labels[index];
        if (meta && metas[index]) meta.textContent = metas[index];
    });

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

function resetMonthlyFinanceCards() {
    if (dom.monthlyFinanceSelectedMonth) dom.monthlyFinanceSelectedMonth.textContent = '--';
    if (dom.monthlyFinanceRevenue) dom.monthlyFinanceRevenue.textContent = '--';
    if (dom.monthlyFinanceCosts) dom.monthlyFinanceCosts.textContent = '--';
    if (dom.monthlyFinanceProfit) dom.monthlyFinanceProfit.textContent = '--';
    if (dom.monthlyFinanceTax) dom.monthlyFinanceTax.textContent = '--';
    if (dom.monthlyFinanceOwed) dom.monthlyFinanceOwed.textContent = '--';
    if (dom.monthlyFinanceRevenueMeta) dom.monthlyFinanceRevenueMeta.textContent = 'Completed jobs + paid subscriptions';
    if (dom.monthlyFinanceCostsMeta) dom.monthlyFinanceCostsMeta.textContent = 'Incurred and recurring costs';
    if (dom.monthlyFinanceProfitMeta) dom.monthlyFinanceProfitMeta.textContent = 'Revenue minus costs';
    if (dom.monthlyFinanceTaxMeta) dom.monthlyFinanceTaxMeta.textContent = '20% of Profit';
    if (dom.monthlyFinanceOwedMeta) dom.monthlyFinanceOwedMeta.textContent = 'Overdue unpaid invoices';
}

function buildMonthlyFinanceTotalEntry() {
    if (!state.monthlyFinance.length) {
        return null;
    }

    const revenue = state.monthlyFinance.reduce(
        (sum, month) => sum + Number(month.revenue_total || 0),
        0
    );
    const costs = state.monthlyFinance.reduce(
        (sum, month) => sum + Number(month.costs_total || 0),
        0
    );
    const profit = revenue - costs;
    const tax = profit * 0.2;
    const owed = state.monthlyFinance.reduce(
        (sum, month) => sum + Number(month.owed_total || 0),
        0
    );

    const firstMonth = state.monthlyFinance[0];
    const lastMonth = state.monthlyFinance[state.monthlyFinance.length - 1];

    return {
        month_start: '__total__',
        month_end: lastMonth?.month_end || lastMonth?.month_start || null,
        range_start: firstMonth?.month_start || null,
        range_end: lastMonth?.month_end || lastMonth?.month_start || null,
        label: 'Total',
        revenue_total: revenue,
        costs_total: costs,
        profit_total: profit,
        tax_total: tax,
        owed_total: owed,
    };
}

function getMonthlyFinanceOptions() {
    const totalEntry = buildMonthlyFinanceTotalEntry();
    if (!totalEntry) {
        return [];
    }

    return [totalEntry, ...state.monthlyFinance];
}

function findSelectedMonthlyFinance() {
    const options = getMonthlyFinanceOptions();
    const selected = options.find(
        (item) => item.month_start === state.monthlyFinanceSelectedMonth
    );

    if (selected) {
        return selected;
    }

    return options[options.length - 1] || null;
}

function resolveMonthlyFinancePeriod(selectedItem) {
    if (!selectedItem) {
        return '';
    }

    if (selectedItem.month_start === '__total__') {
        const rangeStart = selectedItem.range_start;
        const rangeEnd = selectedItem.range_end;
        if (rangeStart && rangeEnd) {
            return `${formatDateWithYear(rangeStart)} to ${formatDateWithYear(rangeEnd)}`;
        }
        return '';
    }

    if (selectedItem.month_start && selectedItem.month_end) {
        return `${formatDateWithYear(selectedItem.month_start)} to ${formatDateWithYear(selectedItem.month_end)}`;
    }

    return selectedItem.label || '';
}

function renderMonthlyFinanceCards() {
    const selectedMonth = findSelectedMonthlyFinance();
    if (!selectedMonth) {
        resetMonthlyFinanceCards();
        return;
    }

    state.monthlyFinanceSelectedMonth = selectedMonth.month_start;
    const periodText = resolveMonthlyFinancePeriod(selectedMonth);

    if (dom.monthlyFinanceSelectedMonth) {
        const selectionLabel = selectedMonth.label || formatMonth(selectedMonth.month_start);
        dom.monthlyFinanceSelectedMonth.textContent = periodText
            ? `Selected: ${selectionLabel} (${periodText})`
            : `Selected: ${selectionLabel}`;
    }
    if (dom.monthlyFinanceRevenue) {
        dom.monthlyFinanceRevenue.textContent = formatCurrency(Number(selectedMonth.revenue_total || 0));
    }
    if (dom.monthlyFinanceCosts) {
        dom.monthlyFinanceCosts.textContent = formatCurrency(Number(selectedMonth.costs_total || 0));
    }
    if (dom.monthlyFinanceProfit) {
        dom.monthlyFinanceProfit.textContent = formatCurrency(Number(selectedMonth.profit_total || 0));
    }
    if (dom.monthlyFinanceTax) {
        dom.monthlyFinanceTax.textContent = formatCurrency(Number(selectedMonth.tax_total || 0));
    }
    if (dom.monthlyFinanceOwed) {
        dom.monthlyFinanceOwed.textContent = formatCurrency(Number(selectedMonth.owed_total || 0));
    }

    const suffix = periodText ? ` • ${periodText}` : '';
    if (dom.monthlyFinanceRevenueMeta) {
        dom.monthlyFinanceRevenueMeta.textContent = `Completed jobs + paid subscriptions${suffix}`;
    }
    if (dom.monthlyFinanceCostsMeta) {
        dom.monthlyFinanceCostsMeta.textContent = `Incurred and recurring costs${suffix}`;
    }
    if (dom.monthlyFinanceProfitMeta) {
        dom.monthlyFinanceProfitMeta.textContent = `Revenue minus costs${suffix}`;
    }
    if (dom.monthlyFinanceTaxMeta) {
        dom.monthlyFinanceTaxMeta.textContent = `20% of Profit${suffix}`;
    }
    if (dom.monthlyFinanceOwedMeta) {
        dom.monthlyFinanceOwedMeta.textContent = `Overdue unpaid invoices${suffix}`;
    }
}

function renderMonthlyFinanceMonths() {
    if (!dom.monthlyFinanceMonths) return;

    dom.monthlyFinanceMonths.innerHTML = '';

    const options = getMonthlyFinanceOptions();
    if (!options.length) {
        const emptyState = document.createElement('div');
        emptyState.className = 'monthly-finance-empty';
        emptyState.textContent = 'No monthly data yet.';
        dom.monthlyFinanceMonths.appendChild(emptyState);
        return;
    }

    options.forEach((month) => {
        const button = document.createElement('button');
        button.type = 'button';
        button.className = 'monthly-finance-month';
        button.dataset.monthStart = month.month_start;
        button.textContent = month.label || formatMonth(month.month_start);
        if (month.month_start === state.monthlyFinanceSelectedMonth) {
            button.classList.add('active');
        }
        dom.monthlyFinanceMonths.appendChild(button);
    });
}

async function loadMonthlyFinance() {
    if (state.role !== 'admin' || !dom.monthlyFinanceMonths) return;

    dom.monthlyFinanceMonths.innerHTML = '<div class="monthly-finance-empty">Loading months...</div>';
    if (dom.monthlyFinanceSelectedMonth) {
        dom.monthlyFinanceSelectedMonth.textContent = 'Selected: Loading...';
    }
    if (dom.monthlyFinanceRevenue) dom.monthlyFinanceRevenue.textContent = '--';
    if (dom.monthlyFinanceCosts) dom.monthlyFinanceCosts.textContent = '--';
    if (dom.monthlyFinanceProfit) dom.monthlyFinanceProfit.textContent = '--';
    if (dom.monthlyFinanceTax) dom.monthlyFinanceTax.textContent = '--';
    if (dom.monthlyFinanceOwed) dom.monthlyFinanceOwed.textContent = '--';

    try {
        const response = await api.get('/api/admin/stats/monthly-finance');
        const months = Array.isArray(response?.data?.months) ? response.data.months : [];
        const apiSelectedMonth = String(response?.data?.selected_month || '');

        state.monthlyFinance = months;

        const options = getMonthlyFinanceOptions();
        const hasCurrentSelection = options.some(
            (item) => item.month_start === state.monthlyFinanceSelectedMonth
        );

        if (!hasCurrentSelection) {
            const hasApiSelection = options.some((item) => item.month_start === apiSelectedMonth);
            state.monthlyFinanceSelectedMonth = hasApiSelection
                ? apiSelectedMonth
                : (options[options.length - 1]?.month_start || null);
        }

        renderMonthlyFinanceMonths();
        renderMonthlyFinanceCards();
    } catch (error) {
        state.monthlyFinance = [];
        state.monthlyFinanceSelectedMonth = null;
        renderMonthlyFinanceMonths();
        resetMonthlyFinanceCards();
    }
}

function findSelectedMonthlyTasks() {
    const selected = state.monthlyTasks.find((item) => item.month_start === state.monthlyTasksSelectedMonth);
    return selected || state.monthlyTasks[state.monthlyTasks.length - 2] || state.monthlyTasks[state.monthlyTasks.length - 1] || null;
}

function renderMonthlyTaskCards() {
    const selected = findSelectedMonthlyTasks();
    if (!selected) {
        if (dom.monthlyTasksSelectedMonth) dom.monthlyTasksSelectedMonth.textContent = '--';
        if (dom.monthlyTasksCompleted) dom.monthlyTasksCompleted.textContent = '--';
        if (dom.monthlyTasksHours) dom.monthlyTasksHours.textContent = '--';
        if (dom.monthlyTasksTaskChange) dom.monthlyTasksTaskChange.textContent = '--';
        if (dom.monthlyTasksHourChange) dom.monthlyTasksHourChange.textContent = '--';
        return;
    }

    state.monthlyTasksSelectedMonth = selected.month_start;
    const isTotal = selected.month_start === 'total';
    if (dom.monthlyTasksSelectedMonth) {
        dom.monthlyTasksSelectedMonth.textContent = `Selected: ${selected.label}`;
    }
    if (dom.monthlyTasksCompleted) dom.monthlyTasksCompleted.textContent = String(selected.completed_tasks || 0);
    if (dom.monthlyTasksHours) dom.monthlyTasksHours.textContent = `${Number(selected.hours_total || 0).toFixed(2)}h`;
    if (dom.monthlyTasksTaskChange) {
        dom.monthlyTasksTaskChange.textContent = selected.tasks_change_percent === null || selected.tasks_change_percent === undefined
            ? '--'
            : `${Number(selected.tasks_change_percent).toFixed(1)}%`;
    }
    if (dom.monthlyTasksHourChange) {
        dom.monthlyTasksHourChange.textContent = selected.hours_change_percent === null || selected.hours_change_percent === undefined
            ? '--'
            : `${Number(selected.hours_change_percent).toFixed(1)}%`;
    }
    if (dom.monthlyTasksCardTaskChange) dom.monthlyTasksCardTaskChange.style.display = isTotal ? 'none' : '';
    if (dom.monthlyTasksCardHourChange) dom.monthlyTasksCardHourChange.style.display = isTotal ? 'none' : '';
}

function renderMonthlyTaskMonths() {
    if (!dom.monthlyTasksMonths) return;
    dom.monthlyTasksMonths.innerHTML = '';

    if (!state.monthlyTasks.length) {
        dom.monthlyTasksMonths.innerHTML = '<div class="monthly-finance-empty">No monthly task data yet.</div>';
        return;
    }

    state.monthlyTasks.forEach((month) => {
        const button = document.createElement('button');
        button.type = 'button';
        button.className = 'monthly-finance-month';
        button.dataset.monthStart = month.month_start;
        button.textContent = month.label || formatMonth(month.month_start);
        if (month.month_start === state.monthlyTasksSelectedMonth) {
            button.classList.add('active');
        }
        dom.monthlyTasksMonths.appendChild(button);
    });
}

async function loadMonthlyTasks() {
    if (!dom.monthlyTasksMonths) return;
    dom.monthlyTasksMonths.innerHTML = '<div class="monthly-finance-empty">Loading months...</div>';

    try {
        const response = await api.get('/api/tasks/monthly');
        state.monthlyTasks = response?.data?.months ?? [];
        const selected = response?.data?.selected_month || state.monthlyTasks[state.monthlyTasks.length - 2]?.month_start || null;
        if (!state.monthlyTasksSelectedMonth) {
            state.monthlyTasksSelectedMonth = selected;
        }
        renderMonthlyTaskMonths();
        renderMonthlyTaskCards();
    } catch (error) {
        state.monthlyTasks = [];
        renderMonthlyTaskMonths();
        renderMonthlyTaskCards();
    }
}

async function loadStaffTracking() {
    if (!dom.staffTrackingTable || state.role !== 'admin') return;
    resetTable(dom.staffTrackingTable);
    const loading = document.createElement('div');
    loading.className = 'table-row table-empty staff-tracking';
    loading.innerHTML = '<span>Loading staff tracking...</span><span></span><span></span><span></span><span></span><span></span>';
    dom.staffTrackingTable.appendChild(loading);

    try {
        const response = await api.get('/api/admin/staff-task-summary');
        state.staffTracking = response?.data?.data ?? [];
        renderStaffTracking();
    } catch (error) {
        resetTable(dom.staffTrackingTable);
        const row = document.createElement('div');
        row.className = 'table-row table-empty staff-tracking';
        row.innerHTML = '<span>Unable to load staff tracking.</span><span></span><span></span><span></span><span></span><span></span>';
        dom.staffTrackingTable.appendChild(row);
    }
}

function renderStaffTracking() {
    if (!dom.staffTrackingTable) return;
    resetTable(dom.staffTrackingTable);

    if (!state.staffTracking.length) {
        const row = document.createElement('div');
        row.className = 'table-row table-empty staff-tracking';
        row.innerHTML = '<span>No staff users yet.</span><span></span><span></span><span></span><span></span><span></span>';
        dom.staffTrackingTable.appendChild(row);
        return;
    }

    state.staffTracking.forEach((staff) => {
        const row = document.createElement('div');
        row.className = 'table-row staff-tracking';
        row.innerHTML = `
            <span>${staff.name ? escapeHtml(staff.name) : formatEmailLink(staff.email, '')}</span>
            <span>${escapeHtml(String(staff.pending_tasks || 0))}</span>
            <span>${escapeHtml(String(staff.completed_this_month || 0))}</span>
            <span>${Number(staff.hours_this_month || 0).toFixed(2)}h</span>
            <span>${escapeHtml(String(staff.total_completed || 0))}</span>
            <span>${Number(staff.total_hours || 0).toFixed(2)}h</span>
        `;
        dom.staffTrackingTable.appendChild(row);
    });
}

async function loadPortalInvoices() {
    try {
        const invoices = await api.get('/api/portal/invoices?per_page=6');
        const items = invoices?.data?.data ?? [];
        state.portalInvoices = items;
        renderPortalInvoices(items, 'No invoices available.');
    } catch (error) {
        renderPortalInvoices([], 'Unable to load invoices.');
    }
}

function renderPortalInvoices(invoices = [], emptyMessage = 'No invoices available.') {
    if (!invoiceTables.portal) return;
    resetTable(invoiceTables.portal);

    if (!invoices.length) {
        const emptyRow = document.createElement('div');
        emptyRow.className = 'table-row table-empty portal-invoices';
        emptyRow.innerHTML = `<span>${emptyMessage}</span><span></span><span></span><span></span><span></span>`;
        invoiceTables.portal.appendChild(emptyRow);
        return;
    }

    invoices.forEach((invoice) => {
        const row = document.createElement('div');
        row.className = 'table-row portal-invoices';

        const isPaid = invoice.status === 'paid';
        const statusLabel = isPaid ? 'paid' : 'unpaid';
        const nextStatus = isPaid ? 'unpaid' : 'paid';
        const actionLabel = isPaid ? 'Mark unpaid' : 'Mark paid';
        const pillClass = isPaid ? 'pill success' : 'pill outline';

        row.innerHTML = `
            <span>#${escapeHtml(invoice.invoice_number)}</span>
            <span class="${pillClass}">${escapeHtml(statusLabel)}</span>
            <span>${formatCurrency(Number(invoice.total))}</span>
            <span>${formatDate(invoice.due_date)}</span>
            <div class="row-actions">
                <button type="button" class="btn btn-outline btn-small" data-action="portal-toggle-payment" data-id="${invoice.id}" data-next-status="${nextStatus}">${actionLabel}</button>
                <button type="button" class="btn btn-outline btn-small" data-action="portal-download-invoice" data-id="${invoice.id}">Download</button>
            </div>
        `;

        invoiceTables.portal.appendChild(row);
    });
}

function renderPortalJobs(jobs = []) {
    if (!dom.portalJobs) return;
    resetTable(dom.portalJobs);

    if (!jobs.length) {
        const emptyRow = document.createElement('div');
        emptyRow.className = 'table-row table-empty portal-jobs';
        emptyRow.innerHTML = '<span>No jobs yet.</span><span></span><span></span><span></span>';
        dom.portalJobs.appendChild(emptyRow);
        return;
    }

    jobs.forEach((job) => {
        const row = document.createElement('div');
        row.className = 'table-row portal-jobs';
        row.innerHTML = `
            <span>${escapeHtml(truncate(job.description, 56))}</span>
            <span>${formatCurrency(Number(job.cost))}</span>
            <span>${escapeHtml(job.status)}</span>
            <span>${formatDate(job.completed_at)}</span>
        `;
        dom.portalJobs.appendChild(row);
    });
}

async function loadPortalJobs() {
    try {
        const response = await api.get('/api/portal/jobs?per_page=100');
        const items = response?.data?.data ?? [];
        state.portalJobs = items;
        renderPortalJobs(items);
    } catch (error) {
        renderPortalJobs([]);
        if (dom.portalJobs) {
            resetTable(dom.portalJobs);
            const emptyRow = document.createElement('div');
            emptyRow.className = 'table-row table-empty portal-jobs';
            emptyRow.innerHTML = '<span>Unable to load jobs.</span><span></span><span></span><span></span>';
            dom.portalJobs.appendChild(emptyRow);
        }
    }
}

function renderPortalSubscriptions(subscriptions = []) {
    if (!dom.portalSubscriptions) return;
    resetTable(dom.portalSubscriptions);

    if (!subscriptions.length) {
        const emptyRow = document.createElement('div');
        emptyRow.className = 'table-row table-empty portal-subscriptions';
        emptyRow.innerHTML = '<span>No subscriptions yet.</span><span></span><span></span><span></span>';
        dom.portalSubscriptions.appendChild(emptyRow);
        return;
    }

    subscriptions.forEach((subscription) => {
        const row = document.createElement('div');
        row.className = 'table-row portal-subscriptions';
        row.innerHTML = `
            <span>${escapeHtml(truncate(subscription.description, 42))}</span>
            <span>${formatCurrency(Number(subscription.monthly_cost))}</span>
            <span>${escapeHtml(subscription.status)}</span>
            <span>${formatDate(subscription.next_invoice_date)}</span>
        `;
        dom.portalSubscriptions.appendChild(row);
    });
}

async function loadPortalSubscriptions() {
    try {
        const response = await api.get('/api/portal/subscriptions?status=all&per_page=100');
        const items = response?.data?.data ?? [];
        state.portalSubscriptions = items;
        renderPortalSubscriptions(items);
    } catch (error) {
        renderPortalSubscriptions([]);
        if (dom.portalSubscriptions) {
            resetTable(dom.portalSubscriptions);
            const emptyRow = document.createElement('div');
            emptyRow.className = 'table-row table-empty portal-subscriptions';
            emptyRow.innerHTML = '<span>Unable to load subscriptions.</span><span></span><span></span><span></span>';
            dom.portalSubscriptions.appendChild(emptyRow);
        }
    }
}

async function loadPortalWebsites() {
    if (!dom.portalWebsites) return;
    dom.portalWebsites.innerHTML = '';

    try {
        const response = await api.get('/api/portal/websites?per_page=20');
        const websites = response?.data?.data ?? [];
        const uniqueWebsites = [];
        const seenWebsiteIds = new Set();

        websites.forEach((website) => {
            const id = Number(website?.id);
            if (!id || seenWebsiteIds.has(id)) {
                return;
            }
            seenWebsiteIds.add(id);
            uniqueWebsites.push(website);
        });

        if (!uniqueWebsites.length) {
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

        uniqueWebsites.forEach((website) => {
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
        populatePortalProfileForm(user);

        await Promise.all([loadPreferences(), loadBrand()]);

        if (role === 'customer') {
            setActiveView('portal');
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
        state.user = response.data.user;
        setToken(response.data.token);
        const role = resolveRole(response.data.user);
        setRole(role);
        if (dom.userName) dom.userName.textContent = response.data.user?.name || 'User';
        if (dom.userRole) dom.userRole.textContent = role;
        populateProfileForm(response.data.user);
        populatePortalProfileForm(response.data.user);
        await Promise.all([loadPreferences(), loadBrand()]);
        if (role === 'customer') {
            setActiveView('portal');
        } else {
            setActiveView('dashboard');
            loadStaffStats();
        }
    } catch (error) {
        if (dom.loginError) {
            dom.loginError.textContent = 'Invalid credentials. Please try again.';
            dom.loginError.style.color = '#ef4444';
        }
    } finally {
        if (submitButton) submitButton.disabled = false;
    }
}

async function handleForgotPassword(event) {
    event.preventDefault();
    if (!dom.forgotPasswordForm) return;

    const submitButton = dom.forgotPasswordForm.querySelector('button[type="submit"]');
    const formData = new FormData(dom.forgotPasswordForm);
    const payload = {
        email: String(formData.get('email') || '').trim(),
    };

    if (!payload.email) {
        setFormStatus(dom.forgotPasswordStatus, 'Email is required.', true);
        return;
    }

    if (submitButton) submitButton.disabled = true;
    setFormStatus(dom.forgotPasswordStatus, '');

    try {
        const response = await api.post('/api/auth/forgot-password', payload);
        setFormStatus(dom.forgotPasswordStatus, response.data?.message || 'If a customer portal account exists for that email, a password reset link has been sent.');
        dom.forgotPasswordForm.reset();
    } catch (error) {
        setFormStatus(dom.forgotPasswordStatus, getErrorMessage(error, 'Unable to request password reset.'), true);
    } finally {
        if (submitButton) submitButton.disabled = false;
    }
}

async function handleResetPassword(event) {
    event.preventDefault();
    if (!dom.resetPasswordForm) return;

    const submitButton = dom.resetPasswordForm.querySelector('button[type="submit"]');
    const formData = new FormData(dom.resetPasswordForm);
    const payload = {
        email: String(formData.get('email') || '').trim(),
        token: String(formData.get('token') || ''),
        password: String(formData.get('password') || ''),
        password_confirmation: String(formData.get('password_confirmation') || ''),
    };

    if (!payload.email || !payload.token || !payload.password || !payload.password_confirmation) {
        setFormStatus(dom.resetPasswordStatus, 'All fields are required.', true);
        return;
    }

    if (payload.password !== payload.password_confirmation) {
        setFormStatus(dom.resetPasswordStatus, 'Passwords do not match.', true);
        return;
    }

    if (submitButton) submitButton.disabled = true;
    setFormStatus(dom.resetPasswordStatus, '');

    try {
        const response = await api.post('/api/auth/reset-password', payload);
        dom.resetPasswordForm.reset();
        window.history.replaceState({}, document.title, window.location.pathname);
        setAuthMode('login');
        if (dom.loginError) {
            dom.loginError.textContent = response.data?.message || 'Your password has been reset. You can now sign in.';
            dom.loginError.style.color = '';
        }
    } catch (error) {
        setFormStatus(dom.resetPasswordStatus, getErrorMessage(error, 'Unable to reset password.'), true);
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
        showToast('Settings saved');
    } catch (error) {
        setFormStatus(dom.profileFormStatus, 'Unable to update profile.', true);
    }
}

async function handlePortalProfileSubmit(event) {
    event.preventDefault();
    if (!dom.portalProfileForm) return;

    const formData = new FormData(dom.portalProfileForm);
    const payload = {
        name: String(formData.get('name') || '').trim(),
        email: String(formData.get('email') || '').trim(),
        phone: String(formData.get('phone') || '').trim() || null,
        billing_address: String(formData.get('billing_address') || '').trim(),
    };

    if (!payload.name || !payload.email || !payload.billing_address) {
        setFormStatus(dom.portalProfileStatus, 'Name, email, and billing address are required.', true);
        return;
    }

    try {
        const response = await api.put('/api/account/profile', payload);
        const user = response?.data?.user ?? null;
        if (user) {
            state.user = user;
            if (dom.userName) dom.userName.textContent = user?.name || 'User';
            populatePortalProfileForm(user);
            populateProfileForm(user);
        }
        setFormStatus(dom.portalProfileStatus, 'Details updated.');
        showToast('Settings saved');
    } catch (error) {
        setFormStatus(dom.portalProfileStatus, getErrorMessage(error, 'Unable to update details.'), true);
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
        showToast('Settings saved');
    } catch (error) {
        setFormStatus(dom.passwordFormStatus, 'Unable to update password.', true);
    }
}

async function handlePortalPasswordSubmit(event) {
    event.preventDefault();
    if (!dom.portalPasswordForm) return;

    const formData = new FormData(dom.portalPasswordForm);
    const payload = {
        current_password: String(formData.get('current_password') || ''),
        password: String(formData.get('password') || ''),
        password_confirmation: String(formData.get('password_confirmation') || ''),
    };

    if (!payload.current_password || !payload.password || !payload.password_confirmation) {
        setFormStatus(dom.portalPasswordStatus, 'All password fields are required.', true);
        return;
    }
    if (payload.password !== payload.password_confirmation) {
        setFormStatus(dom.portalPasswordStatus, 'New passwords do not match.', true);
        return;
    }

    try {
        await api.put('/api/account/password', payload);
        setFormStatus(dom.portalPasswordStatus, 'Password updated.');
        dom.portalPasswordForm.reset();
        showToast('Settings saved');
    } catch (error) {
        setFormStatus(dom.portalPasswordStatus, getErrorMessage(error, 'Unable to update password.'), true);
    }
}

async function handlePortalSupportSubmit(event) {
    event.preventDefault();
    if (!dom.portalSupportForm) return;

    const formData = new FormData(dom.portalSupportForm);
    const problem = String(formData.get('problem') || '').trim();
    const message = String(formData.get('message') || '').trim();

    if (!problem || !message) {
        setFormStatus(dom.portalSupportStatus, 'Please fill in both support fields.', true);
        return;
    }

    try {
        await api.post('/api/portal/support', formData, {
            headers: { 'Content-Type': 'multipart/form-data' },
        });

        setFormStatus(dom.portalSupportStatus, 'Support request sent.');
        showToast('Support request sent');
        dom.portalSupportForm.reset();
    } catch (error) {
        const messageText = getErrorMessage(error, 'Unable to send support request.');
        setFormStatus(dom.portalSupportStatus, messageText, true);
        showToast('Unable to send support request', true);
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
        showToast('Settings saved');
    } catch (error) {
        if (dom.logoUploadStatus) {
            dom.logoUploadStatus.textContent = 'Upload failed. Please try again.';
        }
    }
}

function applyAdminMailSettings(payload) {
    if (!dom.smtp2goEnabled || !dom.smtp2goApiKeyMask || !dom.smtp2goApiKey) return;

    const enabled = Boolean(payload?.smtp2go_enabled);
    dom.smtp2goEnabled.value = enabled ? '1' : '0';
    dom.smtp2goApiKey.value = '';

    if (payload?.smtp2go_api_key_set) {
        const masked = payload?.smtp2go_api_key_masked || 'configured';
        dom.smtp2goApiKeyMask.textContent = `Stored API key: ${masked}`;
    } else {
        dom.smtp2goApiKeyMask.textContent = 'No API key saved yet.';
    }
}

function applyAdminInvoiceSettings(payload) {
    if (!dom.invoiceAccountName || !dom.invoiceSortCode || !dom.invoiceAccountNumber) return;

    dom.invoiceAccountName.value = payload?.account_name || '';
    dom.invoiceSortCode.value = payload?.sort_code || '';
    dom.invoiceAccountNumber.value = payload?.account_number || '';
}

async function loadAdminInvoiceSettings() {
    if (!dom.invoiceSettingsForm || state.role !== 'admin') return;
    setFormStatus(dom.invoiceSettingsStatus, '');

    try {
        const response = await api.get('/api/admin/invoice-settings');
        state.invoiceSettings = response?.data?.data ?? null;
        applyAdminInvoiceSettings(state.invoiceSettings);
    } catch (error) {
        setFormStatus(dom.invoiceSettingsStatus, 'Unable to load invoice settings.', true);
    }
}

async function loadAdminMailSettings() {
    if (!dom.smtp2goSettingsForm || state.role !== 'admin') return;
    setFormStatus(dom.smtp2goSettingsStatus, '');

    try {
        const response = await api.get('/api/admin/mail-settings');
        state.mailSettings = response?.data?.data ?? null;
        applyAdminMailSettings(state.mailSettings);
    } catch (error) {
        setFormStatus(dom.smtp2goSettingsStatus, 'Unable to load mail settings.', true);
    }
}

async function handleSmtp2goSettingsSubmit(event) {
    event.preventDefault();
    if (!dom.smtp2goSettingsForm || !dom.smtp2goEnabled || state.role !== 'admin') return;

    const formData = new FormData(dom.smtp2goSettingsForm);
    const enabled = String(formData.get('smtp2go_enabled') || '0') === '1';
    const apiKey = String(formData.get('smtp2go_api_key') || '').trim();

    if (enabled && !apiKey && !state.mailSettings?.smtp2go_api_key_set) {
        setFormStatus(dom.smtp2goSettingsStatus, 'API key is required when SMTP2GO is enabled.', true);
        return;
    }

    const payload = {
        smtp2go_enabled: enabled,
    };

    if (apiKey) {
        payload.smtp2go_api_key = apiKey;
    }

    try {
        const response = await api.put('/api/admin/mail-settings', payload);
        state.mailSettings = response?.data?.data ?? null;
        applyAdminMailSettings(state.mailSettings);
        setFormStatus(dom.smtp2goSettingsStatus, 'Mail settings updated.');
        showToast('Settings saved');
    } catch (error) {
        setFormStatus(dom.smtp2goSettingsStatus, getErrorMessage(error, 'Unable to update mail settings.'), true);
    }
}

async function handleInvoiceSettingsSubmit(event) {
    event.preventDefault();
    if (!dom.invoiceSettingsForm || state.role !== 'admin') return;

    const formData = new FormData(dom.invoiceSettingsForm);
    const accountName = String(formData.get('account_name') || '').trim();
    const sortCode = String(formData.get('sort_code') || '').trim();
    const accountNumber = String(formData.get('account_number') || '').trim();

    if (!accountName || !sortCode || !accountNumber) {
        setFormStatus(dom.invoiceSettingsStatus, 'All payment details are required.', true);
        return;
    }

    try {
        const response = await api.put('/api/admin/invoice-settings', {
            account_name: accountName,
            sort_code: sortCode,
            account_number: accountNumber,
        });
        state.invoiceSettings = response?.data?.data ?? null;
        applyAdminInvoiceSettings(state.invoiceSettings);
        setFormStatus(dom.invoiceSettingsStatus, 'Invoice settings updated.');
        showToast('Settings saved');
    } catch (error) {
        setFormStatus(dom.invoiceSettingsStatus, getErrorMessage(error, 'Unable to update invoice settings.'), true);
    }
}

function renderProposalTypeOptions() {
    if (!dom.proposalTypeSelect) return;

    const currentValue = dom.proposalTypeSelect.value;
    dom.proposalTypeSelect.innerHTML = '<option value="" selected disabled>Select proposal type</option>';

    (state.proposalFormSettings.types || []).forEach((type) => {
        const option = document.createElement('option');
        option.value = type.slug;
        option.textContent = type.label;
        dom.proposalTypeSelect.appendChild(option);
    });

    if (currentValue && [...dom.proposalTypeSelect.options].some((option) => option.value === currentValue)) {
        dom.proposalTypeSelect.value = currentValue;
    }

    renderProposalFormAnswers();
}

function getSelectedProposalType() {
    const slug = dom.proposalTypeSelect?.value || '';
    return (state.proposalFormSettings.types || []).find((type) => type.slug === slug) || null;
}

function renderProposalFormAnswers(existingAnswers = null) {
    if (!dom.proposalFormAnswers) return;

    const selectedType = getSelectedProposalType();
    const answersByKey = {};
    (existingAnswers || []).forEach((answer) => {
        answersByKey[answer.key] = answer.value;
    });

    dom.proposalFormAnswers.innerHTML = '';

    if (!selectedType) {
        dom.proposalFormAnswers.innerHTML = '<div class="form-hint">Select a proposal type to show its questions.</div>';
        return;
    }

    (selectedType.questions || []).forEach((question) => {
        const label = document.createElement('label');
        label.className = 'field';
        const requiredText = question.required ? ' *' : '';
        const value = answersByKey[question.key] ?? '';

        if (question.type === 'textarea') {
            label.innerHTML = `
                <span>${escapeHtml(question.label)}${requiredText}</span>
                <textarea name="form_answer_${escapeHtml(question.key)}" rows="3" ${question.required ? 'required' : ''}>${escapeHtml(String(value || ''))}</textarea>
            `;
        } else if (question.type === 'checkbox') {
            label.innerHTML = `
                <span>${escapeHtml(question.label)}</span>
                <select name="form_answer_${escapeHtml(question.key)}">
                    <option value="0"${value ? '' : ' selected'}>No</option>
                    <option value="1"${value ? ' selected' : ''}>Yes</option>
                </select>
            `;
        } else if (question.type === 'select') {
            const options = (question.options || []).map((option) => `<option value="${escapeHtml(option)}"${String(value) === String(option) ? ' selected' : ''}>${escapeHtml(option)}</option>`).join('');
            label.innerHTML = `
                <span>${escapeHtml(question.label)}${requiredText}</span>
                <select name="form_answer_${escapeHtml(question.key)}" ${question.required ? 'required' : ''}>
                    <option value="">Select</option>
                    ${options}
                </select>
            `;
        } else {
            const inputType = ['number', 'date'].includes(question.type) ? question.type : 'text';
            label.innerHTML = `
                <span>${escapeHtml(question.label)}${requiredText}</span>
                <input type="${inputType}" name="form_answer_${escapeHtml(question.key)}" value="${escapeHtml(String(value || ''))}" ${question.required ? 'required' : ''}>
            `;
        }

        dom.proposalFormAnswers.appendChild(label);
    });
}

async function loadAdminProposalForms() {
    if (!dom.proposalTypeSelect && !dom.proposalFormsEditor) return;

    try {
        const url = state.role === 'admin' ? '/api/admin/proposal-forms' : '/api/proposal-forms';
        const response = await api.get(url);
        state.proposalFormSettings = response?.data?.data ?? { types: [] };
        renderProposalTypeOptions();
        renderProposalFormsEditor();
    } catch (error) {
        if (dom.proposalFormsSettingsStatus && state.role === 'admin') {
            setFormStatus(dom.proposalFormsSettingsStatus, 'Unable to load proposal forms.', true);
        }
    }
}

function renderProposalFormsEditor() {
    if (!dom.proposalFormsEditor || state.role !== 'admin') return;

    dom.proposalFormsEditor.innerHTML = '';

    if (!(state.proposalFormSettings.types || []).length) {
        dom.proposalFormsEditor.innerHTML = '<div class="form-hint">No proposal forms yet. Click Add type to create one.</div>';
        return;
    }

    (state.proposalFormSettings.types || []).forEach((type, typeIndex) => {
        const row = document.createElement('button');
        row.type = 'button';
        row.className = 'btn btn-outline form-editor-list-item';
        row.dataset.action = 'edit-proposal-form-type';
        row.dataset.typeIndex = String(typeIndex);
        row.innerHTML = `
            <span class="form-editor-list-title">${escapeHtml(type.label || 'Untitled proposal form')}</span>
            <span class="form-editor-question-count">${escapeHtml(String((type.questions || []).length))} questions</span>
        `;
        dom.proposalFormsEditor.appendChild(row);
    });
}

function renderProposalFormEdit() {
    if (!dom.proposalFormEditEditor || state.role !== 'admin') return;

    const typeIndex = Number(state.editing.proposalFormTypeIndex);
    const proposalType = state.proposalFormSettings.types?.[typeIndex];

    if (!proposalType) {
        setActiveView('admin');
        return;
    }

    if (dom.proposalFormEditTitle) {
        dom.proposalFormEditTitle.textContent = `Edit ${proposalType.label || 'proposal form'}`;
    }
    if (dom.proposalFormEditLabel) {
        dom.proposalFormEditLabel.value = proposalType.label || '';
    }

    setFormStatus(dom.proposalFormEditStatus, '');
    dom.proposalFormEditEditor.innerHTML = '';

    (proposalType.questions || []).forEach((question, questionIndex) => {
        dom.proposalFormEditEditor.appendChild(buildProposalQuestionEditor(question, questionIndex));
    });

    if (!(proposalType.questions || []).length) {
        dom.proposalFormEditEditor.innerHTML = '<div class="form-hint">No questions yet. Click Add question to create one.</div>';
    }
}

function buildProposalQuestionEditor(question = {}, questionIndex = 0, removeAction = 'remove-proposal-question') {
    const row = document.createElement('div');
    row.className = 'line-item';
    row.dataset.questionIndex = String(questionIndex);
    row.innerHTML = `
        <input type="text" data-question-label placeholder="Question" value="${escapeHtml(question.label || '')}" required>
        <select data-question-type>
            ${['text', 'textarea', 'number', 'date', 'select', 'checkbox'].map((type) => `<option value="${type}"${(question.type || 'text') === type ? ' selected' : ''}>${type}</option>`).join('')}
        </select>
        <select data-question-required>
            <option value="0"${question.required ? '' : ' selected'}>Optional</option>
            <option value="1"${question.required ? ' selected' : ''}>Required</option>
        </select>
        <input type="text" data-question-options placeholder="Options, comma-separated" value="${escapeHtml((question.options || []).join(', '))}">
        <button type="button" class="btn btn-ghost btn-small" data-action="${removeAction}">Remove</button>
    `;

    return row;
}

function collectProposalFormsEditorPayload() {
    const types = [...(state.proposalFormSettings.types || [])];
    const typeIndex = Number(state.editing.proposalFormTypeIndex);
    const existingType = types[typeIndex] || {};
    const label = dom.proposalFormEditLabel?.value?.trim() || '';
    const questions = [];

    dom.proposalFormEditEditor?.querySelectorAll('[data-question-index]').forEach((questionRow, questionIndex) => {
        const questionLabel = questionRow.querySelector('[data-question-label]')?.value?.trim() || '';
        if (!questionLabel) return;

        const options = String(questionRow.querySelector('[data-question-options]')?.value || '')
            .split(',')
            .map((option) => option.trim())
            .filter(Boolean);

        questions.push({
            label: questionLabel,
            key: existingType.questions?.[questionIndex]?.key || '',
            type: questionRow.querySelector('[data-question-type]')?.value || 'text',
            required: questionRow.querySelector('[data-question-required]')?.value === '1',
            options,
            sort_order: questionIndex,
        });
    });

    if (types[typeIndex]) {
        types[typeIndex] = {
            label,
            slug: existingType.slug || '',
            sort_order: typeIndex,
            questions,
        };
    }

    return { types };
}

async function handleProposalFormsSettingsSubmit(event) {
    event.preventDefault();
    if (!dom.proposalFormsSettingsForm || state.role !== 'admin') return;

    const payload = collectProposalFormsEditorPayload();
    if (!payload.types.length) {
        setFormStatus(dom.proposalFormEditStatus, 'At least one proposal type is required.', true);
        return;
    }

    const typeIndex = Number(state.editing.proposalFormTypeIndex);
    if (!payload.types[typeIndex]?.label) {
        setFormStatus(dom.proposalFormEditStatus, 'Proposal type name is required.', true);
        return;
    }

    try {
        const response = await api.put('/api/admin/proposal-forms', payload);
        state.proposalFormSettings = response?.data?.data ?? { types: [] };
        renderProposalTypeOptions();
        renderProposalFormsEditor();
        renderProposalFormEdit();
        setFormStatus(dom.proposalFormEditStatus, 'Proposal form updated.');
        showToast('Proposal form saved');
    } catch (error) {
        setFormStatus(dom.proposalFormEditStatus, getErrorMessage(error, 'Unable to update proposal form.'), true);
    }
}

async function loadAdminCustomerForms() {
    if (!dom.customerFormsEditor || state.role !== 'admin') return;

    try {
        const response = await api.get('/api/admin/customer-forms');
        state.customerFormSettings = response?.data?.data ?? { types: [] };
        renderCustomerFormsEditor();
    } catch (error) {
        setFormStatus(dom.customerFormsSettingsStatus, 'Unable to load customer forms.', true);
    }
}

function renderCustomerFormsEditor() {
    if (!dom.customerFormsEditor || state.role !== 'admin') return;
    dom.customerFormsEditor.innerHTML = '';

    if (!(state.customerFormSettings.types || []).length) {
        dom.customerFormsEditor.innerHTML = '<div class="form-hint">No customer forms yet. Click Add form to create one.</div>';
        return;
    }

    state.customerFormSettings.types.forEach((type, typeIndex) => {
        const row = document.createElement('button');
        row.type = 'button';
        row.className = 'btn btn-outline form-editor-list-item';
        row.dataset.action = 'edit-customer-form-type';
        row.dataset.typeIndex = String(typeIndex);
        row.innerHTML = `
            <span class="form-editor-list-title">${escapeHtml(type.label || 'Untitled customer form')}</span>
            <span class="form-editor-question-count">${escapeHtml(String((type.questions || []).length))} questions</span>
        `;
        dom.customerFormsEditor.appendChild(row);
    });
}

function renderCustomerFormEdit() {
    if (!dom.customerFormEditEditor || state.role !== 'admin') return;
    const typeIndex = Number(state.editing.customerFormTypeIndex);
    const formType = state.customerFormSettings.types?.[typeIndex];
    if (!formType) {
        setActiveView('admin');
        return;
    }

    if (dom.customerFormEditTitle) dom.customerFormEditTitle.textContent = `Edit ${formType.label || 'customer form'}`;
    if (dom.customerFormEditLabel) dom.customerFormEditLabel.value = formType.label || '';
    setFormStatus(dom.customerFormEditStatus, '');
    dom.customerFormEditEditor.innerHTML = '';

    (formType.questions || []).forEach((question, questionIndex) => {
        dom.customerFormEditEditor.appendChild(buildProposalQuestionEditor(question, questionIndex, 'remove-customer-form-question'));
    });
    if (!(formType.questions || []).length) {
        dom.customerFormEditEditor.innerHTML = '<div class="form-hint">No questions yet. Click Add question to create one.</div>';
    }
}

function collectCustomerFormsEditorPayload() {
    const types = [...(state.customerFormSettings.types || [])];
    const typeIndex = Number(state.editing.customerFormTypeIndex);
    const existingType = types[typeIndex] || {};
    const label = dom.customerFormEditLabel?.value?.trim() || '';
    const questions = [];

    dom.customerFormEditEditor?.querySelectorAll('[data-question-index]').forEach((questionRow, questionIndex) => {
        const questionLabel = questionRow.querySelector('[data-question-label]')?.value?.trim() || '';
        if (!questionLabel) return;
        const options = String(questionRow.querySelector('[data-question-options]')?.value || '')
            .split(',').map((option) => option.trim()).filter(Boolean);
        questions.push({
            label: questionLabel,
            key: existingType.questions?.[questionIndex]?.key || '',
            type: questionRow.querySelector('[data-question-type]')?.value || 'text',
            required: questionRow.querySelector('[data-question-required]')?.value === '1',
            options,
            sort_order: questionIndex,
        });
    });

    if (types[typeIndex]) {
        types[typeIndex] = { label, slug: existingType.slug || '', sort_order: typeIndex, questions };
    }

    return { types };
}

async function handleCustomerFormsSettingsSubmit(event) {
    event.preventDefault();
    if (!dom.customerFormsSettingsForm || state.role !== 'admin') return;
    const payload = collectCustomerFormsEditorPayload();
    const typeIndex = Number(state.editing.customerFormTypeIndex);
    if (!payload.types[typeIndex]?.label) {
        setFormStatus(dom.customerFormEditStatus, 'Customer form name is required.', true);
        return;
    }

    try {
        const response = await api.put('/api/admin/customer-forms', payload);
        state.customerFormSettings = response?.data?.data ?? { types: [] };
        renderCustomerFormsEditor();
        renderCustomerFormEdit();
        setFormStatus(dom.customerFormEditStatus, 'Customer form updated.');
        showToast('Customer form saved');
    } catch (error) {
        setFormStatus(dom.customerFormEditStatus, getErrorMessage(error, 'Unable to update customer form.'), true);
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
            <span>${formatEmailLink(user.email, '')}</span>
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
        populateTaskSelects();
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
    const selects = [dom.jobCustomerSelect, dom.subscriptionCustomerSelect, dom.proposalCustomerSelect, dom.invoiceCustomerSelect, dom.opportunityCustomerSelect];
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
    const selects = [dom.jobsFilterCustomer, dom.subscriptionsFilterCustomer, dom.proposalsFilterCustomer, dom.invoicesFilterCustomer];
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

function populateOpportunityCustomerSelect() {
    populateCustomerSelects(state.customerOptions.length ? state.customerOptions : state.customers);
}

async function loadOpportunitySummary() {
    try {
        const response = await api.get('/api/revenue-opportunities/summary');
        const data = response?.data ?? {};
        if (dom.dashboardPotentialMrr) dom.dashboardPotentialMrr.textContent = formatCurrency(Number(data.potential_mrr || 0));
        if (dom.dashboardOpportunityValue) dom.dashboardOpportunityValue.textContent = formatCurrency(Number(data.pipeline_project_value || 0));
        if (dom.dashboardOpportunityCount) dom.dashboardOpportunityCount.textContent = String(data.open_count || 0);
        if (dom.opportunityPotentialMrr) dom.opportunityPotentialMrr.textContent = formatCurrency(Number(data.potential_mrr || 0));
        if (dom.opportunityWeightedMrr) dom.opportunityWeightedMrr.textContent = formatCurrency(Number(data.weighted_mrr || 0));
        if (dom.opportunityProjectValue) dom.opportunityProjectValue.textContent = formatCurrency(Number(data.pipeline_project_value || 0));
        if (dom.opportunityRenewals) dom.opportunityRenewals.textContent = String(data.renewals_due_30_days || 0);
    } catch (error) {
        [dom.dashboardPotentialMrr, dom.dashboardOpportunityValue, dom.dashboardOpportunityCount].forEach((element) => {
            if (element) element.textContent = '--';
        });
    }
}

async function loadLeadDiscoveryData() {
    if (!dom.discoveredLeadsTable || !dom.leadDiscoveryRuns) return;
    try {
        const [leadsResponse, runsResponse] = await Promise.all([
            api.get('/api/businesses', { params: { source: 'google_places', contacted: state.showingContactedLeads ? 1 : 0, per_page: 100 } }),
            api.get('/api/lead-discovery', { params: { per_page: 20 } }),
        ]);
        state.discoveredLeads = leadsResponse?.data?.data || [];
        state.leadDiscoveryRuns = runsResponse?.data?.data || [];
        renderDiscoveredLeads();
        renderDiscoveryRuns();
        if (state.view === 'lead-discovery' && state.leadDiscoveryRuns.some((run) => ['pending', 'running'].includes(run.status))) {
            window.setTimeout(loadLeadDiscoveryData, 3000);
        }
    } catch (error) {
        showToast(getErrorMessage(error, 'Unable to load discovered leads.'), true);
    }
}

function renderDiscoveredLeads() {
    if (dom.discoveredLeadsTitle) dom.discoveredLeadsTitle.textContent = state.showingContactedLeads ? 'Contacted leads' : 'Discovered leads';
    if (dom.discoveredLeadsSubtitle) dom.discoveredLeadsSubtitle.textContent = state.showingContactedLeads ? 'Leads that have already been contacted.' : 'External businesses ready for review and conversion.';
    if (dom.leadDiscoveryContacted) {
        dom.leadDiscoveryContacted.textContent = state.showingContactedLeads ? 'Discovered leads' : 'Contacted';
        dom.leadDiscoveryContacted.classList.toggle('btn-primary', state.showingContactedLeads);
        dom.leadDiscoveryContacted.classList.toggle('btn-outline', !state.showingContactedLeads);
    }
    resetTable(dom.discoveredLeadsTable);
    if (!state.discoveredLeads.length) {
        const row = document.createElement('div');
        row.className = 'table-row table-empty discovered-leads';
        row.innerHTML = `<span>${state.showingContactedLeads ? 'No contacted leads yet.' : 'No external leads discovered yet.'}</span><span></span><span></span><span></span><span></span>`;
        dom.discoveredLeadsTable.appendChild(row);
        return;
    }
    state.discoveredLeads.forEach((lead) => {
        const row = document.createElement('div');
        row.className = 'table-row discovered-leads';
        const website = lead.website_url ? `<a href="${escapeHtml(lead.website_url)}" target="_blank" rel="noopener">${escapeHtml(new URL(lead.website_url).hostname)}</a>` : '<span class="text-muted">No website</span>';
        const contactedBy = lead.contacted_by?.name
            ? `Contacted by ${escapeHtml(lead.contacted_by.name)}`
            : 'Contacted · user not recorded';
        const contactedAction = state.showingContactedLeads
            ? `<span class="lead-contacted-label">${contactedBy}</span>`
            : `<button class="btn btn-primary lead-contacted-button" type="button" data-lead-contacted="${escapeHtml(lead.id)}">Contacted</button>`;
        row.innerHTML = `<span><button class="lead-business-link" type="button" data-lead-view="${escapeHtml(lead.id)}">${escapeHtml(lead.name)}</button><small>${escapeHtml(lead.address || '')}</small></span><span>${website}</span><span>${lead.google_rating ? `${escapeHtml(lead.google_rating)} ★ (${escapeHtml(lead.google_review_count || 0)})` : '--'}</span><span>${contactedAction}</span><span class="lead-row-actions"><button class="btn btn-danger lead-row-delete" type="button" data-lead-delete="${escapeHtml(lead.id)}">Delete</button></span>`;
        dom.discoveredLeadsTable.appendChild(row);
    });
}

function renderDiscoveryRuns() {
    resetTable(dom.leadDiscoveryRuns);
    if (!state.leadDiscoveryRuns.length) {
        const row = document.createElement('div');
        row.className = 'table-row table-empty discovery-runs';
        row.innerHTML = '<span>No discovery searches yet.</span><span></span><span></span><span></span><span></span><span></span>';
        dom.leadDiscoveryRuns.appendChild(row);
        return;
    }
    state.leadDiscoveryRuns.forEach((run) => {
        const row = document.createElement('div');
        row.className = 'table-row discovery-runs';
        const failure = run.failure_message ? `<small>${escapeHtml(run.failure_message)}</small>` : '';
        row.innerHTML = `<span><strong>${escapeHtml(run.query)}</strong><small>${escapeHtml(run.location)}</small></span><span><span class="badge">${escapeHtml(run.status)}</span>${failure}</span><span>${run.results_found}</span><span>${run.leads_created}</span><span>${run.leads_updated}</span><span>${formatDate(run.started_at || run.created_at)}</span><span><button class="btn btn-danger" type="button" data-discovery-delete="${escapeHtml(run.id)}">Delete</button></span>`;
        dom.leadDiscoveryRuns.appendChild(row);
    });
}

async function handleDiscoveredLeadAction(event) {
    const contactedButton = event.target.closest('[data-lead-contacted]');
    const deleteButton = event.target.closest('[data-lead-delete]');
    const viewButton = event.target.closest('[data-lead-view]');
    try {
        if (viewButton) {
            openLeadDetail(viewButton.dataset.leadView);
            return;
        } else if (contactedButton) {
            contactedButton.disabled = true;
            await api.patch(`/api/businesses/${contactedButton.dataset.leadContacted}/contacted`, { contacted: true });
            showToast('Lead moved to contacted leads.');
        } else if (deleteButton) {
            if (!window.confirm('Delete this lead? This will remove it from the lead list.')) return;
            deleteButton.disabled = true;
            await api.delete(`/api/businesses/${deleteButton.dataset.leadDelete}`);
            showToast('Lead deleted.');
        } else return;
        await loadLeadDiscoveryData();
    } catch (error) {
        if (contactedButton) contactedButton.disabled = false;
        showToast(getErrorMessage(error, 'Unable to update the lead.'), true);
        await loadLeadDiscoveryData();
    }
}

async function handleDiscoveryRunAction(event) {
    const button = event.target.closest('[data-discovery-delete]');
    if (!button || !window.confirm('Delete this discovery activity? Imported leads will be kept.')) return;
    button.disabled = true;
    try {
        await api.delete(`/api/lead-discovery/${button.dataset.discoveryDelete}`);
        showToast('Discovery activity deleted.');
        await loadLeadDiscoveryData();
    } catch (error) {
        button.disabled = false;
        showToast(getErrorMessage(error, 'Unable to delete discovery activity.'), true);
    }
}

function openLeadDetail(leadId) {
    if (!leadId) return;
    state.currentLead = { id: leadId };
    setActiveView('lead-detail');
}

async function loadLeadDetail(leadId) {
    try {
        const response = await api.get(`/api/businesses/${leadId}/intelligence`);
        const intelligence = response?.data?.data || {};
        state.currentLead = intelligence.lead || { id: leadId };
        state.currentLead.intelligence = intelligence;
        renderLeadDetail(intelligence);
        if ((intelligence.audit_history || []).some((audit) => ['pending', 'running'].includes(audit.status)) && state.view === 'lead-detail') {
            window.setTimeout(() => loadLeadDetail(leadId), 5000);
        }
    } catch (error) {
        showToast(getErrorMessage(error, 'Unable to load lead intelligence.'), true);
    }
}

function renderLeadDetail(intelligence) {
    const lead = intelligence.lead || {};
    const audit = intelligence.latest_audit || null;
    if (dom.leadDetailTitle) dom.leadDetailTitle.textContent = lead.name || 'Lead';
    if (dom.leadDetailCategory) dom.leadDetailCategory.textContent = lead.primary_category ? lead.primary_category.replaceAll('_', ' ') : 'Discovered business';
    if (dom.leadDetailWebsite) dom.leadDetailWebsite.innerHTML = lead.website_url ? `<a href="${escapeHtml(lead.website_url)}" target="_blank" rel="noopener">${escapeHtml(lead.website_url)}</a>` : '--';
    if (dom.leadDetailPhone) dom.leadDetailPhone.textContent = lead.phone || '--';
    if (dom.leadDetailAddress) dom.leadDetailAddress.textContent = lead.address || '--';
    if (dom.leadDetailGoogle) dom.leadDetailGoogle.innerHTML = lead.google_maps_url ? `<a href="${escapeHtml(lead.google_maps_url)}" target="_blank" rel="noopener">${lead.google_rating ? `${escapeHtml(lead.google_rating)} ★ · ${escapeHtml(lead.google_review_count || 0)} reviews` : 'Open Google Maps'}</a>` : '--';
    if (dom.leadDetailContacted) dom.leadDetailContacted.textContent = lead.contacted ? 'Contacted ✓' : 'Mark contacted';
    if (dom.leadDetailConvert) { dom.leadDetailConvert.disabled = Boolean(lead.customer_id); dom.leadDetailConvert.textContent = lead.customer_id ? 'Converted' : 'Convert to customer'; }
    if (dom.leadDetailAudit) dom.leadDetailAudit.disabled = !lead.website_url;

    dom.leadDetailScores?.querySelectorAll('[data-score]').forEach((element) => {
        const value = audit?.scores?.[element.dataset.score];
        element.textContent = value === null || value === undefined ? '--' : `${Math.round(Number(value))}/100`;
    });
    if (dom.leadDetailAuditDate) dom.leadDetailAuditDate.textContent = audit ? `${audit.status} · ${formatDateWithYear(audit.completed_at || audit.created_at)}` : 'No audit has been completed yet.';
    renderLeadAuditFacts(audit);
    renderLeadFindings(audit?.findings || []);
    renderLeadAuditHistory(intelligence.audit_history || []);
    if (dom.pageTitle) dom.pageTitle.textContent = lead.name || 'Lead Intelligence';
    if (dom.pageSubtitle) dom.pageSubtitle.textContent = 'Website issues, recommendations, and sales opportunities.';
}

function renderLeadAuditFacts(audit) {
    if (!dom.leadDetailFacts) return;
    if (!audit) { dom.leadDetailFacts.innerHTML = '<div class="table-empty">Run a website audit to generate detailed intelligence.</div>'; return; }
    const seo = audit.seo || {};
    const performance = audit.performance || {};
    const security = audit.security || {};
    const facts = [
        ['Meta title', seo.meta_title || 'Missing'], ['Meta description', seo.meta_description || 'Missing'],
        ['Broken links', seo.broken_link_count ?? '--'], ['Images missing alt text', seo.images_missing_alt ?? '--'],
        ['Sitemap', seo.has_sitemap ? 'Found' : 'Missing'], ['Robots.txt', seo.has_robots_txt ? 'Found' : 'Missing'],
        ['Page size', performance.page_size_bytes ? `${(Number(performance.page_size_bytes) / 1048576).toFixed(2)} MB` : '--'],
        ['Response time', performance.response_time_ms ? `${performance.response_time_ms} ms` : '--'],
        ['HTTPS', security.uses_https ? 'Enabled' : 'Missing'], ['SSL certificate', security.ssl_valid ? 'Valid' : 'Issue detected'],
        ['Server technology', security.server_technology || 'Not detected'], ['Schema items', seo.schema_item_count ?? '--'],
    ];
    dom.leadDetailFacts.innerHTML = facts.map(([label, value]) => `<div class="lead-audit-fact"><span class="meta-label">${escapeHtml(label)}</span><strong>${escapeHtml(value)}</strong></div>`).join('');
}

function renderLeadFindings(findings) {
    if (!dom.leadDetailFindings) return;
    const issues = findings.filter((finding) => !['passed', 'pass'].includes(String(finding.status).toLowerCase()));
    if (!issues.length) { dom.leadDetailFindings.innerHTML = '<div class="table-empty">No actionable issues were found in the latest completed audit.</div>'; return; }
    const rank = { critical: 0, high: 1, medium: 2, low: 3, info: 4 };
    issues.sort((a, b) => (rank[a.severity] ?? 5) - (rank[b.severity] ?? 5));
    dom.leadDetailFindings.innerHTML = issues.map((finding) => `<article class="lead-finding severity-${escapeHtml(finding.severity)}"><div class="lead-finding-head"><div><strong>${escapeHtml(finding.title)}</strong><div class="lead-finding-category">${escapeHtml(finding.category)} issue</div></div><input class="lead-finding-checkbox" type="checkbox" data-finding-resolved="${escapeHtml(finding.id)}" aria-label="Mark ${escapeHtml(finding.title)} as resolved" ${finding.status === 'resolved' ? 'checked' : ''}></div>${finding.description ? `<p><strong>Why it matters:</strong> ${escapeHtml(finding.description)}</p>` : ''}${finding.recommendation ? `<p class="recommendation"><strong>Recommended improvement:</strong> ${escapeHtml(finding.recommendation)}</p>` : ''}</article>`).join('');
}

async function handleLeadFindingChange(event) {
    const checkbox = event.target.closest('[data-finding-resolved]');
    if (!checkbox) return;
    checkbox.disabled = true;
    try {
        await api.patch(`/api/audit-findings/${checkbox.dataset.findingResolved}`, { resolved: checkbox.checked });
    } catch (error) {
        checkbox.checked = !checkbox.checked;
        showToast(getErrorMessage(error, 'Unable to update this issue.'), true);
    } finally {
        checkbox.disabled = false;
    }
}

function renderLeadAuditHistory(history) {
    if (!dom.leadDetailHistory) return;
    resetTable(dom.leadDetailHistory);
    if (!history.length) { const row = document.createElement('div'); row.className = 'table-row table-empty lead-audit-history'; row.innerHTML = '<span>No audit history yet.</span><span></span><span></span><span></span><span></span><span></span><span></span>'; dom.leadDetailHistory.appendChild(row); return; }
    history.forEach((audit) => {
        const row = document.createElement('div'); row.className = 'table-row lead-audit-history';
        row.innerHTML = `<span>${formatDateWithYear(audit.completed_at || audit.created_at)}</span><span><span class="pill outline">${escapeHtml(audit.status)}</span></span>${['overall','seo','performance','accessibility','security'].map((key) => `<span>${audit.scores?.[key] ?? '--'}</span>`).join('')}`;
        dom.leadDetailHistory.appendChild(row);
    });
}

async function handleLeadDetailAction(action) {
    const lead = state.currentLead;
    if (!lead?.id) return;
    try {
        if (action === 'back') return setActiveView('lead-discovery');
        if (action === 'contacted') await api.patch(`/api/businesses/${lead.id}/contacted`, { contacted: !lead.contacted });
        if (action === 'convert') { if (!window.confirm('Convert this lead into a CRM customer?')) return; await api.post(`/api/businesses/${lead.id}/convert`); state.customers = []; state.customerOptions = []; showToast('Lead converted to a customer.'); }
        if (action === 'audit') { await api.post(`/api/businesses/${lead.id}/audit`); showToast('Website audit queued.'); }
        if (action === 'delete') { if (!window.confirm('Delete this lead?')) return; await api.delete(`/api/businesses/${lead.id}`); state.currentLead = null; showToast('Lead deleted.'); return setActiveView('lead-discovery'); }
        await loadLeadDetail(lead.id);
    } catch (error) { showToast(getErrorMessage(error, 'Unable to update the lead.'), true); }
}

async function handleLeadDiscoverySubmit(event) {
    event.preventDefault();
    const form = new FormData(dom.leadDiscoveryForm);
    const submit = dom.leadDiscoveryForm.querySelector('button[type="submit"]');
    submit.disabled = true;
    setFormStatus(dom.leadDiscoveryStatus, 'Queueing business search...');
    try {
        await api.post('/api/lead-discovery', {
            query: form.get('query'), location: form.get('location'), limit: Number(form.get('limit')),
            auto_audit: form.get('auto_audit') === 'on',
        });
        setFormStatus(dom.leadDiscoveryStatus, 'Search queued. Keep the queue worker running; results will appear automatically.');
        showToast('External lead discovery queued.');
        await loadLeadDiscoveryData();
    } catch (error) {
        setFormStatus(dom.leadDiscoveryStatus, getErrorMessage(error, 'Unable to start lead discovery.'), true);
    } finally {
        submit.disabled = false;
    }
}

async function loadRevenueOpportunities() {
    if (!dom.opportunitiesTable) return;
    resetTable(dom.opportunitiesTable);
    const loading = document.createElement('div');
    loading.className = 'table-row table-empty opportunities';
    loading.innerHTML = '<span>Loading opportunities...</span><span></span><span></span><span></span><span></span><span></span><span></span>';
    dom.opportunitiesTable.appendChild(loading);
    try {
        const query = buildQuery({
            per_page: 100,
            status: state.filters.revenueOpportunities.status === 'all' ? null : state.filters.revenueOpportunities.status,
            type: state.filters.revenueOpportunities.type === 'all' ? null : state.filters.revenueOpportunities.type,
        });
        const response = await api.get(`/api/revenue-opportunities${query}`);
        state.revenueOpportunities = response?.data?.data ?? [];
        renderRevenueOpportunities();
        await loadOpportunitySummary();
    } catch (error) {
        resetTable(dom.opportunitiesTable);
        const empty = document.createElement('div');
        empty.className = 'table-row table-empty opportunities';
        empty.innerHTML = '<span>Unable to load opportunities.</span><span></span><span></span><span></span><span></span><span></span><span></span>';
        dom.opportunitiesTable.appendChild(empty);
    }
}

function renderRevenueOpportunities() {
    if (!dom.opportunitiesTable) return;
    resetTable(dom.opportunitiesTable);
    if (!state.revenueOpportunities.length) {
        const empty = document.createElement('div');
        empty.className = 'table-row table-empty opportunities';
        empty.innerHTML = '<span>No opportunities found.</span><span></span><span></span><span></span><span></span><span></span><span></span>';
        dom.opportunitiesTable.appendChild(empty);
        return;
    }
    state.revenueOpportunities.forEach((opportunity) => {
        const row = document.createElement('div');
        const selected = state.selectedOpportunityIds.has(opportunity.id);
        row.className = `table-row opportunities${state.opportunityBulkEdit ? ' opportunity-bulk-row' : ''}${selected ? ' selected' : ''}`;
        if (state.opportunityBulkEdit) row.dataset.opportunitySelectRow = opportunity.id;
        const customer = escapeHtml(opportunity.customer?.name || getCustomerName(opportunity.customer_id));
        const customerCell = state.opportunityBulkEdit
            ? `<label class="opportunity-select-control"><input type="checkbox" data-opportunity-select="${opportunity.id}" ${selected ? 'checked' : ''}><span>${customer}</span></label>`
            : customer;
        const actions = state.opportunityBulkEdit
            ? '<span class="text-muted">Click row to select</span>'
            : `<div class="row-actions"><button class="btn btn-outline btn-small" data-action="edit" data-id="${opportunity.id}">Edit</button>${opportunity.status !== 'won' ? `<button class="btn btn-primary btn-small" data-action="won" data-id="${opportunity.id}">Won</button>` : ''}<button class="btn btn-outline btn-small" data-action="follow-up" data-id="${opportunity.id}">Follow up</button><button class="btn btn-outline btn-small opportunity-delete" data-action="delete" data-id="${opportunity.id}">Delete</button></div>`;
        row.innerHTML = `
            <span>${customerCell}</span>
            <span><strong>${escapeHtml(opportunity.type_label)}</strong><small>${escapeHtml(opportunity.title)}</small></span>
            <span><span class="pill ${opportunity.status === 'won' ? 'success' : 'outline'}">${escapeHtml(opportunity.status)}</span></span>
            <span>${formatCurrency(Number(opportunity.estimated_project_value || 0))}</span>
            <span>${formatCurrency(Number(opportunity.estimated_monthly_revenue || 0))}</span>
            <span>${formatDate(opportunity.next_action_at)}</span>
            ${actions}
        `;
        dom.opportunitiesTable.appendChild(row);
    });
    updateOpportunityBulkControls();
}

function updateOpportunityBulkControls() {
    const count = state.selectedOpportunityIds.size;
    if (dom.opportunitiesBulkEdit) dom.opportunitiesBulkEdit.textContent = state.opportunityBulkEdit ? 'Cancel' : 'Bulk Edit';
    if (dom.opportunitiesBulkDelete) {
        dom.opportunitiesBulkDelete.hidden = !state.opportunityBulkEdit;
        dom.opportunitiesBulkDelete.disabled = count === 0;
        dom.opportunitiesBulkDelete.textContent = `Delete selected (${count})`;
    }
}

function setOpportunityBulkEdit(enabled) {
    state.opportunityBulkEdit = enabled;
    state.selectedOpportunityIds.clear();
    renderRevenueOpportunities();
}

function setOpportunitySelected(id, selected) {
    if (selected) state.selectedOpportunityIds.add(id);
    else state.selectedOpportunityIds.delete(id);
    const checkbox = dom.opportunitiesTable?.querySelector(`[data-opportunity-select="${id}"]`);
    const row = dom.opportunitiesTable?.querySelector(`[data-opportunity-select-row="${id}"]`);
    if (checkbox) checkbox.checked = selected;
    if (row) row.classList.toggle('selected', selected);
    updateOpportunityBulkControls();
}

function handleOpportunitySelection(event) {
    if (!state.opportunityBulkEdit) return;
    const checkbox = event.target.closest('[data-opportunity-select]');
    if (!checkbox) return;
    setOpportunitySelected(checkbox.dataset.opportunitySelect, checkbox.checked);
}

async function deleteSelectedOpportunities() {
    const ids = [...state.selectedOpportunityIds];
    if (!ids.length) return;
    const label = ids.length === 1 ? 'revenue opportunity' : 'revenue opportunities';
    if (!window.confirm(`Delete ${ids.length} selected ${label}? This cannot be undone.`)) return;
    dom.opportunitiesBulkDelete.disabled = true;
    try {
        const response = await api.delete('/api/revenue-opportunities/bulk', { data: { ids } });
        showToast(response?.data?.message || `${ids.length} revenue opportunities deleted.`);
        state.opportunityBulkEdit = false;
        state.selectedOpportunityIds.clear();
        await loadRevenueOpportunities();
    } catch (error) {
        showToast(getErrorMessage(error, 'Unable to delete the selected opportunities.'), true);
        updateOpportunityBulkControls();
    }
}

function resetOpportunityForm() {
    if (!dom.opportunityForm) return;
    dom.opportunityForm.reset();
    state.editing.revenueOpportunity = null;
    if (dom.opportunityFormTitle) dom.opportunityFormTitle.textContent = 'New opportunity';
    setFormStatus(dom.opportunityFormStatus, '');
    populateOpportunityCustomerSelect();
}

function editRevenueOpportunity(opportunity) {
    if (!dom.opportunityForm || !opportunity) return;
    state.editing.revenueOpportunity = opportunity.id;
    if (dom.opportunityFormTitle) dom.opportunityFormTitle.textContent = 'Edit opportunity';
    ['customer_id', 'type', 'title', 'estimated_project_value', 'estimated_monthly_revenue', 'confidence', 'status', 'recommendation', 'notes'].forEach((name) => {
        const field = dom.opportunityForm.elements.namedItem(name);
        if (field) field.value = opportunity[name] ?? '';
    });
    const nextAction = dom.opportunityForm.elements.namedItem('next_action_at');
    if (nextAction) nextAction.value = opportunity.next_action_at ? String(opportunity.next_action_at).slice(0, 10) : '';
}

async function handleOpportunitySubmit(event) {
    event.preventDefault();
    const formData = new FormData(dom.opportunityForm);
    const payload = {
        customer_id: Number(formData.get('customer_id')), type: formData.get('type'), title: String(formData.get('title') || '').trim(),
        estimated_project_value: Number(formData.get('estimated_project_value') || 0), estimated_monthly_revenue: Number(formData.get('estimated_monthly_revenue') || 0),
        confidence: Number(formData.get('confidence') || 0), status: formData.get('status'),
        next_action_at: formData.get('next_action_at') || null, recommendation: String(formData.get('recommendation') || '').trim() || null,
        notes: String(formData.get('notes') || '').trim() || null,
    };
    try {
        if (state.editing.revenueOpportunity) await api.put(`/api/revenue-opportunities/${state.editing.revenueOpportunity}`, payload);
        else await api.post('/api/revenue-opportunities', payload);
        showToast(state.editing.revenueOpportunity ? 'Opportunity updated.' : 'Opportunity created.');
        resetOpportunityForm();
        await loadRevenueOpportunities();
    } catch (error) {
        setFormStatus(dom.opportunityFormStatus, getErrorMessage(error, 'Unable to save opportunity.'), true);
    }
}

async function handleOpportunityAction(event) {
    if (state.opportunityBulkEdit) {
        if (event.target.closest('[data-opportunity-select]')) return;
        const row = event.target.closest('[data-opportunity-select-row]');
        if (row) setOpportunitySelected(row.dataset.opportunitySelectRow, !state.selectedOpportunityIds.has(row.dataset.opportunitySelectRow));
        return;
    }
    const button = event.target.closest('[data-action][data-id]');
    if (!button) return;
    const opportunity = state.revenueOpportunities.find((item) => item.id === button.dataset.id);
    if (!opportunity) return;
    if (button.dataset.action === 'edit') return editRevenueOpportunity(opportunity);
    if (button.dataset.action === 'follow-up') return openOpportunityFollowUp(opportunity);
    try {
        if (button.dataset.action === 'won') await api.put(`/api/revenue-opportunities/${opportunity.id}`, { status: 'won' });
        if (button.dataset.action === 'delete') {
            if (!window.confirm(`Delete the ${opportunity.type_label} opportunity for ${opportunity.customer?.name || 'this customer'}?`)) return;
            await api.delete(`/api/revenue-opportunities/${opportunity.id}`);
            showToast('Opportunity deleted.');
        }
        await loadRevenueOpportunities();
    } catch (error) {
        showToast(getErrorMessage(error, 'Unable to update opportunity.'), true);
    }
}

function openOpportunityFollowUp(opportunity) {
    if (!dom.opportunityFollowUpModal || !dom.opportunityFollowUpForm) return;
    state.editing.opportunityFollowUp = opportunity.id;
    dom.opportunityFollowUpForm.reset();
    const suggestedDate = new Date(Date.now() + 7 * 86400000).toISOString().slice(0, 10);
    if (dom.opportunityFollowUpDate) {
        dom.opportunityFollowUpDate.min = new Date().toISOString().slice(0, 10);
        dom.opportunityFollowUpDate.value = opportunity.next_action_at ? String(opportunity.next_action_at).slice(0, 10) : suggestedDate;
    }
    if (dom.opportunityFollowUpSubtitle) dom.opportunityFollowUpSubtitle.textContent = `${opportunity.customer?.name || 'Customer'} · ${opportunity.type_label}`;
    setFormStatus(dom.opportunityFollowUpStatus, 'An administrator email will be sent on the selected date.');
    dom.opportunityFollowUpModal.hidden = false;
    window.setTimeout(() => dom.opportunityFollowUpDate?.focus(), 0);
}

function closeOpportunityFollowUp() {
    if (dom.opportunityFollowUpModal) dom.opportunityFollowUpModal.hidden = true;
    state.editing.opportunityFollowUp = null;
    setFormStatus(dom.opportunityFollowUpStatus, '');
}

async function handleOpportunityFollowUpSubmit(event) {
    event.preventDefault();
    const opportunityId = state.editing.opportunityFollowUp;
    if (!opportunityId) return;
    const data = new FormData(dom.opportunityFollowUpForm);
    const submit = dom.opportunityFollowUpForm.querySelector('button[type="submit"]');
    submit.disabled = true;
    try {
        await api.post(`/api/revenue-opportunities/${opportunityId}/follow-up`, {
            due_date: data.get('due_date'), notes: String(data.get('notes') || '').trim() || null,
        });
        closeOpportunityFollowUp();
        showToast('Follow-up saved. The administrator will be emailed on that date.');
        await loadRevenueOpportunities();
    } catch (error) {
        setFormStatus(dom.opportunityFollowUpStatus, getErrorMessage(error, 'Unable to save the follow-up.'), true);
    } finally {
        submit.disabled = false;
    }
}

function isViewingArchivedCustomers() {
    return state.filters.customers.archived === true;
}

function updateCustomerArchiveControls() {
    if (dom.customersArchivedToggle) {
        const viewingArchived = isViewingArchivedCustomers();
        dom.customersArchivedToggle.textContent = viewingArchived ? 'Active customers' : 'Archived customers';
        dom.customersArchivedToggle.classList.toggle('is-active', viewingArchived);
    }

    if (dom.customerDetailArchive) {
        const isArchived = state.currentCustomer?.is_archived === true;
        dom.customerDetailArchive.textContent = isArchived ? 'Unarchive' : 'Archive';
    }
}

function isViewingArchivedJobs() {
    return state.filters.jobs.archived === true;
}

function updateJobArchiveControls() {
    if (!dom.jobsArchivedToggle) return;
    const viewingArchived = isViewingArchivedJobs();
    dom.jobsArchivedToggle.textContent = viewingArchived ? 'Active jobs' : 'Archived jobs';
    dom.jobsArchivedToggle.classList.toggle('is-active', viewingArchived);
}

function setJobsArchivedMode(showArchived) {
    state.filters.jobs.archived = showArchived === true;
    updateJobArchiveControls();
    loadJobs();
}

function isViewingPaidInvoices() {
    return state.filters.invoices.paid === true;
}

function updateInvoicePaidControls() {
    const viewingPaid = isViewingPaidInvoices();
    if (dom.invoicesPaidToggle) {
        dom.invoicesPaidToggle.textContent = viewingPaid ? 'Current invoices' : 'Paid invoices';
        dom.invoicesPaidToggle.classList.toggle('is-active', viewingPaid);
    }
    if (dom.invoicesFilterStatus) {
        dom.invoicesFilterStatus.disabled = viewingPaid;
    }
}

function setInvoicesPaidMode(showPaid) {
    state.filters.invoices.paid = showPaid === true;
    state.filters.invoices.status = 'all';
    if (dom.invoicesFilterStatus) dom.invoicesFilterStatus.value = 'all';
    updateInvoicePaidControls();
    loadInvoices();
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

async function ensureJobsLoaded() {
    if (state.role !== 'admin') return;
    if (state.jobs.length && state.jobs.every((job) => job?.is_archived !== true)) return;

    const response = await api.get('/api/jobs?per_page=200');
    state.jobs = response?.data?.data ?? [];
}

function populateTaskSelects() {
    if (dom.taskStaffSelect) {
        const current = dom.taskStaffSelect.value;
        dom.taskStaffSelect.innerHTML = '<option value="" selected disabled>Select staff user</option>';
        state.staffUsers.forEach((staff) => {
            const option = document.createElement('option');
            option.value = String(staff.id);
            option.textContent = staff.name || staff.email || `Staff #${staff.id}`;
            dom.taskStaffSelect.appendChild(option);
        });
        if (current) dom.taskStaffSelect.value = current;
    }

    if (dom.tasksFilterStaff) {
        const current = dom.tasksFilterStaff.value || 'all';
        dom.tasksFilterStaff.innerHTML = '<option value="all">All staff</option>';
        state.staffUsers.forEach((staff) => {
            const option = document.createElement('option');
            option.value = String(staff.id);
            option.textContent = staff.name || staff.email || `Staff #${staff.id}`;
            dom.tasksFilterStaff.appendChild(option);
        });
        dom.tasksFilterStaff.value = current;
    }

    if (dom.taskJobSelect) {
        const current = dom.taskJobSelect.value;
        dom.taskJobSelect.innerHTML = '<option value="">No linked job</option>';
        state.jobs.forEach((job) => {
            const option = document.createElement('option');
            option.value = String(job.id);
            option.textContent = `#${job.id} - ${truncate(job.description || 'Job', 60)}`;
            dom.taskJobSelect.appendChild(option);
        });
        if (current) dom.taskJobSelect.value = current;
    }
}

function formatTaskTime(task) {
    const hours = Number(task?.hours || 0);
    const minutes = Number(task?.minutes || 0);
    return `${hours}h ${String(minutes).padStart(2, '0')}m`;
}

async function loadTasks() {
    if (!dom.tasksTable) return;
    resetTable(dom.tasksTable);
    const loadingRow = document.createElement('div');
    loadingRow.className = 'table-row table-empty tasks';
    loadingRow.innerHTML = '<span>Loading tasks...</span><span></span><span></span><span></span><span></span><span></span><span></span><span></span>';
    dom.tasksTable.appendChild(loadingRow);

    try {
        const query = buildQuery({
            per_page: 100,
            status: state.filters.tasks.status,
            staff_id: state.role === 'admin' ? state.filters.tasks.staff : undefined,
        });
        const response = await api.get(`/api/tasks${query}`);
        state.tasks = response?.data?.data ?? [];
        renderTasks();
    } catch (error) {
        resetTable(dom.tasksTable);
        const row = document.createElement('div');
        row.className = 'table-row table-empty tasks';
        row.innerHTML = '<span>Unable to load tasks.</span><span></span><span></span><span></span><span></span><span></span><span></span><span></span>';
        dom.tasksTable.appendChild(row);
    }
}

function renderTasks() {
    if (!dom.tasksTable) return;
    resetTable(dom.tasksTable);

    if (!state.tasks.length) {
        const row = document.createElement('div');
        row.className = 'table-row table-empty tasks';
        row.innerHTML = '<span>No tasks yet.</span><span></span><span></span><span></span><span></span><span></span><span></span><span></span>';
        dom.tasksTable.appendChild(row);
        return;
    }

    state.tasks.forEach((task) => {
        const row = document.createElement('div');
        row.className = 'table-row tasks';
        const jobText = task.job ? `#${task.job.id} - ${task.job.description || 'Job'}` : 'No linked job';
        const actions = [];
        if (state.role === 'admin') {
            actions.push(`<button class="btn btn-outline btn-small" data-action="edit-task" data-id="${task.id}">Edit</button>`);
            actions.push(`<button class="btn btn-outline btn-small" data-action="delete-task" data-id="${task.id}">Delete</button>`);
        } else {
            actions.push(`<button class="btn btn-outline btn-small" data-action="update-task" data-id="${task.id}">Update</button>`);
        }

        row.innerHTML = `
            <span>${escapeHtml(task.title || '')}</span>
            <span>${escapeHtml(task.assigned_to?.name || 'Staff')}</span>
            <span>${escapeHtml(task.priority || 'normal')}</span>
            <span>${escapeHtml((task.status || '').replace('_', ' '))}</span>
            <span>${formatDate(task.due_date)}</span>
            <span>${escapeHtml(truncate(jobText, 42))}</span>
            <span>${formatTaskTime(task)}</span>
            <div class="row-actions">${actions.join('')}</div>
        `;
        dom.tasksTable.appendChild(row);
    });
}

function resetTaskForm() {
    if (!dom.taskForm) return;
    dom.taskForm.reset();
    dom.taskForm.querySelector('input[name="id"]').value = '';
    state.editing.task = null;
    if (dom.taskFormTitle) {
        dom.taskFormTitle.textContent = state.role === 'staff' ? 'Update task' : 'New task';
    }
    setFormStatus(dom.taskFormStatus, '');
}

async function handleTaskSubmit(event) {
    event.preventDefault();
    if (!dom.taskForm) return;

    const formData = new FormData(dom.taskForm);
    const taskId = Number(formData.get('id') || state.editing.task || 0);
    const progressPayload = {
        status: formData.get('status') || 'pending',
        hours: Number(formData.get('hours') || 0),
        minutes: Number(formData.get('minutes') || 0),
        staff_notes: String(formData.get('staff_notes') || '').trim() || null,
    };

    if (state.role !== 'admin') {
        if (!taskId) {
            setFormStatus(dom.taskFormStatus, 'Choose a task to update first.', true);
            return;
        }

        try {
            await api.put(`/api/tasks/${taskId}`, progressPayload);
            setFormStatus(dom.taskFormStatus, 'Task updated.');
            showToast(progressPayload.status === 'completed' ? 'Task completed' : 'Task updated');
            await loadTasks();
            if (state.view === 'dashboard') await loadStaffStats();
        } catch (error) {
            setFormStatus(dom.taskFormStatus, getErrorMessage(error, 'Unable to update task.'), true);
        }
        return;
    }

    const payload = {
        title: String(formData.get('title') || '').trim(),
        description: String(formData.get('description') || '').trim() || null,
        assigned_to_user_id: Number(formData.get('assigned_to_user_id')),
        job_id: formData.get('job_id') ? Number(formData.get('job_id')) : null,
        priority: formData.get('priority') || 'normal',
        due_date: formData.get('due_date') || null,
        ...progressPayload,
    };

    if (!payload.title || !payload.assigned_to_user_id) {
        setFormStatus(dom.taskFormStatus, 'Title and staff user are required.', true);
        return;
    }

    try {
        if (state.editing.task) {
            await api.put(`/api/tasks/${state.editing.task}`, payload);
            setFormStatus(dom.taskFormStatus, 'Task updated.');
        } else {
            await api.post('/api/tasks', payload);
            setFormStatus(dom.taskFormStatus, 'Task created.');
        }
        resetTaskForm();
        await loadTasks();
    } catch (error) {
        setFormStatus(dom.taskFormStatus, getErrorMessage(error, 'Unable to save task.'), true);
    }
}

async function handleTaskAction(event) {
    const button = event.target.closest('[data-action]');
    if (!button) return;
    const taskId = Number(button.dataset.id);
    const task = state.tasks.find((item) => Number(item.id) === taskId);
    if (!task) return;

    if (button.dataset.action === 'update-task' && dom.taskForm) {
        state.editing.task = task.id;
        dom.taskForm.querySelector('input[name="id"]').value = task.id;
        dom.taskForm.querySelector('select[name="status"]').value = task.status || 'pending';
        dom.taskForm.querySelector('input[name="hours"]').value = Number(task.hours || 0);
        dom.taskForm.querySelector('select[name="minutes"]').value = String(Number(task.minutes || 0));
        dom.taskForm.querySelector('textarea[name="staff_notes"]').value = task.staff_notes || '';
        if (dom.taskFormTitle) dom.taskFormTitle.textContent = `Update ${task.title}`;
        setFormStatus(dom.taskFormStatus, task.job ? `Linked job: #${task.job.id} - ${task.job.description || ''}` : 'No linked job.');
        return;
    }

    if (button.dataset.action === 'edit-task' && state.role === 'admin' && dom.taskForm) {
        state.editing.task = task.id;
        dom.taskForm.querySelector('input[name="id"]').value = task.id;
        dom.taskForm.querySelector('input[name="title"]').value = task.title || '';
        dom.taskForm.querySelector('textarea[name="description"]').value = task.description || '';
        dom.taskForm.querySelector('select[name="assigned_to_user_id"]').value = String(task.assigned_to_user_id || '');
        dom.taskForm.querySelector('select[name="job_id"]').value = task.job_id ? String(task.job_id) : '';
        dom.taskForm.querySelector('select[name="priority"]').value = task.priority || 'normal';
        dom.taskForm.querySelector('input[name="due_date"]').value = task.due_date || '';
        dom.taskForm.querySelector('select[name="status"]').value = task.status || 'pending';
        dom.taskForm.querySelector('input[name="hours"]').value = Number(task.hours || 0);
        dom.taskForm.querySelector('select[name="minutes"]').value = String(Number(task.minutes || 0));
        dom.taskForm.querySelector('textarea[name="staff_notes"]').value = task.staff_notes || '';
        if (dom.taskFormTitle) dom.taskFormTitle.textContent = `Edit ${task.title}`;
        setFormStatus(dom.taskFormStatus, 'Editing task.');
        return;
    }

    if (button.dataset.action === 'delete-task' && state.role === 'admin') {
        if (!window.confirm('Delete this task?')) return;
        try {
            await api.delete(`/api/tasks/${task.id}`);
            await loadTasks();
        } catch (error) {
            showToast('Unable to delete task', true);
        }
    }
}

async function loadCustomers(append = false) {
    if (!dom.customersTable) return;
    setFormStatus(dom.customerFormStatus, '');
    updateCustomerArchiveControls();
    setLoadMoreLoading('customers', true);
    if (!append) {
        resetPagination('customers');
        resetTable(dom.customersTable);
        const loadingRow = document.createElement('div');
        loadingRow.className = 'table-row table-empty customers';
        loadingRow.innerHTML = '<span>Loading customers...</span><span></span><span></span><span></span><span></span><span></span>';
        dom.customersTable.appendChild(loadingRow);
    }

    try {
        const page = append ? state.pagination.customers.page + 1 : 1;
        const query = buildQuery({
            per_page: 20,
            page,
            search: state.filters.customers.search || undefined,
            archived: isViewingArchivedCustomers() ? 1 : undefined,
        });
        const response = await api.get(`/api/customers${query}`);
        const items = response?.data?.data ?? [];
        state.customers = append ? [...state.customers, ...items] : items;
        let optionsSource = state.customerOptions.filter((customer) => customer?.is_archived !== true);
        if (!optionsSource.length && !isViewingArchivedCustomers()) {
            optionsSource = state.customers.filter((customer) => customer?.is_archived !== true);
        }
        populateCustomerSelects(optionsSource);
        populateCustomerFilterSelects(optionsSource);
        updatePagination('customers', response, append);
        renderCustomers();
    } catch (error) {
        resetTable(dom.customersTable);
        const emptyRow = document.createElement('div');
        emptyRow.className = 'table-row table-empty customers';
        emptyRow.innerHTML = '<span>Unable to load customers.</span><span></span><span></span><span></span><span></span><span></span>';
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
        const emptyMessage = isViewingArchivedCustomers() ? 'No archived customers yet.' : 'No customers yet.';
        emptyRow.innerHTML = `<span>${emptyMessage}</span><span></span><span></span><span></span><span></span><span></span>`;
        dom.customersTable.appendChild(emptyRow);
        return;
    }

    state.customers.forEach((customer) => {
        const totalSpent = Number(customer.paid_invoices_sum_total || 0);
        const mrr = Number(customer.subscriptions_sum_monthly_cost || 0);
        const row = document.createElement('div');
        row.className = 'table-row customers clickable';
        row.dataset.id = customer.id;
        row.innerHTML = `
            <span>${escapeHtml(customer.name)}</span>
            <span>${formatEmailLink(customer.email, '')}</span>
            <span>${formatPhoneLink(customer.phone, '')}</span>
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
        emptyRow.innerHTML = '<span>No jobs yet.</span><span></span><span></span><span></span><span></span>';
        dom.customerJobsTable.appendChild(emptyRow);
        return;
    }

    jobs.forEach((job) => {
        const row = document.createElement('div');
        row.className = 'table-row jobs-detail';
        row.innerHTML = `
            <span>#${job.id}</span>
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
        emptyRow.innerHTML = '<span>No subscriptions yet.</span><span></span><span></span><span></span><span></span>';
        dom.customerSubscriptionsTable.appendChild(emptyRow);
        return;
    }

    subscriptions.forEach((subscription) => {
        const row = document.createElement('div');
        row.className = 'table-row subscriptions-detail';
        row.innerHTML = `
            <span>#${subscription.id}</span>
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

function customerFormAnswerDisplay(question, answers = {}) {
    const key = String(question?.key || '');
    const value = answers?.[key];
    if (question?.type === 'checkbox') return value ? 'Yes' : 'No';
    if (value === null || value === undefined || value === '') return 'Not provided';
    return String(value);
}

function renderCustomerFormReview(formRequest) {
    if (!dom.customerFormReview) return;
    if (!formRequest) {
        dom.customerFormReview.hidden = true;
        dom.customerFormReview.innerHTML = '';
        return;
    }

    const questions = Array.isArray(formRequest.form_schema) ? formRequest.form_schema : [];
    const answers = formRequest.answers || {};
    const answerRows = questions.map((question) => `
        <div class="customer-form-answer">
            <div class="customer-form-answer-label">${escapeHtml(question.label || question.key || 'Field')}</div>
            <div class="customer-form-answer-value">${escapeHtml(
                formRequest.status === 'completed'
                    ? customerFormAnswerDisplay(question, answers)
                    : 'Awaiting customer response'
            )}</div>
        </div>
    `).join('');

    dom.customerFormReview.innerHTML = `
        <div class="card-header">
            <div>
                <div class="card-title">${escapeHtml(formRequest.template_name || 'Customer form')}</div>
                <div class="card-subtitle">${formRequest.status === 'completed' ? `Completed ${escapeHtml(formatDateWithYear(formRequest.completed_at))}` : 'Pending customer completion'}</div>
            </div>
            <button class="btn btn-outline btn-small" type="button" data-action="close-customer-form-review">Close</button>
        </div>
        <div class="customer-form-review-grid">${answerRows || '<div class="form-hint">No fields in this form.</div>'}</div>
    `;
    dom.customerFormReview.hidden = false;
}

function populateCustomerFormTemplates(templates = []) {
    if (!dom.customerFormTemplate) return;
    dom.customerFormTemplate.innerHTML = '<option value="">Select a form</option>';
    templates.forEach((template) => {
        const option = document.createElement('option');
        option.value = template.slug;
        option.textContent = `${template.label} (${template.questions_count || 0} fields)`;
        dom.customerFormTemplate.appendChild(option);
    });
}

function renderCustomerForms(forms = []) {
    if (!dom.customerFormsTable) return;
    resetTable(dom.customerFormsTable);

    if (!forms.length) {
        const emptyRow = document.createElement('div');
        emptyRow.className = 'table-row table-empty customer-forms';
        emptyRow.innerHTML = '<span>No forms sent yet.</span><span></span><span></span><span></span><span></span>';
        dom.customerFormsTable.appendChild(emptyRow);
        return;
    }

    forms.forEach((formRequest) => {
        const row = document.createElement('div');
        row.className = 'table-row customer-forms';
        row.innerHTML = `
            <span>${escapeHtml(formRequest.template_name || 'Customer form')}</span>
            <span>${escapeHtml(formRequest.status === 'completed' ? 'Completed' : 'Pending')}</span>
            <span>${escapeHtml(formatDateWithYear(formRequest.sent_at))}</span>
            <span>${escapeHtml(formatDateWithYear(formRequest.completed_at) || '--')}</span>
            <div class="row-actions"><button class="btn btn-outline btn-small" type="button" data-action="view-customer-form" data-id="${formRequest.id}">View</button></div>
        `;
        dom.customerFormsTable.appendChild(row);
    });
}

async function loadCustomerForms(customerId) {
    if (!customerId) return;
    try {
        const response = await api.get(`/api/customers/${customerId}/forms`);
        state.customerForms = response?.data?.data ?? [];
        state.customerFormTemplates = response?.data?.templates ?? [];
        populateCustomerFormTemplates(state.customerFormTemplates);
        renderCustomerForms(state.customerForms);
    } catch (error) {
        state.customerForms = [];
        renderCustomerForms([]);
        setFormStatus(dom.customerFormRequestStatus, getErrorMessage(error, 'Unable to load customer forms.'), true);
    }
}

async function handleCustomerFormRequestSubmit(event) {
    event.preventDefault();
    const customerId = state.currentCustomer?.id;
    const templateSlug = dom.customerFormTemplate?.value || '';
    if (!customerId || !templateSlug) {
        setFormStatus(dom.customerFormRequestStatus, 'Select a form template first.', true);
        return;
    }

    setFormStatus(dom.customerFormRequestStatus, 'Sending form...');
    try {
        const response = await api.post(`/api/customers/${customerId}/forms`, {
            template_slug: templateSlug,
        });
        dom.customerFormRequestForm?.reset();
        const warning = response?.data?.warning;
        setFormStatus(
            dom.customerFormRequestStatus,
            warning || 'Form sent and added to the customer portal.',
            Boolean(warning)
        );
        await loadCustomerForms(customerId);
    } catch (error) {
        setFormStatus(dom.customerFormRequestStatus, getErrorMessage(error, 'Unable to send customer form.'), true);
    }
}

async function loadCustomerDetail(customerId) {
    if (!customerId) return;
    setFormStatus(dom.customerWebsiteStatus, '');

    try {
        const response = await api.get(`/api/customers/${customerId}`);
        const customer = response?.data?.data ?? response?.data ?? null;
        if (!customer) return;

        state.currentCustomer = customer;
        updateCustomerArchiveControls();

        if (dom.customerDetailTitle) dom.customerDetailTitle.textContent = customer.name || 'Customer';
        if (dom.customerDetailEmail) dom.customerDetailEmail.innerHTML = formatEmailLink(customer.email, 'No email address');
        if (dom.customerDetailPhone) dom.customerDetailPhone.innerHTML = formatPhoneLink(customer.phone, 'No phone number');
        if (dom.customerDetailBilling) dom.customerDetailBilling.textContent = customer.billing_address || '--';
        if (dom.customerDetailNotes) dom.customerDetailNotes.textContent = customer.notes || '--';
        if (dom.pageTitle) dom.pageTitle.textContent = customer.name || 'Customer';
        if (dom.pageSubtitle) dom.pageSubtitle.textContent = 'Customer overview';

        const jobs = customer.jobs || [];
        const subscriptions = customer.subscriptions || [];
        const invoices = customer.invoices || [];
        const websites = customer.websites || [];

        const totalSpent = Number(customer.paid_invoices_sum_total || 0)
            || invoices
                .filter((invoice) => invoice.status === 'paid')
                .reduce((sum, invoice) => sum + Number(invoice.total || 0), 0);
        const monthlyRecurring = subscriptions.reduce((sum, sub) => sum + Number(sub.monthly_cost || 0), 0);
        const activeCount = subscriptions.filter((sub) => sub.status === 'active').length;

        if (dom.customerTotalSpent) dom.customerTotalSpent.textContent = formatCurrency(totalSpent);
        if (dom.customerMRR) dom.customerMRR.textContent = formatCurrency(monthlyRecurring);
        if (dom.customerSubscriptionCount) dom.customerSubscriptionCount.textContent = String(activeCount);

        renderCustomerJobs(jobs);
        renderCustomerSubscriptions(subscriptions);
        renderCustomerWebsites(websites);
        renderCustomerFormReview(null);
        loadCustomerForms(customer.id);
    } catch (error) {
        setFormStatus(dom.customerWebsiteStatus, 'Unable to load customer.', true);
    }
}

function openCustomerDetail(customerId) {
    if (!customerId) return;
    resetCustomerWebsiteForm();
    state.currentCustomer = { id: customerId };
    updateCustomerArchiveControls();
    setActiveView('customer-detail');
    loadCustomerDetail(customerId);
}

function setCustomersArchivedMode(showArchived) {
    state.filters.customers.archived = showArchived === true;
    updateCustomerArchiveControls();
    loadCustomers();
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
        phone: String(formData.get('phone') || '').trim() || null,
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
    if (event.target.closest('a.email-link, a.phone-link')) {
        event.stopPropagation();
        return;
    }

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
        dom.customerForm.querySelector('input[name="phone"]').value = customer.phone || '';
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

async function handleCustomerArchiveToggle() {
    if (state.role !== 'admin' || !state.currentCustomer?.id) {
        return;
    }

    const customerId = Number(state.currentCustomer.id);
    if (!customerId) return;

    const isArchived = state.currentCustomer?.is_archived === true;
    const intentLabel = isArchived ? 'unarchive' : 'archive';
    if (!window.confirm(`${isArchived ? 'Unarchive' : 'Archive'} this customer?`)) {
        return;
    }

    if (dom.customerDetailArchive) {
        dom.customerDetailArchive.disabled = true;
    }

    try {
        const endpoint = isArchived ? 'unarchive' : 'archive';
        const response = await api.patch(`/api/customers/${customerId}/${endpoint}`);
        const savedCustomer = response?.data?.data ?? response?.data ?? null;
        if (savedCustomer) {
            state.currentCustomer = {
                ...state.currentCustomer,
                ...savedCustomer,
            };
        }
        updateCustomerArchiveControls();
        await loadCustomerOptions();
        await loadCustomers();
        showToast(`Customer ${intentLabel}d.`);
        await loadCustomerDetail(customerId);
    } catch (error) {
        const message = getErrorMessage(error, `Unable to ${intentLabel} customer.`);
        setFormStatus(dom.customerWebsiteStatus, message, true);
        showToast(message, true);
    } finally {
        if (dom.customerDetailArchive) {
            dom.customerDetailArchive.disabled = false;
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

function formatFileSize(sizeInBytes) {
    const size = Number(sizeInBytes || 0);
    if (!Number.isFinite(size) || size <= 0) return '0 B';
    if (size < 1024) return `${size} B`;
    if (size < 1024 * 1024) return `${(size / 1024).toFixed(1)} KB`;
    return `${(size / (1024 * 1024)).toFixed(1)} MB`;
}

function setJobPhotoActionsEnabled(isEnabled) {
    if (dom.jobPhotosDownloadAll) {
        dom.jobPhotosDownloadAll.disabled = !isEnabled;
    }
    if (dom.jobPhotoFilesInput) {
        dom.jobPhotoFilesInput.disabled = !isEnabled;
    }
    const uploadButton = dom.jobPhotoUploadForm?.querySelector('button[type="submit"]');
    if (uploadButton) {
        uploadButton.disabled = !isEnabled;
    }
}

function getSelectedJobPhotoJobId() {
    const raw = state.editing.jobPhotoJobId ?? dom.jobPhotoJobSelect?.value ?? '';
    const id = Number(raw);
    return Number.isFinite(id) && id > 0 ? id : null;
}

function renderJobPhotosPlaceholder(message) {
    if (!dom.jobPhotosTable) return;
    resetTable(dom.jobPhotosTable);
    const row = document.createElement('div');
    row.className = 'table-row table-empty job-photos';
    row.innerHTML = `<span>${escapeHtml(message)}</span><span></span><span></span><span></span>`;
    dom.jobPhotosTable.appendChild(row);
}

function syncJobPhotoJobOptions() {
    if (!dom.jobPhotoJobSelect) return;

    const previousId = String(state.editing.jobPhotoJobId ?? dom.jobPhotoJobSelect.value ?? '');
    const jobs = state.jobs.filter((job) => Number(job?.id) > 0);

    dom.jobPhotoJobSelect.innerHTML = '';

    if (!jobs.length) {
        const option = document.createElement('option');
        option.value = '';
        option.textContent = 'No jobs available';
        dom.jobPhotoJobSelect.appendChild(option);
        state.editing.jobPhotoJobId = null;
        state.jobPhotos = [];
        setJobPhotoActionsEnabled(false);
        renderJobPhotosPlaceholder('No jobs available.');
        return;
    }

    jobs.forEach((job) => {
        const option = document.createElement('option');
        option.value = String(job.id);
        const customerName = job.customer?.name || getCustomerName(job.customer_id);
        option.textContent = `#${job.id} - ${truncate(job.description, 46)} (${customerName})`;
        dom.jobPhotoJobSelect.appendChild(option);
    });

    const selectedId = jobs.some((job) => String(job.id) === previousId)
        ? previousId
        : String(jobs[0].id);

    dom.jobPhotoJobSelect.value = selectedId;
    state.editing.jobPhotoJobId = Number(selectedId);
    setJobPhotoActionsEnabled(true);
}

function renderJobPhotos() {
    if (!dom.jobPhotosTable) return;
    resetTable(dom.jobPhotosTable);

    if (!state.jobPhotos.length) {
        renderJobPhotosPlaceholder('No photos uploaded for this job yet.');
        return;
    }

    state.jobPhotos.forEach((photo) => {
        const row = document.createElement('div');
        row.className = 'table-row job-photos';
        row.innerHTML = `
            <span>${formatDateWithYear(photo.created_at)}</span>
            <span>${escapeHtml(photo.original_name || 'photo')}</span>
            <span>${escapeHtml(formatFileSize(photo.size))}</span>
            <div class="row-actions">
                <button class="btn btn-outline btn-small" data-action="download-photo" data-id="${photo.id}">Download</button>
            </div>
        `;
        dom.jobPhotosTable.appendChild(row);
    });
}

async function loadJobPhotos() {
    if (!dom.jobPhotosTable) return;
    const jobId = getSelectedJobPhotoJobId();
    if (!jobId) {
        state.jobPhotos = [];
        setJobPhotoActionsEnabled(false);
        renderJobPhotosPlaceholder('Select a job to view uploaded photos.');
        return;
    }

    setJobPhotoActionsEnabled(true);
    renderJobPhotosPlaceholder('Loading photos...');

    try {
        const response = await api.get(`/api/jobs/${jobId}/photos`);
        state.jobPhotos = response?.data?.data ?? [];
        renderJobPhotos();
    } catch (error) {
        state.jobPhotos = [];
        renderJobPhotosPlaceholder('Unable to load photos.');
    }
}

async function downloadJobPhoto(jobId, fileId, filename) {
    try {
        const response = await api.get(`/api/jobs/${jobId}/photos/${fileId}/download`, { responseType: 'blob' });
        const blobUrl = window.URL.createObjectURL(new Blob([response.data]));
        const link = document.createElement('a');
        link.href = blobUrl;
        link.download = filename;
        document.body.appendChild(link);
        link.click();
        link.remove();
        window.URL.revokeObjectURL(blobUrl);
    } catch (error) {
        setFormStatus(dom.jobPhotoUploadStatus, getErrorMessage(error, 'Unable to download photo.'), true);
    }
}

async function downloadAllJobPhotos() {
    const jobId = getSelectedJobPhotoJobId();
    if (!jobId) {
        setFormStatus(dom.jobPhotoUploadStatus, 'Select a job first.', true);
        return;
    }

    try {
        const response = await api.get(`/api/jobs/${jobId}/photos/download-all`, { responseType: 'blob' });
        const blobUrl = window.URL.createObjectURL(new Blob([response.data]));
        const link = document.createElement('a');
        link.href = blobUrl;
        link.download = `job-${jobId}-photos.zip`;
        document.body.appendChild(link);
        link.click();
        link.remove();
        window.URL.revokeObjectURL(blobUrl);
    } catch (error) {
        setFormStatus(dom.jobPhotoUploadStatus, getErrorMessage(error, 'Unable to download all photos.'), true);
    }
}

async function handleJobPhotoUploadSubmit(event) {
    event.preventDefault();
    if (!dom.jobPhotoUploadForm || !dom.jobPhotoFilesInput) return;

    const jobId = getSelectedJobPhotoJobId();
    if (!jobId) {
        setFormStatus(dom.jobPhotoUploadStatus, 'Select a job first.', true);
        return;
    }

    const files = Array.from(dom.jobPhotoFilesInput.files || []);
    if (!files.length) {
        setFormStatus(dom.jobPhotoUploadStatus, 'Choose one or more images first.', true);
        return;
    }

    const formData = new FormData();
    files.forEach((file) => formData.append('photos[]', file));

    try {
        await api.post(`/api/jobs/${jobId}/photos`, formData, {
            headers: { 'Content-Type': 'multipart/form-data' },
        });
        setFormStatus(dom.jobPhotoUploadStatus, `${files.length} photo${files.length === 1 ? '' : 's'} uploaded.`);
        dom.jobPhotoUploadForm.reset();
        await loadJobPhotos();
    } catch (error) {
        setFormStatus(dom.jobPhotoUploadStatus, getErrorMessage(error, 'Unable to upload photos.'), true);
    }
}

async function handleJobPhotoAction(event) {
    const actionButton = event.target.closest('[data-action]');
    if (!actionButton) return;
    const action = actionButton.dataset.action;
    const fileId = Number(actionButton.dataset.id);
    const jobId = getSelectedJobPhotoJobId();
    if (!jobId || !fileId) return;

    if (action === 'download-photo') {
        const photo = state.jobPhotos.find((item) => Number(item.id) === fileId);
        const filename = photo?.original_name || `job-photo-${fileId}.jpg`;
        await downloadJobPhoto(jobId, fileId, filename);
    }
}

async function loadJobs(append = false) {
    if (!dom.jobsTable) return;
    setFormStatus(dom.jobFormStatus, '');
    updateJobArchiveControls();
    setLoadMoreLoading('jobs', true);
    if (!append) {
        resetPagination('jobs');
        resetTable(dom.jobsTable);
        const loadingRow = document.createElement('div');
        loadingRow.className = 'table-row table-empty jobs';
        loadingRow.innerHTML = '<span>Loading jobs...</span><span></span><span></span><span></span><span></span><span></span>';
        dom.jobsTable.appendChild(loadingRow);
    }

    try {
        const page = append ? state.pagination.jobs.page + 1 : 1;
        const query = buildQuery({
            per_page: 20,
            page,
            status: state.filters.jobs.status,
            customer_id: state.filters.jobs.customer,
            archived: isViewingArchivedJobs() ? 1 : undefined,
        });
        const response = await api.get(`/api/jobs${query}`);
        const items = response?.data?.data ?? [];
        state.jobs = append ? [...state.jobs, ...items] : items;
        updatePagination('jobs', response, append);
        renderJobs();
        syncJobPhotoJobOptions();
        if (state.view === 'jobs' && state.editing.jobPhotoJobId) {
            loadJobPhotos();
        }
    } catch (error) {
        resetTable(dom.jobsTable);
        const emptyRow = document.createElement('div');
        emptyRow.className = 'table-row table-empty jobs';
        emptyRow.innerHTML = '<span>Unable to load jobs.</span><span></span><span></span><span></span><span></span><span></span>';
        dom.jobsTable.appendChild(emptyRow);
        state.jobs = [];
        syncJobPhotoJobOptions();
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
        emptyRow.innerHTML = `<span>${isViewingArchivedJobs() ? 'No archived jobs yet.' : 'No jobs yet.'}</span><span></span><span></span><span></span><span></span><span></span>`;
        dom.jobsTable.appendChild(emptyRow);
        return;
    }

    state.jobs.forEach((job) => {
        const row = document.createElement('div');
        row.className = 'table-row jobs';
        const archiveAction = job.is_archived
            ? `<button class="btn btn-outline btn-small" data-action="unarchive" data-id="${job.id}">Unarchive</button>`
            : `<button class="btn btn-outline btn-small" data-action="archive" data-id="${job.id}">Archive</button>`;
        row.innerHTML = `
            <span>#${job.id}</span>
            <span>${escapeHtml(truncate(job.description, 40))}</span>
            <span>${escapeHtml(job.customer?.name || getCustomerName(job.customer_id))}</span>
            <span>${formatCurrency(Number(job.cost))}</span>
            <span>${escapeHtml(job.status)}</span>
            <div class="row-actions">
                ${job.is_archived ? '' : `<button class="btn btn-outline btn-small" data-action="create-task" data-id="${job.id}">Create task</button>`}
                <button class="btn btn-outline btn-small" data-action="edit" data-id="${job.id}">Edit</button>
                ${archiveAction}
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
        notes: String(formData.get('notes') || '').trim() || null,
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
        clearInvoiceBillableCache('job');
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
        dom.jobForm.querySelector('input[name="description"]').value = job.description || '';
        dom.jobForm.querySelector('textarea[name="notes"]').value = job.notes || '';
        dom.jobForm.querySelector('input[name="cost"]').value = job.cost || '';
        dom.jobForm.querySelector('select[name="status"]').value = job.status || 'draft';
        setFormStatus(dom.jobFormStatus, 'Editing job.');
    }

    if (action === 'create-task' && job && state.role === 'admin') {
        setActiveView('tasks');
        setTimeout(() => {
            if (dom.taskJobSelect) dom.taskJobSelect.value = String(job.id);
            if (dom.taskForm?.querySelector('input[name="title"]')) {
                dom.taskForm.querySelector('input[name="title"]').value = job.description || '';
            }
            if (dom.taskForm?.querySelector('textarea[name="description"]')) {
                dom.taskForm.querySelector('textarea[name="description"]').value = job.notes || '';
            }
        }, 100);
    }

    if ((action === 'archive' || action === 'unarchive') && job) {
        const label = action === 'archive' ? 'Archive' : 'Unarchive';
        if (!window.confirm(`${label} this job?`)) return;
        try {
            await api.patch(`/api/jobs/${id}/${action}`);
            clearInvoiceBillableCache('job');
            showToast(`Job ${action}d.`);
            await loadJobs();
        } catch (error) {
            setFormStatus(dom.jobFormStatus, getErrorMessage(error, `Unable to ${action} job.`), true);
        }
    }

    if (action === 'delete' && id) {
        if (!window.confirm('Delete this job?')) return;
        try {
            await api.delete(`/api/jobs/${id}`);
            clearInvoiceBillableCache('job');
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
        loadingRow.innerHTML = '<span>Loading costs...</span><span></span><span></span><span></span><span></span><span></span>';
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
        emptyRow.innerHTML = '<span>Unable to load costs.</span><span></span><span></span><span></span><span></span><span></span>';
        dom.costsTable.appendChild(emptyRow);
    } finally {
        setLoadMoreLoading('costs', false);
    }
}

function toggleCostRecurringFields() {
    if (!dom.costRecurringSelect || !dom.costRecurringFrequencyField || !dom.costRecurringFrequencySelect) {
        return;
    }

    const isRecurring = dom.costRecurringSelect.value === '1';
    dom.costRecurringFrequencyField.style.display = isRecurring ? '' : 'none';

    if (!isRecurring) {
        dom.costRecurringFrequencySelect.value = 'monthly';
    }
}

function getCostTypeLabel(cost) {
    const frequency = String(cost.recurring_frequency || '').toLowerCase();
    if (cost.is_recurring && (frequency === 'monthly' || frequency === 'annual')) {
        return frequency === 'annual' ? 'Recurring (Annual)' : 'Recurring (Monthly)';
    }

    return 'One-off';
}

function renderCosts() {
    if (!dom.costsTable) return;
    resetTable(dom.costsTable);

    if (!state.costs.length) {
        const emptyRow = document.createElement('div');
        emptyRow.className = 'table-row table-empty costs';
        emptyRow.innerHTML = '<span>No costs yet.</span><span></span><span></span><span></span><span></span><span></span>';
        dom.costsTable.appendChild(emptyRow);
        return;
    }

    state.costs.forEach((cost) => {
        const row = document.createElement('div');
        row.className = 'table-row costs';
        const receiptButton = cost.receipt_file_id
            ? `<button class="btn btn-outline btn-small" data-action="receipt" data-id="${cost.id}">Download</button>`
            : '<span class="muted">—</span>';
        const costType = getCostTypeLabel(cost);

        row.innerHTML = `
            <span>${formatDate(cost.incurred_on)}</span>
            <span>${escapeHtml(truncate(cost.description, 42))}</span>
            <span>${formatCurrency(Number(cost.amount))}</span>
            <span>${escapeHtml(costType)}</span>
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
    if (dom.costRecurringSelect) dom.costRecurringSelect.value = '0';
    if (dom.costRecurringFrequencySelect) dom.costRecurringFrequencySelect.value = 'monthly';
    toggleCostRecurringFields();
    state.editing.cost = null;
    if (dom.costFormTitle) dom.costFormTitle.textContent = 'New cost';
    setFormStatus(dom.costFormStatus, '');
}

async function handleCostSubmit(event) {
    event.preventDefault();
    if (!dom.costForm) return;

    const formData = new FormData(dom.costForm);
    const isRecurring = String(formData.get('is_recurring') || '0') === '1';

    if (!isRecurring) {
        formData.set('recurring_frequency', '');
    } else if (!String(formData.get('recurring_frequency') || '').trim()) {
        setFormStatus(dom.costFormStatus, 'Choose monthly or annual for recurring costs.', true);
        return;
    }

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
        dom.costForm.querySelector('input[name="incurred_on"]').value = formatDateInput(cost.incurred_on) || '';
        if (dom.costRecurringSelect) {
            dom.costRecurringSelect.value = cost.is_recurring ? '1' : '0';
        }
        if (dom.costRecurringFrequencySelect) {
            dom.costRecurringFrequencySelect.value =
                cost.recurring_frequency === 'annual' ? 'annual' : 'monthly';
        }
        toggleCostRecurringFields();
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
        loadingRow.innerHTML = '<span>Loading subscriptions...</span><span></span><span></span><span></span><span></span><span></span><span></span>';
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
        emptyRow.innerHTML = '<span>Unable to load subscriptions.</span><span></span><span></span><span></span><span></span><span></span><span></span>';
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
        emptyRow.innerHTML = '<span>No subscriptions yet.</span><span></span><span></span><span></span><span></span><span></span><span></span>';
        dom.subscriptionsTable.appendChild(emptyRow);
        return;
    }

    state.subscriptions.forEach((subscription) => {
        const row = document.createElement('div');
        row.className = 'table-row subscriptions';
        row.innerHTML = `
            <span>#${subscription.id}</span>
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
        clearInvoiceBillableCache('subscription');
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
            clearInvoiceBillableCache('subscription');
            if (state.editing.subscription === id) {
                resetSubscriptionForm();
            }
            await loadSubscriptions();
        } catch (error) {
            setFormStatus(dom.subscriptionFormStatus, 'Unable to delete subscription.', true);
        }
    }
}

async function loadProposalJobsForCustomer(customerId, preferredJobId = null) {
    if (!dom.proposalJobSelect) return;

    dom.proposalJobSelect.innerHTML = '';
    const placeholder = document.createElement('option');
    placeholder.value = '';
    placeholder.disabled = true;
    placeholder.selected = true;
    placeholder.textContent = customerId ? 'Loading jobs...' : 'Select customer first';
    dom.proposalJobSelect.appendChild(placeholder);

    if (!customerId) {
        return;
    }

    try {
        const jobs = await loadInvoiceBillables('job', customerId);
        dom.proposalJobSelect.innerHTML = '';

        const selectOption = document.createElement('option');
        selectOption.value = '';
        selectOption.disabled = true;
        selectOption.selected = true;
        selectOption.textContent = 'Select job';
        dom.proposalJobSelect.appendChild(selectOption);

        jobs.forEach((job) => {
            const option = document.createElement('option');
            option.value = String(job.id);
            option.textContent = `#${job.id} - ${truncate(job.description, 52)} (${formatCurrency(Number(job.cost || 0))})`;
            dom.proposalJobSelect.appendChild(option);
        });

        if (preferredJobId && jobs.some((job) => Number(job.id) === Number(preferredJobId))) {
            dom.proposalJobSelect.value = String(preferredJobId);
        }
    } catch (error) {
        dom.proposalJobSelect.innerHTML = '';
        const option = document.createElement('option');
        option.value = '';
        option.disabled = true;
        option.selected = true;
        option.textContent = 'Unable to load jobs';
        dom.proposalJobSelect.appendChild(option);
    }
}

function applySelectedProposalJobDetails(job, overwriteExisting = false) {
    if (!job || !dom.proposalForm) return;

    const descriptionInput = dom.proposalLineItemDescription
        || dom.proposalForm.querySelector('input[name="line_item_description"]');
    const titleInput = dom.proposalTitle || dom.proposalForm.querySelector('input[name="title"]');
    const unitPriceInput = dom.proposalForm.querySelector('input[name="line_item_unit_price"]');
    const notesInput = dom.proposalForm.querySelector('textarea[name="notes"]');

    if (descriptionInput && (overwriteExisting || !String(descriptionInput.value || '').trim())) {
        descriptionInput.value = String(job.description || '');
    }

    if (titleInput && (overwriteExisting || !String(titleInput.value || '').trim())) {
        titleInput.value = String(job.description || 'Job proposal');
    }

    if (unitPriceInput && (overwriteExisting || !String(unitPriceInput.value || '').trim())) {
        unitPriceInput.value = normalizeQuantityDisplay(job.cost || 0);
    }

    if (notesInput && (overwriteExisting || !String(notesInput.value || '').trim())) {
        notesInput.value = String(job.notes || '');
    }
}

function resetProposalForm() {
    if (!dom.proposalForm) return;
    dom.proposalForm.reset();
    dom.proposalForm.querySelector('input[name="id"]').value = '';
    state.editing.proposal = null;
    if (dom.proposalFormTitle) dom.proposalFormTitle.textContent = 'New proposal';
    setFormStatus(dom.proposalFormStatus, '');

    renderProposalTypeOptions();
}

async function handleProposalCustomerChange() {
    if (!dom.proposalCustomerSelect) return;
    const customerId = Number(dom.proposalCustomerSelect.value || 0);
    if (dom.proposalForm) {
        const descriptionInput = dom.proposalForm.querySelector('input[name="line_item_description"]');
        const unitPriceInput = dom.proposalForm.querySelector('input[name="line_item_unit_price"]');
        if (descriptionInput) descriptionInput.value = '';
        if (unitPriceInput) unitPriceInput.value = '';
    }
    await loadProposalJobsForCustomer(customerId || null);
}

async function handleProposalJobChange(overwriteExisting = false) {
    if (!dom.proposalCustomerSelect || !dom.proposalJobSelect) return;

    const customerId = Number(dom.proposalCustomerSelect.value || 0);
    const jobId = Number(dom.proposalJobSelect.value || 0);
    if (!customerId || !jobId) return;

    try {
        const jobs = await loadInvoiceBillables('job', customerId);
        const selectedJob = jobs.find((job) => Number(job.id) === jobId);
        if (selectedJob) {
            applySelectedProposalJobDetails(selectedJob, overwriteExisting);
        }
    } catch (error) {
        // Keep form values if we cannot load job details.
    }
}

async function loadProposals(append = false) {
    if (!dom.proposalsTable) return;
    setFormStatus(dom.proposalFormStatus, '');
    setLoadMoreLoading('proposals', true);

    if (!append) {
        resetPagination('proposals');
        resetTable(dom.proposalsTable);
        const loadingRow = document.createElement('div');
        loadingRow.className = 'table-row table-empty proposals';
        loadingRow.innerHTML = '<span>Loading proposals...</span><span></span><span></span><span></span><span></span><span></span><span></span><span></span>';
        dom.proposalsTable.appendChild(loadingRow);
    }

    try {
        const page = append ? state.pagination.proposals.page + 1 : 1;
        const query = buildQuery({
            per_page: 20,
            page,
            status: state.filters.proposals.status,
            customer_id: state.filters.proposals.customer,
        });
        const response = await api.get(`/api/proposals${query}`);
        const items = response?.data?.data ?? [];
        state.proposals = append ? [...state.proposals, ...items] : items;
        updatePagination('proposals', response, append);
        renderProposals();
    } catch (error) {
        resetTable(dom.proposalsTable);
        const emptyRow = document.createElement('div');
        emptyRow.className = 'table-row table-empty proposals';
        emptyRow.innerHTML = '<span>Unable to load proposals.</span><span></span><span></span><span></span><span></span><span></span><span></span><span></span>';
        dom.proposalsTable.appendChild(emptyRow);
    } finally {
        setLoadMoreLoading('proposals', false);
    }
}

function renderProposals() {
    if (!dom.proposalsTable) return;
    resetTable(dom.proposalsTable);

    if (!state.proposals.length) {
        const emptyRow = document.createElement('div');
        emptyRow.className = 'table-row table-empty proposals';
        emptyRow.innerHTML = '<span>No proposals yet.</span><span></span><span></span><span></span><span></span><span></span><span></span><span></span>';
        dom.proposalsTable.appendChild(emptyRow);
        return;
    }

    state.proposals.forEach((proposal) => {
        const row = document.createElement('div');
        row.className = 'table-row proposals';

        const lineItem = Array.isArray(proposal.line_items) && proposal.line_items.length
            ? proposal.line_items[0]
            : null;
        const effectiveStatus = proposal.effective_status || proposal.status || 'draft';
        const isLocked = Boolean(proposal.locked_at) || ['pending', 'approved', 'declined', 'expired'].includes(effectiveStatus);
        const actionButtons = [];

        if (proposal.status === 'draft') {
            actionButtons.push(`<button class="btn btn-outline btn-small" data-action="send" data-id="${proposal.id}">Send</button>`);
        }

        if (effectiveStatus !== 'approved') {
            actionButtons.push(`<button class="btn btn-outline btn-small" data-action="set-status" data-next-status="approved" data-id="${proposal.id}">Approve</button>`);
        }

        if (effectiveStatus !== 'declined') {
            actionButtons.push(`<button class="btn btn-outline btn-small" data-action="set-status" data-next-status="declined" data-id="${proposal.id}">Decline</button>`);
        }

        actionButtons.push(`<button class="btn btn-outline btn-small" data-action="edit" data-id="${proposal.id}">Edit</button>`);

        if (isLocked) {
            actionButtons.push(`<button class="btn btn-outline btn-small" data-action="new-version" data-id="${proposal.id}">New version</button>`);
        }

        actionButtons.push(`<button class="btn btn-outline btn-small" data-action="download" data-id="${proposal.id}">Download</button>`);
        actionButtons.push(`<button class="btn btn-outline btn-small" data-action="delete" data-id="${proposal.id}">Delete</button>`);

        row.innerHTML = `
            <span>#${escapeHtml(proposal.proposal_number)}</span>
            <span>v${escapeHtml(String(proposal.version || 1))}</span>
            <span>${escapeHtml(proposal.customer?.name || getCustomerName(proposal.customer_id))}</span>
            <span>${escapeHtml(proposal.proposal_type_label || lineItem?.description || 'Proposal')}</span>
            <span>${formatCurrency(Number(proposal.total || 0))}</span>
            <span>${escapeHtml(effectiveStatus)}</span>
            <span>${formatDate(proposal.expiry_date)}</span>
            <div class="row-actions">${actionButtons.join('')}</div>
        `;
        dom.proposalsTable.appendChild(row);
    });
}

async function handleProposalSubmit(event) {
    event.preventDefault();
    if (!dom.proposalForm) return;

    const formData = new FormData(dom.proposalForm);
    const customerId = Number(formData.get('customer_id'));
    const quantity = 1;
    const unitPrice = Number(formData.get('line_item_unit_price'));
    const description = String(formData.get('line_item_description') || '').trim();
    const proposalType = String(formData.get('proposal_type') || '').trim();
    const selectedType = getSelectedProposalType();

    if (Number.isNaN(unitPrice) || unitPrice < 0) {
        setFormStatus(dom.proposalFormStatus, 'Manual price is invalid.', true);
        return;
    }
    if (!customerId || Number.isNaN(customerId)) {
        setFormStatus(dom.proposalFormStatus, 'Select a customer.', true);
        return;
    }
    if (!proposalType || !selectedType) {
        setFormStatus(dom.proposalFormStatus, 'Select a proposal type.', true);
        return;
    }
    if (!description) {
        setFormStatus(dom.proposalFormStatus, 'Price description is required.', true);
        return;
    }

    const formAnswers = {};
    for (const question of selectedType.questions || []) {
        const rawValue = formData.get(`form_answer_${question.key}`);
        const value = question.type === 'checkbox'
            ? String(rawValue || '0') === '1'
            : String(rawValue || '').trim();

        if (question.required && (value === '' || value === null)) {
            setFormStatus(dom.proposalFormStatus, `${question.label} is required.`, true);
            return;
        }

        formAnswers[question.key] = value;
    }

    const payload = {
        customer_id: customerId,
        title: String(formData.get('title') || '').trim(),
        proposal_type: proposalType,
        issue_date: formData.get('issue_date'),
        expiry_date: formData.get('expiry_date'),
        status: formData.get('status') || 'draft',
        notes: String(formData.get('notes') || '').trim() || null,
        terms: String(formData.get('terms') || '').trim() || null,
        form_answers: formAnswers,
        line_item: {
            description,
            quantity,
            unit_price: unitPrice,
        },
    };

    if (!payload.title) {
        setFormStatus(dom.proposalFormStatus, 'Title is required.', true);
        return;
    }

    try {
        if (state.editing.proposal) {
            await api.put(`/api/proposals/${state.editing.proposal}`, payload);
            setFormStatus(dom.proposalFormStatus, 'Proposal updated.');
        } else {
            await api.post('/api/proposals', payload);
            setFormStatus(dom.proposalFormStatus, 'Proposal created.');
        }
        await loadProposals();
        resetProposalForm();
    } catch (error) {
        setFormStatus(dom.proposalFormStatus, getErrorMessage(error, 'Unable to save proposal.'), true);
    }
}

async function downloadProposal(id, filename, portal = false) {
    try {
        const url = portal ? `/api/portal/proposals/${id}/download` : `/api/proposals/${id}/download`;
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
        if (portal) {
            showToast('Unable to download proposal', true);
        } else {
            setFormStatus(dom.proposalFormStatus, 'Unable to download proposal.', true);
        }
    }
}

async function handleProposalAction(event) {
    const actionButton = event.target.closest('[data-action]');
    if (!actionButton) return;

    const id = Number(actionButton.dataset.id);
    const action = actionButton.dataset.action;
    const proposal = state.proposals.find((item) => item.id === id);

    if (!id || !action) return;

    if (action === 'edit' && proposal && dom.proposalForm) {
        state.editing.proposal = id;
        if (dom.proposalFormTitle) {
            dom.proposalFormTitle.textContent = `Edit ${proposal.proposal_number} v${proposal.version}`;
        }
        dom.proposalForm.querySelector('input[name="id"]').value = proposal.id;
        dom.proposalForm.querySelector('select[name="customer_id"]').value = proposal.customer_id;
        dom.proposalForm.querySelector('select[name="proposal_type"]').value = proposal.proposal_type || '';
        renderProposalFormAnswers(proposal.form_answers || []);
        dom.proposalForm.querySelector('input[name="title"]').value = proposal.title || '';
        dom.proposalForm.querySelector('input[name="issue_date"]').value = proposal.issue_date || '';
        dom.proposalForm.querySelector('input[name="expiry_date"]').value = proposal.expiry_date || '';
        dom.proposalForm.querySelector('select[name="status"]').value = proposal.status === 'pending' ? 'pending' : 'draft';

        const lineItem = Array.isArray(proposal.line_items) && proposal.line_items.length
            ? proposal.line_items[0]
            : null;
        dom.proposalForm.querySelector('input[name="line_item_description"]').value = lineItem?.description || '';
        dom.proposalForm.querySelector('input[name="line_item_quantity"]').value = normalizeQuantityDisplay(lineItem?.quantity || 1);
        dom.proposalForm.querySelector('input[name="line_item_unit_price"]').value = normalizeQuantityDisplay(lineItem?.unit_price || 0);
        dom.proposalForm.querySelector('textarea[name="notes"]').value = proposal.notes || '';
        dom.proposalForm.querySelector('textarea[name="terms"]').value = proposal.terms || '';
        setFormStatus(dom.proposalFormStatus, 'Editing proposal.');
        return;
    }

    if (action === 'send') {
        try {
            await api.post(`/api/proposals/${id}/send`);
            showToast('Proposal sent');
            await loadProposals();
        } catch (error) {
            setFormStatus(dom.proposalFormStatus, getErrorMessage(error, 'Unable to send proposal.'), true);
            showToast('Unable to send proposal', true);
        }
        return;
    }

    if (action === 'set-status') {
        const nextStatus = actionButton.dataset.nextStatus;
        if (!['approved', 'declined'].includes(nextStatus || '')) {
            return;
        }

        actionButton.disabled = true;
        try {
            const payload = { status: nextStatus };
            try {
                await api.patch(`/api/proposals/${id}/status`, payload);
            } catch (error) {
                const statusCode = Number(error?.response?.status || 0);
                if (statusCode !== 404 && statusCode !== 405) {
                    throw error;
                }
                await api.post(`/api/proposals/${id}/status`, payload);
            }
            showToast(nextStatus === 'approved' ? 'Proposal approved' : 'Proposal declined');
            await loadProposals();
        } catch (error) {
            setFormStatus(dom.proposalFormStatus, getErrorMessage(error, 'Unable to update proposal status.'), true);
            showToast('Unable to update proposal status', true);
        } finally {
            actionButton.disabled = false;
        }
        return;
    }

    if (action === 'new-version') {
        try {
            await api.post(`/api/proposals/${id}/new-version`);
            showToast('New proposal version created');
            await loadProposals();
        } catch (error) {
            setFormStatus(dom.proposalFormStatus, getErrorMessage(error, 'Unable to create new version.'), true);
            showToast('Unable to create new version', true);
        }
        return;
    }

    if (action === 'download' && proposal) {
        await downloadProposal(id, `Proposal-${proposal.proposal_number}-v${proposal.version}.pdf`);
        return;
    }

    if (action === 'delete') {
        if (!window.confirm('Delete this proposal?')) return;
        try {
            await api.delete(`/api/proposals/${id}`);
            if (state.editing.proposal === id) {
                resetProposalForm();
            }
            await loadProposals();
        } catch (error) {
            setFormStatus(dom.proposalFormStatus, getErrorMessage(error, 'Unable to delete proposal.'), true);
        }
    }
}

async function loadPortalProposals() {
    if (!dom.portalProposals) return;
    resetTable(dom.portalProposals);
    const loadingRow = document.createElement('div');
    loadingRow.className = 'table-row table-empty portal-proposals';
    loadingRow.innerHTML = '<span>Loading proposals...</span><span></span><span></span><span></span><span></span><span></span>';
    dom.portalProposals.appendChild(loadingRow);

    try {
        const response = await api.get('/api/portal/proposals?per_page=100');
        const items = response?.data?.data ?? [];
        state.portalProposals = items;
        renderPortalProposals(items);
    } catch (error) {
        state.portalProposals = [];
        renderPortalProposals([], 'Unable to load proposals.');
    }
}

function renderPortalProposals(proposals = [], emptyMessage = 'No proposals available.') {
    if (!dom.portalProposals) return;
    resetTable(dom.portalProposals);

    if (!proposals.length) {
        const emptyRow = document.createElement('div');
        emptyRow.className = 'table-row table-empty portal-proposals';
        emptyRow.innerHTML = `<span>${escapeHtml(emptyMessage)}</span><span></span><span></span><span></span><span></span><span></span>`;
        dom.portalProposals.appendChild(emptyRow);
        return;
    }

    proposals.forEach((proposal) => {
        const row = document.createElement('div');
        row.className = 'table-row portal-proposals';
        const effectiveStatus = proposal.effective_status || proposal.status || 'draft';
        const actionButtons = [];

        if (!['approved', 'declined', 'expired'].includes(effectiveStatus)) {
            actionButtons.push(`<button type="button" class="btn btn-outline btn-small" data-action="portal-proposal-status" data-id="${proposal.id}" data-next-status="approved">Approve</button>`);
            actionButtons.push(`<button type="button" class="btn btn-outline btn-small" data-action="portal-proposal-status" data-id="${proposal.id}" data-next-status="declined">Decline</button>`);
        }

        actionButtons.push(`<button type="button" class="btn btn-outline btn-small" data-action="portal-proposal-download" data-id="${proposal.id}">Download</button>`);

        row.innerHTML = `
            <span>#${escapeHtml(proposal.proposal_number)} v${escapeHtml(String(proposal.version || 1))}</span>
            <span>${escapeHtml(proposal.title || proposal.job?.description || 'Proposal')}</span>
            <span>${formatCurrency(Number(proposal.total || 0))}</span>
            <span>${escapeHtml(effectiveStatus)}</span>
            <span>${formatDate(proposal.expiry_date)}</span>
            <div class="row-actions">${actionButtons.join('')}</div>
        `;
        dom.portalProposals.appendChild(row);
    });
}

async function handlePortalProposalAction(event) {
    const actionButton = event.target.closest('[data-action]');
    if (!actionButton) return;

    const proposalId = Number(actionButton.dataset.id);
    const action = actionButton.dataset.action;
    const nextStatus = actionButton.dataset.nextStatus;
    const proposal = state.portalProposals.find((item) => item.id === proposalId);

    if (!proposalId || !action) return;

    if (action === 'portal-proposal-download' && proposal) {
        await downloadProposal(
            proposal.id,
            `Proposal-${proposal.proposal_number}-v${proposal.version}.pdf`,
            true
        );
        return;
    }

    if (action !== 'portal-proposal-status') {
        return;
    }

    if (!['approved', 'declined'].includes(nextStatus || '')) {
        return;
    }

    actionButton.disabled = true;

    try {
        const payload = { status: nextStatus };
        try {
            await api.patch(`/api/portal/proposals/${proposalId}/status`, payload);
        } catch (error) {
            const statusCode = Number(error?.response?.status || 0);
            if (statusCode !== 404 && statusCode !== 405) {
                throw error;
            }
            await api.post(`/api/portal/proposals/${proposalId}/status`, payload);
        }

        showToast(nextStatus === 'approved' ? 'Proposal approved' : 'Proposal declined');
        await loadPortalProposals();
    } catch (error) {
        showToast('Unable to update proposal status', true);
    } finally {
        actionButton.disabled = false;
    }
}

function updatePortalFormsNotification(forms = []) {
    const pendingCount = forms.filter((formRequest) => formRequest.status === 'pending').length;

    if (dom.portalFormsNotification) {
        dom.portalFormsNotification.hidden = pendingCount === 0;
    }

    if (dom.mobileMenuNotification) {
        dom.mobileMenuNotification.hidden = pendingCount === 0;
    }

    if (dom.portalFormsNav) {
        if (pendingCount > 0) {
            dom.portalFormsNav.setAttribute(
                'aria-label',
                `Forms, ${pendingCount} pending ${pendingCount === 1 ? 'form' : 'forms'}`
            );
            dom.portalFormsNav.title = `${pendingCount} pending ${pendingCount === 1 ? 'form' : 'forms'}`;
        } else {
            dom.portalFormsNav.removeAttribute('aria-label');
            dom.portalFormsNav.removeAttribute('title');
        }
    }

    if (dom.mobileMenuToggle) {
        dom.mobileMenuToggle.setAttribute(
            'aria-label',
            pendingCount > 0
                ? `Toggle menu, ${pendingCount} pending ${pendingCount === 1 ? 'form' : 'forms'}`
                : 'Toggle menu'
        );
    }
}

function renderPortalForms(forms = [], emptyMessage = 'No forms available.') {
    if (!dom.portalFormsTable) return;
    updatePortalFormsNotification(forms);
    resetTable(dom.portalFormsTable);

    if (!forms.length) {
        const emptyRow = document.createElement('div');
        emptyRow.className = 'table-row table-empty portal-forms';
        emptyRow.innerHTML = `<span>${escapeHtml(emptyMessage)}</span><span></span><span></span><span></span><span></span>`;
        dom.portalFormsTable.appendChild(emptyRow);
        return;
    }

    forms.forEach((formRequest) => {
        const completed = formRequest.status === 'completed';
        const row = document.createElement('div');
        row.className = 'table-row portal-forms';
        row.innerHTML = `
            <span>${escapeHtml(formRequest.template_name || 'Customer form')}</span>
            <span>${completed ? 'Completed' : 'Pending'}</span>
            <span>${escapeHtml(formatDateWithYear(formRequest.sent_at))}</span>
            <span>${escapeHtml(formatDateWithYear(formRequest.completed_at) || '--')}</span>
            <div class="row-actions"><button class="btn ${completed ? 'btn-outline' : 'btn-primary'} btn-small" type="button" data-action="open-portal-form" data-id="${formRequest.id}">${completed ? 'View' : 'Complete'}</button></div>
        `;
        dom.portalFormsTable.appendChild(row);
    });
}

async function loadPortalForms() {
    if (!dom.portalFormsTable) return;
    try {
        const response = await api.get('/api/portal/forms');
        state.portalForms = response?.data?.data ?? [];
        renderPortalForms(state.portalForms);
    } catch (error) {
        state.portalForms = [];
        renderPortalForms([], getErrorMessage(error, 'Unable to load forms.'));
    }
}

function renderPortalFormFields(formRequest) {
    if (!dom.portalFormFields || !dom.portalFormPanel || !dom.portalCustomerForm) return;
    const completed = formRequest.status === 'completed';
    const questions = Array.isArray(formRequest.form_schema) ? formRequest.form_schema : [];
    const answers = formRequest.answers || {};

    dom.portalFormFields.innerHTML = questions.map((question) => {
        const key = String(question.key || '');
        const label = `${escapeHtml(question.label || key)}${question.required ? ' *' : ''}`;
        const value = answers[key] ?? '';
        const disabled = completed ? ' disabled' : '';
        const common = `data-form-answer-key="${escapeHtml(key)}"${question.required ? ' required' : ''}${disabled}`;

        if (question.type === 'textarea') {
            return `<label class="field"><span>${label}</span><textarea rows="5" ${common}>${escapeHtml(value)}</textarea></label>`;
        }
        if (question.type === 'select') {
            const options = (question.options || []).map((option) => `
                <option value="${escapeHtml(option)}"${String(value) === String(option) ? ' selected' : ''}>${escapeHtml(option)}</option>
            `).join('');
            return `<label class="field"><span>${label}</span><select ${common}><option value="">Select an option</option>${options}</select></label>`;
        }
        if (question.type === 'checkbox') {
            return `<label class="check-row"><input type="checkbox" ${common}${value ? ' checked' : ''}><span>${label}</span></label>`;
        }

        const inputType = ['number', 'date'].includes(question.type) ? question.type : 'text';
        const step = inputType === 'number' ? ' step="any"' : '';
        return `<label class="field"><span>${label}</span><input type="${inputType}" value="${escapeHtml(value)}"${step} ${common}></label>`;
    }).join('');

    state.currentPortalForm = formRequest;
    if (dom.portalFormTitle) dom.portalFormTitle.textContent = formRequest.template_name || 'Customer form';
    if (dom.portalFormSubtitle) {
        dom.portalFormSubtitle.textContent = completed
            ? `Completed ${formatDateWithYear(formRequest.completed_at)}`
            : 'Complete all required fields and submit once.';
    }
    if (dom.portalFormSubmit) dom.portalFormSubmit.hidden = completed;
    setFormStatus(dom.portalFormStatus, completed ? 'This form has been completed and is read-only.' : '');
    dom.portalFormPanel.hidden = false;
}

function closePortalFormModal() {
    state.currentPortalForm = null;
    if (dom.portalFormPanel) dom.portalFormPanel.hidden = true;
    if (dom.portalFormFields) dom.portalFormFields.innerHTML = '';
    setFormStatus(dom.portalFormStatus, '');
}

async function openPortalForm(formId) {
    if (!formId) return;
    try {
        const response = await api.get(`/api/portal/forms/${formId}`);
        const formRequest = response?.data?.data;
        if (formRequest) renderPortalFormFields(formRequest);
    } catch (error) {
        showToast(getErrorMessage(error, 'Unable to open form.'), true);
    }
}

async function handlePortalCustomerFormSubmit(event) {
    event.preventDefault();
    const formRequest = state.currentPortalForm;
    if (!formRequest || formRequest.status === 'completed' || !dom.portalFormFields) return;
    if (!window.confirm('Are you sure you are happy to submit?')) return;

    const answers = {};
    dom.portalFormFields.querySelectorAll('[data-form-answer-key]').forEach((field) => {
        const key = field.dataset.formAnswerKey;
        answers[key] = field.type === 'checkbox' ? field.checked : field.value;
    });

    if (dom.portalFormSubmit) dom.portalFormSubmit.disabled = true;
    setFormStatus(dom.portalFormStatus, 'Submitting form...');
    try {
        const response = await api.post(`/api/portal/forms/${formRequest.id}/submit`, { answers });
        const completedForm = response?.data?.data;
        if (completedForm) {
            const index = state.portalForms.findIndex((item) => item.id === completedForm.id);
            if (index >= 0) state.portalForms[index] = completedForm;
            renderPortalForms(state.portalForms);
            renderPortalFormFields(completedForm);
        }
        showToast('Form submitted');
    } catch (error) {
        setFormStatus(dom.portalFormStatus, getErrorMessage(error, 'Unable to submit form.'), true);
    } finally {
        if (dom.portalFormSubmit) dom.portalFormSubmit.disabled = false;
    }
}

function clearInvoiceLineItems() {
    if (dom.invoiceLineItems) {
        dom.invoiceLineItems.innerHTML = '';
    }
}

function getInvoiceCustomerId() {
    const raw = dom.invoiceCustomerSelect?.value || '';
    const id = Number(raw);
    return Number.isFinite(id) && id > 0 ? id : null;
}

function getInvoiceBillableCache(type) {
    return type === 'subscription'
        ? state.invoiceBillables.subscriptionsByCustomer
        : state.invoiceBillables.jobsByCustomer;
}

function clearInvoiceBillableCache(type = null) {
    if (!type || type === 'job') {
        state.invoiceBillables.jobsByCustomer = {};
    }
    if (!type || type === 'subscription') {
        state.invoiceBillables.subscriptionsByCustomer = {};
    }
}

async function loadInvoiceBillables(type, customerId) {
    if (!customerId || !['job', 'subscription'].includes(type)) {
        return [];
    }

    const cache = getInvoiceBillableCache(type);
    if (cache[customerId]) {
        return cache[customerId];
    }

    const endpoint = type === 'job' ? '/api/jobs' : '/api/subscriptions';
    const perPage = 100;
    let page = 1;
    let lastPage = 1;
    const items = [];

    do {
        const query = buildQuery({ customer_id: customerId, per_page: perPage, page });
        const response = await api.get(`${endpoint}${query}`);
        const pageItems = response?.data?.data ?? [];
        items.push(...pageItems);
        const meta = response?.data?.meta || {};
        lastPage = meta.last_page ?? page;
        page += 1;
    } while (page <= lastPage);

    cache[customerId] = items;
    return items;
}

function applySelectedInvoiceBillableDetails(row, billableType, billables) {
    const descriptionInput = row.querySelector('input[name="description"]');
    const unitPriceInput = row.querySelector('input[name="unit_price"]');
    const billableIdInput = row.querySelector('select[name="billable_id"]');
    if (!descriptionInput || !unitPriceInput || !billableIdInput) return;

    const selectedId = Number(billableIdInput.value);
    const selectedBillable = billables.find((item) => Number(item.id) === selectedId);

    descriptionInput.readOnly = billableType === 'job';

    if (!selectedBillable) {
        return;
    }

    if (billableType === 'job') {
        descriptionInput.value = String(selectedBillable.description || '');
        unitPriceInput.value = normalizeQuantityDisplay(selectedBillable.cost || 0);
        return;
    }

    if (billableType === 'subscription') {
        if (!descriptionInput.value.trim()) {
            descriptionInput.value = String(selectedBillable.description || '');
        }
        if (!String(unitPriceInput.value || '').trim()) {
            unitPriceInput.value = normalizeQuantityDisplay(selectedBillable.monthly_cost || 0);
        }
    }
}

async function syncInvoiceLineItemBillableState(row, preferredBillableId = null) {
    if (!row) return;

    const descriptionInput = row.querySelector('input[name="description"]');
    const billableTypeInput = row.querySelector('select[name="billable_type"]');
    const billableIdInput = row.querySelector('select[name="billable_id"]');

    if (!descriptionInput || !billableTypeInput || !billableIdInput) {
        return;
    }

    const billableType = billableTypeInput.value;
    const currentId = String(preferredBillableId ?? billableIdInput.value ?? '');

    billableIdInput.innerHTML = '';

    if (billableType !== 'job' && billableType !== 'subscription') {
        const option = document.createElement('option');
        option.value = '';
        option.textContent = 'Manual line item';
        billableIdInput.appendChild(option);
        billableIdInput.disabled = true;
        descriptionInput.readOnly = false;
        return;
    }

    const customerId = getInvoiceCustomerId();
    if (!customerId) {
        const option = document.createElement('option');
        option.value = '';
        option.textContent = 'Select customer first';
        billableIdInput.appendChild(option);
        billableIdInput.disabled = true;
        descriptionInput.readOnly = billableType === 'job';
        return;
    }

    const loadingOption = document.createElement('option');
    loadingOption.value = '';
    loadingOption.textContent = billableType === 'job' ? 'Loading jobs...' : 'Loading subscriptions...';
    billableIdInput.appendChild(loadingOption);
    billableIdInput.disabled = true;

    try {
        const billables = await loadInvoiceBillables(billableType, customerId);
        billableIdInput.innerHTML = '';

        const placeholderOption = document.createElement('option');
        placeholderOption.value = '';
        placeholderOption.textContent = billableType === 'job' ? 'Select job' : 'Select subscription';
        billableIdInput.appendChild(placeholderOption);

        billables.forEach((billable) => {
            const option = document.createElement('option');
            option.value = String(billable.id);
            const amount = billableType === 'job'
                ? Number(billable.cost || 0)
                : Number(billable.monthly_cost || 0);
            option.textContent = `#${billable.id} - ${truncate(billable.description, 46)} (${formatCurrency(amount)})`;
            billableIdInput.appendChild(option);
        });

        if (currentId && billables.some((billable) => String(billable.id) === currentId)) {
            billableIdInput.value = currentId;
        } else {
            billableIdInput.value = '';
        }

        billableIdInput.disabled = false;
        applySelectedInvoiceBillableDetails(row, billableType, billables);
    } catch (error) {
        billableIdInput.innerHTML = '';
        const option = document.createElement('option');
        option.value = '';
        option.textContent = billableType === 'job' ? 'Unable to load jobs' : 'Unable to load subscriptions';
        billableIdInput.appendChild(option);
        billableIdInput.disabled = true;
        descriptionInput.readOnly = billableType === 'job';
    }
}

function refreshInvoiceLineItemBillables() {
    if (!dom.invoiceLineItems) return;
    const rows = Array.from(dom.invoiceLineItems.querySelectorAll('.line-item'));
    rows.forEach((row) => {
        const billableIdInput = row.querySelector('select[name="billable_id"]');
        syncInvoiceLineItemBillableState(row, billableIdInput?.value ?? '');
    });
}

function addInvoiceLineItem(item = {}) {
    const template = document.getElementById('invoice-line-item-template');
    if (!template || !dom.invoiceLineItems) return;
    const clone = template.content.cloneNode(true);
    const row = clone.querySelector('.line-item');
    if (!row) return;

    row.querySelector('input[name="description"]').value = item.description || '';
    row.querySelector('input[name="quantity"]').value = normalizeQuantityDisplay(item.quantity || 1);
    row.querySelector('input[name="unit_price"]').value = item.unit_price || '';
    const billableTypeInput = row.querySelector('select[name="billable_type"]');
    const billableIdInput = row.querySelector('select[name="billable_id"]');
    billableTypeInput.value = item.billable_type || '';
    if (item.billable_id) {
        billableIdInput.value = String(item.billable_id);
    }
    syncInvoiceLineItemBillableState(row, item.billable_id || '');

    billableTypeInput.addEventListener('change', () => {
        syncInvoiceLineItemBillableState(row, '');
    });

    billableIdInput.addEventListener('change', () => {
        const customerId = getInvoiceCustomerId();
        const billableType = billableTypeInput.value;
        if (!customerId || !['job', 'subscription'].includes(billableType)) return;
        const cache = getInvoiceBillableCache(billableType);
        const billables = cache[customerId] || [];
        applySelectedInvoiceBillableDetails(row, billableType, billables);
    });

    row.addEventListener('click', (event) => {
        const button = event.target.closest('[data-action="remove-line-item"]');
        if (!button) return;
        row.remove();
    });

    dom.invoiceLineItems.appendChild(clone);
}

function normalizeQuantityDisplay(value) {
    const quantity = Number(value);
    if (Number.isNaN(quantity)) return '';

    if (Math.abs(quantity - Math.round(quantity)) < 0.00001) {
        return String(Math.round(quantity));
    }

    return quantity.toFixed(2).replace(/\.?0+$/, '');
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
        const billableIdRaw = row.querySelector('select[name="billable_id"]').value;
        const billableId = billableType ? (billableIdRaw ? Number(billableIdRaw) : null) : null;

        if (!description) {
            continue;
        }
        const scaledQuantity = quantity * 2;
        const isHalfStepQuantity = Math.abs(scaledQuantity - Math.round(scaledQuantity)) < 0.00001;
        if (!quantity || quantity < 0.5 || Number.isNaN(quantity) || !isHalfStepQuantity) {
            throw new Error('Line item quantity must be a whole number or end in .5.');
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
    updateInvoicePaidControls();
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
            payment_view: isViewingPaidInvoices() ? 'paid' : 'current',
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
        emptyRow.innerHTML = `<span>${isViewingPaidInvoices() ? 'No paid invoices yet.' : 'No current invoices yet.'}</span><span></span><span></span><span></span><span></span><span></span>`;
        dom.invoicesTable.appendChild(emptyRow);
        return;
    }

    state.invoices.forEach((invoice) => {
        const row = document.createElement('div');
        row.className = 'table-row invoices';
        const isPaid = invoice.status === 'paid';
        const displayStatus = invoice.effective_status || invoice.status || 'draft';
        const nextStatus = isPaid ? 'unpaid' : 'paid';
        const paymentActionLabel = isPaid ? 'Mark unpaid' : 'Mark paid';
        row.innerHTML = `
            <span>#${escapeHtml(invoice.invoice_number)}</span>
            <span>${escapeHtml(invoice.customer?.name || getCustomerName(invoice.customer_id))}</span>
            <span>${formatCurrency(Number(invoice.total))}</span>
            <span>${escapeHtml(displayStatus)}</span>
            <span>${formatDate(invoice.due_date)}</span>
            <div class="row-actions">
                <button class="btn btn-outline btn-small" data-action="toggle-payment" data-id="${invoice.id}" data-next-status="${nextStatus}">${paymentActionLabel}</button>
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
            showToast('Invoice successfully sent');
            await loadInvoices();
        } catch (error) {
            showToast('Unable to send invoice', true);
            setFormStatus(dom.invoiceFormStatus, getErrorMessage(error, 'Unable to send invoice.'), true);
        }
    }

    if (action === 'toggle-payment' && id) {
        const nextStatus = actionButton.dataset.nextStatus;

        if (nextStatus !== 'paid' && nextStatus !== 'unpaid') {
            return;
        }

        actionButton.disabled = true;

        try {
            const payload = { payment_status: nextStatus };

            try {
                await api.patch(`/api/invoices/${id}/payment`, payload);
            } catch (error) {
                const statusCode = Number(error?.response?.status || 0);

                // Fallback for shared-host setups that do not pass PATCH requests cleanly.
                if (statusCode !== 404 && statusCode !== 405) {
                    throw error;
                }

                await api.post(`/api/invoices/${id}/payment`, payload);
            }

            showToast(nextStatus === 'paid' ? 'Invoice marked paid' : 'Invoice marked unpaid');
            await loadInvoices();
        } catch (error) {
            showToast('Unable to update invoice status', true);
            setFormStatus(dom.invoiceFormStatus, getErrorMessage(error, 'Unable to update invoice status.'), true);
        } finally {
            actionButton.disabled = false;
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

async function handlePortalInvoiceAction(event) {
    const actionButton = event.target.closest('[data-action]');
    if (!actionButton) return;

    const invoiceId = Number(actionButton.dataset.id);
    const action = actionButton.dataset.action;
    const nextStatus = actionButton.dataset.nextStatus;
    const invoice = state.portalInvoices.find((item) => item.id === invoiceId);

    if (!invoiceId || !action) {
        return;
    }

    if (action === 'portal-download-invoice' && invoice) {
        await downloadInvoice(invoice.id, `Invoice-${invoice.invoice_number}.pdf`, true);
        return;
    }

    if (action !== 'portal-toggle-payment') {
        return;
    }

    if (nextStatus !== 'paid' && nextStatus !== 'unpaid') {
        return;
    }

    actionButton.disabled = true;

    try {
        const payload = { payment_status: nextStatus };

        try {
            await api.patch(`/api/portal/invoices/${invoiceId}/payment`, payload);
        } catch (error) {
            const statusCode = Number(error?.response?.status || 0);

            // Fallback for shared-host setups that do not pass PATCH requests cleanly.
            if (statusCode !== 404 && statusCode !== 405) {
                throw error;
            }

            await api.post(`/api/portal/invoices/${invoiceId}/payment`, payload);
        }

        showToast(nextStatus === 'paid' ? 'Invoice marked paid' : 'Invoice marked unpaid');
        await loadPortalInvoices();
    } catch (error) {
        showToast('Unable to update invoice status', true);
    } finally {
        actionButton.disabled = false;
    }
}

function initializeNavigation() {
    const adminOnlyViews = ['customers', 'jobs', 'subscriptions', 'costs', 'proposals', 'invoices', 'monthly-finance', 'proposal-form-edit', 'customer-form-edit', 'staff-tracking'];
    const staffOnlyViews = ['tasks', 'monthly-tasks'];

    dom.navItems.forEach((item) => {
        item.addEventListener('click', (event) => {
            event.preventDefault();
            if (state.role === 'customer' && !['portal', 'portal-proposals', 'portal-forms', 'portal-support', 'portal-admin'].includes(item.dataset.view)) return;
            if (adminOnlyViews.includes(item.dataset.view) && state.role !== 'admin') return;
            if (item.dataset.view === 'monthly-tasks' && state.role !== 'staff') return;
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
                if (adminOnlyViews.includes(button.dataset.goView) && state.role !== 'admin') {
                    return;
                }
                if (button.dataset.goView === 'monthly-tasks' && state.role !== 'staff') {
                    return;
                }
                if (state.role === 'customer' && !['portal', 'portal-proposals', 'portal-forms', 'portal-support', 'portal-admin'].includes(button.dataset.goView)) {
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

if (dom.forgotPasswordLink) {
    dom.forgotPasswordLink.addEventListener('click', () => setAuthMode('forgot'));
}

if (dom.forgotPasswordBack) {
    dom.forgotPasswordBack.addEventListener('click', () => setAuthMode('login'));
}

if (dom.forgotPasswordForm) {
    dom.forgotPasswordForm.addEventListener('submit', handleForgotPassword);
}

if (dom.resetPasswordBack) {
    dom.resetPasswordBack.addEventListener('click', () => {
        window.history.replaceState({}, document.title, window.location.pathname);
        setAuthMode('login');
    });
}

if (dom.resetPasswordForm) {
    dom.resetPasswordForm.addEventListener('submit', handleResetPassword);
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

if (dom.smtp2goSettingsForm) {
    dom.smtp2goSettingsForm.addEventListener('submit', handleSmtp2goSettingsSubmit);
}

if (dom.invoiceSettingsForm) {
    dom.invoiceSettingsForm.addEventListener('submit', handleInvoiceSettingsSubmit);
}

if (dom.proposalFormsSettingsForm) {
    dom.proposalFormsSettingsForm.addEventListener('submit', handleProposalFormsSettingsSubmit);
}

if (dom.customerFormsSettingsForm) {
    dom.customerFormsSettingsForm.addEventListener('submit', handleCustomerFormsSettingsSubmit);
}

if (dom.customerFormsAddType) {
    dom.customerFormsAddType.addEventListener('click', () => {
        const nextIndex = (state.customerFormSettings.types || []).length;
        state.customerFormSettings.types = [
            ...(state.customerFormSettings.types || []),
            { label: 'New customer form', slug: '', questions: [] },
        ];
        state.editing.customerFormTypeIndex = nextIndex;
        setActiveView('customer-form-edit');
    });
}

if (dom.customerFormsEditor) {
    dom.customerFormsEditor.addEventListener('click', (event) => {
        const button = event.target.closest('[data-action="edit-customer-form-type"]');
        if (!button) return;
        const typeIndex = Number(button.dataset.typeIndex);
        if (Number.isNaN(typeIndex)) return;
        state.editing.customerFormTypeIndex = typeIndex;
        setActiveView('customer-form-edit');
    });
}

if (dom.customerFormEditBack) {
    dom.customerFormEditBack.addEventListener('click', () => {
        state.editing.customerFormTypeIndex = null;
        setActiveView('admin');
    });
}

if (dom.customerFormEditAddQuestion) {
    dom.customerFormEditAddQuestion.addEventListener('click', () => {
        state.customerFormSettings = collectCustomerFormsEditorPayload();
        const typeIndex = Number(state.editing.customerFormTypeIndex);
        if (Number.isNaN(typeIndex) || !state.customerFormSettings.types[typeIndex]) return;
        state.customerFormSettings.types[typeIndex].questions = [
            ...(state.customerFormSettings.types[typeIndex].questions || []),
            { label: 'New question', type: 'text', required: false, options: [] },
        ];
        renderCustomerFormEdit();
    });
}

if (dom.customerFormEditDelete) {
    dom.customerFormEditDelete.addEventListener('click', async () => {
        const typeIndex = Number(state.editing.customerFormTypeIndex);
        if (Number.isNaN(typeIndex) || !state.customerFormSettings.types[typeIndex]) return;
        if (!window.confirm('Delete this customer form?')) return;
        state.customerFormSettings.types.splice(typeIndex, 1);
        try {
            const response = await api.put('/api/admin/customer-forms', { types: state.customerFormSettings.types });
            state.customerFormSettings = response?.data?.data ?? { types: [] };
            state.editing.customerFormTypeIndex = null;
            renderCustomerFormsEditor();
            showToast('Customer form deleted');
            setActiveView('admin');
        } catch (error) {
            setFormStatus(dom.customerFormEditStatus, getErrorMessage(error, 'Unable to delete customer form.'), true);
        }
    });
}

if (dom.customerFormEditEditor) {
    dom.customerFormEditEditor.addEventListener('click', (event) => {
        const button = event.target.closest('[data-action="remove-customer-form-question"]');
        if (!button) return;
        state.customerFormSettings = collectCustomerFormsEditorPayload();
        const typeIndex = Number(state.editing.customerFormTypeIndex);
        const questionIndex = Number(button.closest('[data-question-index]')?.dataset.questionIndex);
        if (Number.isNaN(typeIndex) || Number.isNaN(questionIndex)) return;
        state.customerFormSettings.types[typeIndex].questions.splice(questionIndex, 1);
        renderCustomerFormEdit();
    });
}

if (dom.proposalFormsAddType) {
    dom.proposalFormsAddType.addEventListener('click', () => {
        const nextIndex = (state.proposalFormSettings.types || []).length;
        state.proposalFormSettings.types = [
            ...(state.proposalFormSettings.types || []),
            { label: 'New proposal type', slug: '', questions: [] },
        ];
        state.editing.proposalFormTypeIndex = nextIndex;
        setActiveView('proposal-form-edit');
    });
}

if (dom.proposalFormsEditor) {
    dom.proposalFormsEditor.addEventListener('click', (event) => {
        const button = event.target.closest('[data-action]');
        if (!button) return;

        if (button.dataset.action === 'edit-proposal-form-type') {
            const typeIndex = Number(button.dataset.typeIndex);
            if (Number.isNaN(typeIndex)) return;

            state.editing.proposalFormTypeIndex = typeIndex;
            setActiveView('proposal-form-edit');
        }
    });
}

if (dom.proposalFormEditBack) {
    dom.proposalFormEditBack.addEventListener('click', () => {
        state.editing.proposalFormTypeIndex = null;
        setActiveView('admin');
    });
}

if (dom.proposalFormEditAddQuestion) {
    dom.proposalFormEditAddQuestion.addEventListener('click', () => {
        state.proposalFormSettings = collectProposalFormsEditorPayload();
        const typeIndex = Number(state.editing.proposalFormTypeIndex);
        if (Number.isNaN(typeIndex) || !state.proposalFormSettings.types[typeIndex]) return;

        state.proposalFormSettings.types[typeIndex].questions = [
            ...(state.proposalFormSettings.types[typeIndex].questions || []),
            { label: 'New question', type: 'text', required: false, options: [] },
        ];
        renderProposalFormEdit();
    });
}

if (dom.proposalFormEditDelete) {
    dom.proposalFormEditDelete.addEventListener('click', async () => {
        const typeIndex = Number(state.editing.proposalFormTypeIndex);
        if (Number.isNaN(typeIndex) || !state.proposalFormSettings.types[typeIndex]) return;
        if (!window.confirm('Delete this proposal form?')) return;

        state.proposalFormSettings.types.splice(typeIndex, 1);

        try {
            const response = await api.put('/api/admin/proposal-forms', {
                types: state.proposalFormSettings.types,
            });
            state.proposalFormSettings = response?.data?.data ?? { types: [] };
            state.editing.proposalFormTypeIndex = null;
            renderProposalTypeOptions();
            renderProposalFormsEditor();
            showToast('Proposal form deleted');
            setActiveView('admin');
        } catch (error) {
            setFormStatus(dom.proposalFormEditStatus, getErrorMessage(error, 'Unable to delete proposal form.'), true);
        }
    });
}

if (dom.proposalFormEditEditor) {
    dom.proposalFormEditEditor.addEventListener('click', (event) => {
        const button = event.target.closest('[data-action]');
        if (!button || button.dataset.action !== 'remove-proposal-question') return;

        state.proposalFormSettings = collectProposalFormsEditorPayload();
        const typeIndex = Number(state.editing.proposalFormTypeIndex);
        const questionRow = button.closest('[data-question-index]');
        const questionIndex = Number(questionRow?.dataset.questionIndex);
        if (Number.isNaN(typeIndex) || Number.isNaN(questionIndex)) return;

        state.proposalFormSettings.types[typeIndex].questions.splice(questionIndex, 1);
        renderProposalFormEdit();
    });
}

if (dom.profileForm) {
    dom.profileForm.addEventListener('submit', handleProfileSubmit);
}

if (dom.portalProfileForm) {
    dom.portalProfileForm.addEventListener('submit', handlePortalProfileSubmit);
}

if (dom.passwordForm) {
    dom.passwordForm.addEventListener('submit', handlePasswordSubmit);
}

if (dom.portalPasswordForm) {
    dom.portalPasswordForm.addEventListener('submit', handlePortalPasswordSubmit);
}

if (dom.portalSupportForm) {
    dom.portalSupportForm.addEventListener('submit', handlePortalSupportSubmit);
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

if (dom.customersArchivedToggle) {
    dom.customersArchivedToggle.addEventListener('click', () => {
        setCustomersArchivedMode(!isViewingArchivedCustomers());
    });
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
        state.filters.customers.archived = false;
        updateCustomerArchiveControls();
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

if (dom.customerFormRequestForm) {
    dom.customerFormRequestForm.addEventListener('submit', handleCustomerFormRequestSubmit);
}

if (dom.customerFormsTable) {
    dom.customerFormsTable.addEventListener('click', (event) => {
        const button = event.target.closest('[data-action="view-customer-form"]');
        if (!button) return;
        const formRequest = state.customerForms.find((item) => item.id === Number(button.dataset.id));
        renderCustomerFormReview(formRequest || null);
    });
}

if (dom.customerFormReview) {
    dom.customerFormReview.addEventListener('click', (event) => {
        if (event.target.closest('[data-action="close-customer-form-review"]')) {
            renderCustomerFormReview(null);
        }
    });
}

if (dom.customerDetailBack) {
    dom.customerDetailBack.addEventListener('click', () => setActiveView('customers'));
}

if (dom.customerDetailArchive) {
    dom.customerDetailArchive.addEventListener('click', handleCustomerArchiveToggle);
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

if (dom.jobsArchivedToggle) {
    dom.jobsArchivedToggle.addEventListener('click', () => {
        setJobsArchivedMode(!isViewingArchivedJobs());
    });
}

if (dom.jobPhotoJobSelect) {
    dom.jobPhotoJobSelect.addEventListener('change', () => {
        state.editing.jobPhotoJobId = Number(dom.jobPhotoJobSelect.value || 0) || null;
        setFormStatus(dom.jobPhotoUploadStatus, '');
        loadJobPhotos();
    });
}

if (dom.jobPhotoUploadForm) {
    dom.jobPhotoUploadForm.addEventListener('submit', handleJobPhotoUploadSubmit);
}

if (dom.jobPhotosTable) {
    dom.jobPhotosTable.addEventListener('click', handleJobPhotoAction);
}

if (dom.jobPhotosRefresh) {
    dom.jobPhotosRefresh.addEventListener('click', loadJobPhotos);
}

if (dom.jobPhotosDownloadAll) {
    dom.jobPhotosDownloadAll.addEventListener('click', downloadAllJobPhotos);
}

if (dom.jobsLoadMore) {
    dom.jobsLoadMore.addEventListener('click', () => loadJobs(true));
}

if (dom.costForm) {
    dom.costForm.addEventListener('submit', handleCostSubmit);
    toggleCostRecurringFields();
}

if (dom.costRecurringSelect) {
    dom.costRecurringSelect.addEventListener('change', toggleCostRecurringFields);
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
        state.filters.jobs.archived = false;
        updateJobArchiveControls();
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

if (dom.taskForm) {
    dom.taskForm.addEventListener('submit', handleTaskSubmit);
}

if (dom.taskFormCancel) {
    dom.taskFormCancel.addEventListener('click', resetTaskForm);
}

if (dom.tasksTable) {
    dom.tasksTable.addEventListener('click', handleTaskAction);
}

if (dom.tasksRefresh) {
    dom.tasksRefresh.addEventListener('click', loadTasks);
}

if (dom.tasksFilterStatus) {
    dom.tasksFilterStatus.addEventListener('change', () => {
        state.filters.tasks.status = dom.tasksFilterStatus.value;
        loadTasks();
    });
}

if (dom.tasksFilterStaff) {
    dom.tasksFilterStaff.addEventListener('change', () => {
        state.filters.tasks.staff = dom.tasksFilterStaff.value;
        loadTasks();
    });
}

if (dom.tasksClear) {
    dom.tasksClear.addEventListener('click', () => {
        if (dom.tasksFilterStatus) dom.tasksFilterStatus.value = 'all';
        if (dom.tasksFilterStaff) dom.tasksFilterStaff.value = 'all';
        state.filters.tasks.status = 'all';
        state.filters.tasks.staff = 'all';
        loadTasks();
    });
}

if (dom.proposalForm) {
    dom.proposalForm.addEventListener('submit', handleProposalSubmit);
}

if (dom.proposalFormCancel) {
    dom.proposalFormCancel.addEventListener('click', resetProposalForm);
}

if (dom.proposalCustomerSelect) {
    dom.proposalCustomerSelect.addEventListener('change', () => {
        setFormStatus(dom.proposalFormStatus, '');
    });
}

if (dom.proposalTypeSelect) {
    dom.proposalTypeSelect.addEventListener('change', () => {
        const selectedType = getSelectedProposalType();
        if (selectedType && !dom.proposalTitle?.value) {
            dom.proposalTitle.value = selectedType.label;
        }
        if (selectedType && dom.proposalLineItemDescription && !dom.proposalLineItemDescription.value) {
            dom.proposalLineItemDescription.value = selectedType.label;
        }
        renderProposalFormAnswers();
    });
}

if (dom.proposalsTable) {
    dom.proposalsTable.addEventListener('click', handleProposalAction);
}

if (dom.proposalsRefresh) {
    dom.proposalsRefresh.addEventListener('click', loadProposals);
}

if (dom.proposalsLoadMore) {
    dom.proposalsLoadMore.addEventListener('click', () => loadProposals(true));
}

if (dom.opportunityForm) dom.opportunityForm.addEventListener('submit', handleOpportunitySubmit);
if (dom.opportunityFollowUpForm) dom.opportunityFollowUpForm.addEventListener('submit', handleOpportunityFollowUpSubmit);
if (dom.opportunityFollowUpClose) dom.opportunityFollowUpClose.addEventListener('click', closeOpportunityFollowUp);
if (dom.opportunityFollowUpModal) dom.opportunityFollowUpModal.addEventListener('click', (event) => { if (event.target === dom.opportunityFollowUpModal) closeOpportunityFollowUp(); });
document.addEventListener('keydown', (event) => { if (event.key === 'Escape' && dom.opportunityFollowUpModal && !dom.opportunityFollowUpModal.hidden) closeOpportunityFollowUp(); });
if (dom.leadDiscoveryForm) dom.leadDiscoveryForm.addEventListener('submit', handleLeadDiscoverySubmit);
if (dom.leadDiscoveryRefresh) dom.leadDiscoveryRefresh.addEventListener('click', loadLeadDiscoveryData);
if (dom.leadDiscoveryContacted) dom.leadDiscoveryContacted.addEventListener('click', () => {
    state.showingContactedLeads = !state.showingContactedLeads;
    loadLeadDiscoveryData();
});
if (dom.discoveredLeadsTable) dom.discoveredLeadsTable.addEventListener('click', handleDiscoveredLeadAction);
if (dom.leadDiscoveryRuns) dom.leadDiscoveryRuns.addEventListener('click', handleDiscoveryRunAction);
if (dom.leadDetailBack) dom.leadDetailBack.addEventListener('click', () => handleLeadDetailAction('back'));
if (dom.leadDetailContacted) dom.leadDetailContacted.addEventListener('click', () => handleLeadDetailAction('contacted'));
if (dom.leadDetailConvert) dom.leadDetailConvert.addEventListener('click', () => handleLeadDetailAction('convert'));
if (dom.leadDetailAudit) dom.leadDetailAudit.addEventListener('click', () => handleLeadDetailAction('audit'));
if (dom.leadDetailDelete) dom.leadDetailDelete.addEventListener('click', () => handleLeadDetailAction('delete'));
if (dom.leadDetailFindings) dom.leadDetailFindings.addEventListener('change', handleLeadFindingChange);
if (dom.opportunityFormCancel) dom.opportunityFormCancel.addEventListener('click', resetOpportunityForm);
if (dom.opportunitiesTable) dom.opportunitiesTable.addEventListener('click', handleOpportunityAction);
if (dom.opportunitiesTable) dom.opportunitiesTable.addEventListener('change', handleOpportunitySelection);
if (dom.opportunitiesRefresh) dom.opportunitiesRefresh.addEventListener('click', loadRevenueOpportunities);
if (dom.opportunitiesBulkEdit) dom.opportunitiesBulkEdit.addEventListener('click', () => setOpportunityBulkEdit(!state.opportunityBulkEdit));
if (dom.opportunitiesBulkDelete) dom.opportunitiesBulkDelete.addEventListener('click', deleteSelectedOpportunities);
if (dom.opportunitiesRecommend) {
    dom.opportunitiesRecommend.addEventListener('click', async () => {
        dom.opportunitiesRecommend.disabled = true;
        try {
            const response = await api.post('/api/revenue-opportunities/recommend');
            showToast(`${response?.data?.created ?? 0} new opportunities found.`);
            await loadRevenueOpportunities();
        } catch (error) {
            showToast(getErrorMessage(error, 'Unable to find opportunities.'), true);
        } finally {
            dom.opportunitiesRecommend.disabled = false;
        }
    });
}
if (dom.opportunitiesFilterStatus) {
    dom.opportunitiesFilterStatus.addEventListener('change', () => {
        state.filters.revenueOpportunities.status = dom.opportunitiesFilterStatus.value;
        loadRevenueOpportunities();
    });
}
if (dom.opportunitiesFilterType) {
    dom.opportunitiesFilterType.addEventListener('change', () => {
        state.filters.revenueOpportunities.type = dom.opportunitiesFilterType.value;
        loadRevenueOpportunities();
    });
}

if (dom.proposalsFilterStatus) {
    dom.proposalsFilterStatus.addEventListener('change', () => {
        state.filters.proposals.status = dom.proposalsFilterStatus.value;
        loadProposals();
    });
}

if (dom.proposalsFilterCustomer) {
    dom.proposalsFilterCustomer.addEventListener('change', () => {
        state.filters.proposals.customer = dom.proposalsFilterCustomer.value;
        loadProposals();
    });
}

if (dom.proposalsClear) {
    dom.proposalsClear.addEventListener('click', () => {
        if (dom.proposalsFilterStatus) dom.proposalsFilterStatus.value = 'all';
        if (dom.proposalsFilterCustomer) dom.proposalsFilterCustomer.value = 'all';
        state.filters.proposals.status = 'all';
        state.filters.proposals.customer = 'all';
        loadProposals();
    });
}

if (dom.invoiceForm) {
    dom.invoiceForm.addEventListener('submit', handleInvoiceSubmit);
}

if (dom.invoiceCustomerSelect) {
    dom.invoiceCustomerSelect.addEventListener('change', () => {
        refreshInvoiceLineItemBillables();
    });
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

if (dom.invoicesPaidToggle) {
    dom.invoicesPaidToggle.addEventListener('click', () => {
        setInvoicesPaidMode(!isViewingPaidInvoices());
    });
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
        state.filters.invoices.paid = false;
        updateInvoicePaidControls();
        loadInvoices();
    });
}

if (dom.monthlyFinanceMonths) {
    dom.monthlyFinanceMonths.addEventListener('click', (event) => {
        const monthButton = event.target.closest('[data-month-start]');
        if (!monthButton) return;

        state.monthlyFinanceSelectedMonth = monthButton.dataset.monthStart || null;
        renderMonthlyFinanceMonths();
        renderMonthlyFinanceCards();
    });
}

if (dom.monthlyFinanceRefresh) {
    dom.monthlyFinanceRefresh.addEventListener('click', loadMonthlyFinance);
}

if (dom.monthlyTasksMonths) {
    dom.monthlyTasksMonths.addEventListener('click', (event) => {
        const monthButton = event.target.closest('[data-month-start]');
        if (!monthButton) return;
        state.monthlyTasksSelectedMonth = monthButton.dataset.monthStart || null;
        renderMonthlyTaskMonths();
        renderMonthlyTaskCards();
    });
}

if (dom.monthlyTasksRefresh) {
    dom.monthlyTasksRefresh.addEventListener('click', loadMonthlyTasks);
}

if (dom.staffTrackingRefresh) {
    dom.staffTrackingRefresh.addEventListener('click', loadStaffTracking);
}

if (dom.monthlyFinanceSettingsToggle) {
    dom.monthlyFinanceSettingsToggle.addEventListener('click', (event) => {
        event.preventDefault();
        const isOpen = dom.monthlyFinanceSettingsPopover ? dom.monthlyFinanceSettingsPopover.hidden : false;
        setMonthlyFinanceSettingsOpen(isOpen);
    });
}

if (dom.dashboardSettingsToggle) {
    dom.dashboardSettingsToggle.addEventListener('click', (event) => {
        event.preventDefault();
        const isOpen = dom.dashboardSettingsPopover ? dom.dashboardSettingsPopover.hidden : false;
        setDashboardSettingsOpen(isOpen);
    });
}

const monthlyFinanceToggleMap = {
    revenue: dom.monthlyFinanceToggleRevenue,
    costs: dom.monthlyFinanceToggleCosts,
    profit: dom.monthlyFinanceToggleProfit,
    tax: dom.monthlyFinanceToggleTax,
    owed: dom.monthlyFinanceToggleOwed,
};

Object.entries(monthlyFinanceToggleMap).forEach(([key, input]) => {
    if (!input) return;
    input.addEventListener('change', () => {
        state.monthlyFinanceBoxVisibility = {
            ...state.monthlyFinanceBoxVisibility,
            [key]: input.checked,
        };
        applyMonthlyFinanceBoxVisibility();
        saveMonthlyFinanceBoxVisibility();
    });
});

const dashboardToggleMap = {
    revenue: dom.dashboardToggleRevenue,
    costs: dom.dashboardToggleCosts,
    profit: dom.dashboardToggleProfit,
    jobs: dom.dashboardToggleJobs,
    subscriptions: dom.dashboardToggleSubscriptions,
    potential_mrr: dom.dashboardTogglePotentialMrr,
    pipeline_value: dom.dashboardTogglePipelineValue,
    open_opportunities: dom.dashboardToggleOpenOpportunities,
};

Object.entries(dashboardToggleMap).forEach(([key, input]) => {
    if (!input) return;
    input.addEventListener('change', () => {
        state.dashboardTileVisibility = {
            ...state.dashboardTileVisibility,
            [key]: input.checked,
        };
        applyDashboardTileVisibility();
        saveDashboardTileVisibility();
    });
});

document.addEventListener('click', (event) => {
    if (dom.monthlyFinanceSettingsPopover && dom.monthlyFinanceSettingsToggle
        && !dom.monthlyFinanceSettingsPopover.hidden) {
        const popoverClicked = dom.monthlyFinanceSettingsPopover.contains(event.target);
        const toggleClicked = dom.monthlyFinanceSettingsToggle.contains(event.target);
        if (!popoverClicked && !toggleClicked) {
            setMonthlyFinanceSettingsOpen(false);
        }
    }

    if (dom.dashboardSettingsPopover && dom.dashboardSettingsToggle
        && !dom.dashboardSettingsPopover.hidden) {
        const popoverClicked = dom.dashboardSettingsPopover.contains(event.target);
        const toggleClicked = dom.dashboardSettingsToggle.contains(event.target);
        if (!popoverClicked && !toggleClicked) {
            setDashboardSettingsOpen(false);
        }
    }
});

if (dom.portalDownloadLatest) {
    dom.portalDownloadLatest.addEventListener('click', handlePortalDownloadLatest);
}

if (invoiceTables.portal) {
    invoiceTables.portal.addEventListener('click', handlePortalInvoiceAction);
}

if (dom.portalProposalsRefresh) {
    dom.portalProposalsRefresh.addEventListener('click', loadPortalProposals);
}

if (dom.portalProposals) {
    dom.portalProposals.addEventListener('click', handlePortalProposalAction);
}

if (dom.portalFormsRefresh) {
    dom.portalFormsRefresh.addEventListener('click', loadPortalForms);
}

if (dom.portalFormsTable) {
    dom.portalFormsTable.addEventListener('click', (event) => {
        const button = event.target.closest('[data-action="open-portal-form"]');
        if (button) openPortalForm(Number(button.dataset.id));
    });
}

if (dom.portalCustomerForm) {
    dom.portalCustomerForm.addEventListener('submit', handlePortalCustomerFormSubmit);
}

if (dom.portalFormClose) {
    dom.portalFormClose.addEventListener('click', closePortalFormModal);
}

if (dom.portalFormPanel) {
    dom.portalFormPanel.addEventListener('click', (event) => {
        if (event.target === dom.portalFormPanel) closePortalFormModal();
    });
}

initializeInvoiceForm();
initializeNavigation();
applyStoredTheme();
initializePasswordResetMode();
applyMonthlyFinanceBoxVisibility();
applyDashboardTileVisibility();
renderSubscriptionMonths();
updateCustomerArchiveControls();
updateJobArchiveControls();
loadSession();
