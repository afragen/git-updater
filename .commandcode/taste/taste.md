# Taste (Continuously Learned by [CommandCode][cmd])

[cmd]: https://commandcode.ai/

# Plans
- Create plans in the local `.commandcode/plans/` directory. Confidence: 0.70

# Security
- Proactively offer security reviews for codebase changes. Confidence: 0.85

# Workflow
- Commit changes after completing each distinct work phase. Confidence: 0.90
- Run tests before committing changes to verify nothing is broken. Confidence: 0.80
- Use tiered approach: address High priority issues first, then Medium, then Low. Confidence: 0.80
- When taste files are modified, omit them from any commit rather than reverting them with git checkout. Confidence: 0.70
- Create a CHANGES.md entry before committing, unless the user explicitly specifies to commit without changelog entries. Confidence: 0.85

# Testing
- Achieve 100% line test coverage with zero test failures for all modifications. Confidence: 0.94
- Use @codeCoverageIgnore annotation when appropriate for uncovered lines. Confidence: 0.75
- Remove dead code (unused methods) when they are no longer called, and update tests accordingly. Confidence: 0.70
- Back up every file in fixture directories (not just style.css) when a test modifies bind-mounted theme files during upgrade tests; restore all files in tear_down() so the host working tree is left unchanged. Confidence: 0.80

# Wordpress
- Use `$wpdb->prepare()` with `%i` placeholders for table names (SQL identifiers) in `$wpdb->query()` DDL calls instead of raw concatenation with `phpcs:ignore`. Confidence: 0.65

# Workflow
- When adding third-party tools that replace system components (e.g., container runtimes), test the add-on/plugin against the existing system first before replacing the system runtime. Confidence: 0.65

# architecture
See [architecture/taste.md](architecture/taste.md)
