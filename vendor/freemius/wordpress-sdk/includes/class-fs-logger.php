<?php
	/**
	 * @package     Freemius
	 * @copyright   Copyright (c) 2015, Freemius, Inc.
	 * @license     https://www.gnu.org/licenses/gpl-3.0.html GNU General Public License Version 3
	 * @since       1.0.3
	 */

	if ( ! defined( 'ABSPATH' ) ) {
		exit;
	}

	class FS_Logger {
		private $_id;
		private $_on = false;
		private $_echo = false;
		private $_file_start = 0;
		/**
		 * @var int PHP Process ID.
		 */
		private static $_processID;
		/**
		 * @var string PHP Script user name.
		 */
		private static $_ownerName;
		/**
		 * @var bool Is storage logging turned on.
		 */
		private static $_isStorageLoggingOn;
		/**
		 * @var int ABSPATH length.
		 */
		private static $_abspathLength;

		private static $LOGGERS = array();
		private static $LOG = array();
		private static $CNT = 0;
		private static $_HOOKED_FOOTER = false;

		private function __construct( $id, $on = false, $echo = false ) {
			$this->_id = $id;

			$bt     = debug_backtrace();
			$caller = $bt[2];

			if ( false !== strpos( $caller['file'], 'plugins' ) ) {
				$this->_file_start = strpos( $caller['file'], 'plugins' ) + strlen( 'plugins/' );
			} else {
				$this->_file_start = strpos( $caller['file'], 'themes' ) + strlen( 'themes/' );
			}

			if ( $on ) {
				$this->on();
			}
			if ( $echo ) {
				$this->echo_on();
			}
		}

		/**
		 * @param string $id
		 * @param bool   $on
		 * @param bool   $echo
		 *
		 * @return FS_Logger
		 */
		public static function get_logger( $id, $on = false, $echo = false ) {
			$id = strtolower( $id );

			if ( ! isset( self::$_processID ) ) {
				self::init();
			}

			if ( ! isset( self::$LOGGERS[ $id ] ) ) {
				self::$LOGGERS[ $id ] = new FS_Logger( $id, $on, $echo );
			}

			return self::$LOGGERS[ $id ];
		}

		/**
		 * Initialize logging global info.
		 *
		 * @author Vova Feldman (@svovaf)
		 * @since  1.2.1.6
		 */
		private static function init() {
			self::$_ownerName          = function_exists( 'get_current_user' ) ?
				get_current_user() :
				'unknown';
			self::$_isStorageLoggingOn = ( 1 == get_option( 'fs_storage_logger', 0 ) );
			self::$_abspathLength      = strlen( ABSPATH );
			self::$_processID          = mt_rand( 0, 32000 );

			// Process ID may be `false` on errors.
			if ( ! is_numeric( self::$_processID ) ) {
				self::$_processID = 0;
			}
		}

		private static function hook_footer() {
			if ( self::$_HOOKED_FOOTER ) {
				return;
			}

			if ( is_admin() ) {
				add_action( 'admin_footer', 'FS_Logger::dump', 100 );
			} else {
				add_action( 'wp_footer', 'FS_Logger::dump', 100 );
			}
		}

		function is_on() {
			return $this->_on;
		}

		function on() {
			$this->_on = true;

			if ( ! function_exists( 'dbDelta' ) ) {
				require_once ABSPATH . 'wp-admin/includes/upgrade.php';
			}

			self::hook_footer();
		}

		function echo_on() {
			$this->on();

			$this->_echo = true;
		}

		function is_echo_on() {
			return $this->_echo;
		}

		function get_id() {
			return $this->_id;
		}

		function get_file() {
			return $this->_file_start;
		}

		private function _log( &$message, $type, $wrapper = false ) {
			if ( ! $this->is_on() ) {
				return;
			}

			$bt    = debug_backtrace();
			$depth = $wrapper ? 3 : 2;
			while ( $depth < count( $bt ) - 1 && 'eval' === $bt[ $depth ]['function'] ) {
				$depth ++;
			}

			$caller = $bt[ $depth ];

			/**
			 * Retrieve the correct call file & line number from backtrace
			 * when logging from a wrapper method.
			 *
			 * @author Vova Feldman
			 * @since  1.2.1.6
			 */
			if ( empty( $caller['line'] ) ) {
				$depth --;

				while ( $depth >= 0 ) {
					if ( ! empty( $bt[ $depth ]['line'] ) ) {
						$caller['line'] = $bt[ $depth ]['line'];
						$caller['file'] = $bt[ $depth ]['file'];
						break;
					}
				}
			}

			$log = array_merge( $caller, array(
				'cnt'       => self::$CNT ++,
				'logger'    => $this,
				'timestamp' => microtime( true ),
				'log_type'  => $type,
				'msg'       => $message,
			) );

			if ( self::$_isStorageLoggingOn ) {
				$this->db_log( $type, $message, self::$CNT, $caller );
			}

			self::$LOG[] = $log;

			if ( $this->is_echo_on() && ! Freemius::is_ajax() ) {
				echo self::format_html( $log ) . "\n";
			}
		}

		function log( $message, $wrapper = false ) {
			$this->_log( $message, 'log', $wrapper );
		}

		function info( $message, $wrapper = false ) {
			$this->_log( $message, 'info', $wrapper );
		}

		function warn( $message, $wrapper = false ) {
			$this->_log( $message, 'warn', $wrapper );
		}

		function error( $message, $wrapper = false ) {
			$this->_log( $message, 'error', $wrapper );
		}

		/**
		 * Log API error.
		 *
		 * @author Vova Feldman (@svovaf)
		 * @since  1.2.1.5
		 *
		 * @param mixed $api_result
		 * @param bool  $wrapper
		 */
		function api_error( $api_result, $wrapper = false ) {
			$message = '';
			if ( is_object( $api_result ) &&
			     ! empty( $api_result->error ) &&
			     ! empty( $api_result->error->message )
			) {
				$message = $api_result->error->message;
			} else if ( is_object( $api_result ) ) {
				$message = var_export( $api_result, true );
			} else if ( is_string( $api_result ) ) {
				$message = $api_result;
			} else if ( empty( $api_result ) ) {
				$message = 'Empty API result.';
			}

			$message = 'API Error: ' . $message;

			$this->_log( $message, 'error', $wrapper );
		}

		function entrance( $message = '', $wrapper = false ) {
			$msg = 'Entrance' . ( empty( $message ) ? '' : ' > ' ) . $message;

			$this->_log( $msg, 'log', $wrapper );
		}

		function departure( $message = '', $wrapper = false ) {
			$msg = 'Departure' . ( empty( $message ) ? '' : ' > ' ) . $message;

			$this->_log( $msg, 'log', $wrapper );
		}

		#--------------------------------------------------------------------------------
		#region Log Formatting
		#--------------------------------------------------------------------------------

		private static function format( $log, $show_type = true ) {
			return '[' . str_pad( $log['cnt'], strlen( self::$CNT ), '0', STR_PAD_LEFT ) . '] [' . $log['logger']->_id . '] ' . ( $show_type ? '[' . $log['log_type'] . ']' : '' ) . ( ! empty( $log['class'] ) ? $log['class'] . $log['type'] : '' ) . $log['function'] . ' >> ' . $log['msg'] . ( isset( $log['file'] ) ? ' (' . substr( $log['file'], $log['logger']->_file_start ) . ' ' . $log['line'] . ') ' : '' ) . ' [' . $log['timestamp'] . ']';
		}

		private static function format_html( $log ) {
			return '<div style="font-size: 13px; font-family: monospace; color: #7da767; padding: 8px 3px; background: #000; border-bottom: 1px solid #555;">[' . $log['cnt'] . '] [' . $log['logger']->_id . '] [' . $log['log_type'] . '] <b><code style="color: #c4b1e0;">' . ( ! empty( $log['class'] ) ? $log['class'] . $log['type'] : '' ) . $log['function'] . '</code> >> <b style="color: #f59330;">' . esc_html( $log['msg'] ) . '</b></b>' . ( isset( $log['file'] ) ? ' (' . substr( $log['file'], $log['logger']->_file_start ) . ' ' . $log['line'] . ')' : '' ) . ' [' . $log['timestamp'] . ']</div>';
		}

		#endregion

		static function dump() {
			?>
			<!-- BEGIN: Freemius PHP Console Log -->
			<script type="text/javascript">
				<?php
				foreach ( self::$LOG as $log ) {
					echo 'console.' . $log['log_type'] . '(' . json_encode( self::format( $log, false ) ) . ')' . "\n";
				}
				?>
			</script>
			<!-- END: Freemius PHP Console Log -->
			<?php
		}

		static function get_log() {
			return self::$LOG;
		}

		#--------------------------------------------------------------------------------
		#region Database Logging
		#--------------------------------------------------------------------------------

		/**
		 * @author Vova Feldman (@svovaf)
		 * @since  1.2.1.6
		 *
		 * @return bool
		 */
		public static function is_storage_logging_on() {
			if ( ! isset( self::$_isStorageLoggingOn ) ) {
				self::$_isStorageLoggingOn = ( 1 == get_option( 'fs_storage_logger', 0 ) );
			}

			return self::$_isStorageLoggingOn;
		}

		/**
		 * Turns on/off database persistent debugging to capture
		 * multi-session logs to debug complex flows like
		 * plugin auto-deactivate on premium version activation.
		 *
		 * @todo   Check if Theme Check has issues with DB tables for themes.
		 *
		 * @author Vova Feldman (@svovaf)
		 * @since  1.2.1.6
		 *
		 * @param bool $is_on
		 *
		 * @return bool
		 */
		public static function _set_storage_logging( $is_on = true ) {
			global $wpdb;

			$table = "{$wpdb->prefix}fs_logger";

			if ( $is_on ) {
				/**
				 * Create logging table.
				 *
				 * NOTE:
				 *  dbDelta must use KEY and not INDEX for indexes.
				 *
				 * @link https://core.trac.wordpress.org/ticket/2695
				 */
				$result = $wpdb->query( "CREATE TABLE {$table} (
`id` BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
`process_id` INT UNSIGNED NOT NULL,
`user_name` VARCHAR(64) NOT NULL,
`logger` VARCHAR(128) NOT NULL,
`log_order` INT UNSIGNED NOT NULL,
`type` ENUM('log','info','warn','error') NOT NULL DEFAULT 'log',
`message` TEXT NOT NULL,
`file` VARCHAR(256) NOT NULL,
`line` INT UNSIGNED NOT NULL,
`function` VARCHAR(256) NOT NULL,
`request_type` ENUM('call','ajax','cron') NOT NULL DEFAULT 'call',
`request_url` VARCHAR(1024) NOT NULL,
`created` DECIMAL(16, 6) NOT NULL,
PRIMARY KEY (`id`),
KEY `process_id` (`process_id` ASC),
KEY `process_logger` (`process_id` ASC, `logger` ASC),
KEY `function` (`function` ASC),
KEY `type` (`type` ASC))" );
			} else {
				/**
				 * Drop logging table.
				 */
				$result = $wpdb->query( "DROP TABLE IF EXISTS $table;" );
			}

			if ( false !== $result ) {
				update_option( 'fs_storage_logger', ( $is_on ? 1 : 0 ) );
			}

			return ( false !== $result );
		}

		/**
		 * @author Vova Feldman (@svovaf)
		 * @since  1.2.1.6
		 *
		 * @param string $type
		 * @param string $message
		 * @param int    $log_order
		 * @param array  $caller
		 *
		 * @return false|int
		 */
		private function db_log(
			&$type,
			&$message,
			&$log_order,
			&$caller
		) {
			global $wpdb;

			$request_type = 'call';
			if ( defined( 'DOING_CRON' ) && DOING_CRON ) {
				$request_type = 'cron';
			} else if ( defined( 'DOING_AJAX' ) && DOING_AJAX ) {
				$request_type = 'ajax';
			}

			$request_url = WP_FS__IS_HTTP_REQUEST ?
				$_SERVER['REQUEST_URI'] :
				'';

			return $wpdb->insert(
				"{$wpdb->prefix}fs_logger",
				array(
					'process_id'   => self::$_processID,
					'user_name'    => self::$_ownerName,
					'logger'       => $this->_id,
					'log_order'    => $log_order,
					'type'         => $type,
					'request_type' => $request_type,
					'request_url'  => $request_url,
					'message'      => $message,
					'file'         => isset( $caller['file'] ) ?
						substr( $caller['file'], self::$_abspathLength ) :
						'',
					'line'         => $caller['line'],
					'function'     => ( ! empty( $caller['class'] ) ? $caller['class'] . $caller['type'] : '' ) . $caller['function'],
					'created'      => microtime( true ),
				)
			);
		}

		/**
		 * Persistent DB logger columns.
		 *
		 * @var array
		 */
		private static $_log_columns = array(
			'id',
			'process_id',
			'user_name',
			'logger',
			'log_order',
			'type',
			'message',
			'file',
			'line',
			'function',
			'request_type',
			'request_url',
			'created',
		);

		/**
		 * Create DB logs query.
		 *
		 * @author Vova Feldman (@svovaf)
		 * @since  1.2.1.6
		 *
		 * @param bool $filters
		 * @param int  $limit
		 * @param int  $offset
		 * @param bool $order
		 * @param bool $escape_eol
		 *
		 * @return string
		 */
		private static function build_db_logs_query(
			$filters = false,
			$limit = 200,
			$offset = 0,
			$order = false,
			$escape_eol = false
		) {
			global $wpdb;

			$select = '*';

			if ( $escape_eol ) {
				$select = '';
				for ( $i = 0, $len = count( self::$_log_columns ); $i < $len; $i ++ ) {
					if ( $i > 0 ) {
						$select .= ', ';
					}

					if ( 'message' !== self::$_log_columns[ $i ] ) {
						$select .= self::$_log_columns[ $i ];
					} else {
						$select .= 'REPLACE(message , \'\n\', \' \') AS message';
					}
				}
			}

			$query = "SELECT {$select} FROM {$wpdb->prefix}fs_logger";
			if ( is_array( $filters ) ) {
				$criteria = array();

				if ( ! empty( $filters['type'] ) && 'all' !== $filters['type'] ) {
					$filters['type'] = strtolower( $filters['type'] );

					switch ( $filters['type'] ) {
						case 'warn_error':
							$criteria[] = array( 'col' => 'type', 'val' => array( 'warn', 'error' ) );
							break;
						case 'error':
						case 'warn':
							$criteria[] = array( 'col' => 'type', 'val' => $filters['type'] );
							break;
						case 'info':
						default:
							$criteria[] = array( 'col' => 'type', 'val' => array( 'info', 'log' ) );
							break;
					}
				}

				if ( ! empty( $filters['request_type'] ) ) {
					$filters['request_type'] = strtolower( $filters['request_type'] );

					if ( in_array( $filters['request_type'], array( 'call', 'ajax', 'cron' ) ) ) {
						$criteria[] = array( 'col' => 'request_type', 'val' => $filters['request_type'] );
					}
				}

				if ( ! empty( $filters['file'] ) ) {
					$criteria[] = array(
						'col' => 'file',
						'op'  => 'LIKE',
						'val' => '%' . esc_sql( $filters['file'] ),
					);
				}

				if ( ! empty( $filters['function'] ) ) {
					$criteria[] = array(
						'col' => 'function',
						'op'  => 'LIKE',
						'val' => '%' . esc_sql( $filters['function'] ),
					);
				}

				if ( ! empty( $filters['process_id'] ) && is_numeric( $filters['process_id'] ) ) {
					$criteria[] = array( 'col' => 'process_id', 'val' => $filters['process_id'] );
				}

				if ( ! empty( $filters['logger'] ) ) {
					$criteria[] = array(
						'col' => 'logger',
						'op'  => 'LIKE',
						'val' => '%' . esc_sql( $filters['logger'] ) . '%',
					);
				}

				if ( ! empty( $filters['message'] ) ) {
					$criteria[] = array(
						'col' => 'message',
						'op'  => 'LIKE',
						'val' => '%' . esc_sql( $filters['message'] ) . '%',
					);
				}

				if ( 0 < count( $criteria ) ) {
					$query .= "\nWHERE\n";

					$first = true;
					foreach ( $criteria as $c ) {
						if ( ! $first ) {
							$query .= "AND\n";
						}

						if ( is_array( $c['val'] ) ) {
							$operator = 'IN';

							for ( $i = 0, $len = count( $c['val'] ); $i < $len; $i ++ ) {
								$c['val'][ $i ] = "'" . esc_sql( $c['val'][ $i ] ) . "'";
							}

							$val = '(' . implode( ',', $c['val'] ) . ')';
						} else {
							$operator = ! empty( $c['op'] ) ? $c['op'] : '=';
							$val      = "'" . esc_sql( $c['val'] ) . "'";
						}

						$query .= "`{$c['col']}` {$operator} {$val}\n";

						$first = false;
					}
				}
			}

			if ( ! is_array( $order ) ) {
				$order = array(
					'col'   => 'id',
					'order' => 'desc'
				);
			}

			$query .= " ORDER BY {$order['col']} {$order['order']} LIMIT {$offset},{$limit}";

			return $query;
		}

		/**
		 * Load logs from DB.
		 *
		 * @author Vova Feldman (@svovaf)
		 * @since  1.2.1.6
		 *
		 * @param bool $filters
		 * @param int  $limit
		 * @param int  $offset
		 * @param bool $order
		 *
		 * @return object[]|null
		 */
		public static function load_db_logs(
			$filters = false,
			$limit = 200,
			$offset = 0,
			$order = false
		) {
			global $wpdb;

			$query = self::build_db_logs_query(
				$filters,
				$limit,
				$offset,
				$order
			);

			return $wpdb->get_results( $query );
		}

		/**
		 * Load logs from DB.
		 *
		 * @author Vova Feldman (@svovaf)
		 * @since  1.2.1.6
		 *
		 * @param bool   $filters
		 * @param string $filename
		 * @param int    $limit
		 * @param int    $offset
		 * @param bool   $order
		 *
		 * @return false|string File download URL or false on failure.
		 */
		public static function download_db_logs(
			$filters = false,
			$filename = '',
			$limit = 10000,
			$offset = 0,
			$order = false
		) {
			global $wpdb;

			$query = self::build_db_logs_query(
				$filters,
				$limit,
				$offset,
				$order,
				true
			);

			$upload_dir = wp_upload_dir();
			if ( empty( $filename ) ) {
				$filename = 'fs-logs-' . date( 'Y-m-d_H-i-s', WP_FS__SCRIPT_START_TIME ) . '.csv';
			}
			$filepath = rtrim( $upload_dir['path'], '/' ) . "/{$filename}";

			$query .= " INTO OUTFILE '{$filepath}' FIELDS TERMINATED BY '\t' ESCAPED BY '\\\\' OPTIONALLY ENCLOSED BY '\"' LINES TERMINATED BY '\\n'";

			$columns = '';
			for ( $i = 0, $len = count( self::$_log_columns ); $i < $len; $i ++ ) {
				if ( $i > 0 ) {
					$columns .= ', ';
				}

				$columns .= "'" . self::$_log_columns[ $i ] . "'";
			}

			$query = "SELECT {$columns} UNION ALL " . $query;

			$result = $wpdb->query( $query );

			if ( false === $result ) {
				return false;
			}

			return rtrim( $upload_dir['url'], '/' ) . '/' . $filename;
		}

		/**
		 * @author Vova Feldman (@svovaf)
		 * @since  1.2.1.6
		 *
		 * @param string $filename
		 *
		 * @return string
		 */
		public static function get_logs_download_url( $filename = '' ) {
			$upload_dir = wp_upload_dir();
			if ( empty( $filename ) ) {
				$filename = 'fs-logs-' . date( 'Y-m-d_H-i-s', WP_FS__SCRIPT_START_TIME ) . '.csv';
			}

			return rtrim( $upload_dir['url'], '/' ) . $filename;
		}

		#endregion
	}
