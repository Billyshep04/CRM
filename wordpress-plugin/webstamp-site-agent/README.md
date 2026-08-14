# WebStamp Site Agent

1. Copy `webstamp-site-agent` into `wp-content/plugins/` and activate it.
2. In the CRM, create the website or regenerate its agent token. Copy the token immediately.
3. In WordPress, open **Settings → WebStamp Agent**, paste the token, and save.
4. The authenticated endpoint is `GET /wp-json/webstamp/v1/status`. Send the token as `Authorization: Bearer …` or `X-WebStamp-Token`.

The endpoint is read-only and exposes the WordPress/PHP versions, total plugin count, update counts, database size, active theme and debug state. It does not expose users, passwords, configuration contents, files or database records.
