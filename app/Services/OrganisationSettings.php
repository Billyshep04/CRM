<?php

namespace App\Services;

use App\Models\OrganisationSetting;

class OrganisationSettings
{
    public const DEFAULTS = [
        'company_name' => 'WebStamp', 'trading_name' => '', 'business_email' => '', 'business_phone' => '', 'website_url' => '',
        'business_address' => '', 'company_number' => '', 'vat_number' => '', 'primary_colour' => '#35b8ef', 'accent_colour' => '#1477a8',
        'background_colour' => '#f5f6f8', 'surface_colour' => '#ffffff', 'dark_background_colour' => '#0b0e14', 'dark_surface_colour' => '#111620',
        'login_title' => 'WebStamp CRM', 'footer_text' => '', 'currency' => 'GBP', 'timezone' => 'Europe/London', 'date_format' => 'd/m/Y',
        'financial_year_start_month' => 4, 'invoice_prefix' => 'INV', 'invoice_payment_terms_days' => 14, 'default_tax_rate' => 20,
        'invoice_notes' => '', 'invoice_footer' => '', 'proposal_prefix' => 'PROP', 'proposal_validity_days' => 30, 'proposal_terms' => '',
        'sender_name' => 'WebStamp', 'reply_to_email' => '', 'email_signature' => '', 'invoice_email_template' => '',
        'proposal_email_template' => '', 'reminder_email_template' => '', 'portal_welcome_message' => 'Welcome to your customer portal.',
        'portal_support_email' => '', 'portal_show_jobs' => true, 'portal_show_invoices' => true, 'portal_show_proposals' => true,
        'portal_show_subscriptions' => true, 'portal_allow_invoice_payment' => true, 'portal_allow_proposal_approval' => true,
        'session_timeout_minutes' => 120, 'default_dashboard_period' => 'month', 'mrr_target' => 1000, 'revenue_target' => 5000,
        'overdue_warning_days' => 1, 'lead_inactivity_days' => 7, 'show_today_overdue' => true, 'show_today_upcoming' => true,
        'show_today_invoices' => true, 'show_today_proposals' => true,
    ];

    public function all(): array
    {
        return array_replace(self::DEFAULTS, OrganisationSetting::query()->first()?->settings ?? []);
    }

    public function update(array $settings, ?int $userId): array
    {
        $record = OrganisationSetting::query()->firstOrNew();
        $record->fill(['settings' => array_replace($this->all(), $settings), 'updated_by_user_id' => $userId])->save();

        return $this->all();
    }
}
