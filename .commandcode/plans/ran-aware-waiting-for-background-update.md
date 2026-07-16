# Plan: Make `waiting_for_background_update()` detect incomplete (`ran`) caches too

## Context

The initial memory fix for `waiting_for_background_update()` (GU_Trait.php `null` branch) keys
purely on **row existence**: a repo is "done" iff it has any row in `git_updater_cache`. That is
memory-efficient (`SELECT slug FROM <table>`) but it treats a repo with a **partial/limited row**
as finished — so a repo whose fetch cycle failed partway (e.g. `contents` succeeded but `readme`
threw) is never re-queued.

The `ran` column already encodes fetch completeness: `Base.php` writes
`set_repo_cache('ran', array_filter($ran))` after each cycle, where `$ran` collects one key per
successful fetch step (`contents, assets, readme, changes, tags, branches, meta`).
`is_fetch_complete($slug)` (GU_Trait.php) returns true only when `ran` contains all 7 expected
keys. But `is_fetch_complete()` calls `get_repo()` → `SELECT *` + unserialize of **all 22 columns**,
so using it per-repo in the `null` branch would reintroduce the bulk-load we just removed.

The user's suggestion: use the `ran` entry to decide completeness, but keep it memory-safe.

## Goal

Detect "waiting" as: **no cache row OR an incomplete `ran` set** — while still loading only the
small `slug` + `ran` columns (not the full per-repo LONGTEXT payload) in a single query.

## Approach

### 1. Add `get_cached_ran()` to `Abstract_Cache_Table`
File: `src/Git_Updater/DB/Abstract_Cache_Table.php`

A column projection of `slug, ran` only. Unserialize **just** the `ran` value (a small array), not
the whole row. Return a `slug => ran-array|null` map.

```php
/**
 * Return a map of slug => unserialized `ran` column for every cached repo.
 *
 * Projects only the `slug` and `ran` columns (not the full LONGTEXT payload),
 * so per-repo readme/changes/contents/meta are never read or unserialized. Used
 * to decide fetch-cycle completeness without materializing full rows.
 *
 * @return array<string, array<int, string>|null>
 */
public function get_cached_ran(): array {
    global $wpdb;

    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
    $rows = (array) $wpdb->get_results(
        $wpdb->prepare( 'SELECT slug, ran FROM %i', $this->table_name() ),
        ARRAY_A
    );

    $map = [];
    foreach ( $rows as $row ) {
        $slug = (string) ( $row['slug'] ?? '' );
        if ( '' === $slug ) {
            continue;
        }
        $ran  = isset( $row['ran'] ) && is_string( $row['ran'] )
            ? maybe_unserialize( $row['ran'] )
            : null;
        $map[ $slug ] = is_array( $ran ) ? $ran : null;
    }

    return $map;
}
```

Properties:
- Single query, two columns only. The `ran` payload is tiny (≤7 short strings), so unserializing it
  per row is cheap and does NOT pull readme/changes/contents/meta into memory.
- Does NOT warm `$row_cache` (avoids the 2x retention that `get_all_rows()` caused).
- Returns `null` for `ran` when the column is empty/absent, so "row exists but no `ran`" is treated
  as incomplete.

### 2. Rewrite the `null` branch of `waiting_for_background_update()`
File: `src/Git_Updater/Traits/GU_Trait.php

Definition of "waiting" per live repo:
- slug not present in the map → no cache row → waiting.
- present but `ran` is null or missing any of the 7 expected keys → incomplete → waiting.

```php
$table   = \Fragen\Git_Updater\DB\Repo_Cache_Table::instance();
$ran_map = $table->get_cached_ran();

$waiting = false;
foreach ( $repos as $git_repo ) {
    $ran = $ran_map[ $git_repo->slug ] ?? null;
    if ( null === $ran || [] !== array_diff( self::EXPECTED_RAN_STEPS, $ran ) ) {
        $waiting = true;
        break;
    }
}

return $waiting;
```

This preserves prior semantics for the "no data" case (no row → waiting) and **adds** detection of
"limited data" repos (row present but `ran` incomplete → waiting). Early-exits on first miss.

**Shared completeness definition.** Extract the expected-key list into a private constant on
`GU_Trait` and use it in BOTH `is_fetch_complete()` and this branch, so the two completeness
definitions can never drift:

```php
private const EXPECTED_RAN_STEPS = [ 'contents', 'assets', 'readme', 'changes', 'tags', 'branches', 'meta' ];
```

- In `is_fetch_complete()`: replace the local `$expected = [...]` with `self::EXPECTED_RAN_STEPS`.
- In the `null` branch: use `self::EXPECTED_RAN_STEPS` (as shown above).

This removes the comment-only guard from the previous plan and makes the duplication structurally
impossible.

### 3. Keep or remove `get_cached_slugs()`?
- `get_cached_slugs()` (added in the prior fix) is still a valid, tested public helper. After this
  change it is no longer called by `waiting_for_background_update()`. To avoid dead code, remove its
  call site usage but **keep the method** only if a test depends on it. The two new tests
  (`test_get_cached_slugs_*`) in `test-cache-table.php` assert on it — so keep the method and its
  tests (it's a harmless, useful utility). Alternatively delete both the method and its tests.
  Decision: KEEP the method + tests (low cost, still exercised, and `get_cached_ran` is the one now
  used). No removal needed.

## Files to modify
- `src/Git_Updater/DB/Abstract_Cache_Table.php` — add `get_cached_ran()`.
- `src/Git_Updater/Traits/GU_Trait.php` — rewrite `null` branch of `waiting_for_background_update()`.

## Tests / verification
1. `tests/test-cache-table.php`:
   - Add `test_get_cached_ran_returns_slug_to_ran_map()` — seed a repo with a complete `ran` array,
     assert map contains `slug => expected_keys`; seed a second repo with a partial `ran`, assert it
     maps to the partial array (not null); seed a third with no `ran`, assert `null`.
2. `tests/test-gutrait.php` — extend `waiting_for_background_update` coverage to lock the new
   behavior (currently only `assertIsBool` for the `null` branch):
   - `test_null_branch_waiting_when_repo_has_no_row` — live repo with no cache row → `true`.
   - `test_null_branch_not_waiting_when_ran_complete` — seed complete `ran` → `false`.
   - `test_null_branch_waiting_when_ran_incomplete` — seed partial `ran` (e.g. only `tags`) → `true`
     (this is the new "limited data" case the user called out).
3. Run `tests/test-cache-table.php` + `tests/test-gutrait.php` (filter
   `Test_Cache_Table|waiting_for_background_update`) via `npm run wp-env -- run tests-cli …`.
4. Run full suite `npm run test` and confirm the pre-existing theme-fixture failures
   (`test-gu-theme` not discovered) are the same 4 as before my changes (established via git stash
   in the prior step) — i.e. no new regressions.

## Risk
- Low. Localized to one method + one new read-only DB helper. Return contract is preserved and
  *strengthened* (now also catches incomplete caches). No schema change. The expected-key list is
  shared via `GU_Trait::EXPECTED_RAN_STEPS` between `is_fetch_complete()` and the `null` branch, so
  the two completeness definitions cannot drift.
