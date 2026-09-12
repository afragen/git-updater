# Taste (Continuously Learned by [CommandCode][cmd])

[cmd]: https://commandcode.ai/

# Plans
- Create plans in the home-directory `~/.commandcode/plans/` (e.g., `/Users/afragen/.commandcode/plans/`), not a repo-local `.commandcode/plans/` directory — a plan written to the repo directory was corrected to the home location. Confidence: 0.75
- Do NOT accept a divergence from an approved plan as the settled answer — fix the divergence at its root cause instead, including by making a coordinated change in a sibling/client repo. When the assistant reported it had deliberately deviated from the plan by exempting `update-api`, the user pushed back with a concrete alternative ("Instead of diverging from plan on update-api, what about updating git-updater-lite to return X-GU-Site-Domain?") that removed the need for the exemption entirely, turning a planned special case into a uniform change spanning two repos. The user prefers amending the plan to reflect the root-cause fix over keeping the deviation. Confidence: 0.75

# Communication
- User issues terse, single-word directives (e.g., "commit", "update tests for <commit hash>") and expects the assistant to infer the full scope of the action from the preceding context; also pastes raw CI/test failure output as the entire message, with no instructions, expecting the assistant to diagnose and fix it; also issues single-word directives like "next" or "what's next" to advance to the next approved work item or solicit the next recommended step, relying on the assistant to track the remaining scope. A terse confirmation like "yes, make a plan to cleanup those issues" likewise refers back to the assistant's previously enumerated findings and expects the assistant to convert them into a written cleanup plan without re-explaining the issues. Confidence: 0.92
- When the user asks to "evaluate" a specific method/function for the best way to do something — whether phrased as "evaluate 'use_release_asset()' for the best way to determine if a release asset is needed" or "in waiting_for_background_update() is there a better way to test for $repos that are managed or not managed?" — they expect a critical evaluation before any code change: trace the actual data flow through the codebase, identify concrete weaknesses/flaws in the current implementation (e.g., states conflated by a proxy/emptiness check), and recommend the best approach — delivered as a written plan with rationale and a files-to-change list, not merely an explanation of how the code currently works. Confidence: 0.75 Similarly, reports observed tool behavior as a terse factual statement (e.g., "'/status' doesn't seem to show the current additionalDirectories") with no explicit instruction, expecting the assistant to re-investigate against the source and correct any earlier claims rather than defend them. When a claim is made, the user probes it with terse verification questions (e.g., "are you certain it doesn't clear once the user opens the admin dashboard?") and expects an answer backed by exhaustive source evidence — e.g., enumerating every code path that touches the state (set/cleared/read with line numbers) — not a restatement of the original claim. Confidence: 0.90
- When the user validates a proposed diagnosis, they reason from the behavioral consequences the analysis implies — e.g., "if your analysis is correct then that OAuth flow should result in delete_token() and the notice, email" — and treat whether those expected side-effects actually occurred as confirmation evidence for the diagnosis. The assistant should verify whether the code path reached the predicted consequences, and when it did not, explain that the missing consequence is itself the proof of the bug (e.g., the token was never deleted/emailed, which confirms the parsing bug), rather than merely restating the diagnosis. Confidence: 0.7
- User reports suspected bugs/edge-case states in the codebase as tentative factual claims with no explicit instruction (e.g., "it seems that it is possible for use_release_asset() to have a repo that has $need_release_asset = true, an empty $this->type->release_asset, and an empty cache['release_assets']"); the expected response is to verify the claim by tracing the exact data flow — including whether the stated consequence actually occurs given the full return logic — and, if confirmed, plan and implement the fix rather than defend the prior implementation. The claim is often framed as a caching/performance question ("Is there a way to safely and effectively cache X? It seems like any call to X doesn't hit the cache."); the response must first measure the real per-call query counts (e.g., a temporary diagnostic asserting 1st-read vs 2nd-read DB queries) to confirm exactly which reads miss the cache before proposing a fix. Confidence: 0.75
- When the user reports an intermittent production error by pasting the exact WP debug.log message (e.g., "Cron unschedule event error for hook: gu_get_remote_plugin, Error code: could_not_set, Error message: The cron event list could not be saved.") and asks "create a plan to fix... What is the issue and can it be fixed?", the expected response is a full root-cause investigation before any plan: locate the error string in its originating source — including WordPress core files (e.g., wp-includes/cron.php in the wp-env install under `~/.wp-env/`), not just the plugin's own code — to pin down the exact WP_Error condition (e.g., `_set_cron_array()` returning `could_not_set` when `update_option('cron')` fails), then trace the plugin-side trigger that calls the core function on a hot path (e.g., `merge_and_reschedule_cron_batch()` doing two sequential cron writes on nearly every load), state whether the error is benign or has real consequences (e.g., duplicate cron events), and deliver a written plan with the fix. Confidence: 0.7

# Security
See [security/taste.md](security/taste.md)
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
- Return user-facing operation failures (e.g. a rejected install host) as a `WP_Error` with a readable, actionable message — naming the offending host and how to remedy it — instead of a bare plain-string failure, and keep the consuming paths accepting both a `WP_Error` and legacy strings (rendered escaped in admin, forwarded in CLI/REST) so the message always surfaces. The user flagged that a failure must be easy to message back to the admin. Confidence: 0.6

# Phpstan
See [phpstan/taste.md](phpstan/taste.md)
# Php
- Keep reflection code forward-compatible with upcoming PHP versions: guard `ReflectionMethod::setAccessible()` / `ReflectionProperty::setAccessible()` calls with `PHP_VERSION_ID < 80100 && $reflection->setAccessible( true );` because the call is unnecessary on PHP 8.1+ and deprecated in PHP 8.5 — apply this guard in both source and tests rather than leaving bare calls. Confidence: 0.90

# architecture
See [architecture/taste.md](architecture/taste.md)

# Documentation
- Place documentation files in the `docs/` directory. Confidence: 0.95
