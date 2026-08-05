# Testing
- Coverage reports are produced as `coverage/clover.xml` (single-site) and `coverage/clover-multisite.xml` (multisite); inspect `count="0"` lines in these files to locate uncovered statements. Confidence: 0.85
- Achieve 100% line test coverage with zero test failures for all modifications. Confidence: 0.94
- Use @codeCoverageIgnore annotation when appropriate for uncovered lines. Confidence: 0.75
- Test on both single-site and multisite WordPress configurations before committing test changes. Confidence: 0.65
- Remove dead code (unused methods) when they are no longer called, and update tests accordingly. Confidence: 0.70
- Maintain `coverage-exclude.json` when triaging uncovered lines: add entries for lines that legitimately cannot be covered, and the file is consulted during coverage checks to skip those lines when measuring coverage completeness. Confidence: 0.70
- When checking test coverage, examine the existing `coverage/clover.xml` and `coverage/clover-multisite.xml` reports first before rerunning tests; only regenerate coverage if existing reports already show 100% line coverage, since code edits may have introduced uncovered lines. Confidence: 0.85
- Back up every file in fixture directories (not just style.css) when a test modifies bind-mounted theme files during upgrade tests; restore all files in tear_down() so the host working tree is left unchanged. Confidence: 0.80
- When code behavior changes (committed or uncommitted), update existing tests that assert the old behavior to assert the new behavior, and add new coverage for the changed edge cases (e.g., dev release asset version differing from vs. matching the remote version). The user asks to "fix and update tests for current uncommitted changes" expecting the assistant to inspect the working tree and align the suite with it. Confidence: 0.75
