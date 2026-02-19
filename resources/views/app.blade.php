<!DOCTYPE html>
<html lang="en" data-theme="light">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>WebStamp CRM</title>
        <link rel="icon" href="/favicon.ico">
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
                    <h1>Welcome back</h1>
                    <p>Sign in to manage customers, subscriptions, and invoices.</p>

                    <form id="login-form" class="form-stack">
                        <label class="field">
                            <span>Email</span>
                            <input type="email" name="email" placeholder="you@company.com" required>
                        </label>
                        <label class="field">
                            <span>Password</span>
                            <input type="password" name="password" placeholder="********" required>
                        </label>
                        <div id="login-error" class="form-error"></div>
                        <button type="submit" class="btn btn-primary">Sign in</button>
                        <div class="form-hint">
                            Demo: admin@example.com / password
                        </div>
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
                    <a href="#" class="nav-item" data-view="customers">Customers</a>
                    <a href="#" class="nav-item" data-view="jobs">Jobs</a>
                    <a href="#" class="nav-item" data-view="subscriptions">Subscriptions</a>
                    <a href="#" class="nav-item" data-view="costs">Costs</a>
                    <a href="#" class="nav-item" data-view="invoices">Invoices</a>
                    <a href="#" class="nav-item admin-only" data-view="admin">Admin</a>
                    <a href="#" class="nav-item customer-only" data-view="portal">My Portal</a>
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
                        <div class="card highlight">
                            <div class="card-label">Revenue this month</div>
                            <div class="card-value" id="dashboard-revenue">--</div>
                            <div class="card-meta">Completed jobs this month + active subscriptions</div>
                        </div>
                        <div class="card">
                            <div class="card-label">Costs this month</div>
                            <div class="card-value" id="dashboard-costs">--</div>
                            <div class="card-meta">Total incurred costs this month</div>
                        </div>
                        <div class="card">
                            <div class="card-label">Profit this month</div>
                            <div class="card-value" id="dashboard-profit">--</div>
                            <div class="card-meta">Revenue this month minus costs this month</div>
                        </div>
                        <div class="card">
                            <div class="card-label">Customers</div>
                            <div class="card-value" data-stat="customers">--</div>
                            <div class="card-meta">Active accounts</div>
                        </div>
                        <div class="card">
                            <div class="card-label">Jobs</div>
                            <div class="card-value" data-stat="jobs">--</div>
                            <div class="card-meta">Open or invoiced</div>
                        </div>
                        <div class="card">
                            <div class="card-label">Subscriptions</div>
                            <div class="card-value" data-stat="subscriptions">--</div>
                            <div class="card-meta">Recurring monthly</div>
                        </div>
                    </section>

                    <section class="content-grid">
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

                        <div class="card">
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
                </section>

                <section class="view staff-view" data-view="customers">
                    <section class="content-grid">
                        <div class="card wide">
                            <div class="card-header">
                                <div>
                                    <div class="card-title">Customers</div>
                                    <div class="card-subtitle">Manage customer profiles and portal access.</div>
                                </div>
                                <button class="btn btn-outline" id="customers-refresh">Refresh</button>
                            </div>
                            <div class="filters">
                                <label class="field">
                                    <span>Search customers</span>
                                    <input type="text" id="customers-search" placeholder="Search by name or email">
                                </label>
                                <button class="btn btn-ghost" id="customers-clear" type="button">Clear</button>
                            </div>
                            <div class="table" id="customers-table">
                                <div class="table-row table-header customers">
                                    <span>Name</span>
                                    <span>Email</span>
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
                                    <span>Billing address</span>
                                    <textarea name="billing_address" rows="3" required></textarea>
                                </label>
                                <label class="field">
                                    <span>Notes</span>
                                    <textarea name="notes" rows="3"></textarea>
                                </label>
                                <label class="field">
                                    <span>Portal user ID (optional)</span>
                                    <input type="number" name="user_id" min="1">
                                </label>
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
                                </div>
                                <button class="btn btn-outline" id="customer-detail-back">Back to customers</button>
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
                                <div class="card-meta">Sum of all jobs</div>
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
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="content-grid">
                            <div class="card wide">
                                <div class="card-header">
                                    <div>
                                        <div class="card-title">Websites</div>
                                        <div class="card-subtitle">Quick login links for this customer.</div>
                                    </div>
                                </div>
                                <div class="stack" id="customer-websites-list">
                                    <div class="site-card">
                                        <div>
                                            <div class="site-name">No websites yet</div>
                                            <div class="site-url">Add one to enable quick login.</div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="card">
                                <div class="card-header">
                                    <div>
                                        <div class="card-title" id="customer-website-title">Add website</div>
                                        <div class="card-subtitle">Store a login link (no passwords).</div>
                                    </div>
                                </div>
                                <form id="customer-website-form" class="form-stack">
                                    <input type="hidden" name="id">
                                    <label class="field">
                                        <span>Website name</span>
                                        <input type="text" name="name" required>
                                    </label>
                                    <label class="field">
                                        <span>Login URL</span>
                                        <input type="url" name="login_url" placeholder="https://example.com/login" required>
                                    </label>
                                    <label class="field">
                                        <span>Notes</span>
                                        <textarea name="notes" rows="3"></textarea>
                                    </label>
                                    <div id="customer-website-status" class="form-hint"></div>
                                    <div class="form-actions">
                                        <button type="submit" class="btn btn-primary">Save website</button>
                                        <button type="button" class="btn btn-outline" id="customer-website-cancel">Cancel</button>
                                    </div>
                                </form>
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
                                <button class="btn btn-outline" id="jobs-refresh">Refresh</button>
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
                                    <textarea name="description" rows="3" required></textarea>
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
                            <div class="table" id="subscription-months-table">
                                <div class="table-row table-header subscriptions">
                                    <span>Month</span>
                                    <span>Status</span>
                                    <span>Payment</span>
                                    <span>Actions</span>
                                    <span></span>
                                    <span></span>
                                </div>
                                <div class="table-row table-empty subscriptions">
                                    <span>Select a subscription to track months.</span>
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
                                    <span>Receipt</span>
                                    <span>Actions</span>
                                </div>
                                <div class="table-row table-empty costs">
                                    <span>Loading costs...</span>
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
                                <button class="btn btn-outline" id="invoices-refresh">Refresh</button>
                            </div>
                            <div class="filters">
                                <label class="field">
                                    <span>Status</span>
                                    <select id="invoices-filter-status">
                                        <option value="all">All</option>
                                        <option value="draft">Draft</option>
                                        <option value="sent">Sent</option>
                                        <option value="paid">Paid</option>
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
                        <div class="card wide">
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

                        <div class="card">
                            <div class="card-header">
                                <div>
                                    <div class="card-title">Profile</div>
                                    <div class="card-subtitle">Update your admin contact details.</div>
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
                                    <div class="card-subtitle">Choose a new admin password.</div>
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
                </section>
            </main>
        </div>

        <template id="invoice-line-item-template">
            <div class="line-item">
                <input type="text" name="description" placeholder="Description" required>
                <input type="number" name="quantity" min="1" step="1" value="1" required>
                <input type="number" name="unit_price" min="0" step="0.01" placeholder="Unit price" required>
                <select name="billable_type">
                    <option value="">Manual</option>
                    <option value="job">Job</option>
                    <option value="subscription">Subscription</option>
                </select>
                <input type="number" name="billable_id" min="1" placeholder="Billable ID">
                <button type="button" class="btn btn-outline btn-small" data-action="remove-line-item">Remove</button>
            </div>
        </template>
    </body>
</html>
