# Website Hosting & Management

This module is local-first and adds a central website register, historical monitoring, incidents, hosting-provider connections, a customer-safe portal view, and an authenticated WordPress agent.

## Local setup

Run migrations, then keep the normal queue worker and scheduler active:

```bash
php artisan migrate
php artisan queue:work
php artisan schedule:work
```

The scheduler queues lightweight HTTP checks every 10 minutes and fuller checks every four hours. Only sites with hosting or management enabled are monitored. Two consecutive failures are required before a critical outage incident opens; recovery resolves the existing incident rather than creating duplicates.

## Security

- Hosting credentials use Laravel's encrypted cast and are never included in API resources.
- WordPress agent tokens are stored encrypted for future server-to-server checks and as a SHA-256 hash for inbound authentication. Tokens are only returned when created or regenerated.
- Portal queries are scoped to the authenticated customer's customer records.
- Portal visibility settings default to customer-friendly status information; hosting usage and technical details are opt-in.
- HTTP monitoring uses the existing safe URL guard to block internal/private network targets and validates redirects.

## Hosting providers

`HostingProviderInterface` isolates hosting integrations. The mock provider supports safe local development. The cPanel adapter uses HTTPS API tokens and can be extended without changing website controllers or monitoring history.

## Operational handover

Production requires a queue worker and Laravel scheduler, a valid `APP_KEY`, and HTTPS. Configure thresholds through `WEBSITE_SSL_WARNING_DAYS`, `WEBSITE_BACKUP_WARNING_HOURS`, `WEBSITE_AGENT_STALE_HOURS`, and `WEBSITE_FAILURE_THRESHOLD`. Never place hosting or WordPress tokens in source control.
