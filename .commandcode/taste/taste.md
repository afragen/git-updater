# Taste (Continuously Learned by [CommandCode][cmd])

[cmd]: https://commandcode.ai/

# Security
- Proactively offer security reviews for codebase changes. Confidence: 0.85

# Workflow
- Commit changes after completing each distinct work phase. Confidence: 0.90
- Run tests before committing changes to verify nothing is broken. Confidence: 0.80
- Use tiered approach: address High priority issues first, then Medium, then Low. Confidence: 0.80
- When taste files are modified, omit them from any commit rather than reverting them with git checkout. Confidence: 0.70
- Ask the user before committing, and create a CHANGES.md entry first. Confidence: 0.75

# Testing
- Achieve 100% line test coverage with zero test failures for all modifications. Confidence: 0.94
- Use @codeCoverageIgnore annotation when appropriate for uncovered lines. Confidence: 0.75

# Wordpress
- Use `$wpdb->prepare()` with `%i` placeholders for table names (SQL identifiers) in `$wpdb->query()` DDL calls instead of raw concatenation with `phpcs:ignore`. Confidence: 0.65

# architecture
See [architecture/taste.md](architecture/taste.md)
