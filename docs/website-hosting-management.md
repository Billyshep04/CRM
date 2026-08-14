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

### Krystal Trinity reseller architecture

The CRM stores one encrypted WHM connection in `hosting_servers`, mirrors reseller cPanel accounts in `hosting_accounts`, and links websites to those accounts. WHM functions used are `listaccts`, `listpkgs`, `createacct`, and `create_user_session`, all through HTTPS port 2087 with server-side `whm username:token` authentication. Tokens are never serialized, logged, or sent to JavaScript.

Account and package sync uses stable WHM usernames/package names with `updateOrCreate`, so repeated syncs do not duplicate data. Exact domain matches can safely suggest/link an existing website; uncertain accounts remain Unassigned.

### Provisioning

Provisioning is an explicit admin-only state machine: pending → creating hosting → hosting created → installing/configuring WordPress → connecting agent → checks → complete. Every step is stored, retryable, and audited through website activities. Domain and idempotency keys are unique. Failures never terminate or delete a cPanel account.

`HOSTING_PROVISIONING_MODE` defaults to `mock`. Live account creation requires both `HOSTING_PROVISIONING_MODE=live` and `ALLOW_HOSTING_PROVISIONING=true`. WordPress installation remains deliberately blocked in live mode until the available Krystal installer/API is confirmed; mock mode exercises the full workflow safely.

Profiles are configuration records, not hard-coded provisioning branches. The included profiles contain no premium licence keys.

### Connecting Krystal Trinity

1. In Krystal/WHM, note the secure WHM server hostname shown in the welcome email or WHM address bar.
2. Use the reseller username shown in the top-right of WHM or account information.
3. In WHM open **Development → Manage API Tokens**, create a dedicated CRM token, and copy it once.
4. Grant only reseller functions needed for account listing, package listing, account creation and user-session creation. Exact availability depends on the reseller ACL Krystal assigns.
5. In CRM **Websites → Krystal Trinity / WHM**, enter the hostname, reseller username and token, then save.
6. Click **Test Connection**. No token is returned to the browser after saving.
7. Click **Sync Hosting Accounts** to import existing cPanel accounts and packages.
8. Map unassigned accounts to customers/websites by domain and verify the suggested relationship.
9. Keep mock mode enabled and create a development test site through the Create Website form; confirm the complete progress history and duplicate protection.
10. Only after the checklist below, set both live environment flags on the server and clear Laravel's configuration cache.

### Before enabling live Krystal provisioning

- Confirm a current off-platform backup and recovery route.
- Test WHM connectivity, account sync and package sync without provisioning.
- Verify the token uses least-privilege reseller ACLs and cannot terminate accounts.
- Confirm the selected package exists and its limits are appropriate.
- Confirm the test domain does not exist in CRM, WHM, DNS or another account.
- Confirm `APP_KEY`, HTTPS, queue worker, scheduler and audit logs are healthy.
- Verify mock provisioning, duplicate clicks, retries and forced failures locally.
- Confirm WordPress installation tooling with Krystal; live WordPress steps intentionally stop until this is known.
- Start with a disposable development subdomain, never a live client domain.
- Require an admin confirmation and monitor the first run step by step.
- Keep automatic account termination unimplemented.

## Operational handover

Production requires a queue worker and Laravel scheduler, a valid `APP_KEY`, and HTTPS. Configure thresholds through `WEBSITE_SSL_WARNING_DAYS`, `WEBSITE_BACKUP_WARNING_HOURS`, `WEBSITE_AGENT_STALE_HOURS`, and `WEBSITE_FAILURE_THRESHOLD`. Never place hosting or WordPress tokens in source control.
