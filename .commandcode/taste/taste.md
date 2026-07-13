# Security
- Proactively offer security reviews for codebase changes. Confidence: 0.85

# Workflow
- Commit changes after completing each distinct work phase. Confidence: 0.90
- Run tests before committing changes to verify nothing is broken. Confidence: 0.80
- Use tiered approach: address High priority issues first, then Medium, then Low. Confidence: 0.80

# Testing
- Achieve 100% line test coverage with zero test failures for all modifications. Confidence: 0.94
- Use @codeCoverageIgnore annotation when appropriate for uncovered lines. Confidence: 0.75

# WordPress
- Use `$wpdb->prepare()` with `%i` placeholders for table names (SQL identifiers) in `$wpdb->query()` DDL calls instead of raw concatenation with `phpcs:ignore`. Confidence: 0.65
