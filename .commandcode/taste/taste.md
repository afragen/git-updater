# Taste (Continuously Learned by [CommandCode][cmd])

[cmd]: https://commandcode.ai/

# Plans
- Create plans in the local `.commandcode/plans/` directory. Confidence: 0.70

# Security
- Proactively offer security reviews for codebase changes. Confidence: 0.85

# Workflow
See [workflow/taste.md](workflow/taste.md)

# Testing
- Achieve 100% line test coverage with zero test failures for all modifications. Confidence: 0.94
- Use @codeCoverageIgnore annotation when appropriate for uncovered lines. Confidence: 0.75
- Test on both single-site and multisite WordPress configurations before committing test changes. Confidence: 0.65
- Remove dead code (unused methods) when they are no longer called, and update tests accordingly. Confidence: 0.70
- Back up every file in fixture directories (not just style.css) when a test modifies bind-mounted theme files during upgrade tests; restore all files in tear_down() so the host working tree is left unchanged. Confidence: 0.80

# Wordpress
- Use `$wpdb->prepare()` with `%i` placeholders for table names (SQL identifiers) in `$wpdb->query()` DDL calls instead of raw concatenation with `phpcs:ignore`. Confidence: 0.65
- Fix WPCS errors properly by restructuring code rather than silencing them with `phpcs:ignore` comments. Confidence: 0.82
- Place `phpcs:ignore` comments on the line preceding the suppressed code, not trailing on the same line. Confidence: 0.72
- Use `wp_remote_retrieve_body()` to extract the response body from WordPress HTTP API calls rather than manually accessing the array. Confidence: 0.95

# PHPStan
- When fixing PHPStan return type errors, update the `@return` docblock to match the actual return type of the underlying method call, instead of changing the calling code's return behavior. Confidence: 0.75
- When adding new error handling or authentication recovery logic, extract complex inline sections into focused, well-named protected/private helper methods (e.g., `should_attempt_token_refresh()`, `has_bad_credentials_message()`, `maybe_refresh_token_and_retry()`) to improve readability, testability, and separation of concerns. Confidence: 0.90
- For string matching of error messages from external services, use case-insensitive comparison (e.g., `stripos()`) to be robust against provider variations. Confidence: 0.90
- When implementing automatic retry mechanisms for API authentication, trigger token refresh on HTTP 401/403 OR when the response body contains auth error indicators (like "Bad Credentials") on 200 or 4xx codes, but never retry on 5xx server errors. Confidence: 0.85

# architecture
See [architecture/taste.md](architecture/taste.md)

# Documentation
- Place documentation files in the `docs/` directory. Confidence: 0.95
