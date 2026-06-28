# GU_Trait.php — Test Coverage Plan

## Current Coverage: 99.08% (from stale coverage report)

## Analysis

Many uncovered lines already have tests in `tests/test-gutrait.php`:
- `set_repo_cache_timeout()` fallback/complete paths — tests exist at lines 362-430
- `get_class_vars()` property not found — test exists at line 653
- `can_update_repo()` requires_php — tests exist at lines 1346-1362
- `get_running_git_servers()` filter — test exists at line 834
- `get_file_headers()` string parsing — test exists at line 163
- `populate_api_data()` readme — test exists at line 1063

## Remaining Gaps (may need new tests)

### 1. `set_repo_cache()` — Filter application (line 168)
The `gu_repo_cache_timeout` filter is applied but no test verifies it fires.

**Test to write:** `test_set_repo_cache_applies_gu_repo_cache_timeout_filter`

### 2. `set_repo_cache()` — Timeout preservation via `??` (lines 169-172)
When cache already has a timeout, `??` preserves it.

**Test to write:** `test_set_repo_cache_preserves_existing_timeout`

### 3. `set_repo_cache_timeout()` — Fallback filter (line 189)
The `gu_repo_cache_timeout_fallback` filter.

**Test to write:** `test_set_repo_cache_timeout_fallback_applies_filter` (may already exist at line 414)

### 4. `modify_options()` — Default bypass_background_processing (lines 1013-1017)
When `bypass_background_processing` is not set, defaults to '0'.

**Test to write:** `test_modify_options_sets_default_bypass_background_processing`

### 5. `modify_options()` — gu_disable_wpcron filter (lines 1013-1017)
When `gu_disable_wpcron` filter is true, sets bypass to '1'.

**Test to write:** `test_modify_options_gu_disable_wpcron_sets_bypass_to_1`

---

## Action Plan

1. Run coverage with current code to get accurate line numbers
2. Compare against existing tests
3. Write only the truly missing tests
4. Achieve 100% line coverage
