# Taste (Continuously Learned by [CommandCode][cmd])

[cmd]: https://commandcode.ai/

# Plans
- Create plans in the home-directory `~/.commandcode/plans/` (e.g., `/Users/afragen/.commandcode/plans/`), not a repo-local `.commandcode/plans/` directory — a plan written to the repo directory was corrected to the home location. Confidence: 0.75

# Communication
- User issues terse, single-word directives (e.g., "commit", "update tests for <commit hash>") and expects the assistant to infer the full scope of the action from the preceding context; also pastes raw CI/test failure output as the entire message, with no instructions, expecting the assistant to diagnose and fix it.
- When the user asks to "evaluate" a specific method/function for the best way to do something — whether phrased as "evaluate 'use_release_asset()' for the best way to determine if a release asset is needed" or "in waiting_for_background_update() is there a better way to test for $repos that are managed or not managed?" — they expect a critical evaluation before any code change: trace the actual data flow through the codebase, identify concrete weaknesses/flaws in the current implementation (e.g., states conflated by a proxy/emptiness check), and recommend the best approach — delivered as a written plan with rationale and a files-to-change list, not merely an explanation of how the code currently works. Confidence: 0.75 Similarly, reports observed tool behavior as a terse factual statement (e.g., "'/status' doesn't seem to show the current additionalDirectories") with no explicit instruction, expecting the assistant to re-investigate against the source and correct any earlier claims rather than defend them. When a claim is made, the user probes it with terse verification questions (e.g., "are you certain it doesn't clear once the user opens the admin dashboard?") and expects an answer backed by exhaustive source evidence — e.g., enumerating every code path that touches the state (set/cleared/read with line numbers) — not a restatement of the original claim. Confidence: 0.90
- User reports suspected bugs/edge-case states in the codebase as tentative factual claims with no explicit instruction (e.g., "it seems that it is possible for use_release_asset() to have a repo that has $need_release_asset = true, an empty $this->type->release_asset, and an empty cache['release_assets']"); the expected response is to verify the claim by tracing the exact data flow — including whether the stated consequence actually occurs given the full return logic — and, if confirmed, plan and implement the fix rather than defend the prior implementation. Confidence: 0.65

# Security
- Proactively offer security reviews for codebase changes. Confidence: 0.85

# Workflow
See [workflow/taste.md](workflow/taste.md)

# Testing
See [testing/taste.md](testing/taste.md)

# Wordpress
- Use `$wpdb->prepare()` with `%i` placeholders for table names (SQL identifiers) in `$wpdb->query()` DDL calls instead of raw concatenation with `phpcs:ignore`. Confidence: 0.65
- Fix WPCS errors properly by restructuring code rather than silencing them with `phpcs:ignore` comments. Confidence: 0.82
- Place `phpcs:ignore` comments on the line preceding the suppressed code, not trailing on the same line. Confidence: 0.72
- Use `wp_remote_retrieve_body()` to extract the response body from WordPress HTTP API calls rather than manually accessing the array. Confidence: 0.95

# WordPress
- When an admin notification must persist until the user takes corrective action (e.g., reconnecting a revoked OAuth token), use a persistent site option flag instead of an expiring transient so the notice cannot silently vanish before the admin sees it. Confidence: 0.70

# PHPStan
- When fixing PHPStan return type errors, update the `@return` docblock to match the actual return type of the underlying method call, instead of changing the calling code's return behavior. Confidence: 0.75
- When adding new error handling or authentication recovery logic, extract complex inline sections into focused, well-named protected/private helper methods (e.g., `should_attempt_token_refresh()`, `has_bad_credentials_message()`, `maybe_refresh_token_and_retry()`) to improve readability, testability, and separation of concerns. Confidence: 0.90
- For string matching of error messages from external services, use case-insensitive comparison (e.g., `stripos()`) to be robust against provider variations. Confidence: 0.90
- When implementing automatic retry mechanisms for API authentication, trigger token refresh on HTTP 401/403 OR when the response body contains auth error indicators (like "Bad Credentials") on 200 or 4xx codes, but never retry on 5xx server errors. Confidence: 0.85

# PHP
- Keep reflection code forward-compatible with upcoming PHP versions: guard `ReflectionMethod::setAccessible()` / `ReflectionProperty::setAccessible()` calls with `PHP_VERSION_ID < 80100 && $reflection->setAccessible( true );` because the call is unnecessary on PHP 8.1+ and deprecated in PHP 8.5 — apply this guard in both source and tests rather than leaving bare calls. Confidence: 0.9

# architecture
See [architecture/taste.md](architecture/taste.md)

# Documentation
- Place documentation files in the `docs/` directory. Confidence: 0.95
- `maybe_refresh_token_and_retry()` to improve readability, testability, and separation of concerns. Confidence: 0.90
- For string matching of error messages from external services, use case-insensitive comparison (e.g., `stripos()`) to be robust against provider variations. Confidence: 0.90
- When implementing automatic retry mechanisms for API authentication, trigger token refresh on HTTP 401/403 OR when the response body contains auth error indicators (like "Bad Credentials") on 200 or 4xx codes, but never retry on 5xx server errors. Confidence: 0.85

# architecture
See [architecture/taste.md](architecture/taste.md)

# Documentation
- Place documentation files in the `docs/` directory. Confidence: 0.95
