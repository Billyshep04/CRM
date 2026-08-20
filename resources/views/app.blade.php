<!DOCTYPE html>
<html lang="en" data-theme="light">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>WebStamp CRM</title>
        <link rel="icon" type="image/x-icon" href="/favicon.ico?v=2">
        <link rel="icon" type="image/png" sizes="60x60" href="/favicon.png?v=2">
        <link rel="apple-touch-icon" href="/favicon.png?v=2">
        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@400;500;600;700&family=Manrope:wght@400;500;600;700&display=swap" rel="stylesheet">
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body data-auth="guest">
        <div class="ambient-gradient"></div>

        <div class="auth-shell guest-only">
            <div class="auth-card">
                <div class="auth-theme">
                    <button class="btn btn-ghost" type="button" data-theme-toggle>
                        <span class="theme-label">Light</span>
                    </button>
                </div>
                <div class="auth-brand">
                    <div class="logo-dot"></div>
                    <div>
                        <div class="brand-title">WebStamp CRM</div>
                        <div class="brand-subtitle">Client portal & invoicing suite</div>
                    </div>
                </div>

                <div class="auth-panel">
                    <div id="login-intro">
                        <h1>Welcome back</h1>
                        <p>Sign in to manage customers, subscriptions, and invoices.</p>
                    </div>
                    <div id="forgot-password-intro" hidden>
                        <h1>Reset password</h1>
                        <p>Enter your customer portal email and we will send you a reset link.</p>
                    </div>
                    <div id="reset-password-intro" hidden>
                        <h1>Choose a new password</h1>
                        <p>Enter and confirm your new customer portal password.</p>
                    </div>

                    <form id="login-form" class="form-stack">
                        <label class="field">
                            <span>Email</span>
                            <input type="email" name="email" placeholder="you@company.com" required>
                        </label>
                        <label class="field">
                            <span>Password</span>
                            <input type="password" name="password" placeholder="********" required>
                        </label>
                        <button type="button" class="auth-link" id="forgot-password-link">forgotten password?</button>
                        <div id="login-error" class="form-error"></div>
                        <button type="submit" class="btn btn-primary">Sign in</button>
                        <div class="form-hint">
                            Demo: admin@example.com / password
                        </div>
                    </form>

                    <form id="forgot-password-form" class="form-stack" hidden>
                        <label class="field">
                            <span>Email</span>
                            <input type="email" name="email" placeholder="you@company.com" required>
                        </label>
                        <div id="forgot-password-status" class="form-hint"></div>
                        <button type="submit" class="btn btn-primary">Send reset link</button>
                        <button type="button" class="auth-link" id="forgot-password-back">Back to sign in</button>
                    </form>

                    <form id="reset-password-form" class="form-stack" hidden>
                        <input type="hidden" name="token">
                        <label class="field">
                            <span>Email</span>
                            <input type="email" name="email" required>
                        </label>
                        <label class="field">
                            <span>New password</span>
                            <input type="password" name="password" minlength="8" required>
                        </label>
                        <label class="field">
                            <span>Confirm new password</span>
                            <input type="password" name="password_confirmation" minlength="8" required>
                        </label>
                        <div id="reset-password-status" class="form-hint"></div>
                        <button type="submit" class="btn btn-primary">Reset password</button>
                        <button type="button" class="auth-link" id="reset-password-back">Back to sign in</button>
                    </form>
                </div>
            </div>
        </div>

        <div class="app-shell auth-only">
            <aside class="sidebar">
                <div class="sidebar-header">
                    <div class="logo-dot"></div>
                    <div>
                        <div class="brand-title">WebStamp</div>
                        <div class="brand-subtitle">CRM Suite</div>
                    </div>
                </div>
                <nav class="nav-stack">
                    <a href="#" class="nav-item active" data-view="dashboard">Dashboard</a>
                    <a href="#" class="nav-item staff-only" data-view="customers">Customers</a>
                    <a href="#" class="nav-item staff-only" data-view="lead-discovery">Lead Discovery</a>
                    <a href="#" class="nav-item staff-only" data-view="revenue-opportunities">Revenue Opportunities</a>
                    <a href="#" class="nav-item staff-only" data-view="jobs">Jobs</a>
                    <a href="#" class="nav-item staff-only" data-view="websites">Websites</a>
                    <a href="#" class="nav-item staff-only" data-view="subscriptions">Subscriptions</a>
                    <a href="#" class="nav-item staff-only" data-view="costs">Costs</a>
                    <a href="#" class="nav-item staff-only" data-view="proposals">Proposals</a>
                    <a href="#" class="nav-item staff-only" data-view="invoices">Invoices</a>
                    <a href="#" class="nav-item staff-only" data-view="tasks">Tasks</a>
                    <a href="#" class="nav-item admin-only" data-view="monthly-finance">Monthly Finance</a>
                    <a href="#" class="nav-item staff-member-only" data-view="monthly-tasks">Monthly Tasks</a>
                    <a href="#" class="nav-item admin-only" data-view="staff-tracking">Staff</a>
                    <a href="#" class="nav-item staff-only" data-view="admin">Admin</a>
                    <a href="#" class="nav-item customer-only" data-view="portal">My Portal</a>
                    <a href="#" class="nav-item customer-only" data-view="portal-websites">Websites</a>
                    <a href="#" class="nav-item customer-only" data-view="portal-proposals" id="portal-proposals-nav">
                        Proposals
                        <span class="portal-forms-notification" id="portal-proposals-notification" aria-hidden="true" hidden></span>
                    </a>
                    <a href="#" class="nav-item customer-only" data-view="portal-forms" id="portal-forms-nav">
                        Forms
                        <span class="portal-forms-notification" id="portal-forms-notification" aria-hidden="true" hidden></span>
                    </a>
                    <a href="#" class="nav-item customer-only" data-view="portal-support">Support</a>
                    <a href="#" class="nav-item customer-only" data-view="portal-admin">Admin</a>
                    <button type="button" class="nav-item nav-logout" id="logout-button-mobile">Logout</button>
                </nav>
                <div class="sidebar-footer">
                    <div class="status-card">
                        <div class="status-label">Sync Status</div>
                        <div class="status-value" id="sync-status">Connected</div>
                    </div>
                </div>
            </aside>
            <main class="main">
                <header class="topbar">
                    <div class="topbar-left">
                        <button class="btn btn-ghost menu-toggle" id="mobile-menu-toggle" type="button" aria-label="Toggle menu" aria-expanded="false">
                            <span class="menu-icon" aria-hidden="true">
                                <span></span>
                                <span></span>
                                <span></span>
                            </span>
                            <span class="mobile-menu-notification" id="mobile-menu-notification" aria-hidden="true" hidden></span>
                        </button>
                        <div>
                            <div class="page-title" id="page-title">Dashboard</div>
                            <div class="page-subtitle" id="page-subtitle">Overview and performance snapshots.</div>
                        </div>
                    </div>
                    <div class="topbar-right">
                        <button class="btn btn-ghost" type="button" data-theme-toggle>
                            <span class="theme-label">Light</span>
                        </button>
                        <a href="#" class="logo-slot" data-go-view="admin" aria-label="Open admin settings">
                            <img id="brand-logo" alt="Brand logo" />
                        </a>
                        <div class="user-menu">
                            <div class="user-info">
                                <div class="user-name" id="user-name">User</div>
                                <div class="user-role" id="user-role">Role</div>
                            </div>
                            <button id="logout-button" class="btn btn-outline" type="button">Logout</button>
                        </div>
                    </div>
                </header>

                <section class="view staff-view active" data-view="dashboard">
                    <section class="panel-grid">
                        <div class="card highlight dashboard-tile" id="dashboard-tile-revenue">
                            <div class="card-label">Revenue this month</div>
                            <div class="card-value" id="dashboard-revenue">--</div>
                            <div class="card-meta">Completed jobs this month + paid subscriptions</div>
                        </div>
                        <div class="card dashboard-tile" id="dashboard-tile-costs">
                            <div class="card-label">Costs this month</div>
                            <div class="card-value" id="dashboard-costs">--</div>
                            <div class="card-meta">Total incurred costs this month</div>
                        </div>
                        <div class="card dashboard-tile" id="dashboard-tile-profit">
                            <div class="card-label">Profit this month</div>
                            <div class="card-value" id="dashboard-profit">--</div>
                            <div class="card-meta">Revenue this month minus costs this month</div>
                        </div>
                        <div class="card dashboard-tile" id="dashboard-tile-jobs">
                            <div class="card-label">Jobs</div>
                            <div class="card-value" data-stat="jobs">--</div>
                            <div class="card-meta">Open or invoiced</div>
                        </div>
                        <div class="card dashboard-tile" id="dashboard-tile-subscriptions">
                            <div class="card-label">Subscriptions</div>
                            <div class="card-value" data-stat="subscriptions">--</div>
                            <div class="card-meta">Recurring monthly</div>
                        </div>
                        <div class="card opportunity-metric-card dashboard-tile" id="dashboard-tile-potential-mrr">
                            <div class="card-label">Potential MRR</div>
                            <div class="card-value" id="dashboard-potential-mrr">--</div>
                            <div class="card-meta">Open recurring-revenue opportunities</div>
                        </div>
                        <div class="card opportunity-metric-card dashboard-tile" id="dashboard-tile-pipeline-value">
                            <div class="card-label">Pipeline value</div>
                            <div class="card-value" id="dashboard-opportunity-value">--</div>
                            <div class="card-meta">Potential project revenue</div>
                        </div>
                        <div class="card opportunity-metric-card dashboard-tile" id="dashboard-tile-open-opportunities">
                            <div class="card-label">Open opportunities</div>
                            <div class="card-value" id="dashboard-opportunity-count">--</div>
                            <div class="card-meta">Hosting, SEO, care plans and upsells</div>
                        </div>
                    </section>

                    <section class="content-grid">
                        <div class="card wide today-card">
                            <div class="card-header"><div><div class="card-title">Today</div><div class="card-subtitle">Your prioritised sales, delivery and finance actions</div></div><button class="btn btn-outline" id="today-refresh" type="button">Refresh</button></div>
                            <div id="today-action-feed" class="today-action-feed" aria-live="polite"><div class="table-empty">Loading today’s actions…</div></div>
                        </div>
                        <div class="card dashboard-chart-card">
                            <div class="card-header">
                                <div>
                                    <div class="card-title">Weekly profit (2026)</div>
                                    <div class="card-subtitle" id="dashboard-profit-chart-range">Jan 1, 2026 to Dec 31, 2026</div>
                                </div>
                            </div>
                            <div id="dashboard-profit-chart" class="profit-chart">
                                <div class="profit-chart-empty">Loading weekly profit...</div>
                            </div>
                        </div>

                        <div class="card wide">
                            <div class="card-header">
                                <div>
                                    <div class="card-title">Recent invoices</div>
                                    <div class="card-subtitle">Latest billing activity</div>
                                </div>
                                <button class="btn btn-outline" data-go-view="invoices">Create invoice</button>
                            </div>
                            <div class="table" id="recent-invoices">
                                <div class="table-row table-header">
                                    <span>Invoice</span>
                                    <span>Status</span>
                                    <span>Amount</span>
                                    <span>Due</span>
                                </div>
                                <div class="table-row table-empty">
                                    <span>Loading invoices...</span>
                                    <span></span>
                                    <span></span>
                                    <span></span>
                                </div>
                            </div>
                        </div>

                        <div class="card staff-only">
                            <div class="card-header">
                                <div>
                                    <div class="card-title">Quick actions</div>
                                    <div class="card-subtitle">Stay on top of work</div>
                                </div>
                            </div>
                            <div class="stack">
                                <button class="btn btn-primary" data-go-view="customers">Add customer</button>
                                <button class="btn btn-outline" data-go-view="jobs">Create job</button>
                                <button class="btn btn-outline" data-go-view="subscriptions">Start subscription</button>
                            </div>
                        </div>
                    </section>

                    <div class="dashboard-settings">
                        <button class="btn btn-outline" id="dashboard-settings-toggle" type="button" aria-expanded="false">Settings</button>
                        <div class="dashboard-settings-popover" id="dashboard-settings-popover" hidden>
                            <div class="dashboard-settings-title">Show tiles</div>
                            <label class="dashboard-settings-option"><input type="checkbox" id="dashboard-toggle-revenue" checked><span>Revenue</span></label>
                            <label class="dashboard-settings-option"><input type="checkbox" id="dashboard-toggle-costs" checked><span>Costs</span></label>
                            <label class="dashboard-settings-option"><input type="checkbox" id="dashboard-toggle-profit" checked><span>Profit</span></label>
                            <label class="dashboard-settings-option"><input type="checkbox" id="dashboard-toggle-jobs" checked><span>Jobs</span></label>
                            <label class="dashboard-settings-option"><input type="checkbox" id="dashboard-toggle-subscriptions" checked><span>Subscriptions</span></label>
                            <label class="dashboard-settings-option"><input type="checkbox" id="dashboard-toggle-potential-mrr" checked><span>Potential MRR</span></label>
                            <label class="dashboard-settings-option"><input type="checkbox" id="dashboard-toggle-pipeline-value" checked><span>Pipeline value</span></label>
                            <label class="dashboard-settings-option"><input type="checkbox" id="dashboard-toggle-open-opportunities" checked><span>Open opportunities</span></label>
                        </div>
                    </div>
                </section>

                <section class="view staff-view" data-view="lead-discovery">
                    <section class="content-grid lead-discovery-layout">
                        <div class="card">
                            <div class="card-header"><div><div class="card-title">Discover businesses</div><div class="card-subtitle">Search Google Places and import businesses as CRM leads.</div></div></div>
                            <form id="lead-discovery-form" class="form-stack">
                                <label class="field"><span>Business type or search</span><input name="query" placeholder="e.g. plumbers, restaurants, accountants" maxlength="200" required></label>
                                <label class="field"><span>Location</span><input name="location" placeholder="e.g. Norwich, Norfolk" maxlength="200" required></label>
                                <label class="field"><span>Maximum results</span><select name="limit"><option value="10">10</option><option value="20" selected>20</option><option value="40">40</option><option value="60">60</option></select></label>
                                <label class="check-row"><input type="checkbox" name="auto_audit" checked><span>Queue website audits automatically</span></label>
                                <div id="lead-discovery-status" class="form-hint"></div>
                                <button class="btn btn-primary" type="submit">Discover new leads</button>
                            </form>
                        </div>
                        <div class="card wide discovered-leads-card">
                            <div class="card-header"><div><div class="card-title" id="discovered-leads-title">Discovered leads</div><div class="card-subtitle" id="discovered-leads-subtitle">External businesses ready for review and conversion.</div></div><div class="card-header-actions"><button class="btn btn-outline" id="lead-discovery-contacted" type="button">Contacted</button><button class="btn btn-outline" id="lead-discovery-refresh" type="button">Refresh</button></div></div>
                            <div class="filters lead-contacted-filters" id="lead-contacted-filters" hidden>
                                <label class="field">
                                    <span>Contacted by</span>
                                    <select id="lead-contacted-user-filter">
                                        <option value="all">All users</option>
                                    </select>
                                </label>
                            </div>
                            <div class="table" id="discovered-leads-table">
                                <div class="table-row table-header discovered-leads"><span>Business</span><span>Website</span><span>Google</span><span>Contacted</span><span>Actions</span></div>
                                <div class="table-row table-empty discovered-leads"><span>No leads loaded.</span><span></span><span></span><span></span><span></span></div>
                            </div>
                        </div>
                        <div class="card wide discovery-runs-card">
                            <div class="card-header"><div><div class="card-title">Discovery activity</div><div class="card-subtitle">Queued searches and import results.</div></div></div>
                            <div class="table" id="lead-discovery-runs">
                                <div class="table-row table-header discovery-runs"><span>Search</span><span>Status</span><span>Found</span><span>New</span><span>Updated</span><span>Started</span><span>Actions</span></div>
                            </div>
                        </div>
                    </section>
                </section>

                <section class="view staff-view" data-view="lead-detail">
                    <section class="detail-grid lead-detail-grid">
                        <div class="card wide lead-detail-hero">
                            <div class="card-header">
                                <div><div class="card-title" id="lead-detail-title">Lead</div><div class="card-subtitle" id="lead-detail-category">Business intelligence</div></div>
                                <div class="card-header-actions">
                                    <button class="btn btn-outline" id="lead-detail-contacted" type="button">Mark contacted</button>
                                    <button class="btn btn-primary" id="lead-detail-convert" type="button">Convert to customer</button>
                                    <button class="btn btn-outline" id="lead-detail-audit" type="button">Re-run audit</button>
                                    <button class="btn btn-outline opportunity-delete" id="lead-detail-delete" type="button">Delete</button>
                                    <button class="btn btn-outline" id="lead-detail-back" type="button">Back to leads</button>
                                </div>
                            </div>
                            <div class="lead-detail-meta">
                                <div><div class="meta-label">Website</div><div class="meta-value" id="lead-detail-website">--</div></div>
                                <div><div class="meta-label">Phone</div><div class="meta-value" id="lead-detail-phone">--</div></div>
                                <div><div class="meta-label">Address</div><div class="meta-value" id="lead-detail-address">--</div></div>
                                <div><div class="meta-label">Google presence</div><div class="meta-value" id="lead-detail-google">--</div></div>
                            </div>
                        </div>

                        <div class="panel-grid lead-score-grid" id="lead-detail-scores">
                            <div class="card"><div class="card-label">Overall health</div><div class="card-value" data-score="overall">--</div><div class="card-meta">Latest website audit</div></div>
                            <div class="card"><div class="card-label">SEO</div><div class="card-value" data-score="seo">--</div><div class="card-meta">Search visibility</div></div>
                            <div class="card"><div class="card-label">Performance</div><div class="card-value" data-score="performance">--</div><div class="card-meta">Speed and page weight</div></div>
                            <div class="card"><div class="card-label">Accessibility</div><div class="card-value" data-score="accessibility">--</div><div class="card-meta">Usability and inclusion</div></div>
                            <div class="card"><div class="card-label">Security</div><div class="card-value" data-score="security">--</div><div class="card-meta">HTTPS and protection</div></div>
                        </div>

                        <div class="card wide" id="lead-detail-audit-summary">
                            <div class="card-header"><div><div class="card-title">Website audit overview</div><div class="card-subtitle" id="lead-detail-audit-date">No audit available</div></div></div>
                            <div class="lead-audit-facts" id="lead-detail-facts"></div>
                        </div>

                        <div class="card wide">
                            <div class="card-header"><div><div class="card-title">Issues and recommendations</div><div class="card-subtitle">What is wrong, why it matters, and how it could be improved.</div></div></div>
                            <div class="lead-findings" id="lead-detail-findings"><div class="table-empty">No audit findings available.</div></div>
                        </div>

                        <div class="card wide">
                            <div class="card-header"><div><div class="card-title">Audit history</div><div class="card-subtitle">Website health over time.</div></div></div>
                            <div class="table" id="lead-detail-history">
                                <div class="table-row table-header lead-audit-history"><span>Date</span><span>Status</span><span>Overall</span><span>SEO</span><span>Performance</span><span>Accessibility</span><span>Security</span></div>
                            </div>
                        </div>
                    </section>
                </section>

                <section class="view staff-view" data-view="revenue-opportunities">
                    <section class="panel-grid opportunity-summary-grid">
                        <div class="card highlight"><div class="card-label">Potential MRR</div><div class="card-value" id="opportunity-potential-mrr">--</div><div class="card-meta">Open monthly revenue</div></div>
                        <div class="card"><div class="card-label">Weighted MRR</div><div class="card-value" id="opportunity-weighted-mrr">--</div><div class="card-meta">Adjusted by confidence</div></div>
                        <div class="card"><div class="card-label">Project pipeline</div><div class="card-value" id="opportunity-project-value">--</div><div class="card-meta">Potential one-off work</div></div>
                        <div class="card"><div class="card-label">Renewals due</div><div class="card-value" id="opportunity-renewals">--</div><div class="card-meta">Next 30 days</div></div>
                    </section>
                    <section class="content-grid opportunity-layout">
                        <div class="card wide">
                            <div class="card-header">
                                <div><div class="card-title">Revenue opportunities</div><div class="card-subtitle">Turn customer needs into recurring and project revenue.</div></div>
                                <div class="card-header-actions">
                                    <button class="btn btn-danger opportunity-bulk-delete" id="opportunities-bulk-delete" type="button" hidden>Delete selected (0)</button>
                                    <button class="btn btn-outline" id="opportunities-bulk-edit" type="button">Bulk Edit</button>
                                    <button class="btn btn-outline admin-inline-only" id="opportunities-recommend" type="button">Find opportunities</button>
                                    <button class="btn btn-outline" id="opportunities-refresh" type="button">Refresh</button>
                                </div>
                            </div>
                            <div class="filters opportunity-filters">
                                <label class="field"><span>Status</span><select id="opportunities-filter-status"><option value="all">All open</option><option value="identified">Identified</option><option value="qualified">Qualified</option><option value="proposed">Proposed</option><option value="won">Won</option><option value="lost">Lost</option><option value="deferred">Deferred</option></select></label>
                                <label class="field"><span>Service</span><select id="opportunities-filter-type"><option value="all">All services</option><option value="hosting">Hosting</option><option value="seo">SEO</option><option value="care_plan">Care plan</option><option value="website_management">Website management</option><option value="new_website">New website</option><option value="upsell">Upsell</option><option value="retention">Retention</option></select></label>
                            </div>
                            <div class="table" id="opportunities-table">
                                <div class="table-row table-header opportunities"><span>Customer</span><span>Opportunity</span><span>Status</span><span>Project</span><span>MRR</span><span>Next action</span><span>Actions</span></div>
                                <div class="table-row table-empty opportunities"><span>Loading opportunities...</span><span></span><span></span><span></span><span></span><span></span><span></span></div>
                            </div>
                        </div>
                        <div class="card">
                            <div class="card-header"><div><div class="card-title" id="opportunity-form-title">New opportunity</div><div class="card-subtitle">Record a growth or retention opportunity.</div></div></div>
                            <form id="opportunity-form" class="form-stack">
                                <label class="field"><span>Customer</span><select name="customer_id" id="opportunity-customer-select" required></select></label>
                                <label class="field"><span>Service</span><select name="type" required><option value="hosting">Website Hosting</option><option value="seo">SEO Package</option><option value="care_plan">Website Care Plan</option><option value="website_management">Website Management</option><option value="new_website">New Website Build</option><option value="upsell">Upsell</option><option value="retention">Customer Retention</option></select></label>
                                <label class="field"><span>Title</span><input name="title" maxlength="200" required></label>
                                <div class="form-grid"><label class="field"><span>Project value</span><input name="estimated_project_value" type="number" min="0" step="0.01" value="0"></label><label class="field"><span>Monthly revenue</span><input name="estimated_monthly_revenue" type="number" min="0" step="0.01" value="0"></label></div>
                                <div class="form-grid"><label class="field"><span>Confidence</span><input name="confidence" type="number" min="0" max="100" value="50"></label><label class="field"><span>Status</span><select name="status"><option value="identified">Identified</option><option value="qualified">Qualified</option><option value="proposed">Proposed</option><option value="won">Won</option><option value="lost">Lost</option><option value="deferred">Deferred</option></select></label></div>
                                <label class="field"><span>Next action</span><input name="next_action_at" type="date"></label>
                                <label class="field"><span>Recommendation</span><textarea name="recommendation" rows="3"></textarea></label>
                                <label class="field"><span>Notes</span><textarea name="notes" rows="3"></textarea></label>
                                <div id="opportunity-form-status" class="form-hint"></div>
                                <div class="row-actions"><button class="btn btn-primary" type="submit">Save opportunity</button><button class="btn btn-outline" id="opportunity-form-cancel" type="button">Cancel</button></div>
                            </form>
                        </div>
                    </section>
                </section>

                <section class="view staff-view admin-only-view" data-view="monthly-finance">
                    <section class="content-grid monthly-finance-layout">
                        <div class="card">
                            <div class="card-header">
                                <div>
                                    <div class="card-title">Months</div>
                                    <div class="card-subtitle">From March to the current month.</div>
                                    <div class="monthly-finance-selected-month" id="monthly-finance-selected-month">--</div>
                                </div>
                                <button class="btn btn-outline" id="monthly-finance-refresh" type="button">Refresh</button>
                            </div>
                            <div id="monthly-finance-months" class="monthly-finance-months">
                                <div class="monthly-finance-empty">Loading months...</div>
                            </div>
                        </div>

                        <div class="monthly-finance-right">
                            <div class="monthly-finance-metrics">
                                <div class="card monthly-finance-card" id="monthly-finance-card-revenue">
                                    <div class="card-label">Revenue</div>
                                    <div class="card-value" id="monthly-finance-revenue">--</div>
                                    <div class="card-meta" id="monthly-finance-revenue-meta">Completed jobs + paid subscriptions</div>
                                </div>
                                <div class="card monthly-finance-card" id="monthly-finance-card-costs">
                                    <div class="card-label">Costs</div>
                                    <div class="card-value" id="monthly-finance-costs">--</div>
                                    <div class="card-meta" id="monthly-finance-costs-meta">Incurred and recurring costs</div>
                                </div>
                                <div class="card monthly-finance-card" id="monthly-finance-card-profit">
                                    <div class="card-label">Profit</div>
                                    <div class="card-value" id="monthly-finance-profit">--</div>
                                    <div class="card-meta" id="monthly-finance-profit-meta">Revenue minus costs</div>
                                </div>
                                <div class="card monthly-finance-card" id="monthly-finance-card-tax">
                                    <div class="card-label">Tax</div>
                                    <div class="card-value" id="monthly-finance-tax">--</div>
                                    <div class="card-meta" id="monthly-finance-tax-meta">20% of Profit</div>
                                </div>
                                <div class="card monthly-finance-card" id="monthly-finance-card-owed">
                                    <div class="card-label">Owed</div>
                                    <div class="card-value" id="monthly-finance-owed">--</div>
                                    <div class="card-meta" id="monthly-finance-owed-meta">Overdue unpaid invoices</div>
                                </div>
                            </div>
                            <div class="monthly-finance-settings">
                                <button class="btn btn-outline" id="monthly-finance-settings-toggle" type="button">Settings</button>
                                <div class="monthly-finance-settings-popover" id="monthly-finance-settings-popover" hidden>
                                    <div class="monthly-finance-settings-title">Show boxes</div>
                                    <label class="monthly-finance-settings-option">
                                        <input type="checkbox" id="monthly-finance-toggle-revenue" checked>
                                        <span>Revenue</span>
                                    </label>
                                    <label class="monthly-finance-settings-option">
                                        <input type="checkbox" id="monthly-finance-toggle-costs" checked>
                                        <span>Costs</span>
                                    </label>
                                    <label class="monthly-finance-settings-option">
                                        <input type="checkbox" id="monthly-finance-toggle-profit" checked>
                                        <span>Profit</span>
                                    </label>
                                    <label class="monthly-finance-settings-option">
                                        <input type="checkbox" id="monthly-finance-toggle-tax" checked>
                                        <span>Tax</span>
                                    </label>
                                    <label class="monthly-finance-settings-option">
                                        <input type="checkbox" id="monthly-finance-toggle-owed" checked>
                                        <span>Owed</span>
                                    </label>
                                </div>
                            </div>
                        </div>
                    </section>
                </section>

                <section class="view staff-view" data-view="websites">
                    <section class="content-grid websites-workspace">
                        <div class="card wide websites-directory-card">
                            <div class="card-header"><div><div class="card-title">Your websites</div><div class="card-subtitle" id="websites-list-subtitle">Hosting, WordPress and monitoring shown as one clear website record.</div></div><div class="card-header-actions"><button class="btn btn-primary admin-only" id="krystal-import-open" type="button">Connect existing Krystal site</button><button class="btn btn-outline admin-only" id="manual-website-toggle" type="button">Create new website</button><button class="btn btn-outline" id="websites-unlinked-toggle" type="button">Needs attention</button><button class="btn btn-outline" id="websites-refresh" type="button">Refresh</button></div></div>
                            <div class="stats-grid" id="website-summary"><div class="stat-card"><span>Total</span><strong>—</strong></div><div class="stat-card"><span>Healthy</span><strong>—</strong></div><div class="stat-card"><span>Needs attention</span><strong>—</strong></div><div class="stat-card"><span>Offline</span><strong>—</strong></div></div>
                            <div class="filters"><label class="field"><span>Search</span><input id="websites-search" placeholder="Name or domain"></label><label class="field"><span>Status</span><select id="websites-status"><option value="">All statuses</option><option>healthy</option><option>attention</option><option>critical</option><option>unknown</option><option>paused</option></select></label></div>
                            <div class="site-card" id="website-agent-token-panel" hidden><div><div class="site-name">WordPress connection token</div><div class="site-url" id="website-agent-token-site">Copy this token into WordPress → Settings → WebStamp Agent.</div><input id="website-agent-token-value" type="text" readonly aria-label="Website agent token"></div><div class="site-actions"><button class="btn btn-primary btn-small" id="website-agent-token-copy" type="button">Copy token</button><button class="btn btn-outline btn-small" id="website-agent-token-close" type="button">Close</button></div></div>
                            <div class="stack" id="websites-list"><div class="site-card"><div><div class="site-name">Loading websites…</div></div></div></div>
                        </div>

                        <div class="card wide admin-only krystal-import-card" id="krystal-import-panel" hidden>
                            <div class="card-header"><div><div class="card-title">Import websites from Krystal</div><div class="card-subtitle">We find the domains. You only choose the customer where a match is not already available.</div></div><div class="card-header-actions"><span class="connection-badge" id="krystal-connection-summary">Checking connection…</span><button class="btn btn-primary" id="hosting-sync" type="button">Scan Krystal</button><button class="btn btn-outline" id="krystal-import-close" type="button">Close</button></div></div>
                            <div class="website-setup-steps" aria-label="Website import steps"><div><span>1</span><strong>Scan Krystal</strong><small>Primary and addon domains</small></div><div><span>2</span><strong>Choose customer</strong><small>Only when we cannot match one</small></div><div><span>3</span><strong>Connect</strong><small>Hosting details are mapped automatically</small></div></div>
                            <div class="form-hint" id="krystal-import-summary">Select Scan Krystal to find websites.</div>
                            <div class="stack krystal-import-list" id="krystal-import-list"><div class="table-empty">No scan has been run yet.</div></div>
                            <details class="integration-settings">
                                <summary>Krystal connection settings</summary>
                                <div class="integration-settings-body">
                                    <div class="card-header"><div><div class="card-title">Krystal WHM connection</div><div class="card-subtitle">These settings apply once to the whole Websites section.</div></div><button class="btn btn-outline" id="hosting-test" type="button">Test connection</button></div>
                                    <form id="hosting-server-form" class="form-grid"><input name="id" type="hidden"><label class="field"><span>Connection name</span><input name="name" value="Krystal Trinity" required></label><label class="field"><span>WHM hostname</span><input name="hostname" placeholder="server.example.com" required></label><label class="field"><span>WHM reseller username</span><input name="username" autocomplete="off" required></label><label class="field"><span>WHM API token</span><input name="token" type="password" autocomplete="new-password" placeholder="Leave blank to keep saved token"></label><div class="form-hint form-grid-wide" id="hosting-server-status">Checking Krystal settings…</div><div class="form-grid-wide"><button class="btn btn-primary" type="submit">Save connection</button></div></form>
                                </div>
                            </details>
                        </div>

                        <div class="card wide" id="manual-website-panel" hidden>
                            <div class="card-header"><div><div class="card-title">Create a new website</div><div class="card-subtitle">Creates the CRM record and, after confirmation, runs the resumable Krystal setup pipeline.</div></div><button class="btn btn-outline" id="manual-website-close" type="button">Close</button></div>
                            <form id="managed-website-form" class="form-stack">
                                <label class="field"><span>Customer</span><select name="customer_id" id="managed-website-customer" required><option value="">Select customer</option></select></label>
                                <label class="field"><span>Website name</span><input name="name" required></label>
                                <label class="field"><span>Domain</span><input name="login_url" type="text" inputmode="url" autocapitalize="none" spellcheck="false" placeholder="example.co.uk" required></label>
                                <label class="field"><span>Environment</span><select name="environment"><option>production</option><option>staging</option><option>development</option></select></label>
                                <label class="field"><span>Hosting provider</span><select name="hosting_server_id" id="managed-website-server"><option value="">No hosting provisioning</option></select></label>
                                <label class="field"><span>Hosting package</span><select name="hosting_package_id" id="managed-website-package"><option value="">Select package</option></select></label>
                                <label class="field"><span>Website type</span><select name="website_type"><option value="wordpress">WordPress</option><option value="blank">Blank hosting</option></select></label>
                                <label class="field"><span>Provisioning profile</span><select name="wordpress_profile_id" id="managed-website-profile"><option value="">No profile</option></select></label>
                                <label class="check-row admin-only"><input name="create_cpanel_account" type="checkbox"><span>Create cPanel account (confirmation required)</span></label>
                                <label class="field"><span>GA4 Property ID</span><input name="google_analytics_property_id" placeholder="123456789"></label>
                                <label class="field"><span>Google Analytics dashboard URL</span><input name="google_analytics_dashboard_url" type="url" placeholder="https://analytics.google.com/..."></label>
                                <label class="check-row"><input name="wordpress_enabled" type="checkbox"><span>WordPress site</span></label><label class="check-row"><input name="management_enabled" type="checkbox" checked><span>Managed by us</span></label><label class="check-row"><input name="hosting_enabled" type="checkbox"><span>Hosted by us</span></label>
                                <label class="field"><span>Internal notes</span><textarea name="notes" rows="3"></textarea></label>
                                <div class="form-hint" id="managed-website-status"></div><button class="btn btn-primary" type="submit">Save website</button>
                            </form>
                        </div>
                    </section>
                </section>

                <section class="view staff-view" data-view="website-detail">
                    <section class="content-grid">
                        <div class="card wide">
                            <div class="card-header"><div><div class="card-title" id="website-detail-title">Website</div><div class="card-subtitle" id="website-detail-domain">Loading details…</div></div><div class="card-header-actions"><button class="btn btn-outline" id="website-detail-check" type="button">Check now</button><button class="btn btn-outline" id="website-detail-back" type="button">Back</button></div></div>
                            <div class="stats-grid" id="website-detail-summary"></div>
                        </div>
                        <nav class="website-detail-tabs wide" aria-label="Website details"><button class="btn btn-primary" data-website-tab="overview">Overview</button><button class="btn btn-outline" data-website-tab="hosting">Hosting</button><button class="btn btn-outline" data-website-tab="wordpress">WordPress</button><button class="btn btn-outline" data-website-tab="issues">Issues</button><button class="btn btn-outline" data-website-tab="activity">Activity</button><button class="btn btn-outline" data-website-tab="settings">Settings</button></nav>
                        <div class="card wide" data-website-tab-panel="overview"><div class="card-header"><div><div class="card-title">Overview</div><div class="card-subtitle">Ownership, service and connection information.</div></div></div><div class="detail-grid" id="website-detail-overview"></div></div>
                        <div class="card wide" data-website-tab-panel="overview"><div class="card-header"><div><div class="card-title">Data sources</div><div class="card-subtitle">Shows which integrations are current and why a customer metric may be unavailable.</div></div></div><div class="detail-grid" id="website-detail-data-sources"></div></div>
                        <div class="card wide" data-website-tab-panel="overview"><div class="card-header"><div><div class="card-title">Health history</div><div class="card-subtitle">Latest monitoring results and response times.</div></div></div><div class="stack" id="website-detail-health"></div></div>
                        <div class="card wide" data-website-tab-panel="hosting" hidden><div class="card-header"><div><div class="card-title">Hosting account</div><div class="card-subtitle">Cached Krystal account usage and limits. Refreshing the page never triggers a hosting action.</div></div></div><div class="detail-grid" id="website-detail-hosting"></div></div>
                        <div class="card wide" data-website-tab-panel="wordpress" hidden><div class="card-header"><div><div class="card-title">WordPress</div><div class="card-subtitle">Core, plugin, theme and Site Agent information.</div></div></div><div class="detail-grid" id="website-detail-wordpress"></div></div>
                        <div class="card" data-website-tab-panel="settings" hidden><div class="card-header"><div><div class="card-title">Google Analytics</div><div class="card-subtitle">Link this website to its GA4 property.</div></div></div><form id="website-analytics-form" class="form-stack"><label class="field"><span>GA4 Property ID</span><input name="google_analytics_property_id" placeholder="123456789"></label><label class="field"><span>Dashboard URL</span><input name="google_analytics_dashboard_url" type="url" placeholder="https://analytics.google.com/..."></label><div id="website-analytics-status" class="form-hint"></div><div class="form-actions"><button class="btn btn-primary" type="submit">Save Analytics link</button><a class="btn btn-outline" id="website-analytics-open" href="#" target="_blank" rel="noopener" hidden>Open Analytics</a></div></form></div>
                        <div class="card wide" data-website-tab-panel="issues" hidden><div class="card-header"><div><div class="card-title">Needs attention</div><div class="card-subtitle">Current and resolved hosting, monitoring and WordPress warnings.</div></div></div><div class="stack" id="website-detail-incidents"></div></div>
                        <div class="card" data-website-tab-panel="activity" hidden><div class="card-header"><div><div class="card-title">Activity</div><div class="card-subtitle">Maintenance and website changes.</div></div></div><div class="stack" id="website-detail-activities"></div></div>
                        <div class="card wide" data-website-tab-panel="wordpress" hidden><div class="card-header"><div><div class="card-title">Provisioning</div><div class="card-subtitle">A resumable record of every hosting and WordPress setup step.</div></div></div><div class="stack" id="website-detail-provisioning"></div></div>
                        <div class="card wide danger-zone admin-only" data-website-tab-panel="settings" hidden><div class="card-header"><div><div class="card-title">Danger zone</div><div class="card-subtitle">Deletion is audited. Hosting is only removed after strict ownership and shared-account checks.</div></div><button class="btn btn-danger" id="website-delete-open" type="button">Delete website</button></div></div>
                    </section>
                </section>

                <section class="view staff-view" data-view="customers">
                    <section class="content-grid">
                        <div class="card wide">
                            <div class="card-header">
                                <div>
                                    <div class="card-title">Customers</div>
                                    <div class="card-subtitle">Manage customer profiles and portal access.</div>
                                </div>
                                <div class="card-header-actions">
                                    <button class="btn btn-outline" id="customers-archived-toggle" type="button">Archived customers</button>
                                    <button class="btn btn-outline" id="customers-refresh" type="button">Refresh</button>
                                </div>
                            </div>
                            <div class="filters">
                                <label class="field">
                                    <span>Search customers</span>
                                    <input type="text" id="customers-search" placeholder="Search by name, email or phone">
                                </label>
                                <button class="btn btn-ghost" id="customers-clear" type="button">Clear</button>
                            </div>
                            <div class="table" id="customers-table">
                                <div class="table-row table-header customers">
                                    <span>Name</span>
                                    <span>Email</span>
                                    <span>Phone</span>
                                    <span>Billing</span>
                                    <span>Totals</span>
                                    <span>Actions</span>
                                </div>
                                <div class="table-row table-empty customers">
                                    <span>Loading customers...</span>
                                    <span></span>
                                    <span></span>
                                    <span></span>
                                    <span></span>
                                    <span></span>
                                </div>
                            </div>
                            <div class="table-actions" style="margin-top: 20px;">
                                <button class="btn btn-ghost" id="customers-load-more" type="button">Load more</button>
                            </div>
                        </div>

                        <div class="card">
                            <div class="card-header">
                                <div>
                                    <div class="card-title" id="customer-form-title">New customer</div>
                                    <div class="card-subtitle">Create or update customer details.</div>
                                </div>
                            </div>
                            <form id="customer-form" class="form-stack">
                                <input type="hidden" name="id">
                                <label class="field">
                                    <span>Name</span>
                                    <input type="text" name="name" required>
                                </label>
                                <label class="field">
                                    <span>Email</span>
                                    <input type="email" name="email" required>
                                </label>
                                <label class="field">
                                    <span>Phone number</span>
                                    <input type="tel" name="phone" autocomplete="tel" maxlength="50">
                                </label>
                                <label class="field">
                                    <span>Billing address</span>
                                    <textarea name="billing_address" rows="3" required></textarea>
                                </label>
                                <label class="field">
                                    <span>Notes</span>
                                    <textarea name="notes" rows="3"></textarea>
                                </label>
                                <div class="form-hint">Portal login is auto-created using this email. Default password: WebStamp123</div>
                                <div id="customer-form-status" class="form-hint"></div>
                                <div class="form-actions">
                                    <button type="submit" class="btn btn-primary">Save customer</button>
                                    <button type="button" class="btn btn-outline" id="customer-form-cancel">Cancel</button>
                                </div>
                            </form>
                        </div>
                    </section>
                </section>

                <section class="view staff-view" data-view="customer-detail">
                    <section class="detail-grid">
                        <div class="card wide">
                            <div class="card-header">
                                <div>
                                    <div class="card-title" id="customer-detail-title">Customer</div>
                                    <div class="card-subtitle" id="customer-detail-email">Email</div>
                                    <div class="card-subtitle" id="customer-detail-phone">Phone</div>
                                </div>
                                <div class="card-header-actions">
                                    <button class="btn btn-outline admin-inline-only" id="customer-detail-archive" type="button">Archive</button>
                                    <button class="btn btn-outline" id="customer-detail-back" type="button">Back to customers</button>
                                </div>
                            </div>
                            <div class="detail-meta">
                                <div>
                                    <div class="meta-label">Billing address</div>
                                    <div class="meta-value" id="customer-detail-billing">--</div>
                                </div>
                                <div>
                                    <div class="meta-label">Notes</div>
                                    <div class="meta-value" id="customer-detail-notes">--</div>
                                </div>
                            </div>
                        </div>

                        <div class="panel-grid">
                            <div class="card">
                                <div class="card-label">Total spent</div>
                                <div class="card-value" id="customer-total-spent">£0.00</div>
                                <div class="card-meta">Sum of paid invoices</div>
                            </div>
                            <div class="card">
                                <div class="card-label">Monthly recurring revenue</div>
                                <div class="card-value" id="customer-mrr">£0.00</div>
                                <div class="card-meta">Subscriptions per month</div>
                            </div>
                            <div class="card">
                                <div class="card-label">Active subscriptions</div>
                                <div class="card-value" id="customer-subscription-count">0</div>
                                <div class="card-meta">Currently active</div>
                            </div>
                        </div>

                        <div class="card wide">
                            <div class="card-header">
                                <div>
                                    <div class="card-title">Customer Forms</div>
                                    <div class="card-subtitle">Send forms to the customer and review submitted answers.</div>
                                </div>
                            </div>
                            <form id="customer-form-request-form" class="filters customer-form-send-controls">
                                <label class="field">
                                    <span>Form template</span>
                                    <select id="customer-form-template" name="template_slug" required>
                                        <option value="">Select a form</option>
                                    </select>
                                </label>
                                <button class="btn btn-primary" type="submit">Send Form</button>
                            </form>
                            <div id="customer-form-request-status" class="form-hint"></div>
                            <div class="table" id="customer-forms-table">
                                <div class="table-row table-header customer-forms">
                                    <span>Form</span><span>Status</span><span>Sent</span><span>Completed</span><span>Actions</span>
                                </div>
                                <div class="table-row table-empty customer-forms">
                                    <span>No forms sent yet.</span><span></span><span></span><span></span><span></span>
                                </div>
                            </div>
                            <div class="customer-form-review" id="customer-form-review" hidden></div>
                        </div>

                        <div class="content-grid">
                            <div class="card wide">
                                <div class="card-header">
                                    <div>
                                        <div class="card-title">Jobs</div>
                                        <div class="card-subtitle">Work history for this customer.</div>
                                    </div>
                                </div>
                            <div class="table" id="customer-jobs-table">
                                <div class="table-row table-header jobs-detail">
                                    <span>Billable ID</span>
                                    <span>Description</span>
                                    <span>Cost</span>
                                    <span>Status</span>
                                    <span>Completed</span>
                                </div>
                                <div class="table-row table-empty jobs-detail">
                                    <span>Loading jobs...</span>
                                    <span></span>
                                    <span></span>
                                    <span></span>
                                    <span></span>
                                </div>
                            </div>
                            </div>

                            <div class="card">
                                <div class="card-header">
                                    <div>
                                        <div class="card-title">Subscriptions</div>
                                        <div class="card-subtitle">Recurring services.</div>
                                    </div>
                                </div>
                            <div class="table" id="customer-subscriptions-table">
                                <div class="table-row table-header subscriptions-detail">
                                    <span>Billable ID</span>
                                    <span>Description</span>
                                    <span>Monthly</span>
                                    <span>Status</span>
                                    <span>Next invoice</span>
                                </div>
                                <div class="table-row table-empty subscriptions-detail">
                                    <span>Loading subscriptions...</span>
                                    <span></span>
                                    <span></span>
                                    <span></span>
                                    <span></span>
                                </div>
                            </div>
                            </div>
                        </div>

                        <div class="content-grid">
                            <div class="card wide">
                                <div class="card-header">
                                    <div>
                                        <div class="card-title">Websites</div>
                                        <div class="card-subtitle">Websites connected to this customer, Krystal hosting and monitoring.</div>
                                    </div>
                                    <button class="btn btn-primary admin-inline-only" id="customer-website-add" type="button">Add website</button>
                                </div>
                                <div class="stack" id="customer-websites-list">
                                    <div class="site-card">
                                        <div>
                                            <div class="site-name">No websites yet</div>
                                            <div class="site-url">Select Add website to connect one.</div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </section>
                </section>

                <section class="view staff-view" data-view="jobs">
                    <section class="content-grid">
                        <div class="card wide">
                            <div class="card-header">
                                <div>
                                    <div class="card-title">Jobs</div>
                                    <div class="card-subtitle">Track one-off work and invoice status.</div>
                                </div>
                                <div class="card-header-actions">
                                    <button class="btn btn-outline" id="jobs-archived-toggle" type="button">Archived jobs</button>
                                    <button class="btn btn-outline" id="jobs-refresh">Refresh</button>
                                </div>
                            </div>
                            <div class="filters">
                                <label class="field">
                                    <span>Status</span>
                                    <select id="jobs-filter-status">
                                        <option value="all">All</option>
                                        <option value="draft">Draft</option>
                                        <option value="completed">Completed</option>
                                        <option value="invoiced">Invoiced</option>
                                    </select>
                                </label>
                                <label class="field">
                                    <span>Customer</span>
                                    <select id="jobs-filter-customer"></select>
                                </label>
                                <button class="btn btn-ghost" id="jobs-clear" type="button">Clear</button>
                            </div>
                            <div class="table" id="jobs-table">
                                <div class="table-row table-header jobs">
                                    <span>Billable ID</span>
                                    <span>Description</span>
                                    <span>Customer</span>
                                    <span>Cost</span>
                                    <span>Status</span>
                                    <span>Actions</span>
                                </div>
                                <div class="table-row table-empty jobs">
                                    <span>Loading jobs...</span>
                                    <span></span>
                                    <span></span>
                                    <span></span>
                                    <span></span>
                                    <span></span>
                                </div>
                            </div>
                            <div class="table-actions" style="margin-top: 20px;">
                                <button class="btn btn-ghost" id="jobs-load-more" type="button">Load more</button>
                            </div>
                        </div>

                        <div class="card">
                            <div class="card-header">
                                <div>
                                    <div class="card-title" id="job-form-title">New job</div>
                                    <div class="card-subtitle">Capture work requests and status.</div>
                                </div>
                            </div>
                            <form id="job-form" class="form-stack">
                                <input type="hidden" name="id">
                                <label class="field">
                                    <span>Customer</span>
                                    <select name="customer_id" id="job-customer-select" required></select>
                                </label>
                                <label class="field">
                                    <span>Description</span>
                                    <input type="text" name="description" required>
                                </label>
                                <label class="field">
                                    <span>Notes</span>
                                    <textarea name="notes" rows="3" placeholder="Detailed notes for this job (shown on invoice)"></textarea>
                                </label>
                                <label class="field">
                                    <span>Cost</span>
                                    <input type="number" name="cost" min="0" step="0.01" required>
                                </label>
                                <label class="field">
                                    <span>Status</span>
                                    <select name="status">
                                        <option value="draft">Draft</option>
                                        <option value="completed">Completed</option>
                                        <option value="invoiced">Invoiced</option>
                                    </select>
                                </label>
                                <div id="job-form-status" class="form-hint"></div>
                                <div class="form-actions">
                                    <button type="submit" class="btn btn-primary">Save job</button>
                                    <button type="button" class="btn btn-outline" id="job-form-cancel">Cancel</button>
                                </div>
                            </form>
                        </div>

                        <div class="card wide jobs-photos-card">
                            <div class="card-header">
                                <div>
                                    <div class="card-title">Job photos</div>
                                    <div class="card-subtitle">Upload and download multiple images per job.</div>
                                </div>
                                <div class="row-actions">
                                    <button class="btn btn-outline" id="job-photos-refresh" type="button">Refresh</button>
                                    <button class="btn btn-outline" id="job-photos-download-all" type="button">Download all</button>
                                </div>
                            </div>

                            <div class="filters">
                                <label class="field">
                                    <span>Job</span>
                                    <select id="job-photo-job-select"></select>
                                </label>
                            </div>

                            <div class="table" id="job-photos-table">
                                <div class="table-row table-header job-photos">
                                    <span>Uploaded</span>
                                    <span>Filename</span>
                                    <span>Size</span>
                                    <span>Actions</span>
                                </div>
                                <div class="table-row table-empty job-photos">
                                    <span>Choose a job to load photos.</span>
                                    <span></span>
                                    <span></span>
                                    <span></span>
                                </div>
                            </div>

                            <form id="job-photo-upload-form" class="form-stack" style="margin-top: 16px;">
                                <label class="field">
                                    <span>Upload images</span>
                                    <input type="file" name="photos[]" id="job-photo-files" accept=".png,.jpg,.jpeg,.webp,.gif,image/*" multiple required>
                                </label>
                                <div id="job-photo-upload-status" class="form-hint"></div>
                                <div class="form-actions">
                                    <button type="submit" class="btn btn-primary">Upload photos</button>
                                </div>
                            </form>
                        </div>
                    </section>
                </section>

                <section class="view staff-view" data-view="tasks">
                    <section class="content-grid tasks-layout">
                        <div class="card wide">
                            <div class="card-header">
                                <div>
                                    <div class="card-title">Tasks</div>
                                    <div class="card-subtitle">Assigned work and completion tracking.</div>
                                </div>
                                <div class="card-header-actions">
                                    <button class="btn btn-outline" id="tasks-completed-toggle" type="button">Completed</button>
                                    <button class="btn btn-outline" id="tasks-refresh" type="button">Refresh</button>
                                </div>
                            </div>
                            <div class="filters">
                                <label class="field">
                                    <span>Status</span>
                                    <select id="tasks-filter-status">
                                        <option value="all">All</option>
                                        <option value="pending">Pending</option>
                                        <option value="in_progress">In progress</option>
                                    </select>
                                </label>
                                <label class="field staff-only">
                                    <span>Staff</span>
                                    <select id="tasks-filter-staff"></select>
                                </label>
                                <button class="btn btn-ghost" id="tasks-clear" type="button">Clear</button>
                            </div>
                            <div class="table" id="tasks-table">
                                <div class="table-row table-header tasks">
                                    <span>Task</span>
                                    <span>Staff</span>
                                    <span>Priority</span>
                                    <span>Status</span>
                                    <span>Due</span>
                                    <span>Job</span>
                                    <span>Time</span>
                                    <span>Actions</span>
                                </div>
                                <div class="table-row table-empty tasks">
                                    <span>Loading tasks...</span>
                                    <span></span><span></span><span></span><span></span><span></span><span></span><span></span>
                                </div>
                            </div>
                        </div>

                        <div class="card">
                            <div class="card-header">
                                <div>
                                    <div class="card-title" id="task-form-title">New task</div>
                                    <div class="card-subtitle">Create, edit, and update task progress.</div>
                                </div>
                            </div>
                            <form id="task-form" class="form-stack">
                                <input type="hidden" name="id">
                                <label class="field staff-only">
                                    <span>Title</span>
                                    <input type="text" name="title">
                                </label>
                                <label class="field staff-only">
                                    <span>Description</span>
                                    <textarea name="description" rows="3"></textarea>
                                </label>
                                <label class="field staff-only">
                                    <span>Assign to</span>
                                    <select name="assigned_to_user_id" id="task-staff-select"></select>
                                </label>
                                <label class="field staff-only">
                                    <span>Linked job (optional)</span>
                                    <select name="job_id" id="task-job-select"></select>
                                </label>
                                <label class="field staff-only">
                                    <span>Priority</span>
                                    <select name="priority">
                                        <option value="low">Low</option>
                                        <option value="normal" selected>Normal</option>
                                        <option value="high">High</option>
                                        <option value="urgent">Urgent</option>
                                    </select>
                                </label>
                                <label class="field staff-only">
                                    <span>Due date</span>
                                    <input type="date" name="due_date">
                                </label>
                                <label class="field">
                                    <span>Status</span>
                                    <select name="status">
                                        <option value="pending">Pending</option>
                                        <option value="in_progress">In progress</option>
                                        <option value="completed">Completed</option>
                                    </select>
                                </label>
                                <div class="line-item">
                                    <label class="field">
                                        <span>Hours</span>
                                        <input type="number" name="hours" min="0" step="1" value="0">
                                    </label>
                                    <label class="field">
                                        <span>Minutes</span>
                                        <select name="minutes">
                                            <option value="0">00</option>
                                            <option value="15">15</option>
                                            <option value="30">30</option>
                                            <option value="45">45</option>
                                        </select>
                                    </label>
                                </div>
                                <label class="field">
                                    <span>Notes</span>
                                    <textarea name="staff_notes" rows="3"></textarea>
                                </label>
                                <div id="task-form-status" class="form-hint"></div>
                                <div class="form-actions">
                                    <button type="submit" class="btn btn-primary">Save task</button>
                                    <button type="button" class="btn btn-outline" id="task-form-cancel">Cancel</button>
                                </div>
                            </form>
                        </div>
                    </section>
                </section>

                <section class="view staff-view staff-member-only-view" data-view="monthly-tasks">
                    <section class="content-grid monthly-finance-layout">
                        <div class="card">
                            <div class="card-header">
                                <div>
                                    <div class="card-title">Monthly Tasks</div>
                                    <div class="card-subtitle">Completed task and hour totals.</div>
                                    <div class="monthly-finance-selected-month" id="monthly-tasks-selected-month">--</div>
                                </div>
                                <button class="btn btn-outline" id="monthly-tasks-refresh" type="button">Refresh</button>
                            </div>
                            <div id="monthly-tasks-months" class="monthly-finance-months">
                                <div class="monthly-finance-empty">Loading months...</div>
                            </div>
                        </div>

                        <div class="monthly-finance-right">
                            <div class="monthly-finance-metrics">
                                <div class="card monthly-finance-card">
                                    <div class="card-label">Tasks completed</div>
                                    <div class="card-value" id="monthly-tasks-completed">--</div>
                                    <div class="card-meta">Completed in selected month</div>
                                </div>
                                <div class="card monthly-finance-card">
                                    <div class="card-label">Hours done</div>
                                    <div class="card-value" id="monthly-tasks-hours">--</div>
                                    <div class="card-meta">Logged hours in selected month</div>
                                </div>
                                <div class="card monthly-finance-card" id="monthly-tasks-card-task-change">
                                    <div class="card-label">Task change</div>
                                    <div class="card-value" id="monthly-tasks-task-change">--</div>
                                    <div class="card-meta">Compared with previous month</div>
                                </div>
                                <div class="card monthly-finance-card" id="monthly-tasks-card-hour-change">
                                    <div class="card-label">Hour change</div>
                                    <div class="card-value" id="monthly-tasks-hour-change">--</div>
                                    <div class="card-meta">Compared with previous month</div>
                                </div>
                            </div>
                        </div>
                    </section>
                </section>

                <section class="view staff-view admin-only-view" data-view="staff-tracking">
                    <section class="content-grid">
                        <div class="card wide">
                            <div class="card-header">
                                <div>
                                    <div class="card-title">Staff</div>
                                    <div class="card-subtitle">Track pending tasks, completed tasks, and logged hours by staff member.</div>
                                </div>
                                <button class="btn btn-outline" id="staff-tracking-refresh" type="button">Refresh</button>
                            </div>
                            <div class="table" id="staff-tracking-table">
                                <div class="table-row table-header staff-tracking">
                                    <span>Staff</span>
                                    <span>Pending</span>
                                    <span>Completed this month</span>
                                    <span>Hours this month</span>
                                    <span>Total completed</span>
                                    <span>Total hours</span>
                                </div>
                                <div class="table-row table-empty staff-tracking">
                                    <span>Loading staff tracking...</span>
                                    <span></span><span></span><span></span><span></span><span></span>
                                </div>
                            </div>
                        </div>

                        <div class="card wide admin-only">
                            <div class="card-header">
                                <div>
                                    <div class="card-title">Staff users</div>
                                    <div class="card-subtitle">Create staff accounts with full CRM access (no admin controls).</div>
                                </div>
                                <button class="btn btn-outline" id="staff-users-refresh" type="button">Refresh</button>
                            </div>
                            <form id="staff-user-form" class="form-stack">
                                <label class="field">
                                    <span>Name</span>
                                    <input type="text" name="name" required>
                                </label>
                                <label class="field">
                                    <span>Email</span>
                                    <input type="email" name="email" required>
                                </label>
                                <label class="field">
                                    <span>Password</span>
                                    <input type="password" name="password" minlength="8" required>
                                </label>
                                <div id="staff-user-form-status" class="form-hint"></div>
                                <button type="submit" class="btn btn-primary">Create staff user</button>
                            </form>
                            <div class="table" id="staff-users-table" style="margin-top: 14px;">
                                <div class="table-row table-header staff-users">
                                    <span>Name</span>
                                    <span>Email</span>
                                    <span>Created</span>
                                    <span>Actions</span>
                                </div>
                                <div class="table-row table-empty staff-users">
                                    <span>Loading staff users...</span>
                                    <span></span>
                                    <span></span>
                                    <span></span>
                                </div>
                            </div>
                        </div>
                    </section>
                </section>

                <section class="view staff-view" data-view="subscriptions">
                    <section class="content-grid">
                        <div class="card wide">
                            <div class="card-header">
                                <div>
                                    <div class="card-title">Subscriptions</div>
                                    <div class="card-subtitle">Monthly recurring billing plans.</div>
                                </div>
                                <button class="btn btn-outline" id="subscriptions-refresh">Refresh</button>
                            </div>
                            <div class="filters">
                                <label class="field">
                                    <span>Status</span>
                                    <select id="subscriptions-filter-status">
                                        <option value="all">All</option>
                                        <option value="active">Active</option>
                                        <option value="paused">Paused</option>
                                    </select>
                                </label>
                                <label class="field">
                                    <span>Customer</span>
                                    <select id="subscriptions-filter-customer"></select>
                                </label>
                                <button class="btn btn-ghost" id="subscriptions-clear" type="button">Clear</button>
                            </div>
                            <div class="table" id="subscriptions-table">
                                <div class="table-row table-header subscriptions">
                                    <span>Billable ID</span>
                                    <span>Description</span>
                                    <span>Customer</span>
                                    <span>Monthly</span>
                                    <span>Status</span>
                                    <span>Next invoice</span>
                                    <span>Actions</span>
                                </div>
                                <div class="table-row table-empty subscriptions">
                                    <span>Loading subscriptions...</span>
                                    <span></span>
                                    <span></span>
                                    <span></span>
                                    <span></span>
                                    <span></span>
                                    <span></span>
                                </div>
                            </div>
                            <div class="table-actions" style="margin-top: 20px;">
                                <button class="btn btn-ghost" id="subscriptions-load-more" type="button">Load more</button>
                            </div>
                        </div>

                        <div class="card">
                            <div class="card-header">
                                <div>
                                    <div class="card-title" id="subscription-form-title">New subscription</div>
                                    <div class="card-subtitle">Set recurring services and pricing.</div>
                                </div>
                            </div>
                            <form id="subscription-form" class="form-stack">
                                <input type="hidden" name="id">
                                <label class="field">
                                    <span>Customer</span>
                                    <select name="customer_id" id="subscription-customer-select" required></select>
                                </label>
                                <label class="field">
                                    <span>Description</span>
                                    <textarea name="description" rows="3" required></textarea>
                                </label>
                                <label class="field">
                                    <span>Monthly cost</span>
                                    <input type="number" name="monthly_cost" min="0" step="0.01" required>
                                </label>
                                <label class="field">
                                    <span>Start date</span>
                                    <input type="date" name="start_date" required>
                                </label>
                                <label class="field">
                                    <span>Status</span>
                                    <select name="status">
                                        <option value="active">Active</option>
                                        <option value="paused">Paused</option>
                                    </select>
                                </label>
                                <div id="subscription-form-status" class="form-hint"></div>
                                <div class="form-actions">
                                    <button type="submit" class="btn btn-primary">Save subscription</button>
                                    <button type="button" class="btn btn-outline" id="subscription-form-cancel">Cancel</button>
                                </div>
                            </form>

                            <div class="card-header" style="margin-top: 24px;">
                                <div>
                                    <div class="card-title">Monthly tracking</div>
                                    <div class="card-subtitle">Track status and paid/unpaid by month.</div>
                                </div>
                                <button class="btn btn-outline" id="subscription-months-refresh" type="button">Refresh</button>
                            </div>
                            <div id="subscription-months-status" class="form-hint"></div>
                            <div class="table" id="subscription-months-table">
                                <div class="table-row table-header subscription-months">
                                    <span>Month</span>
                                    <span>Status</span>
                                    <span>Payment</span>
                                    <span>Actions</span>
                                </div>
                                <div class="table-row table-empty subscription-months">
                                    <span>Select a subscription to track months.</span>
                                    <span></span>
                                    <span></span>
                                    <span></span>
                                </div>
                            </div>
                        </div>
                    </section>
                </section>

                <section class="view staff-view" data-view="costs">
                    <section class="content-grid">
                        <div class="card wide">
                            <div class="card-header">
                                <div>
                                    <div class="card-title">Costs</div>
                                    <div class="card-subtitle">Track expenses and attach receipts.</div>
                                </div>
                                <button class="btn btn-outline" id="costs-refresh">Refresh</button>
                            </div>
                            <div class="table" id="costs-table">
                                <div class="table-row table-header costs">
                                    <span>Date</span>
                                    <span>Description</span>
                                    <span>Amount</span>
                                    <span>Type</span>
                                    <span>Receipt</span>
                                    <span>Actions</span>
                                </div>
                                <div class="table-row table-empty costs">
                                    <span>Loading costs...</span>
                                    <span></span>
                                    <span></span>
                                    <span></span>
                                    <span></span>
                                    <span></span>
                                </div>
                            </div>
                            <div class="table-actions" style="margin-top: 20px;">
                                <button class="btn btn-ghost" id="costs-load-more" type="button">Load more</button>
                            </div>
                        </div>

                        <div class="card">
                            <div class="card-header">
                                <div>
                                    <div class="card-title" id="cost-form-title">New cost</div>
                                    <div class="card-subtitle">Add expenses with receipt files.</div>
                                </div>
                            </div>
                            <form id="cost-form" class="form-stack">
                                <input type="hidden" name="id">
                                <label class="field">
                                    <span>Description</span>
                                    <textarea name="description" rows="3" required></textarea>
                                </label>
                                <label class="field">
                                    <span>Amount</span>
                                    <input type="number" name="amount" min="0" step="0.01" required>
                                </label>
                                <label class="field">
                                    <span>Incurred on</span>
                                    <input type="date" name="incurred_on" required>
                                </label>
                                <label class="field">
                                    <span>Recurring cost</span>
                                    <select name="is_recurring" id="cost-is-recurring">
                                        <option value="0">One-off</option>
                                        <option value="1">Recurring</option>
                                    </select>
                                </label>
                                <label class="field" id="cost-frequency-field">
                                    <span>Recurring frequency</span>
                                    <select name="recurring_frequency" id="cost-recurring-frequency">
                                        <option value="monthly">Monthly</option>
                                        <option value="annual">Annual</option>
                                    </select>
                                </label>
                                <label class="field">
                                    <span>Notes</span>
                                    <textarea name="notes" rows="3"></textarea>
                                </label>
                                <label class="field">
                                    <span>Receipt (image or PDF)</span>
                                    <input type="file" name="receipt" accept="image/*,application/pdf">
                                </label>
                                <div id="cost-form-status" class="form-hint"></div>
                                <div class="form-actions">
                                    <button type="submit" class="btn btn-primary">Save cost</button>
                                    <button type="button" class="btn btn-outline" id="cost-form-cancel">Cancel</button>
                                </div>
                            </form>
                        </div>
                    </section>
                </section>

                <section class="view staff-view" data-view="proposals">
                    <section class="content-grid proposals-layout">
                        <div class="card">
                            <div class="card-header">
                                <div>
                                    <div class="card-title" id="proposal-form-title">New proposal</div>
                                    <div class="card-subtitle">Complete a proposal form and build a detailed price.</div>
                                </div>
                            </div>
                            <form id="proposal-form" class="form-stack">
                                <input type="hidden" name="id">
                                <label class="field">
                                    <span>Customer</span>
                                    <select name="customer_id" id="proposal-customer-select" required></select>
                                </label>
                                <label class="field">
                                    <span>Proposal type</span>
                                    <select name="proposal_type" id="proposal-type-select" required></select>
                                </label>
                                <label class="field">
                                    <span>Title</span>
                                    <input type="text" name="title" id="proposal-title" placeholder="Website amendments proposal" required>
                                </label>
                                <label class="field">
                                    <span>Issue date</span>
                                    <input type="date" name="issue_date" required>
                                </label>
                                <label class="field">
                                    <span>Expiry date</span>
                                    <input type="date" name="expiry_date" required>
                                </label>
                                <div id="proposal-form-answers" class="form-stack"></div>
                                <div class="line-items proposal-line-items-section">
                                    <div class="line-items-header">
                                        <span>Price breakdown</span>
                                        <button type="button" class="btn btn-ghost" id="proposal-add-line-item">Add line item</button>
                                    </div>
                                    <div id="proposal-line-items"></div>
                                    <div class="proposal-price-total">
                                        <span>Total</span>
                                        <strong id="proposal-price-total">£0.00</strong>
                                    </div>
                                </div>
                                <label class="field">
                                    <span>Status</span>
                                    <select name="status">
                                        <option value="draft">Draft</option>
                                        <option value="pending">Send now / Pending</option>
                                    </select>
                                </label>
                                <label class="field">
                                    <span>Brief</span>
                                    <textarea name="notes" rows="3" placeholder="Scope, assumptions, and delivery details"></textarea>
                                </label>
                                <label class="field">
                                    <span>Terms</span>
                                    <textarea name="terms" rows="3" placeholder="Payment terms, validity, and conditions"></textarea>
                                </label>
                                <div id="proposal-form-status" class="form-hint"></div>
                                <div class="form-actions">
                                    <button type="submit" class="btn btn-primary">Save proposal</button>
                                    <button type="button" class="btn btn-outline" id="proposal-form-cancel">Cancel</button>
                                </div>
                            </form>
                        </div>

                        <div class="card wide">
                            <div class="card-header">
                                <div>
                                    <div class="card-title">Proposals</div>
                                    <div class="card-subtitle">Create, send, and download proposals.</div>
                                </div>
                                <button class="btn btn-outline" id="proposals-refresh">Refresh</button>
                            </div>
                            <div class="filters">
                                <label class="field">
                                    <span>Status</span>
                                    <select id="proposals-filter-status">
                                        <option value="all">All</option>
                                        <option value="draft">Draft</option>
                                        <option value="pending">Pending</option>
                                        <option value="approved">Approved</option>
                                        <option value="declined">Declined</option>
                                        <option value="expired">Expired</option>
                                    </select>
                                </label>
                                <label class="field">
                                    <span>Customer</span>
                                    <select id="proposals-filter-customer"></select>
                                </label>
                                <button class="btn btn-ghost" id="proposals-clear" type="button">Clear</button>
                            </div>
                            <div class="table" id="proposals-table">
                                <div class="table-row table-header proposals">
                                    <span>Proposal</span>
                                    <span>Version</span>
                                    <span>Customer</span>
                                    <span>Type</span>
                                    <span>Total</span>
                                    <span>Status</span>
                                    <span>Expires</span>
                                    <span>Actions</span>
                                </div>
                                <div class="table-row table-empty proposals">
                                    <span>Loading proposals...</span>
                                    <span></span>
                                    <span></span>
                                    <span></span>
                                    <span></span>
                                    <span></span>
                                    <span></span>
                                    <span></span>
                                </div>
                            </div>
                            <div class="table-actions" style="margin-top: 20px;">
                                <button class="btn btn-ghost" id="proposals-load-more" type="button">Load more</button>
                            </div>
                        </div>
                    </section>
                </section>

                <section class="view staff-view" data-view="invoices">
                    <section class="content-grid invoices-layout">
                        <div class="card">
                            <div class="card-header">
                                <div>
                                    <div class="card-title" id="invoice-form-title">New invoice</div>
                                    <div class="card-subtitle">Add line items and set due dates.</div>
                                </div>
                            </div>
                            <form id="invoice-form" class="form-stack">
                                <input type="hidden" name="id">
                                <label class="field">
                                    <span>Customer</span>
                                    <select name="customer_id" id="invoice-customer-select" required></select>
                                </label>
                                <label class="field">
                                    <span>Issue date</span>
                                    <input type="date" name="issue_date" required>
                                </label>
                                <label class="field">
                                    <span>Due date</span>
                                    <input type="date" name="due_date" required>
                                </label>
                                <label class="field">
                                    <span>Tax amount</span>
                                    <input type="number" name="tax_amount" min="0" step="0.01">
                                </label>
                                <label class="field">
                                    <span>Status</span>
                                    <select name="status">
                                        <option value="draft">Draft</option>
                                        <option value="sent">Sent</option>
                                        <option value="paid">Paid</option>
                                        <option value="overdue">Overdue</option>
                                    </select>
                                </label>

                                <div class="line-items">
                                    <div class="line-items-header">
                                        <span>Line items</span>
                                        <button type="button" class="btn btn-ghost" id="invoice-add-line-item">Add line item</button>
                                    </div>
                                    <div id="invoice-line-items"></div>
                                    <div class="form-hint">Use the Billable ID shown in the Jobs and Subscriptions tables.</div>
                                </div>

                                <div id="invoice-form-status" class="form-hint"></div>
                                <div class="form-actions">
                                    <button type="submit" class="btn btn-primary">Save invoice</button>
                                    <button type="button" class="btn btn-outline" id="invoice-form-cancel">Cancel</button>
                                </div>
                            </form>
                        </div>

                        <div class="card wide">
                            <div class="card-header">
                                <div>
                                    <div class="card-title">Invoices</div>
                                    <div class="card-subtitle">Create, send, and download invoices.</div>
                                </div>
                                <div class="card-header-actions">
                                    <button class="btn btn-outline" id="invoices-paid-toggle" type="button">Paid invoices</button>
                                    <button class="btn btn-outline" id="invoices-refresh">Refresh</button>
                                </div>
                            </div>
                            <div class="filters">
                                <label class="field">
                                    <span>Status</span>
                                    <select id="invoices-filter-status">
                                        <option value="all">All</option>
                                        <option value="draft">Draft</option>
                                        <option value="sent">Sent</option>
                                        <option value="overdue">Overdue</option>
                                    </select>
                                </label>
                                <label class="field">
                                    <span>Customer</span>
                                    <select id="invoices-filter-customer"></select>
                                </label>
                                <button class="btn btn-ghost" id="invoices-clear" type="button">Clear</button>
                            </div>
                            <div class="table" id="invoices-table">
                                <div class="table-row table-header invoices">
                                    <span>Invoice</span>
                                    <span>Customer</span>
                                    <span>Total</span>
                                    <span>Status</span>
                                    <span>Due</span>
                                    <span>Actions</span>
                                </div>
                                <div class="table-row table-empty invoices">
                                    <span>Loading invoices...</span>
                                    <span></span>
                                    <span></span>
                                    <span></span>
                                    <span></span>
                                    <span></span>
                                </div>
                            </div>
                            <div class="table-actions" style="margin-top: 20px;">
                                <button class="btn btn-ghost" id="invoices-load-more" type="button">Load more</button>
                            </div>
                        </div>
                    </section>
                </section>

                <section class="view staff-view admin-view" data-view="admin">
                    <section class="content-grid admin-panel">
                        <div class="card admin-only organisation-settings-card">
                            <div class="card-header"><div><div class="card-title">CRM customisation</div><div class="card-subtitle">Brand, regional, document, portal and dashboard defaults for this organisation.</div></div></div>
                            <form id="organisation-settings-form" class="form-stack">
                                <details open><summary>Company and branding</summary><div class="settings-fields">
                                    <label class="field"><span>Company name</span><input name="company_name" required maxlength="120"></label>
                                    <label class="field"><span>Trading name</span><input name="trading_name" maxlength="120"></label>
                                    <label class="field"><span>Business email</span><input name="business_email" type="email"></label>
                                    <label class="field"><span>Phone</span><input name="business_phone"></label>
                                    <label class="field"><span>Website</span><input name="website_url" type="url"></label>
                                    <label class="field"><span>Company number</span><input name="company_number"></label>
                                    <label class="field"><span>VAT number</span><input name="vat_number"></label>
                                    <label class="field"><span>Login title</span><input name="login_title" required></label>
                                    <label class="field"><span>Primary colour</span><input name="primary_colour" type="color"></label>
                                    <label class="field"><span>Accent colour</span><input name="accent_colour" type="color"></label>
                                    <label class="field"><span>Light page background</span><input name="background_colour" type="color"></label>
                                    <label class="field"><span>Light card background</span><input name="surface_colour" type="color"></label>
                                    <label class="field"><span>Dark page background</span><input name="dark_background_colour" type="color"></label>
                                    <label class="field"><span>Dark card background</span><input name="dark_surface_colour" type="color"></label>
                                    <label class="field settings-field-wide"><span>Business address</span><textarea name="business_address" rows="3"></textarea></label>
                                    <label class="field settings-field-wide"><span>Footer text</span><input name="footer_text" maxlength="500"></label>
                                </div></details>
                                <details><summary>Regional and financial defaults</summary><div class="settings-fields">
                                    <label class="field"><span>Currency</span><select name="currency"><option>GBP</option><option>EUR</option><option>USD</option></select></label>
                                    <label class="field"><span>Timezone</span><select name="timezone"><option>Europe/London</option><option>Europe/Dublin</option><option>America/New_York</option><option>America/Los_Angeles</option><option>UTC</option></select></label>
                                    <label class="field"><span>Date format</span><select name="date_format"><option value="d/m/Y">DD/MM/YYYY</option><option value="m/d/Y">MM/DD/YYYY</option><option value="Y-m-d">YYYY-MM-DD</option></select></label>
                                    <label class="field"><span>Financial year starts</span><select name="financial_year_start_month"><option value="1">January</option><option value="4">April</option><option value="7">July</option><option value="10">October</option></select></label>
                                </div></details>
                                <details><summary>Invoices and proposals</summary><div class="settings-fields">
                                    <label class="field"><span>Invoice prefix</span><input name="invoice_prefix" required></label><label class="field"><span>Payment terms (days)</span><input name="invoice_payment_terms_days" type="number" min="0"></label>
                                    <label class="field"><span>Default tax rate (%)</span><input name="default_tax_rate" type="number" min="0" max="100" step="0.01"></label><label class="field"><span>Proposal prefix</span><input name="proposal_prefix" required></label>
                                    <label class="field"><span>Proposal validity (days)</span><input name="proposal_validity_days" type="number" min="1"></label>
                                    <label class="field settings-field-wide"><span>Default invoice notes</span><textarea name="invoice_notes" rows="3"></textarea></label>
                                    <label class="field settings-field-wide"><span>Invoice footer</span><textarea name="invoice_footer" rows="2"></textarea></label>
                                    <label class="field settings-field-wide"><span>Default proposal terms</span><textarea name="proposal_terms" rows="4"></textarea></label>
                                </div></details>
                                <details><summary>Email defaults and templates</summary><div class="settings-fields">
                                    <label class="field"><span>Sender name</span><input name="sender_name" required></label><label class="field"><span>Reply-to email</span><input name="reply_to_email" type="email"></label>
                                    <label class="field settings-field-wide"><span>Email signature</span><textarea name="email_signature" rows="3"></textarea></label>
                                    <label class="field settings-field-wide"><span>Invoice email template</span><textarea name="invoice_email_template" rows="4" placeholder="Use placeholders such as @{{customer_name}} and @{{invoice_number}}"></textarea></label>
                                    <label class="field settings-field-wide"><span>Proposal email template</span><textarea name="proposal_email_template" rows="4"></textarea></label>
                                    <label class="field settings-field-wide"><span>Reminder email template</span><textarea name="reminder_email_template" rows="4"></textarea></label>
                                </div></details>
                                <details><summary>Customer portal</summary><div class="settings-fields">
                                    <label class="field settings-field-wide"><span>Welcome message</span><textarea name="portal_welcome_message" rows="3"></textarea></label><label class="field"><span>Support email</span><input name="portal_support_email" type="email"></label>
                                    <div class="settings-checks settings-field-wide"><label class="check-row"><input type="checkbox" name="portal_show_jobs"><span>Show jobs</span></label><label class="check-row"><input type="checkbox" name="portal_show_invoices"><span>Show invoices</span></label><label class="check-row"><input type="checkbox" name="portal_show_proposals"><span>Show proposals</span></label><label class="check-row"><input type="checkbox" name="portal_show_subscriptions"><span>Show subscriptions</span></label><label class="check-row"><input type="checkbox" name="portal_allow_invoice_payment"><span>Allow invoice payment updates</span></label><label class="check-row"><input type="checkbox" name="portal_allow_proposal_approval"><span>Allow proposal approval</span></label></div>
                                </div></details>
                                <details><summary>Dashboard and security defaults</summary><div class="settings-fields">
                                    <label class="field"><span>Session timeout (minutes)</span><input name="session_timeout_minutes" type="number" min="15"></label><label class="field"><span>Default reporting period</span><select name="default_dashboard_period"><option value="week">Week</option><option value="month">Month</option><option value="quarter">Quarter</option><option value="year">Year</option></select></label>
                                    <label class="field"><span>MRR target</span><input name="mrr_target" type="number" min="0" step="0.01"></label><label class="field"><span>Revenue target</span><input name="revenue_target" type="number" min="0" step="0.01"></label>
                                    <label class="field"><span>Overdue warning (days)</span><input name="overdue_warning_days" type="number" min="0"></label><label class="field"><span>Lead inactivity warning (days)</span><input name="lead_inactivity_days" type="number" min="1"></label>
                                    <div class="settings-checks settings-field-wide"><label class="check-row"><input type="checkbox" name="show_today_overdue"><span>Show overdue actions in Today</span></label><label class="check-row"><input type="checkbox" name="show_today_upcoming"><span>Show upcoming actions</span></label><label class="check-row"><input type="checkbox" name="show_today_invoices"><span>Show invoices</span></label><label class="check-row"><input type="checkbox" name="show_today_proposals"><span>Show proposals</span></label></div>
                                </div></details>
                                <div id="organisation-settings-status" class="form-hint" aria-live="polite"></div><button class="btn btn-primary" type="submit">Save CRM customisation</button>
                            </form>
                        </div>
                        <div class="card wide admin-only">
                            <div class="card-header">
                                <div>
                                    <div class="card-title">Brand settings</div>
                                    <div class="card-subtitle">Upload a new logo for the top navigation.</div>
                                </div>
                            </div>
                            <form id="logo-upload-form" class="form-stack">
                                <label class="field">
                                    <span>Logo file (PNG, JPG, SVG)</span>
                                    <input type="file" name="logo" accept=".png,.jpg,.jpeg,.svg" required>
                                </label>
                                <div id="logo-upload-status" class="form-hint"></div>
                                <button type="submit" class="btn btn-primary">Upload logo</button>
                            </form>
                        </div>

                        <div class="card admin-only">
                            <div class="card-header">
                                <div>
                                    <div class="card-title">Email delivery</div>
                                    <div class="card-subtitle">Use SMTP2GO API for invoice sending.</div>
                                </div>
                            </div>
                            <form id="smtp2go-settings-form" class="form-stack">
                                <label class="field">
                                    <span>Provider</span>
                                    <select name="smtp2go_enabled" id="smtp2go-enabled">
                                        <option value="0">Default server</option>
                                        <option value="1">SMTP2GO API</option>
                                    </select>
                                </label>
                                <label class="field">
                                    <span>SMTP2GO API key</span>
                                    <input type="password" name="smtp2go_api_key" id="smtp2go-api-key" placeholder="Paste key to save or rotate">
                                </label>
                                <div class="form-hint" id="smtp2go-api-key-mask"></div>
                                <div id="smtp2go-settings-status" class="form-hint"></div>
                                <button type="submit" class="btn btn-primary">Save mail settings</button>
                            </form>
                        </div>

                        <div class="card admin-only">
                            <div class="card-header">
                                <div>
                                    <div class="card-title">Invoice payment details</div>
                                    <div class="card-subtitle">Shown on generated invoice PDFs.</div>
                                </div>
                            </div>
                            <form id="invoice-settings-form" class="form-stack">
                                <label class="field">
                                    <span>Account Name</span>
                                    <input type="text" name="account_name" id="invoice-account-name" required>
                                </label>
                                <label class="field">
                                    <span>Sort Code</span>
                                    <input type="text" name="sort_code" id="invoice-sort-code" required>
                                </label>
                                <label class="field">
                                    <span>Account Number</span>
                                    <input type="text" name="account_number" id="invoice-account-number" required>
                                </label>
                                <div id="invoice-settings-status" class="form-hint"></div>
                                <button type="submit" class="btn btn-primary">Save payment settings</button>
                            </form>
                        </div>

                        <div class="card wide admin-only">
                            <div class="card-header">
                                <div>
                                    <div class="card-title">Edit Proposal forms</div>
                                    <div class="card-subtitle">Choose a proposal form to edit its questions.</div>
                                </div>
                                <button class="btn btn-outline" id="proposal-forms-add-type" type="button">Add type</button>
                            </div>
                            <div id="proposal-forms-editor" class="form-stack"></div>
                            <div id="proposal-forms-settings-status" class="form-hint"></div>
                        </div>

                        <div class="card wide admin-only">
                            <div class="card-header">
                                <div>
                                    <div class="card-title">Edit Customer forms</div>
                                    <div class="card-subtitle">Create and edit the forms that can be sent from a customer's page.</div>
                                </div>
                                <button class="btn btn-outline" id="customer-forms-add-type" type="button">Add form</button>
                            </div>
                            <div id="customer-forms-editor" class="form-stack"></div>
                            <div id="customer-forms-settings-status" class="form-hint"></div>
                        </div>

                        <div class="card">
                            <div class="card-header">
                                <div>
                                    <div class="card-title">Profile</div>
                                    <div class="card-subtitle">Update your account contact details.</div>
                                </div>
                            </div>
                            <form id="profile-form" class="form-stack">
                                <label class="field">
                                    <span>Name</span>
                                    <input type="text" name="name" id="profile-name" required>
                                </label>
                                <label class="field">
                                    <span>Email</span>
                                    <input type="email" name="email" id="profile-email" required>
                                </label>
                                <div id="profile-form-status" class="form-hint"></div>
                                <button type="submit" class="btn btn-primary">Save profile</button>
                            </form>
                        </div>

                        <div class="card">
                            <div class="card-header">
                                <div>
                                    <div class="card-title">Reset password</div>
                                    <div class="card-subtitle">Choose a new account password.</div>
                                </div>
                            </div>
                            <form id="password-form" class="form-stack">
                                <label class="field">
                                    <span>Current password</span>
                                    <input type="password" name="current_password" id="current-password" required>
                                </label>
                                <label class="field">
                                    <span>New password</span>
                                    <input type="password" name="password" id="new-password" required>
                                </label>
                                <label class="field">
                                    <span>Confirm new password</span>
                                    <input type="password" name="password_confirmation" id="new-password-confirmation" required>
                                </label>
                                <div id="password-form-status" class="form-hint"></div>
                                <button type="submit" class="btn btn-primary">Update password</button>
                            </form>
                        </div>
                    </section>
                </section>

                <section class="view staff-view admin-only-view" data-view="proposal-form-edit">
                    <section class="content-grid">
                        <div class="card wide admin-only">
                            <div class="card-header">
                                <div>
                                    <div class="card-title" id="proposal-form-edit-title">Edit proposal form</div>
                                    <div class="card-subtitle">Update the form name and questions shown when creating this proposal type.</div>
                                </div>
                                <button class="btn btn-outline" id="proposal-form-edit-back" type="button">Back to Admin</button>
                            </div>
                            <form id="proposal-forms-settings-form" class="form-stack">
                                <label class="field">
                                    <span>Proposal type name</span>
                                    <input type="text" id="proposal-form-edit-label" required>
                                </label>
                                <div id="proposal-form-edit-editor" class="form-stack"></div>
                                <div id="proposal-form-edit-status" class="form-hint"></div>
                                <div class="form-actions">
                                    <button type="button" class="btn btn-outline" id="proposal-form-edit-add-question">Add question</button>
                                    <button type="submit" class="btn btn-primary">Save form</button>
                                    <button type="button" class="btn btn-ghost" id="proposal-form-edit-delete">Delete form</button>
                                </div>
                            </form>
                        </div>
                    </section>
                </section>

                <section class="view staff-view admin-only-view" data-view="customer-form-edit">
                    <section class="content-grid">
                        <div class="card wide admin-only">
                            <div class="card-header">
                                <div>
                                    <div class="card-title" id="customer-form-edit-title">Edit customer form</div>
                                    <div class="card-subtitle">Update the form name and questions shown to customers in their portal.</div>
                                </div>
                                <button class="btn btn-outline" id="customer-form-edit-back" type="button">Back to Admin</button>
                            </div>
                            <form id="customer-forms-settings-form" class="form-stack">
                                <label class="field">
                                    <span>Form name</span>
                                    <input type="text" id="customer-form-edit-label" required>
                                </label>
                                <div id="customer-form-edit-editor" class="form-stack"></div>
                                <div id="customer-form-edit-status" class="form-hint"></div>
                                <div class="form-actions">
                                    <button type="button" class="btn btn-outline" id="customer-form-edit-add-question">Add question</button>
                                    <button type="submit" class="btn btn-primary">Save form</button>
                                    <button type="button" class="btn btn-ghost" id="customer-form-edit-delete">Delete form</button>
                                </div>
                            </form>
                        </div>
                    </section>
                </section>

                <section class="view portal-view" data-view="portal">
                    <section class="content-grid">
                        <div class="card wide">
                            <div class="card-header">
                                <div>
                                    <div class="card-title">Your invoices</div>
                                    <div class="card-subtitle">Download or review billing history</div>
                                </div>
                                <button class="btn btn-outline" id="portal-download-latest">Download latest PDF</button>
                            </div>
                            <div class="table" id="portal-invoices">
                                <div class="table-row table-header portal-invoices">
                                    <span>Invoice</span>
                                    <span>Status</span>
                                    <span>Amount</span>
                                    <span>Due</span>
                                    <span>Actions</span>
                                </div>
                                <div class="table-row table-empty portal-invoices">
                                    <span>Loading invoices...</span>
                                    <span></span>
                                    <span></span>
                                    <span></span>
                                    <span></span>
                                </div>
                            </div>
                        </div>

                        <div class="card">
                            <div class="card-header">
                                <div>
                                    <div class="card-title">Your websites</div>
                                    <div class="card-subtitle">Quick login links</div>
                                </div>
                            </div>
                            <div class="stack" id="portal-websites">
                                <div class="site-card">
                                    <div>
                                        <div class="site-name">Loading websites...</div>
                                        <div class="site-url">Please wait.</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </section>

                    <section class="content-grid">
                        <div class="card wide">
                            <div class="card-header">
                                <div>
                                    <div class="card-title">Your jobs</div>
                                    <div class="card-subtitle">All one-off jobs on your account</div>
                                </div>
                            </div>
                            <div class="table" id="portal-jobs">
                                <div class="table-row table-header portal-jobs">
                                    <span>Description</span>
                                    <span>Cost</span>
                                    <span>Status</span>
                                    <span>Completed</span>
                                </div>
                                <div class="table-row table-empty portal-jobs">
                                    <span>Loading jobs...</span>
                                    <span></span>
                                    <span></span>
                                    <span></span>
                                </div>
                            </div>
                        </div>

                        <div class="card">
                            <div class="card-header">
                                <div>
                                    <div class="card-title">Your subscriptions</div>
                                    <div class="card-subtitle">All recurring services</div>
                                </div>
                            </div>
                            <div class="table" id="portal-subscriptions">
                                <div class="table-row table-header portal-subscriptions">
                                    <span>Description</span>
                                    <span>Monthly</span>
                                    <span>Status</span>
                                    <span>Next invoice</span>
                                </div>
                                <div class="table-row table-empty portal-subscriptions">
                                    <span>Loading subscriptions...</span>
                                    <span></span>
                                    <span></span>
                                    <span></span>
                                </div>
                            </div>
                        </div>
                    </section>
                </section>

                <section class="view portal-view" data-view="portal-websites">
                    <section class="content-grid"><div class="card wide"><div class="card-header"><div><div class="card-title">Your websites</div><div class="card-subtitle">Current availability, maintenance and service information.</div></div><button class="btn btn-outline" id="portal-websites-refresh" type="button">Refresh</button></div><div class="stack" id="portal-websites-detail"><div class="site-card"><div><div class="site-name">Loading websites…</div></div></div></div></div></section>
                </section>

                <section class="view portal-view" data-view="portal-website-detail">
                    <section class="content-grid">
                        <div class="card wide"><div class="card-header"><div><div class="card-title" id="portal-website-detail-title">Website</div><div class="card-subtitle" id="portal-website-detail-domain">Loading details…</div></div><div class="card-header-actions"><a class="btn btn-primary" id="portal-website-visit" href="#" target="_blank" rel="noopener">Visit website</a><button class="btn btn-outline" id="portal-website-detail-back" type="button">Back</button></div></div><div class="stats-grid" id="portal-website-detail-summary"></div></div>
                        <div class="card wide"><div class="card-header"><div><div class="card-title">Website care</div><div class="card-subtitle">Availability, security, backups, performance and maintenance.</div></div></div><div class="detail-grid" id="portal-website-detail-care"></div></div>
                        <div class="card wide"><div class="card-header"><div><div class="card-title">Recent work</div><div class="card-subtitle">Customer-visible maintenance and website activity.</div></div></div><div class="stack" id="portal-website-detail-activities"></div></div>
                    </section>
                </section>

                <section class="view portal-view" data-view="portal-proposals">
                    <section class="content-grid">
                        <div class="card wide">
                            <div class="card-header">
                                <div>
                                    <div class="card-title">Your proposals</div>
                                    <div class="card-subtitle">Review, approve, decline, and download proposal PDFs</div>
                                </div>
                                <button class="btn btn-outline" id="portal-proposals-refresh" type="button">Refresh</button>
                            </div>
                            <div class="table" id="portal-proposals">
                                <div class="table-row table-header portal-proposals">
                                    <span>Proposal</span>
                                    <span>Title</span>
                                    <span>Total</span>
                                    <span>Status</span>
                                    <span>Expires</span>
                                    <span>Actions</span>
                                </div>
                                <div class="table-row table-empty portal-proposals">
                                    <span>Loading proposals...</span>
                                    <span></span>
                                    <span></span>
                                    <span></span>
                                    <span></span>
                                    <span></span>
                                </div>
                            </div>
                        </div>
                    </section>
                </section>

                <section class="view portal-view" data-view="portal-forms">
                    <section class="content-grid">
                        <div class="card wide">
                            <div class="card-header">
                                <div>
                                    <div class="card-title">Forms</div>
                                    <div class="card-subtitle">Complete forms requested by WebStamp and review previous submissions.</div>
                                </div>
                                <button class="btn btn-outline" id="portal-forms-refresh" type="button">Refresh</button>
                            </div>
                            <div class="table" id="portal-forms-table">
                                <div class="table-row table-header portal-forms">
                                    <span>Form</span><span>Status</span><span>Sent</span><span>Completed</span><span>Actions</span>
                                </div>
                                <div class="table-row table-empty portal-forms">
                                    <span>No forms available.</span><span></span><span></span><span></span><span></span>
                                </div>
                            </div>
                        </div>

                        <div class="crm-modal-backdrop" id="portal-form-panel" hidden>
                            <section class="crm-modal customer-form-modal" role="dialog" aria-modal="true" aria-labelledby="portal-form-title">
                            <div class="crm-modal-header">
                                <div>
                                    <div class="card-title" id="portal-form-title">Customer form</div>
                                    <div class="card-subtitle" id="portal-form-subtitle"></div>
                                </div>
                                <button class="crm-modal-close" id="portal-form-close" type="button" aria-label="Close">×</button>
                            </div>
                            <form id="portal-customer-form" class="form-stack">
                                <div id="portal-form-fields" class="form-stack"></div>
                                <div id="portal-form-status" class="form-hint"></div>
                                <button class="btn btn-primary" id="portal-form-submit" type="submit">Submit Form</button>
                            </form>
                            </section>
                        </div>
                    </section>
                </section>

                <section class="view portal-view" data-view="portal-support">
                    <section class="content-grid">
                        <div class="card wide">
                            <div class="card-header">
                                <div>
                                    <div class="card-title">Support</div>
                                    <div class="card-subtitle">Send us details so we can troubleshoot quickly.</div>
                                </div>
                            </div>
                            <form id="portal-support-form" class="form-stack" enctype="multipart/form-data">
                                <label class="field">
                                    <span>What's the problem?</span>
                                    <input type="text" name="problem" id="portal-support-problem" required>
                                </label>
                                <label class="field">
                                    <span>Tell us about this issue</span>
                                    <textarea name="message" id="portal-support-message" rows="6" required></textarea>
                                </label>
                                <label class="field">
                                    <span>Upload a screenshot of the issue to help us troubleshoot</span>
                                    <input type="file" name="screenshot" id="portal-support-screenshot" accept="image/*,application/pdf">
                                </label>
                                <div id="portal-support-status" class="form-hint"></div>
                                <button type="submit" class="btn btn-primary">Send support request</button>
                            </form>
                        </div>
                    </section>
                </section>

                <section class="view portal-view" data-view="portal-admin">
                    <section class="content-grid">
                        <div class="card wide">
                            <div class="card-header">
                                <div>
                                    <div class="card-title">Account settings</div>
                                    <div class="card-subtitle">Update your portal details and password</div>
                                </div>
                            </div>
                            <form id="portal-profile-form" class="form-stack">
                                <label class="field">
                                    <span>Name</span>
                                    <input type="text" name="name" id="portal-profile-name" required>
                                </label>
                                <label class="field">
                                    <span>Email</span>
                                    <input type="email" name="email" id="portal-profile-email" required>
                                </label>
                                <label class="field">
                                    <span>Phone number</span>
                                    <input type="tel" name="phone" id="portal-profile-phone" autocomplete="tel" maxlength="50">
                                </label>
                                <label class="field">
                                    <span>Billing address</span>
                                    <textarea name="billing_address" id="portal-profile-billing-address" rows="3" required></textarea>
                                </label>
                                <div id="portal-profile-status" class="form-hint"></div>
                                <button type="submit" class="btn btn-primary">Save details</button>
                            </form>

                            <hr style="margin: 22px 0; border: 0; border-top: 1px solid var(--border);">

                            <form id="portal-password-form" class="form-stack">
                                <label class="field">
                                    <span>Current password</span>
                                    <input type="password" name="current_password" required>
                                </label>
                                <label class="field">
                                    <span>New password</span>
                                    <input type="password" name="password" required>
                                </label>
                                <label class="field">
                                    <span>Confirm new password</span>
                                    <input type="password" name="password_confirmation" required>
                                </label>
                                <div id="portal-password-status" class="form-hint"></div>
                                <button type="submit" class="btn btn-primary">Update password</button>
                            </form>
                        </div>
                    </section>
                </section>
            </main>
        </div>

        <div class="crm-modal-backdrop" id="opportunity-follow-up-modal" hidden>
            <section class="crm-modal" role="dialog" aria-modal="true" aria-labelledby="opportunity-follow-up-title">
                <div class="crm-modal-header">
                    <div><div class="card-title" id="opportunity-follow-up-title">Schedule follow-up</div><div class="card-subtitle" id="opportunity-follow-up-subtitle">Choose when the administrator should be reminded.</div></div>
                    <button class="crm-modal-close" id="opportunity-follow-up-close" type="button" aria-label="Close">×</button>
                </div>
                <form id="opportunity-follow-up-form" class="form-stack">
                    <label class="field"><span>Follow-up date</span><input type="date" name="due_date" id="opportunity-follow-up-date" required></label>
                    <label class="field"><span>Notes</span><textarea name="notes" rows="5" placeholder="What should you discuss or prepare for this follow-up?"></textarea></label>
                    <div id="opportunity-follow-up-status" class="form-hint"></div>
                    <button class="btn btn-primary" type="submit">Save follow-up</button>
                </form>
            </section>
        </div>

        <div class="crm-modal-backdrop" id="customer-website-modal" hidden>
            <section class="crm-modal customer-form-modal customer-website-modal" role="dialog" aria-modal="true" aria-labelledby="customer-website-title">
                <div class="crm-modal-header">
                    <div><div class="card-title" id="customer-website-title">Add website</div><div class="card-subtitle" id="customer-website-modal-subtitle">Choose how this website should be connected.</div></div>
                    <button class="crm-modal-close" id="customer-website-modal-close" type="button" aria-label="Close">×</button>
                </div>

                <div id="customer-website-choice" class="website-add-choices">
                    <button class="website-add-choice" type="button" data-customer-website-mode="create">
                        <strong>Create with Krystal</strong><span>Create a new cPanel account and add it to the CRM automatically.</span><small>Recommended for a new hosted website</small>
                    </button>
                    <button class="website-add-choice" type="button" data-customer-website-mode="connect">
                        <strong>Connect existing Krystal website</strong><span>Find a domain already hosted in your Krystal account and link it to this customer.</span><small>No technical mapping required</small>
                    </button>
                    <button class="website-add-choice website-add-choice-secondary" type="button" data-customer-website-mode="external">
                        <strong>Add externally hosted website</strong><span>Add a website that is not hosted through your Krystal account.</span>
                    </button>
                </div>

                <form id="customer-website-krystal-form" class="form-stack" hidden>
                    <div class="guided-customer-label">Customer: <strong id="customer-website-krystal-customer">—</strong></div>
                    <label class="field"><span>Website name</span><input name="name" required placeholder="Customer website"></label>
                    <label class="field"><span>Domain</span><input name="domain" required placeholder="example.co.uk" inputmode="url" autocapitalize="none" spellcheck="false"></label>
                    <label class="field"><span>Krystal hosting package</span><select name="hosting_package_id" id="customer-website-krystal-package" required><option value="">Select package</option></select></label>
                    <label class="field"><span>Website type</span><select name="website_type"><option value="wordpress">WordPress</option><option value="blank">Blank hosting</option></select></label>
                    <div id="customer-website-krystal-status" class="form-hint"></div>
                    <div class="form-actions"><button type="button" class="btn btn-outline" data-customer-website-back>Back</button><button type="submit" class="btn btn-primary">Create and connect website</button></div>
                </form>

                <div id="customer-website-connect-panel" hidden>
                    <div class="guided-customer-label">Connecting to: <strong id="customer-website-connect-customer">—</strong></div>
                    <div id="customer-website-connect-status" class="form-hint">Scanning Krystal for websites…</div>
                    <div id="customer-website-connect-list" class="stack customer-website-connect-list"></div>
                    <div class="form-actions"><button type="button" class="btn btn-outline" data-customer-website-back>Back</button><button type="button" class="btn btn-outline" id="customer-website-connect-refresh">Scan again</button></div>
                </div>

                <form id="customer-website-form" class="form-stack" hidden>
                    <input type="hidden" name="id">
                    <label class="field"><span>Website name</span><input type="text" name="name" required></label>
                    <label class="field"><span>Website URL</span><input type="url" name="login_url" placeholder="https://example.com" required></label>
                    <label class="field"><span>Notes</span><textarea name="notes" rows="3"></textarea></label>
                    <div id="customer-website-status" class="form-hint"></div>
                    <div class="form-actions"><button type="button" class="btn btn-outline" id="customer-website-cancel">Back</button><button type="submit" class="btn btn-primary">Save website</button></div>
                </form>
            </section>
        </div>

        <div class="crm-modal-backdrop" id="website-edit-modal" hidden>
            <section class="crm-modal customer-form-modal website-edit-modal" role="dialog" aria-modal="true" aria-labelledby="website-edit-title">
                <div class="crm-modal-header">
                    <div><div class="card-title" id="website-edit-title">Edit website</div><div class="card-subtitle">Update the CRM details and choose whether WordPress monitoring is required.</div></div>
                    <button class="crm-modal-close" id="website-edit-close" type="button" aria-label="Close">×</button>
                </div>
                <form id="website-edit-form" class="form-stack">
                    <input type="hidden" name="id">
                    <label class="field"><span>Customer</span><select name="customer_id" required><option value="">Select customer</option></select></label>
                    <label class="field"><span>Website name</span><input name="name" required></label>
                    <label class="field"><span>Domain</span><input name="domain" required placeholder="example.co.uk" inputmode="url" autocapitalize="none" spellcheck="false"></label>
                    <label class="field"><span>Website URL</span><input name="login_url" type="url" required placeholder="https://example.co.uk"></label>
                    <label class="field"><span>Environment</span><select name="environment"><option value="production">Production</option><option value="staging">Staging</option><option value="development">Development</option></select></label>
                    <div class="website-edit-switches">
                        <label class="check-row"><input name="wordpress_enabled" type="checkbox"><span>WordPress website — monitoring can be connected</span></label>
                        <label class="check-row"><input name="management_enabled" type="checkbox"><span>Managed by us</span></label>
                        <label class="check-row"><input name="hosting_enabled" type="checkbox"><span>Hosted by us</span></label>
                    </div>
                    <label class="field"><span>GA4 Property ID</span><input name="google_analytics_property_id"></label>
                    <label class="field"><span>Google Analytics dashboard URL</span><input name="google_analytics_dashboard_url" type="url" placeholder="https://analytics.google.com/..."></label>
                    <label class="field"><span>Internal notes</span><textarea name="notes" rows="4"></textarea></label>
                    <div id="website-edit-status" class="form-hint"></div>
                    <div class="form-actions"><button class="btn btn-outline" id="website-edit-cancel" type="button">Cancel</button><button class="btn btn-primary" type="submit">Save changes</button></div>
                </form>
            </section>
        </div>

        <div class="crm-modal-backdrop" id="website-delete-modal" hidden>
            <section class="crm-modal customer-form-modal" role="dialog" aria-modal="true" aria-labelledby="website-delete-title">
                <div class="crm-modal-header"><div><div class="card-title" id="website-delete-title">Delete website</div><div class="card-subtitle">This action is recorded permanently.</div></div><button class="crm-modal-close" id="website-delete-close" type="button" aria-label="Close">×</button></div>
                <form id="website-delete-form" class="form-stack">
                    <div class="danger-warning" id="website-delete-preview">Loading deletion checks…</div>
                    <label class="check-row"><input type="radio" name="deletion_type" value="crm_only" checked><span><strong>Remove from CRM only</strong><br>Leaves the cPanel account and website online.</span></label>
                    <label class="check-row"><input type="radio" name="deletion_type" value="hosting_and_crm" id="website-delete-hosting-choice"><span><strong>Delete hosting and remove from CRM</strong><br>Permanently terminates the verified, exclusive cPanel account.</span></label>
                    <label class="field"><span>Type the domain to confirm</span><input name="confirmation" id="website-delete-confirmation" autocomplete="off" required></label>
                    <label class="check-row"><input type="checkbox" name="backup_confirmed" id="website-delete-backup"><span>I have reviewed the latest known backup information and understand restoration is not guaranteed.</span></label>
                    <div id="website-delete-status" class="form-hint"></div>
                    <div class="form-actions"><button class="btn btn-outline" id="website-delete-cancel" type="button">Cancel</button><button class="btn btn-danger" id="website-delete-submit" type="submit" disabled>Delete website</button></div>
                </form>
            </section>
        </div>

        <div id="app-toast" class="toast" role="status" aria-live="polite"></div>

        <div class="crm-modal-backdrop" id="staff-user-detail-modal" hidden>
            <section class="crm-modal customer-form-modal staff-user-detail-modal" role="dialog" aria-modal="true" aria-labelledby="staff-user-detail-title">
                <div class="crm-modal-header">
                    <div><div class="card-title" id="staff-user-detail-title">Staff overview</div><div class="card-subtitle" id="staff-user-detail-subtitle"></div></div>
                    <button class="crm-modal-close" id="staff-user-detail-close" type="button" aria-label="Close">×</button>
                </div>
                <div id="staff-user-detail-content"><div class="form-hint">Loading staff overview...</div></div>
            </section>
        </div>

        <template id="invoice-line-item-template">
            <div class="line-item">
                <input type="text" name="description" placeholder="Description" required>
                <input type="number" name="quantity" min="0.5" step="0.5" value="1" required>
                <input type="number" name="unit_price" min="0" step="0.01" placeholder="Unit price" required>
                <select name="billable_type">
                    <option value="">Manual</option>
                    <option value="job">Job</option>
                    <option value="subscription">Subscription</option>
                </select>
                <select name="billable_id" disabled>
                    <option value="">Manual line item</option>
                </select>
                <button type="button" class="btn btn-outline btn-small" data-action="remove-line-item">Remove</button>
            </div>
        </template>

        <template id="proposal-line-item-template">
            <div class="line-item proposal-line-item">
                <input type="text" name="description" placeholder="Description" aria-label="Description" required>
                <input type="number" name="quantity" min="0.01" step="0.01" value="1" placeholder="Quantity" aria-label="Quantity" required>
                <input type="number" name="unit_price" min="0" step="0.01" placeholder="Unit price" aria-label="Unit price" required>
                <output class="proposal-line-total" aria-label="Line total">£0.00</output>
                <button type="button" class="btn btn-outline btn-small" data-action="remove-proposal-line-item">Remove</button>
            </div>
        </template>
    </body>
</html>
