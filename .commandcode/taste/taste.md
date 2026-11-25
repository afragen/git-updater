# Taste (Continuously Learned by [CommandCode][cmd])

[cmd]: https://commandcode.ai/

# Plans
- Create plans in the home-directory `~/.commandcode/plans/` (e.g., `/Users/afragen/.commandcode/plans/`), not a repo-local `.commandcode/plans/` directory — a plan written to the repo directory was corrected to the home location. Confidence: 0.75
- Do NOT accept a divergence from an approved plan as the settled answer — fix the divergence at its root cause instead, including by making a coordinated change in a sibling/client repo. When the assistant reported it had deliberately deviated from the plan by exempting `update-api`, the user pushed back with a concrete alternative ("Instead of diverging from plan on update-api, what about updating git-updater-lite to return X-GU-Site-Domain?") that removed the need for the exemption entirely, turning a planned special case into a uniform change spanning two repos. The user prefers amending the plan to reflect the root-cause fix over keeping the deviation. Confidence: 0.75

# Communication
See [communication/taste.md](communication/taste.md)
# Security
See [security/taste.md](security/taste.md)
# Workflow
See [workflow/taste.md](workflow/taste.md)

# testing
See [testing/taste.md](testing/taste.md)

# WordPress
See [wordpress/taste.md](wordpress/taste.md)
# Phpstan
See [phpstan/taste.md](phpstan/taste.md)

# Php
- When the user asks for a snippet/example, give the minimal idiomatic form they asked for — a multi-line, defensive implementation (persistence via options, early-return guards, `static function` wrappers) is rejected with "Just a single line filter …"; prefer a one-liner with arrow function syntax (e.g., `add_filter( 'hook', fn() => bin2hex( random_bytes( 32 ) ) );`) plus a brief caveat note if a trade-off exists. Confidence: 0.6
- Brevity must not come at the cost of correct behavior: do NOT hand back a minimal snippet you know behaves wrongly (e.g., a key generated fresh on every request) and merely annotate the flaw in a caveat — the user rejects that with a terse requirement restating the intended behavior ("I want the same key returned"). Keep the snippet compact but implement the behavior the user actually needs (e.g., read/persist the value via `get_site_option`/`update_site_option`). Confidence: 0.65
- Keep reflection code forward-compatible with upcoming PHP versions: guard `ReflectionMethod::setAccessible()` / `ReflectionProperty::setAccessible()` calls with `PHP_VERSION_ID < 80100 && $reflection->setAccessible( true );` because the call is unnecessary on PHP 8.1+ and deprecated in PHP 8.5 — apply this guard in both source and tests rather than leaving bare calls. Confidence: 0.90

# architecture
See [architecture/taste.md](architecture/taste.md)

# Documentation
- Place documentation files in the `docs/` directory. Confidence: 0.95
