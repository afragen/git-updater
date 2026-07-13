<?php
/**
 * Git Updater
 *
 * @author   Andy Fragen
 * @license  GPL-3.0-or-later
 * @link     https://github.com/afragen/git-updater
 * @package  git-updater
 */

namespace Fragen\Git_Updater\WP_CLI;

/**
 * Class CLI_Common
 */
class CLI_Common {
	/**
	 * Delete all cached repository data from the cache table.
	 *
	 * @return bool
	 */
	public function delete_all_cached_data() {
		return \Fragen\Git_Updater\DB\Repo_Cache_Table::instance()->delete_all_repos();
	}
}
