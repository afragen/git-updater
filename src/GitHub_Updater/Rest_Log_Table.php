<?php
/**
 * GitHub Updater
 *
 * @package	GitHub_Updater
 * @author	Andy Fragen
 * @license	GPL-2.0+
 * @link	 https://github.com/afragen/github-updater
 */

namespace Fragen\GitHub_Updater;

require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';

/*
 * Exit if called directly.
 */
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Class Rest Log Table
 *
 * Class that will display our custom table records in nice table
 * based on: https://github.com/pmbaldha/WP-Custom-List-Table-With-Database-Example
 *
 * @package Fragen\GitHub_Updater
 */
class Rest_Log_Table extends \WP_List_Table
{
	/**
	 * Constructor and give some basic params
	 */
	function __construct()
	{
		global $status, $page;

		parent::__construct(array(
			'singular' => 'log',
			'plural' => 'logs',
		));
	}

	/**
	 * [REQUIRED] Default column renderer
	 *
	 * @param $item - row (key, value array)
	 * @param $column_name - string (key)
	 * @return HTML
	 */
	function column_default($item, $column_name)
	{
		return $item[$column_name];
	}

	/**
	 * [OPTIONAL] this is example, how to render specific column
	 *
	 * method name must be like this: "column_[column_name]"
	 *
	 * @param $item - row (key, value array)
	 * @return HTML
	 */
	function column_time($item)
	{
		return '<em>' . $item['time'] . '</em>';
	}

	/**
	 * [REQUIRED] this is how checkbox column renders
	 *
	 * @param $item - row (key, value array)
	 * @return HTML
	 */
	function column_cb($item)
	{
		return sprintf(
			'<input type="checkbox" name="id[]" value="%s" />',
			$item['id']
		);
	}

	/**
	 * [REQUIRED] This method return columns to display in table
	 * you can skip columns that you do not want to show
	 * like content, or description
	 *
	 * @return array
	 */
	function get_columns()
	{
		$columns = array(
			'cb' => '<input type="checkbox" />', //Render a checkbox instead of text
			'status' => __('Status Code', 'github-updater'),
			'time' => __('Local Time', 'github-updater'),
			'elapsed_time' => __('Elapsed time', 'github-updater'),
			'update_resource' => __('Theme/Plugin Name', 'github-updater'),
			'webhook_source' => __('Hook Source', 'github-updater'),
		);
		return $columns;
	}

	/**
	 * [OPTIONAL] This method return columns that may be used to sort table
	 * all strings in array - is column names
	 * notice that true on name column means that its default sort
	 *
	 * @return array
	 */
	function get_sortable_columns()
	{
		$sortable_columns = array(
			'status' => array('status', false),
			'time' => array('time', true),
			'elapsed_time' => array('elapsed_time', false),
			'update_resource' => array('update_resource', false),
			'webhook_source' => array('webhook_source', false),
		);
		return $sortable_columns;
	}

	/**
	 * [OPTIONAL] Return array of bulk actions if has any
	 *
	 * @return array
	 */
	function get_bulk_actions()
	{
		$actions = array(
			'delete' => 'Delete'
		);
		return $actions;
	}

	/**
	 * [OPTIONAL] This method processes bulk actions
	 * it can be outside of class
	 * it can not use wp_redirect coz there is output already
	 * in this example we are processing delete action
	 * message about successful deletion will be shown on page in next part
	 */
	function process_bulk_action()
	{
		global $wpdb;
		$table_name = $wpdb->prefix . GHU_TABLE_LOGS; // do not forget about tables prefix

		if ('delete' === $this->current_action()) {
			$ids = isset($_REQUEST['id']) ? $_REQUEST['id'] : array();
			if (is_array($ids)) $ids = implode(',', $ids);

			if (!empty($ids)) {
			  $wpdb->query("DELETE FROM $table_name WHERE id IN($ids)");
			}
		}
	}

