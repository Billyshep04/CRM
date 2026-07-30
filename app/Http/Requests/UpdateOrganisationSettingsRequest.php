<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateOrganisationSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasRole('admin') === true;
    }

    public function rules(): array
    {
        return [
            'company_name' => ['required', 'string', 'max:120'], 'trading_name' => ['nullable', 'string', 'max:120'], 'business_email' => ['nullable', 'email', 'max:255'],
            'business_phone' => ['nullable', 'string', 'max:50'], 'website_url' => ['nullable', 'url:http,https', 'max:2048'], 'business_address' => ['nullable', 'string', 'max:2000'],
            'company_number' => ['nullable', 'string', 'max:50'], 'vat_number' => ['nullable', 'string', 'max:50'], 'primary_colour' => ['required', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'accent_colour' => ['required', 'regex:/^#[0-9A-Fa-f]{6}$/'], 'login_title' => ['required', 'string', 'max:120'], 'footer_text' => ['nullable', 'string', 'max:500'],
            'background_colour' => ['required', 'regex:/^#[0-9A-Fa-f]{6}$/'], 'surface_colour' => ['required', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'dark_background_colour' => ['required', 'regex:/^#[0-9A-Fa-f]{6}$/'], 'dark_surface_colour' => ['required', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'currency' => ['required', Rule::in(['GBP', 'EUR', 'USD'])], 'timezone' => ['required', 'timezone:all'], 'date_format' => ['required', Rule::in(['d/m/Y', 'm/d/Y', 'Y-m-d'])],
            'financial_year_start_month' => ['required', 'integer', 'between:1,12'], 'invoice_prefix' => ['required', 'alpha_dash', 'max:20'], 'invoice_payment_terms_days' => ['required', 'integer', 'between:0,365'],
            'default_tax_rate' => ['required', 'numeric', 'between:0,100'], 'invoice_notes' => ['nullable', 'string', 'max:5000'], 'invoice_footer' => ['nullable', 'string', 'max:2000'],
            'proposal_prefix' => ['required', 'alpha_dash', 'max:20'], 'proposal_validity_days' => ['required', 'integer', 'between:1,365'], 'proposal_terms' => ['nullable', 'string', 'max:10000'],
            'sender_name' => ['required', 'string', 'max:120'], 'reply_to_email' => ['nullable', 'email', 'max:255'], 'email_signature' => ['nullable', 'string', 'max:5000'],
            'invoice_email_template' => ['nullable', 'string', 'max:10000'], 'proposal_email_template' => ['nullable', 'string', 'max:10000'], 'reminder_email_template' => ['nullable', 'string', 'max:10000'],
            'portal_welcome_message' => ['nullable', 'string', 'max:2000'], 'portal_support_email' => ['nullable', 'email', 'max:255'],
            'portal_show_jobs' => ['required', 'boolean'], 'portal_show_invoices' => ['required', 'boolean'], 'portal_show_proposals' => ['required', 'boolean'], 'portal_show_subscriptions' => ['required', 'boolean'],
            'portal_allow_invoice_payment' => ['required', 'boolean'], 'portal_allow_proposal_approval' => ['required', 'boolean'], 'session_timeout_minutes' => ['required', 'integer', 'between:15,1440'],
            'default_dashboard_period' => ['required', Rule::in(['week', 'month', 'quarter', 'year'])], 'mrr_target' => ['required', 'numeric', 'min:0', 'max:999999999'],
            'revenue_target' => ['required', 'numeric', 'min:0', 'max:999999999'], 'overdue_warning_days' => ['required', 'integer', 'between:0,90'], 'lead_inactivity_days' => ['required', 'integer', 'between:1,365'],
            'show_today_overdue' => ['required', 'boolean'], 'show_today_upcoming' => ['required', 'boolean'], 'show_today_invoices' => ['required', 'boolean'], 'show_today_proposals' => ['required', 'boolean'],
        ];
    }
}
