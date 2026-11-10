<p align="center"><a href="https://laravel.com" target="_blank"><img src="https://raw.githubusercontent.com/laravel/art/master/logo-lockup/5%20SVG/2%20CMYK/1%20Full%20Color/laravel-logolockup-cmyk-red.svg" width="400" alt="Laravel Logo"></a></p>

<p align="center">
<a href="https://github.com/laravel/framework/actions"><img src="https://github.com/laravel/framework/workflows/tests/badge.svg" alt="Build Status"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/dt/laravel/framework" alt="Total Downloads"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/v/laravel/framework" alt="Latest Stable Version"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/l/laravel/framework" alt="License"></a>
</p>

## Email Marketing

Admin-only mailing lists and campaigns (sidebar: **Marketing → Email Marketing**). It's separate from CRM customers: subscribers are only ever added manually or by CSV import, and each add records how the person gave consent (UK PECR / UK GDPR).

### How it works

- **Lists and subscribers.** Each email address is stored once (matched case-insensitively), however many lists it's on.
- **Unsubscribes are global.** Anyone who unsubscribes or bounces goes on a suppression list and is never emailed again from any list, even if re-added or re-imported, until an admin resubscribes them with a note. Unsubscribe dates are kept.
- **Campaigns.** Paste or upload full HTML. It's sent exactly as written, apart from these merge tags: `{{first_name}}`, `{{last_name}}`, `{{email}}`, `{{unsubscribe_url}}`, `{{view_in_browser_url}}`, with fallbacks such as `{{first_name|there}}`. A campaign can't be sent to a list unless its HTML contains `{{unsubscribe_url}}`.
- **Sending.** Every person gets their own email, with a plain-text version and RFC 8058 one-click `List-Unsubscribe` headers. Sending runs on the queue in batches of `MARKETING_RATE_PER_MINUTE`, never in the web request. Every attempt is logged per recipient.
- **Scheduling.** A campaign can be scheduled for a later time. It's checked again and sent by the scheduler, with recipients worked out at send time. If it can no longer be sent (for example its list was deleted), it returns to draft and shows the reason.
- **Public pages.** `/email/unsubscribe/{token}` and `/email/view/{token}` need no login. The tokens are random, unique per recipient, and stored only as hashes.

### Setup

Add to `.env` (see `.env.example`):

| Variable | Purpose |
| --- | --- |
| `MARKETING_MAIL_TRANSPORT` | `log` (default) writes emails to `storage/logs/laravel.log`. Set to `smtp` to send for real. |
| `MARKETING_MAIL_HOST`, `MARKETING_MAIL_PORT` | SMTP2GO relay, `mail.smtp2go.com` and `2525`. |
| `MARKETING_MAIL_USERNAME`, `MARKETING_MAIL_PASSWORD` | An SMTP user created in SMTP2GO for marketing. Never commit these. |
| `MARKETING_FROM_ADDRESS`, `MARKETING_FROM_NAME`, `MARKETING_REPLY_TO` | Defaults for new campaigns. |
| `MARKETING_ALLOWED_FROM_DOMAINS` | Comma-separated domains campaigns may send from. Defaults to the domain of `MARKETING_FROM_ADDRESS`. |
| `MARKETING_RATE_PER_MINUTE` | Emails per minute (default 60). Keep within your SMTP2GO plan. |

`APP_URL` must be the public CRM address (for example `https://crm.web-stamp.co.uk`), because unsubscribe and "view online" links are built from it.

Then:

1. Run `php artisan migrate`.
2. Make sure a queue worker processes the `default` queue. The existing cron worker does; locally, run `php artisan queue:work`.
3. Make sure the scheduler runs (`php artisan schedule:run` every minute; the existing cron already does this). It starts scheduled campaigns and resumes interrupted sends.

### Deliverability

Before sending real campaigns, the sending domain needs **SPF**, **DKIM** and **DMARC** DNS records. Add the records SMTP2GO gives you under *Sender Domains*, and publish a DMARC record (start with `v=DMARC1; p=none; rua=mailto:you@yourdomain`). Without them, Gmail and Outlook are likely to send campaigns to spam or reject them. Ideally use a subdomain such as `news.web-stamp.co.uk`, so marketing can't affect invoice and proposal delivery. In SMTP2GO, turn off click tracking and its own unsubscribe footer, so emails go out exactly as written.

## About Laravel

Laravel is a web application framework with expressive, elegant syntax. We believe development must be an enjoyable and creative experience to be truly fulfilling. Laravel takes the pain out of development by easing common tasks used in many web projects, such as:

- [Simple, fast routing engine](https://laravel.com/docs/routing).
- [Powerful dependency injection container](https://laravel.com/docs/container).
- Multiple back-ends for [session](https://laravel.com/docs/session) and [cache](https://laravel.com/docs/cache) storage.
- Expressive, intuitive [database ORM](https://laravel.com/docs/eloquent).
- Database agnostic [schema migrations](https://laravel.com/docs/migrations).
- [Robust background job processing](https://laravel.com/docs/queues).
- [Real-time event broadcasting](https://laravel.com/docs/broadcasting).

Laravel is accessible, powerful, and provides tools required for large, robust applications.

## Learning Laravel

Laravel has the most extensive and thorough [documentation](https://laravel.com/docs) and video tutorial library of all modern web application frameworks, making it a breeze to get started with the framework. You can also check out [Laravel Learn](https://laravel.com/learn), where you will be guided through building a modern Laravel application.

If you don't feel like reading, [Laracasts](https://laracasts.com) can help. Laracasts contains thousands of video tutorials on a range of topics including Laravel, modern PHP, unit testing, and JavaScript. Boost your skills by digging into our comprehensive video library.

## Laravel Sponsors

We would like to extend our thanks to the following sponsors for funding Laravel development. If you are interested in becoming a sponsor, please visit the [Laravel Partners program](https://partners.laravel.com).

### Premium Partners

- **[Vehikl](https://vehikl.com)**
- **[Tighten Co.](https://tighten.co)**
- **[Kirschbaum Development Group](https://kirschbaumdevelopment.com)**
- **[64 Robots](https://64robots.com)**
- **[Curotec](https://www.curotec.com/services/technologies/laravel)**
- **[DevSquad](https://devsquad.com/hire-laravel-developers)**
- **[Redberry](https://redberry.international/laravel-development)**
- **[Active Logic](https://activelogic.com)**

## Contributing

Thank you for considering contributing to the Laravel framework! The contribution guide can be found in the [Laravel documentation](https://laravel.com/docs/contributions).

## Code of Conduct

In order to ensure that the Laravel community is welcoming to all, please review and abide by the [Code of Conduct](https://laravel.com/docs/contributions#code-of-conduct).

## Security Vulnerabilities

If you discover a security vulnerability within Laravel, please send an e-mail to Taylor Otwell via [taylor@laravel.com](mailto:taylor@laravel.com). All security vulnerabilities will be promptly addressed.

## License

The Laravel framework is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).
# CRM
