# Testing
- Coverage reports are produced as `coverage/clover.xml` (single-site) and `coverage/clover-multisite.xml` (multisite); inspect `count="0"` lines in these files to locate uncovered statements. Confidence: 0.85
- Achieve 100% line test coverage with zero test failures for all modifications. Confidence: 0.94
- Use @codeCoverageIgnore annotation when appropriate for uncovered lines. Confidence: 0.75
- Test on both single-site and multisite WordPress configurations before committing test changes. Confidence: 0.65
- Remove dead code (unused methods) when they are no longer called, and update tests accordingly. Confidence: 0.70
- Back up every file in fixture directories (not just style.css) when a test modifies bind-mounted theme files during upgrade tests; restore all files in tear_down() so the host working tree is left unchanged. Confidence: 0.80
