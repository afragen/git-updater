# Taste (Continuously Learned by [CommandCode][cmd])

[cmd]: https://commandcode.ai/

# Plans
- Create plans in the home-directory `~/.commandcode/plans/` (e.g., `/Users/afragen/.commandcode/plans/`), not a repo-local `.commandcode/plans/` directory — a plan written to the repo directory was corrected to the home location. Confidence: 0.75

# Communication
- User issues terse, single-word directives (e.g., "commit", "update tests for <commit hash>") and expects the assistant to infer the full scope of the action from the preceding context; also pastes raw CI/test failure output as the entire message, with no instructions, expecting the assistant to diagnose and fix it; also issues single-word directives like "next" to advance to the next approved work item, relying on the assistant to track the remaining scope. Confidence: 0.85
- When the user asks to "evaluate" a specific method/function for the best way to do something — whether phrased as "evaluate 'use_release_asset()' for the best way to determine if a release asset is needed" or "in waiting_for_background_update() is there a better way to test for $repos that are managed or not managed?" — they expect a critical evaluation before any code change: trace the actual data flow through the codebase, identify concrete weaknesses/flaws in the current implementation (e.g., states conflated by a proxy/emptiness check), and recommend the best approach — delivered as a written plan with rationale and a files-to-change list, not merely an explanation of how the code currently works. Confidence: 0.75 Similarly, reports observed tool behavior as a terse factual statement (e.g., "'/status' doesn't seem to show the current additionalDirectories") with no explicit instruction, expecting the assistant to re-investigate against the source and correct any earlier claims rather than defend them. When a claim is made, the user probes it with terse verification questions (e.g., "are you certain it doesn't clear once the user opens the admin dashboard?") and expects an answer backed by exhaustive source evidence — e.g., enumerating every code path that touches the state (set/cleared/read with line numbers) — not a restatement of the original claim. Confidence: 0.90
- User reports suspected bugs/edge-case states in the codebase as tentative factual claims with no explicit instruction (e.g., "it seems that it is possible for use_release_asset() to have a repo that has $need_release_asset = true, an empty $this->type->release_asset, and an empty cache['release_assets']"); the expected response is to verify the claim by tracing the exact data flow — including whether the stated consequence actually occurs given the full return logic — and, if confirmed, plan and implement the fix rather than defend the prior implementation. Confidence: 0.65

# Security
- When a security fix is proposed (e.g., S4 token scoping in `Basic_Auth_Loader`), the user asks for both the fix approach AND the threat model ("explain how you would fix S4 and how a malicious/compromised repo header could occur") — they want the concrete attack vectors spelled out (how the exploit could actually happen in the current code, e.g., the "Enterprise" branch sending a GitHub PAT to any host named in a repo header) before implementation, not just the patch; ground the explanation in the actual source. Confidence: 0.65
- Proactively offer security reviews for codebase changes. Confidence: 0.85
- Admin settings save handlers must verify capability in addition to nonce verification — a nonce alone lets a low-privilege authenticated user with a minted nonce alter site settings, tokens, or config lists; apply the capability check to every POST save handler (e.g., `Settings::update_settings()`, `Additions/Settings`, `Lite_Domains`). The check must work in BOTH single-site and multisite: use the established dual-context pattern `current_user_can( is_multisite() ? 'manage_network_options' : 'manage_options' )` for network-wide settings, since `manage_options` alone is wrong on multisite. The user explicitly required this ("capabilities checks must work for both single site and multisite"). Confidence: 0.9

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
- PHPStan must stay fully clean (0 errors) across the whole project, and the code must remain compatible with the plugin's PHP 8.0 floor. When PHPStan flags a language construct as unsupported below the floor (e.g., `final protected const` inside a trait is PHP 8.2-only), convert the construct to the PHP-8.0-compatible equivalent (a `final protected static function` returning the value) and update all call sites — do not raise the PHP floor or suppress the error. The codebase documents this exact pattern: "Declared as a method (not a trait constant) because PHPStan flags constants inside traits." Confidence: 0.7

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
