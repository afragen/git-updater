<?php
/**
 * Git Updater
 *
 * @author  Andy Fragen
 * @license MIT
 * @link    https://github.com/afragen/git-updater
 * @package git-updater
 * @source  List Table Example Plugin by Matt van Andel
 *          Copyright 2015, GPL2
 */

namespace Fragen\Git_Updater\Additions;

/*
 * Exit if called directly.
 * PHP version check and exit.
 */
if ( ! defined( 'WPINC' ) ) {
	die;
}

// Load base class.
if ( ! class_exists( 'WP_List_Table' ) ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

/**
 * Class Site_List_Table
 */
class Repo_List_Table extends \WP_List_Table {
	/**
	 * Holds site options.
	 *
	 * @var array
	 */
	protected static $options = [];

	/**
	 * Holds examples.
	 *
	 * @var array
	 */
	protected static $examples = [];

	/**
	 * Constructor.
	 *
	 * @param array $options Array of saved options.
	 */
	public function __construct( $options ) {
		global $status, $page;

		$examples = [
			[
				'ID'   => md5( 'plugin-noheader/plugin-noheader.php' ),
				'type' => 'github_plugin',
				'slug' => 'plugin-noheader/plugin-noheader.php',
				'uri'  => 'https://github.com/afragen/plugin-noheader',
			],
			[
				'ID'   => md5( 'theme-noheader' ),
				'type' => 'bitbucket_theme',
				'slug' => 'theme-noheader',
				'uri'  => 'https://bitbucket.org/afragen/theme-noheader/',
			],
		];
		// self::$examples = $examples;
		foreach ( (array) $options as $key => $option ) {
			$option['ID']             = $option['ID'] ?: null;
			$option['type']           = $option['type'] ?: null;
			$option['slug']           = $option['slug'] ?: null;
			$option['uri']            = $option['uri'] ?: null;
			$option['primary_branch'] = ! empty( $option['primary_branch'] ) ? $option['primary_branch'] : 'master';
			$option['release_asset']  = isset( $option['release_asset'] ) ? '<span class="dashicons dashicons-yes"></span>' : null;
			$options[ $key ]          = $option;
		}
		self::$options = (array) $options;

		// Set parent defaults.
		parent::__construct(
			[
				'singular' => 'slug',     // singular name of the listed records.
				'plural'   => 'slugs',    // plural name of the listed records.
				'ajax'     => false,      // does this table support ajax?
			]
		);
	}

	/** ************************************************************************
	 * Recommended. This method is called when the parent class can't find a method
	 * specifically build for a given column. Generally, it's recommended to include
	 * one method for each column you want to render, keeping your package class
	 * neat and organized. For example, if the class needs to process a column
	 * named 'title', it would first see if a method named $this->column_title()
	 * exists - if it does, that method will be used. If it doesn't, this one will
	 * be used. Generally, you should try to use custom column methods as much as
	 * possible.
	 *
	 * Since we have defined a column_title() method later on, this method doesn't
	 * need to concern itself with any column with a name of 'title'. Instead, it
	 * needs to handle everything else.
	 *
	 * For more detailed insight into how columns are handled, take a look at
	 * WP_List_Table::single_row_columns()
	 *
	 * @param  array $item        A singular item (one full row's worth of data).
	 * @param  array $column_name The name/slug of the column to be processed.
	 * @return string Text or HTML to be placed inside the column <td>
	 **************************************************************************/
	public function column_default( $item, $column_name ) {
		switch ( $column_name ) {
			case 'uri':
			case 'slug':
			case 'primary_branch':
			case 'release_asset':
			case 'type':
				return $item[ $column_name ];
			default:
				// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_print_r
				return print_r( $item, true ); // Show the whole array for troubleshooting purposes.
		}
	}

	/** ************************************************************************
	 * Recommended. This is a custom column method and is responsible for what
	 * is rendered in any column with a name/slug of 'site'. Every time the class
	 * needs to render a column, it first looks for a method named
	 * column_{$column_title} - if it exists, that method is run. If it doesn't
	 * exist, column_default() is called instead.
	 *
	 * This example also illustrates how to implement rollover actions. Actions
	 * should be an associative array formatted as 'slug'=>'link html' - and you
	 * will need to generate the URLs yourself. You could even ensure the links
	 *
	 * @see WP_List_Table::::single_row_columns()
	 * @param  array $item A singular item (one full row's worth of data).
	 * @return string Text to be placed inside the column <td> (site title only)
	 **************************************************************************/
	public function column_slug( $item ) {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended, WordPress.Security.ValidatedSanitizedInput
		$page = isset( $_REQUEST['page'] ) ? sanitize_title_with_dashes( wp_slash( $_REQUEST['page'] ) ) : null;
		$tab  = isset( $_REQUEST['tab'] ) ? sanitize_title_with_dashes( wp_slash( $_REQUEST['tab'] ) ) : null;
		// phpcs:enable
		$location = add_query_arg(
			[
				'page' => $page,
				'tab'  => $tab,
			],
			''
		);

		// Build row actions.
		$actions = [
			// 'edit'   => sprintf( '<a href="%s&action=%s&slug=%s">Edit</a>', $location, 'edit', $item['ID'] ),
			'delete' => sprintf( '<a href="%s&action=%s&slug=%s">Delete</a>', wp_nonce_url( $location, 'delete_row_item', '_wpnonce_row_action_delete' ), 'delete_row_item', $item['ID'] ),
		];

		// Return the title contents.
		return sprintf(
			/* translators: 1: title, 2: ID, 3: row actions */
			'%1$s <span style="color:silver">(id:%2$s)</span>%3$s',
			/*$1%s*/
			$item['slug'],
			/*$2%s*/
			$item['ID'],
			/*$3%s*/
			$this->row_actions( $actions )
		);
	}

	/** ************************************************************************
	 * REQUIRED if displaying checkboxes or using bulk actions! The 'cb' column
	 * is given special treatment when columns are processed. It ALWAYS needs to
	 * have it's own method.
	 *
	 * @see WP_List_Table::::single_row_columns()
	 * @param  array $item A singular item (one full row's worth of data).
	 * @return string Text to be placed inside the column <td> (movie title only)
	 **************************************************************************/
	public function column_cb( $item ) {
		return sprintf(
			'<input type="checkbox" name="%1$s[]" value="%2$s" />',
			/*$1%s*/
			$this->_args['singular'],  // Let's simply repurpose the table's singular label ("site").
			/*$2%s*/
			$item['ID']                // The value of the checkbox should be the record's id.
		);
	}

	/** ************************************************************************
	 * REQUIRED! This method dictates the table's columns and titles. This should
	 * return an array where the key is the column slug (and class) and the value
	 * is the column's title text. If you need a checkbox for bulk actions, refer
	 * to the $columns array below.
	 *
	 * The 'cb' column is treated differently than the rest. If including a checkbox
	 * column in your table you must create a column_cb() method. If you don't need
	 * bulk actions or checkboxes, simply leave the 'cb' entry out of your array.
	 *
	 * @see WP_List_Table::::single_row_columns()
	 * @return array An associative array containing column information: 'slugs'=>'Visible Titles'
	 **************************************************************************/
	public function get_columns() {
		$columns = [
			// 'cb'             => '<input type="checkbox" />', // Render a checkbox instead of text.
			'slug'           => esc_html__( 'Slug', 'git-updater-additions' ),
			'uri'            => esc_html__( 'URL', 'git-updater-additions' ),
			'primary_branch' => esc_html__( 'Primary Branch', 'git-updater-additions' ),
			'release_asset'  => esc_html__( 'Release Asset', 'git-updater-additions' ),
			'type'           => esc_html__( 'Type', 'git-updater-additions' ),
		];

		return $columns;
	}

	/** ************************************************************************
	 * Optional. If you want one or more columns to be sortable (ASC/DESC toggle),
	 * you will need to register it here. This should return an array where the
	 * key is the column that needs to be sortable, and the value is db column to
	 * sort by. Often, the key and value will be the same, but this is not always
	 * the case (as the value is a column name from the database, not the list table).
	 *
	 * This method merely defines which columns should be sortable and makes them
	 * clickable - it does not handle the actual sorting. You still need to detect
	 * the ORDERBY and ORDER querystring variables within prepare_items() and sort
	 * your data accordingly (usually by modifying your query).
	 *
	 * @return array An associative array containing all the columns that should be sortable: 'slugs'=>array('data_values',bool)
	 **************************************************************************/
	public function get_sortable_columns() {
		$sortable_columns = [
			'slug' => [ 'slug', true ],     // true means it's already sorted.
			'type' => [ 'type', true ],
			// 'api_key' => [ 'api_key', false ],
		];

		return $sortable_columns;
	}

	/** ************************************************************************
	 * Optional. If you need to include bulk actions in your list table, this is
	 * the place to define them. Bulk actions are an associative array in the format
	 * 'slug'=>'Visible Title'
	 *
	 * If this method returns an empty value, no bulk action will be rendered. If
	 * you specify any bulk actions, the bulk actions box will be rendered with
	 * the table automatically on display().
	 *
	 * Also note that list tables are not automatically wrapped in <form> elements,
	 * so you will need to create those manually in order for bulk actions to function.
	 *
	 * @return void|array An associative array containing all the bulk actions: 'slugs'=>'Visible Titles'
	 **************************************************************************/
	public function get_bulk_actions() {
		$actions = [
			'delete' => esc_html__( 'Delete', 'git-updater-additions' ),
		];

		// return $actions;
	}

	/** ************************************************************************
	 * Optional. You can handle your bulk actions anywhere or anyhow you prefer.
	 * For this example package, we will handle it in the class to keep things
	 * clean and organized.
	 *
	 * @see $this->prepare_items()
	 **************************************************************************/
	public function process_bulk_action() {
		// Detect when a bulk action is being triggered...
		if ( ! isset( $_REQUEST['_wpnonce_row_action_delete'] )
				|| ! \wp_verify_nonce( \sanitize_key( \wp_unslash( $_REQUEST['_wpnonce_row_action_delete'] ) ), 'delete_row_item' )
			) {
			return;
		}
		$slugs = isset( $_REQUEST['slug'] ) ? sanitize_key( wp_unslash( $_REQUEST['slug'] ) ) : null;
		$slugs = is_array( $slugs ) ? $slugs : (array) $slugs;
		foreach ( $slugs as $slug ) {
			foreach ( self::$options as $key => $option ) {
				if ( in_array( $slug, $option, true ) ) {
					unset( self::$options[ $key ] );
					update_site_option( 'git_updater_additions', self::$options );
				}
			}
		}
		if ( 'edit' === $this->current_action() ) {
			wp_die( esc_html__( 'Items would go to edit when we write that code.', 'git-updater-additions' ) );
		}
	}

	/** ************************************************************************
	 * REQUIRED! This is where you prepare your data for display. This method will
	 * usually be used to query the database, sort and filter the data, and generally
	 * get it ready to be displayed. At a minimum, we should set $this->items and
	 * $this->set_pagination_args(), although the following properties and methods
	 * are frequently interacted with here...
	 *
	 * @global WPDB $wpdb
	 * @uses $this->_column_headers
	 * @uses $this->items
	 * @uses $this->get_columns()
	 * @uses $this->get_sortable_columns()
	 * @uses $this->get_pagenum()
	 * @uses $this->set_pagination_args()
	 **************************************************************************/
	public function prepare_items() {
		global $wpdb; // This is used only if making any database queries.

		/**
		 * First, lets decide how many records per page to show.
		 */
		$per_page = 5;

		/**
		 * REQUIRED. Now we need to define our column headers. This includes a complete
		 * array of columns to be displayed (slugs & titles), a list of columns
		 * to keep hidden, and a list of columns that are sortable. Each of these
		 * can be defined in another method (as we've done here) before being
		 * used to build the value for our _column_headers property.
		 */
		$columns  = $this->get_columns();
		$hidden   = [];
		$sortable = $this->get_sortable_columns();

		/**
		 * REQUIRED. Finally, we build an array to be used by the class for column
		 * headers. The $this->_column_headers property takes an array which contains
		 * 3 other arrays. One for all columns, one for hidden columns, and one
		 * for sortable columns.
		 */
		$this->_column_headers = [ $columns, $hidden, $sortable ];

		/**
		 * Optional. You can handle your bulk actions however you see fit. In this
		 * case, we'll handle them within our package just to keep things clean.
		 */
		$this->process_bulk_action();

		/**
		 * Instead of querying a database, we're going to fetch the example data
		 * property we created for use in this plugin. This makes this example
		 * package slightly different than one you might build on your own. In
		 * this example, we'll be using array manipulation to sort and paginate
		 * our data. In a real-world implementation, you will probably want to
		 * use sort and pagination data to build a custom query instead, as you'll
		 * be able to use your precisely-queried data immediately.
		 */
		$data = array_merge( self::$examples, self::$options );

		usort( $data, [ $this, 'usort_reorder' ] );

		/***********************************************************************
		 * ---------------------------------------------------------------------
		 * vvvvvvvvvvvvvvvvvvvvvvvvvvvvvvvvvvvvvvvvvvvvvvvvvvvvvvvvvvvvvvvvvvvvv
		 *
		 * In a real-world situation, this is where you would place your query.
		 *
		 * For information on making queries in WordPress, see this Codex entry:
		 * http://codex.wordpress.org/Class_Reference/wpdb
		 *
		 * ^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^
		 * ---------------------------------------------------------------------
		 */

		/**
		 * REQUIRED for pagination. Let's figure out what page the user is currently
		 * looking at. We'll need this later, so you should always include it in
		 * your own package classes.
		 */
		$current_page = $this->get_pagenum();

		/**
		 * REQUIRED for pagination. Let's check how many items are in our data array.
		 * In real-world use, this would be the total number of items in your database,
		 * without filtering. We'll need this later, so you should always include it
		 * in your own package classes.
		 */
		$total_items = count( $data );

		/**
		 * The WP_List_Table class does not handle pagination for us, so we need
		 * to ensure that the data is trimmed to only the current page. We can use
		 * array_slice() to
		 */
		$data = array_slice( $data, ( ( $current_page - 1 ) * $per_page ), $per_page );

		/**
		 * REQUIRED. Now we can add our *sorted* data to the items property, where
		 * it can be used by the rest of the class.
		 */
		$this->items = $data;

		/**
		 * REQUIRED. We also have to register our pagination options & calculations.
		 */
		$this->set_pagination_args(
			[
				'total_items' => $total_items,  // WE have to calculate the total number of items.
				'per_page'    => $per_page,  // WE have to determine how many items to show on a page.
				'total_pages' => ceil( $total_items / $per_page ), // WE have to calculate the total number of pages.
			]
		);
	}

	/**
	 * This checks for sorting input and sorts the data in our array accordingly.
	 *
	 * In a real-world situation involving a database, you would probably want
	 * to handle sorting by passing the 'orderby' and 'order' values directly
	 * to a custom query. The returned data will be pre-sorted, and this array
	 * sorting technique would be unnecessary.
	 *
	 * @param array $a Array of table row data.
	 * @param array $b Array of table row data.
	 *
	 * @return int Sort order, either 1 or -1.
	 */
	public function usort_reorder( $a, $b ) {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended
		$orderby = ( ! empty( $_REQUEST['orderby'] ) ) ? sanitize_text_field( wp_unslash( $_REQUEST['orderby'] ) ) : 'slug'; // If no sort, default to site.
		$order   = ( ! empty( $_REQUEST['order'] ) ) ? sanitize_text_field( wp_unslash( $_REQUEST['order'] ) ) : 'asc'; // If no order, default to asc.
		// phpcs:enable
		$result = strcmp( $a[ $orderby ], $b[ $orderby ] ); // Determine sort order.

		return ( 'asc' === $order ) ? $result : -$result; // Send final sort direction to usort.
	}

	/**
	 * Render list table.
	 *
	 * Explicitly calls prepare_items() and display().
	 *
	 * @return void
	 */
	public function render_list_table() {
		// Fetch, prepare, sort, and filter our data...
		$this->prepare_items();
		echo '<div class="wrap">';
		echo '<h2>' . esc_html__( 'Additions List Table', 'git-updater-additions' ) . '</h2>';

		// Forms are NOT created automatically, so you need to wrap the table in one to use features like bulk actions.
		echo '<form id="sites-list" method="get">';
		wp_nonce_field( 'process-items', '_wpnonce_list' );

		// For plugins, we also need to ensure that the form posts back to our current page.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$current_page = isset( $_REQUEST['page'] ) ? sanitize_title_with_dashes( wp_unslash( $_REQUEST['page'] ) ) : null;
		echo '<input type="hidden" name="page" value="' . esc_attr( $current_page ) . '" />';

		// Now we can render the completed list table.
		$this->display();
		echo '</form>';
		echo '</div>';
	}
}
