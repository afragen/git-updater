# WordPress
- Use `$wpdb->prepare()` with `%i` placeholders for table names (SQL identifiers) in `$wpdb->query()` DDL calls instead of raw concatenation with `phpcs:ignore`. Confidence: 0.65
- Fix WPCS errors properly by restructuring code rather than silencing them with `phpcs:ignore` comments. Confidence: 0.82
- Place `phpcs:ignore` comments on the line preceding the suppressed code, not trailing on the same line. Confidence: 0.72
- Use `wp_remote_retrieve_body()` to extract the response body from WordPress HTTP API calls rather than manually accessing the array. Confidence: 0.95
- When an admin notification must persist until the user takes corrective action (e.g., reconnecting a revoked OAuth token), use a persistent site option flag instead of an expiring transient so the notice cannot silently vanish before the admin sees it. Confidence: 0.70
- Return user-facing operation failures (e.g. a rejected install host) as a `WP_Error` with a readable, actionable message — naming the offending host and how to remedy it — instead of a bare plain-string failure, and keep the consuming paths accepting both a `WP_Error` and legacy strings (rendered escaped in admin, forwarded in CLI/REST) so the message always surfaces. The user flagged that a failure must be easy to message back to the admin. Confidence: 0.6