	/**
	 * [REQUIRED] This is the most important method
	 *
	 * It will get rows from database and prepare them to be showed in table
	 */
	function prepare_items()
	{
		global $wpdb;
		$table_name = $wpdb->prefix . GHU_TABLE_LOGS; // do not forget about tables prefix

		$per_page = 5; // constant, how much records will be shown per page

		$columns = $this->get_columns();
		$hidden = array();
		$sortable = $this->get_sortable_columns();

		// here we configure table headers, defined in our methods
		$this->_column_headers = array($columns, $hidden, $sortable);

		// [OPTIONAL] process bulk action if any
		$this->process_bulk_action();

		// will be used in pagination settings
		$total_items = $wpdb->get_var("SELECT COUNT(id) FROM $table_name");

		// prepare query params, as usual current page, order by and order direction
		$paged = isset($_REQUEST['paged']) ? max(0, intval($_REQUEST['paged']) - 1) : 0;
		$orderby = (isset($_REQUEST['orderby']) && in_array($_REQUEST['orderby'], array_keys($this->get_sortable_columns()))) ? $_REQUEST['orderby'] : 'time';
		$order = (isset($_REQUEST['order']) && in_array($_REQUEST['order'], array('asc', 'desc'))) ? $_REQUEST['order'] : 'asc';

		// [REQUIRED] define $items array
		// notice that last argument is ARRAY_A, so we will retrieve array
		$this->items = $wpdb->get_results($wpdb->prepare("SELECT * FROM $table_name ORDER BY $orderby $order LIMIT %d OFFSET %d", $per_page, $paged), ARRAY_A);

		// [REQUIRED] configure pagination
		$this->set_pagination_args(array(
			'total_items' => $total_items, // total items defined above
			'per_page' => $per_page, // per page constant defined at top of method
			'total_pages' => ceil($total_items / $per_page) // calculate pages count
		));
	}

		/**
		 *List page handler
		 *
		 * This function renders our custom table
		 * Notice how we display message about successfull deletion
		 * Actualy this is very easy, and you can add as many features
		 * as you want.
		 *
		 * Look into /wp-admin/includes/class-wp-*-list-table.php for examples
		 */
		public function output()
		{
			global $wpdb;

			$table = $this;
			$table->prepare_items();

			$message = '';
			if ('delete' === $table->current_action()) {
				$message = '<div class="updated below-h2" id="message"><p>' . sprintf(__('Items deleted: %d', 'github-updater'), count($_REQUEST['id'])) . '</p></div>';
			}
			?>
			<hr style="clear: both;">
			<div class="wrap">
				<h3><?php _e('Recent Requests', 'github-updater')?></h3>

				<?php echo $message; ?>

				<p>
					<?php
							esc_html_e('Everytime someone made a call to the Rest API Endpoint we stored some basic info about the request, you can use these details to easily figure out if something went wrong (or detect an excessive use of the api).', 'github-updater');
					?>
				</p>

				<form id="persons-table" method="GET">
					<input type="hidden" name="page" value="<?php echo $_REQUEST['page'] ?>"/>
					<?php $table->display() ?>
				</form>

				<hr>

				<p>More info about: <a href="https://github.com/afragen/github-updater/wiki/Messages" target="_blank">Response Codes</a>, <a href="https://github.com/afragen/github-updater/wiki/Remote-Management---RESTful-Endpoints#restful-endpoints-for-remote-management" target="_blank">Rest API</a></p>

			</div>
			<?php
		}

		/**
		 * Create or updates to the "ghu_logs" table latest db version
		 *
		 * TODO: move in a better place?
		 */
		public static function update_db_table()
		{
			global $wpdb;

			$installed_ver = get_site_option('ghu_db_version');
			if ($installed_ver != GHU_DB_VERSION) {

				$table_name = $wpdb->prefix . GHU_TABLE_LOGS;

				if($wpdb->get_var("show tables like '$table_name'") != $table_name) {

					$sql = "CREATE TABLE " . $table_name . " (
						id int(11) NOT NULL AUTO_INCREMENT,
						status int(11) NOT NULL,
						time datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
						elapsed_time tinytext DEFAULT '' NOT NULL,
						update_resource tinytext DEFAULT '' NOT NULL,
						webhook_source tinytext DEFAULT '' NOT NULL,
						PRIMARY KEY  (id)
					);";

					require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
					dbDelta($sql);

					// save current database version for later use (on upgrade)
					update_site_option('ghu_db_version', GHU_DB_VERSION);
				}
			}
		}

		/**
		 * Delete the "ghu_logs" table from db
		 *
		 * TODO: move in a better place?
		 */
		public static function drop_db_table()
		{
			global $wpdb;
			$sql = "DROP TABLE {$wpdb->prefix}".GHU_TABLE_LOGS;
			$wpdb->query($sql);
		}

		/**
		 * Insert a new log entry to the "ghu_logs" table
		 */
		public static function insert_db_record($row)
		{
			global $wpdb;
			$table_name = $wpdb->prefix . GHU_TABLE_LOGS; // do not forget about tables prefix
			$wpdb->insert($table_name, $row);
		}
}
