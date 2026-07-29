# Phase 1 sales operations

## Local rollout

1. Back up the database and deploy the application code.
2. Run `php artisan migrate --force`. The three Phase 1 migrations are additive and should complete quickly; the only backfill maps legacy `reviewing`, `converted`, and `disqualified` lead stages to their pipeline equivalents.
3. Keep the Laravel scheduler running. It invokes `follow-ups:process` every 15 minutes in `Europe/London`, with overlap and single-server protection.
4. Keep the queue worker running for existing proposal mail delivery.

No new environment variables are required. Follow-up definitions and their step timing are stored in the database so they can be changed without a code release.

## Rollback

Pause the scheduler/queue, then roll back the three Phase 1 migrations in reverse order. Rollback removes Phase 1 activity, transition and follow-up data and the additive sales fields; it does not delete legacy businesses, proposals, opportunities or tasks. A database backup is recommended because down migrations cannot restore deleted Phase 1 history.

## Manual QA

- Open the dashboard as admin and staff; confirm Today only includes the staff member's owned/assigned records.
- Move a lead into Contacted without a next action and confirm validation; repeat with a future action and confirm the timeline entry.
- Record a Call back outcome and confirm one task is created with the same due time.
- Send a linked proposal twice and confirm one enrolment with four executions exists.
- Approve or decline the proposal and confirm pending executions are cancelled.
- Run `php artisan follow-ups:process` twice and confirm it does not duplicate tasks.
- Check the dashboard at a mobile viewport and in both themes.

## Later phases

Existing-client service coverage, MRR movements/dashboard, onboarding checklists, milestone billing, review/referral automation, analytics, cashflow forecasting and Sales Mode remain intentionally deferred until the Phase 1 review checkpoint.
