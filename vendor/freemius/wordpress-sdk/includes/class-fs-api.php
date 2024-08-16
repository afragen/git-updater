<?php
	/**
	 * @package     Freemius
	 * @copyright   Copyright (c) 2015, Freemius, Inc.
	 * @license     https://www.gnu.org/licenses/gpl-3.0.html GNU General Public License Version 3
	 * @since       1.0.4
	 */

	if ( ! defined( 'ABSPATH' ) ) {
		exit;
	}

	/**
	 * Class FS_Api
	 *
	 * Wraps Freemius API SDK to handle:
	 *      1. Clock sync.
	 *      2. Fallback to HTTP when HTTPS fails.
	 *      3. Adds caching layer to GET requests.
	 *      4. Adds consistency for failed requests by using last cached version.
	 */
	class FS_Api {
		/**
		 * @var FS_Api[]
		 */
		private static $_instances = array();

		/**
		 * @var FS_Option_Manager Freemius options, options-manager.
		 */
		private static $_options;

		/**
		 * @var FS_Cache_Manager API Caching layer
		 */
		private static $_cache;

		/**
		 * @var int Clock diff in seconds between current server to API server.
		 */
		private static $_clock_diff;

		/**
		 * @var Freemius_Api_WordPress
		 */
		private $_api;

		/**
		 * @var string
		 */
		private $_slug;

		/**
		 * @var FS_Logger
		 * @since 1.0.4
		 */
		private $_logger;

        /**
         * @author Leo Fajardo (@leorw)
         * @since 2.3.0
         *
         * @var string
         */
        private $_sdk_version;

        /**
         * @author Leo Fajardo (@leorw)
         * @since 2.5.0
         *
         * @var string
         */
        private $_url;

        /**
		 * @param string      $slug
		 * @param string      $scope      'app', 'developer', 'user' or 'install'.
		 * @param number      $id         Element's id.
		 * @param string      $public_key Public key.
		 * @param bool        $is_sandbox
		 * @param bool|string $secret_key Element's secret key.
		 * @param null|string $sdk_version
		 * @param null|string $url
		 *
		 * @return FS_Api
		 */
		static function instance(
		    $slug,
            $scope,
            $id,
            $public_key,
            $is_sandbox,
            $secret_key = false,
            $sdk_version = null,
            $url = null
        ) {
			$identifier = md5( $slug . $scope . $id . $public_key . ( is_string( $secret_key ) ? $secret_key : '' ) . json_encode( $is_sandbox ) );

			if ( ! isset( self::$_instances[ $identifier ] ) ) {
				self::_init();

				self::$_instances[ $identifier ] = new FS_Api( $slug, $scope, $id, $public_key, $secret_key, $is_sandbox, $sdk_version, $url );
			}

			return self::$_instances[ $identifier ];
		}

		private static function _init() {
			if ( isset( self::$_options ) ) {
				return;
			}

			if ( ! class_exists( 'Freemius_Api_WordPress' ) ) {
				require_once WP_FS__DIR_SDK . '/FreemiusWordPress.php';
			}

			self::$_options = FS_Option_Manager::get_manager( WP_FS__OPTIONS_OPTION_NAME, true, true );
			self::$_cache   = FS_Cache_Manager::get_manager( WP_FS__API_CACHE_OPTION_NAME );

			self::$_clock_diff = self::$_options->get_option( 'api_clock_diff', 0 );
			Freemius_Api_WordPress::SetClockDiff( self::$_clock_diff );

			if ( self::$_options->get_option( 'api_force_http', false ) ) {
				Freemius_Api_WordPress::SetHttp();
			}
		}

		/**
		 * @param string      $slug
		 * @param string      $scope      'app', 'developer', 'user' or 'install'.
		 * @param number      $id         Element's id.
		 * @param string      $public_key Public key.
		 * @param bool|string $secret_key Element's secret key.
		 * @param bool        $is_sandbox
		 * @param null|string $sdk_version
		 * @param null|string $url
		 */
		private function __construct(
		    $slug,
            $scope,
            $id,
            $public_key,
            $secret_key,
            $is_sandbox,
            $sdk_version,
            $url
        ) {
			$this->_api = new Freemius_Api_WordPress( $scope, $id, $public_key, $secret_key, $is_sandbox );

			$this->_slug        = $slug;
			$this->_sdk_version = $sdk_version;
			$this->_url         = $url;
			$this->_logger      = FS_Logger::get_logger( WP_FS__SLUG . '_' . $slug . '_api', WP_FS__DEBUG_SDK, WP_FS__ECHO_DEBUG_SDK );
		}

		/**
		 * Find clock diff between server and API server, and store the diff locally.
		 *
		 * @param bool|int $diff
		 *
		 * @return bool|int False if clock diff didn't change, otherwise returns the clock diff in seconds.
		 */
		private function _sync_clock_diff( $diff = false ) {
			$this->_logger->entrance();

			// Sync clock and store.
			$new_clock_diff = ( false === $diff ) ?
				Freemius_Api_WordPress::FindClockDiff() :
				$diff;

			if ( $new_clock_diff === self::$_clock_diff ) {
				return false;
			}

			self::$_clock_diff = $new_clock_diff;

			// Update API clock's diff.
			Freemius_Api_WordPress::SetClockDiff( self::$_clock_diff );

			// Store new clock diff in storage.
			self::$_options->set_option( 'api_clock_diff', self::$_clock_diff, true );

			return $new_clock_diff;
		}

		/**
		 * Override API call to enable retry with servers' clock auto sync method.
		 *
		 * @param string $path
		 * @param string $method
		 * @param array  $params
		 * @param bool   $in_retry Is in retry or first call attempt.
		 *
		 * @return array|mixed|string|void
		 */
		private function _call( $path, $method = 'GET', $params = array(), $in_retry = false ) {
            $this->_logger->entrance( $method . ':' . $path );

            $force_http = ( ! $in_retry && self::$_options->get_option( 'api_force_http', false ) );

            if ( self::is_temporary_down() ) {
                $result = $this->get_temporary_unavailable_error();
            } else {
                /**
                 * @since 2.3.0 Include the SDK version with all API requests that going through the API manager. IMPORTANT: Only pass the SDK version if the caller didn't include it yet.
                 */
                if ( ! empty( $this->_sdk_version ) ) {
                    if ( false === strpos( $path, 'sdk_version=' ) &&
                         ! isset( $params['sdk_version'] )
                    ) {
                        // Always add the sdk_version param in the querystring. DO NOT INCLUDE IT IN THE BODY PARAMS, OTHERWISE, IT MAY LEAD TO AN UNEXPECTED PARAMS PARSING IN CASES WHERE THE $params IS A REGULAR NON-ASSOCIATIVE ARRAY.
                        $path = add_query_arg( 'sdk_version', $this->_sdk_version, $path );
                    }
                }

                /**
                 * @since 2.5.0 Include the site's URL, if available, in all API requests that are going through the API manager.
                 */
                if ( ! empty( $this->_url ) ) {
                    if ( false === strpos( $path, 'url=' ) &&
                         ! isset( $params['url'] )
                    ) {
                        $path = add_query_arg( 'url', $this->_url, $path );
                    }
                }

                $result = $this->_api->Api( $path, $method, $params );

                if (
                    ! $in_retry &&
                    null !== $result &&
                    isset( $result->error ) &&
                    isset( $result->error->code )
                ) {
                    $retry = false;

                    if ( 'request_expired' === $result->error->code ) {
                        $diff = isset( $result->error->timestamp ) ?
                            ( time() - strtotime( $result->error->timestamp ) ) :
                            false;

                        // Try to sync clock diff.
                        if ( false !== $this->_sync_clock_diff( $diff ) ) {
                            // Retry call with new synced clock.
                            $retry = true;
                        }
                    } else if (
                        Freemius_Api_WordPress::IsHttps() &&
                        FS_Api::is_ssl_error_response( $result )
                    ) {
                        $force_http = true;
                        $retry      = true;
                    }

                    if ( $retry ) {
                        if ( $force_http ) {
                            $this->toggle_force_http( true );
                        }

                        $result = $this->_call( $path, $method, $params, true );
                    }
                }
            }

            if ( self::is_api_error( $result ) ) {
                if ( $this->_logger->is_on() ) {
                    // Log API errors.
                    $this->_logger->api_error( $result );
                }

                if ( $force_http ) {
                    $this->toggle_force_http( false );
                }
            }

            return $result;
        }

		/**
		 * Override API call to wrap it in servers' clock sync method.
		 *
		 * @param string $path
		 * @param string $method
		 * @param array  $params
		 *
		 * @return array|mixed|string|void
		 * @throws Freemius_Exception
		 */
		function call( $path, $method = 'GET', $params = array() ) {
			return $this->_call( $path, $method, $params );
		}

		/**
		 * Get API request URL signed via query string.
		 *
		 * @param string $path
		 *
		 * @return string
		 */
		function get_signed_url( $path ) {
			return $this->_api->GetSignedUrl( $path );
		}

		/**
		 * @param string $path
		 * @param bool   $flush
		 * @param int    $expiration (optional) Time until expiration in seconds from now, defaults to 24 hours
		 *
		 * @return stdClass|mixed
		 */
		function get( $path = '/', $flush = false, $expiration = WP_FS__TIME_24_HOURS_IN_SEC ) {
			$this->_logger->entrance( $path );

			$cache_key = $this->get_cache_key( $path );

			// Always flush during development.
			if ( WP_FS__DEV_MODE || $this->_api->IsSandbox() ) {
				$flush = true;
			}

			$has_valid_cache = self::$_cache->has_valid( $cache_key, $expiration );
			$cached_result   = $has_valid_cache ?
				self::$_cache->get( $cache_key ) :
				null;

			if ( $flush || is_null( $cached_result ) ) {
				$result = $this->call( $path );

				if ( ! is_object( $result ) || isset( $result->error ) ) {
					// Api returned an error.
					if ( is_object( $cached_result ) &&
					     ! isset( $cached_result->error )
					) {
						// If there was an error during a newer data fetch,
						// fallback to older data version.
						$result = $cached_result;

						if ( $this->_logger->is_on() ) {
							$this->_logger->warn( 'Fallback to cached API result: ' . var_export( $cached_result, true ) );
						}
					} else {
					    if ( is_object( $result ) && isset( $result->error->http ) && 404 == $result->error->http ) {
                            /**
                             * If the response code is 404, cache the result for half of the `$expiration`.
                             *
                             * @author Leo Fajardo (@leorw)
                             * @since 2.2.4
                             */
					        $expiration /= 2;
                        } else {
                            // If no older data version and the response code is not 404, return result without
                            // caching the error.
                            return $result;
                        }
					}
				}

				self::$_cache->set( $cache_key, $result, $expiration );

				$cached_result = $result;
			} else {
				$this->_logger->log( 'Using cached API result.' );
			}

			return $cached_result;
		}

        /**
         * @todo Remove this method after migrating Freemius::safe_remote_post() to FS_Api::call().
         *
         * @author Leo Fajardo (@leorw)
         * @since 2.5.4
         *
         * @param string $url
         * @param array  $remote_args
         *
         * @return array|WP_Error The response array or a WP_Error on failure.
         */
        static function remote_request( $url, $remote_args ) {
            if ( ! class_exists( 'Freemius_Api_WordPress' ) ) {
                require_once WP_FS__DIR_SDK . '/FreemiusWordPress.php';
            }

            if ( method_exists( 'Freemius_Api_WordPress', 'RemoteRequest' ) ) {
                return Freemius_Api_WordPress::RemoteRequest( $url, $remote_args );
            }

            // The following is for backward compatibility when a modified PHP SDK version is in use and the `Freemius_Api_WordPress:RemoteRequest()` method doesn't exist.
            $response = wp_remote_request( $url, $remote_args );

            if (
                is_array( $response ) &&
                (
                    empty( $response['headers'] ) ||
                    empty( $response['headers']['x-api-server'] )
                )
            ) {
                // API is considered blocked if the response doesn't include the `x-api-server` header. When there's no error but this header doesn't exist, the response is usually not in the expected form (e.g., cannot be JSON-decoded).
                $response = new WP_Error( 'api_blocked', htmlentities( $response['body'] ) );
            }

            return $response;
        }

		/**
		 * Check if there's a cached version of the API request.
		 *
		 * @author Vova Feldman (@svovaf)
		 * @since  1.2.1
		 *
		 * @param string $path
		 * @param string $method
		 * @param array  $params
		 *
		 * @return bool
		 */
		function is_cached( $path, $method = 'GET', $params = array() ) {
			$cache_key = $this->get_cache_key( $path, $method, $params );

			return self::$_cache->has_valid( $cache_key );
		}

		/**
		 * Invalidate a cached version of the API request.
		 *
		 * @author Vova Feldman (@svovaf)
		 * @since  1.2.1.5
		 *
		 * @param string $path
		 * @param string $method
		 * @param array  $params
		 */
		function purge_cache( $path, $method = 'GET', $params = array() ) {
			$this->_logger->entrance( "{$method}:{$path}" );

			$cache_key = $this->get_cache_key( $path, $method, $params );

			self::$_cache->purge( $cache_key );
		}

        /**
         * Invalidate a cached version of the API request.
         *
         * @author Vova Feldman (@svovaf)
         * @since  2.0.0
         *
         * @param string $path
         * @param int    $expiration
         * @param string $method
         * @param array  $params
         */
        function update_cache_expiration( $path, $expiration = WP_FS__TIME_24_HOURS_IN_SEC, $method = 'GET', $params = array() ) {
            $this->_logger->entrance( "{$method}:{$path}:{$expiration}" );

            $cache_key = $this->get_cache_key( $path, $method, $params );

            self::$_cache->update_expiration( $cache_key, $expiration );
        }

        /**
		 * @param string $path
		 * @param string $method
		 * @param array  $params
		 *
		 * @return string
		 * @throws \Freemius_Exception
		 */
		private function get_cache_key( $path, $method = 'GET', $params = array() ) {
			$canonized = $this->_api->CanonizePath( $path );
//			$exploded = explode('/', $canonized);
//			return $method . '_' . array_pop($exploded) . '_' . md5($canonized . json_encode($params));
			return strtolower( $method . ':' . $canonized ) . ( ! empty( $params ) ? '#' . md5( json_encode( $params ) ) : '' );
		}

        /**
         * @author Leo Fajardo (@leorw)
         * @since 2.5.4
         *
         * @param bool $is_http
         */
        private function toggle_force_http( $is_http ) {
            self::$_options->set_option( 'api_force_http', $is_http, true );

            if ( $is_http ) {
                Freemius_Api_WordPress::SetHttp();
            } else if ( method_exists( 'Freemius_Api_WordPress', 'SetHttps' ) ) {
                Freemius_Api_WordPress::SetHttps();
            }
        }

        /**
         * @author Leo Fajardo (@leorw)
         * @since 2.5.4
         *
         * @param mixed $response
         *
         * @return bool
         */
        static function is_blocked( $response ) {
            return (
                self::is_api_error_object( $response, true ) &&
                isset( $response->error->code ) &&
                'api_blocked' === $response->error->code
            );
        }

		/**
		 * Check if API is temporary down.
		 *
		 * @author Vova Feldman (@svovaf)
		 * @since  1.1.6
		 *
		 * @return bool
		 */
		static function is_temporary_down() {
			self::_init();

			$test = self::$_cache->get_valid( 'ping_test', null );

			return ( false === $test );
		}

		/**
		 * @author Vova Feldman (@svovaf)
		 * @since  1.1.6
		 *
		 * @return object
		 */
		private function get_temporary_unavailable_error() {
			return (object) array(
				'error' => (object) array(
					'type'    => 'TemporaryUnavailable',
					'message' => 'API is temporary unavailable, please retry in ' . ( self::$_cache->get_record_expiration( 'ping_test' ) - WP_FS__SCRIPT_START_TIME ) . ' sec.',
					'code'    => 'temporary_unavailable',
					'http'    => 503
				)
			);
		}

		/**
		 * Check if based on the API result we should try
		 * to re-run the same request with HTTP instead of HTTPS.
		 *
		 * @author Vova Feldman (@svovaf)
		 * @since  1.1.6
		 *
		 * @param $result
		 *
		 * @return bool
		 */
		private static function should_try_with_http( $result ) {
			if ( ! Freemius_Api_WordPress::IsHttps() ) {
				return false;
			}

			return ( ! is_object( $result ) ||
			         ! isset( $result->error ) ||
			         ! isset( $result->error->code ) ||
			         ! in_array( $result->error->code, array(
				         'curl_missing',
				         'cloudflare_ddos_protection',
				         'maintenance_mode',
				         'squid_cache_block',
				         'too_many_requests',
			         ) ) );

		}

		function get_url( $path = '' ) {
			return Freemius_Api_WordPress::GetUrl( $path, $this->_api->IsSandbox() );
		}

		/**
		 * Clear API cache.
		 *
		 * @author Vova Feldman (@svovaf)
		 * @since  1.0.9
		 */
		static function clear_cache() {
			self::_init();

			self::$_cache = FS_Cache_Manager::get_manager( WP_FS__API_CACHE_OPTION_NAME );
			self::$_cache->clear();
		}

        /**
         * @author Leo Fajardo (@leorw)
         * @since  2.5.4
         */
        static function clear_force_http_flag() {
            self::$_options->unset_option( 'api_force_http' );
        }

		#----------------------------------------------------------------------------------
		#region Error Handling
		#----------------------------------------------------------------------------------

		/**
		 * @author Vova Feldman (@svovaf)
		 * @since  1.2.1.5
		 *
		 * @param mixed $result
		 *
		 * @return bool Is API result contains an error.
		 */
		static function is_api_error( $result ) {
			return ( is_object( $result ) && isset( $result->error ) ) ||
			       is_string( $result );
		}

        /**
         * @author Vova Feldman (@svovaf)
         * @since  2.0.0
         *
         * @param mixed $result
         * @param bool  $ignore_message
         *
         * @return bool Is API result contains an error.
         */
        static function is_api_error_object( $result, $ignore_message = false ) {
            return (
                is_object( $result ) &&
                isset( $result->error ) &&
                ( $ignore_message || isset( $result->error->message ) )
            );
        }

        /**
         * @author Leo Fajardo (@leorw)
         * @since 2.5.4
         *
         * @param WP_Error|object|string $response
         *
         * @return bool
         */
        static function is_ssl_error_response( $response ) {
            $http_error = null;

            if ( $response instanceof WP_Error ) {
                if (
                    isset( $response->errors ) &&
                    isset( $response->errors['http_request_failed'] )
                ) {
                    $http_error = strtolower( $response->errors['http_request_failed'][0] );
                }
            } else if (
                self::is_api_error_object( $response ) &&
                ! empty( $response->error->message )
            ) {
                $http_error = $response->error->message;
            }

            return (
                ! empty( $http_error ) &&
                (
                    false !== strpos( $http_error, 'curl error 35' ) ||
                    (
                        false === strpos( $http_error, '</html>' ) &&
                        false !== strpos( $http_error, 'ssl' )
                    )
                )
            );
        }

		/**
		 * Checks if given API result is a non-empty and not an error object.
		 *
		 * @author Vova Feldman (@svovaf)
		 * @since  1.2.1.5
		 *
		 * @param mixed       $result
		 * @param string|null $required_property Optional property we want to verify that is set.
		 *
		 * @return bool
		 */
		static function is_api_result_object( $result, $required_property = null ) {
			return (
				is_object( $result ) &&
				! isset( $result->error ) &&
				( empty( $required_property ) || isset( $result->{$required_property} ) )
			);
		}

		/**
		 * Checks if given API result is a non-empty entity object with non-empty ID.
		 *
		 * @author Vova Feldman (@svovaf)
		 * @since  1.2.1.5
		 *
		 * @param mixed $result
		 *
		 * @return bool
		 */
		static function is_api_result_entity( $result ) {
			return self::is_api_result_object( $result, 'id' ) &&
			       FS_Entity::is_valid_id( $result->id );
		}

        /**
         * Get API result error code. If failed to get code, returns an empty string.
         *
         * @author Vova Feldman (@svovaf)
         * @since  2.0.0
         *
         * @param mixed $result
         *
         * @return string
         */
        static function get_error_code( $result ) {
            if ( is_object( $result ) &&
                 isset( $result->error ) &&
                 is_object( $result->error ) &&
                 ! empty( $result->error->code )
            ) {
                return $result->error->code;
            }

            return '';
        }

		#endregion
	}