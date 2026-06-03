# Security
- Proactively offer security reviews for codebase changes. Confidence: 0.85

# Workflow
- Commit changes after completing each distinct work phase. Confidence: 0.90
- Use tiered approach: address High priority issues first, then Medium, then Low. Confidence: 0.80
- Follow through on discussed architectural plans rather than substituting simpler alternatives without asking first. Implement ALL components of an agreed plan (backend, client-side, UI/settings) rather than partially implementing and omitting major sections. Confidence: 0.90

# Testing
- Ensure new functions have test coverage and additional lines are covered. Confidence: 0.75
- Use @codeCoverageIgnore annotation when appropriate for uncovered lines. Confidence: 0.70
- Use phpstan for PHP error checking and static analysis rather than manual debugging. Confidence: 0.70

# Architecture
See [architecture/taste.md](architecture/taste.md)
