<?php
	/**
	 * Copyright 2016 Freemius, Inc.
	 *
	 * Licensed under the GPL v2 (the "License"); you may
	 * not use this file except in compliance with the License. You may obtain
	 * a copy of the License at
	 *
	 *     http://choosealicense.com/licenses/gpl-v2/
	 *
	 * Unless required by applicable law or agreed to in writing, software
	 * distributed under the License is distributed on an "AS IS" BASIS, WITHOUT
	 * WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied. See the
	 * License for the specific language governing permissions and limitations
	 * under the License.
	 */
    if ( ! defined( 'ABSPATH' ) ) {
        exit;
    }

	require_once dirname( __FILE__ ) . '/FreemiusBase.php';

	if ( ! defined( 'FS_SDK__USER_AGENT' ) ) {
		define( 'FS_SDK__USER_AGENT', 'fs-php-' . Freemius_Api_Base::VERSION );
	}

	if ( ! defined( 'FS_SDK__SIMULATE_NO_CURL' ) ) {
		define( 'FS_SDK__SIMULATE_NO_CURL', false );
	}

	if ( ! defined( 'FS_SDK__SIMULATE_NO_API_CONNECTIVITY_CLOUDFLARE' ) ) {
		define( 'FS_SDK__SIMULATE_NO_API_CONNECTIVITY_CLOUDFLARE', false );
	}

	if ( ! defined( 'FS_SDK__SIMULATE_NO_API_CONNECTIVITY_SQUID_ACL' ) ) {
		define( 'FS_SDK__SIMULATE_NO_API_CONNECTIVITY_SQUID_ACL', false );
	}

	if ( ! defined( 'FS_SDK__HAS_CURL' ) ) {
		if ( FS_SDK__SIMULATE_NO_CURL ) {
			define( 'FS_SDK__HAS_CURL', false );
		} else {
			$curl_required_methods = array(
				'curl_version',
				'curl_exec',
				'curl_init',
				'curl_close',
				'curl_setopt',
				'curl_setopt_array',
				'curl_error',
			);

			$has_curl = true;
			foreach ( $curl_required_methods as $m ) {
				if ( ! function_exists( $m ) ) {
					$has_curl = false;
					break;
				}
			}

			define( 'FS_SDK__HAS_CURL', $has_curl );
		}
	}

    if ( ! defined( 'FS_SDK__SSLVERIFY' ) ) {
        define( 'FS_SDK__SSLVERIFY', false );
    }

	$curl_version = FS_SDK__HAS_CURL ?
		curl_version() :
		array( 'version' => '7.37' );

	if ( ! defined( 'FS_API__PROTOCOL' ) ) {
		define( 'FS_API__PROTOCOL', version_compare( $curl_version['version'], '7.37', '>=' ) ? 'https' : 'http' );
	}

	if ( ! defined( 'FS_API__LOGGER_ON' ) ) {
		define( 'FS_API__LOGGER_ON', false );
	}

	if ( ! defined( 'FS_API__ADDRESS' ) ) {
		define( 'FS_API__ADDRESS', '://api.freemius.com' );
	}
	if ( ! defined( 'FS_API__SANDBOX_ADDRESS' ) ) {
		define( 'FS_API__SANDBOX_ADDRESS', '://sandbox-api.freemius.com' );
	}

	if ( ! class_exists( 'Freemius_Api_WordPress' ) ) {
	class Freemius_Api_WordPress extends Freemius_Api_Base {
		private static $_logger = array();

		/**
		 * @param string      $pScope   'app', 'developer', 'user' or 'install'.
		 * @param number      $pID      Element's id.
		 * @param string      $pPublic  Public key.
		 * @param string|bool $pSecret  Element's secret key.
		 * @param bool        $pSandbox Whether or not to run API in sandbox mode.
		 */
		public function __construct( $pScope, $pID, $pPublic, $pSecret = false, $pSandbox = false ) {
			// If secret key not provided, use public key encryption.
			if ( is_bool( $pSecret ) ) {
				$pSecret = $pPublic;
			}

			parent::Init( $pScope, $pID, $pPublic, $pSecret, $pSandbox );
		}

		public static function GetUrl( $pCanonizedPath = '', $pIsSandbox = false ) {
			$address = ( $pIsSandbox ? FS_API__SANDBOX_ADDRESS : FS_API__ADDRESS );

			if ( ':' === $address[0] ) {
				$address = self::$_protocol . $address;
			}

			return $address . $pCanonizedPath;
		}

		#----------------------------------------------------------------------------------
		#region Servers Clock Diff
		#----------------------------------------------------------------------------------

		/**
		 * @var int Clock diff in seconds between current server to API server.
		 */
		private static $_clock_diff = 0;

		/**
		 * Set clock diff for all API calls.
		 *
		 * @since 1.0.3
		 *
		 * @param $pSeconds
		 */
		public static function SetClockDiff( $pSeconds ) {
			self::$_clock_diff = $pSeconds;
		}

		/**
		 * Find clock diff between current server to API server.
		 *
		 * @since 1.0.2
		 * @return int Clock diff in seconds.
		 */
		public static function FindClockDiff() {
			$time = time();
			$pong = self::Ping();

			return ( $time - strtotime( $pong->timestamp ) );
		}

		#endregion

		/**
		 * @var string http or https
		 */
		private static $_protocol = FS_API__PROTOCOL;

		/**
		 * Set API connection protocol.
		 *
		 * @since 1.0.4
		 */
		public static function SetHttp() {
			self::$_protocol = 'http';
		}

        /**
         * Sets API connection protocol to HTTPS.
         *
         * @since 2.5.4
         */
        public static function SetHttps() {
            self::$_protocol = 'https';
        }

		/**
		 * @since 1.0.4
		 *
		 * @return bool
		 */
		public static function IsHttps() {
			return ( 'https' === self::$_protocol );
		}

		/**
		 * Sign request with the following HTTP headers:
		 *      Content-MD5: MD5(HTTP Request body)
		 *      Date: Current date (i.e Sat, 14 Feb 2016 20:24:46 +0000)
		 *      Authorization: FS {scope_entity_id}:{scope_entity_public_key}:base64encode(sha256(string_to_sign,
		 *      {scope_entity_secret_key}))
		 *
		 * @param string $pResourceUrl
		 * @param array  $pWPRemoteArgs
		 *
		 * @return array
		 */
		function SignRequest( $pResourceUrl, $pWPRemoteArgs ) {
			$auth = $this->GenerateAuthorizationParams(
				$pResourceUrl,
				$pWPRemoteArgs['method'],
				! empty( $pWPRemoteArgs['body'] ) ? $pWPRemoteArgs['body'] : ''
			);

			$pWPRemoteArgs['headers']['Date']          = $auth['date'];
			$pWPRemoteArgs['headers']['Authorization'] = $auth['authorization'];

			if ( ! empty( $auth['content_md5'] ) ) {
				$pWPRemoteArgs['headers']['Content-MD5'] = $auth['content_md5'];
			}

			return $pWPRemoteArgs;
		}

		/**
		 * Generate Authorization request headers:
		 *
		 *      Content-MD5: MD5(HTTP Request body)
		 *      Date: Current date (i.e Sat, 14 Feb 2016 20:24:46 +0000)
		 *      Authorization: FS {scope_entity_id}:{scope_entity_public_key}:base64encode(sha256(string_to_sign,
		 *      {scope_entity_secret_key}))
		 *
		 * @author Vova Feldman
		 *
		 * @param string $pResourceUrl
		 * @param string $pMethod
		 * @param string $pPostParams
		 *
		 * @return array
		 * @throws Freemius_Exception
		 */
		function GenerateAuthorizationParams(
			$pResourceUrl,
			$pMethod = 'GET',
			$pPostParams = ''
		) {
			$pMethod = strtoupper( $pMethod );

			$eol          = "\n";
			$content_md5  = '';
			$content_type = '';
			$now          = ( time() - self::$_clock_diff );
			$date         = date( 'r', $now );

			if ( in_array( $pMethod, array( 'POST', 'PUT' ) ) ) {
                $content_type = 'application/json';

                if ( ! empty( $pPostParams ) ) {
                    $content_md5 = md5( $pPostParams );
                }
            }

			$string_to_sign = implode( $eol, array(
				$pMethod,
				$content_md5,
				$content_type,
				$date,
				$pResourceUrl
			) );

			// If secret and public keys are identical, it means that
			// the signature uses public key hash encoding.
			$auth_type = ( $this->_secret !== $this->_public ) ? 'FS' : 'FSP';

			$auth = array(
				'date'          => $date,
				'authorization' => $auth_type . ' ' . $this->_id . ':' .
				                   $this->_public . ':' .
				                   self::Base64UrlEncode( hash_hmac(
					                   'sha256', $string_to_sign, $this->_secret
				                   ) )
			);

			if ( ! empty( $content_md5 ) ) {
				$auth['content_md5'] = $content_md5;
			}

			return $auth;
		}

		/**
		 * Get API request URL signed via query string.
         *
         * @since 1.2.3 Stopped using http_build_query(). Instead, use urlencode(). In some environments the encoding of http_build_query() can generate a URL that once used with a redirect, the `&` querystring separator is escaped to `&amp;` which breaks the URL (Added by @svovaf).
		 *
		 * @param string $pPath
		 *
		 * @throws Freemius_Exception
		 *
		 * @return string
		 */
        function GetSignedUrl( $pPath ) {
            $resource     = explode( '?', $this->CanonizePath( $pPath ) );
            $pResourceUrl = $resource[0];

            $auth = $this->GenerateAuthorizationParams( $pResourceUrl );

            return Freemius_Api_WordPress::GetUrl(
                $pResourceUrl . '?' .
                ( 1 < count( $resource ) && ! empty( $resource[1] ) ? $resource[1] . '&' : '' ) .
                'authorization=' . urlencode( $auth['authorization'] ) .
                '&auth_date=' . urlencode( $auth['date'] )
                , $this->_isSandbox );
        }

		/**
		 * @author Vova Feldman
		 *
		 * @param string $pUrl
		 * @param array  $pWPRemoteArgs
		 *
		 * @return mixed
		 */
		private static function ExecuteRequest( $pUrl, &$pWPRemoteArgs ) {
            $bt = debug_backtrace();

			$start = microtime( true );

            $response = self::RemoteRequest( $pUrl, $pWPRemoteArgs );

			if ( FS_API__LOGGER_ON ) {
				$end = microtime( true );

				$has_body      = ( isset( $pWPRemoteArgs['body'] ) && ! empty( $pWPRemoteArgs['body'] ) );
				$is_http_error = is_wp_error( $response );

				self::$_logger[] = array(
					'id'        => count( self::$_logger ),
					'start'     => $start,
					'end'       => $end,
					'total'     => ( $end - $start ),
					'method'    => $pWPRemoteArgs['method'],
					'path'      => $pUrl,
					'body'      => $has_body ? $pWPRemoteArgs['body'] : null,
					'result'    => ! $is_http_error ?
						$response['body'] :
						json_encode( $response->get_error_messages() ),
					'code'      => ! $is_http_error ? $response['response']['code'] : null,
					'backtrace' => $bt,
				);
			}

			return $response;
		}

        /**
         * @author Leo Fajardo (@leorw)
         *
         * @param string $pUrl
         * @param array  $pWPRemoteArgs
         *
         * @return array|WP_Error The response array or a WP_Error on failure.
         */
        static function RemoteRequest( $pUrl, $pWPRemoteArgs ) {
            $response = wp_remote_request( $pUrl, $pWPRemoteArgs );

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
		 * @return array
		 */
		static function GetLogger() {
			return self::$_logger;
		}

		/**
		 * @param string        $pCanonizedPath
		 * @param string        $pMethod
		 * @param array         $pParams
		 * @param null|array    $pWPRemoteArgs
		 * @param bool          $pIsSandbox
		 * @param null|callable $pBeforeExecutionFunction
		 *
		 * @return object[]|object|null
		 *
		 * @throws \Freemius_Exception
		 */
		private static function MakeStaticRequest(
			$pCanonizedPath,
			$pMethod = 'GET',
			$pParams = array(),
			$pWPRemoteArgs = null,
			$pIsSandbox = false,
			$pBeforeExecutionFunction = null
		) {
			// Connectivity errors simulation.
			if ( FS_SDK__SIMULATE_NO_API_CONNECTIVITY_CLOUDFLARE ) {
				self::ThrowCloudFlareDDoSException();
			} else if ( FS_SDK__SIMULATE_NO_API_CONNECTIVITY_SQUID_ACL ) {
				self::ThrowSquidAclException();
			}

			if ( empty( $pWPRemoteArgs ) ) {
				$user_agent = 'Freemius/WordPress-SDK/' . Freemius_Api_Base::VERSION . '; ' .
				              home_url();

				$pWPRemoteArgs = array(
					'method'           => strtoupper( $pMethod ),
					'connect_timeout'  => 10,
					'timeout'          => 60,
					'follow_redirects' => true,
					'redirection'      => 5,
					'user-agent'       => $user_agent,
					'blocking'         => true,
				);
			}

			if ( ! isset( $pWPRemoteArgs['headers'] ) ||
			     ! is_array( $pWPRemoteArgs['headers'] )
			) {
				$pWPRemoteArgs['headers'] = array();
			}

			if ( in_array( $pMethod, array( 'POST', 'PUT' ) ) ) {
                $pWPRemoteArgs['headers']['Content-type'] = 'application/json';

                if ( is_array( $pParams ) && 0 < count( $pParams ) ) {
                    $pWPRemoteArgs['body'] = json_encode( $pParams );
                }
			}

			$request_url = self::GetUrl( $pCanonizedPath, $pIsSandbox );

			$resource = explode( '?', $pCanonizedPath );

            if ( FS_SDK__HAS_CURL ) {
                // Disable the 'Expect: 100-continue' behaviour. This causes cURL to wait
                // for 2 seconds if the server does not support this header.
                $pWPRemoteArgs['headers']['Expect'] = '';
            }

			if ( 'https' === substr( strtolower( $request_url ), 0, 5 ) ) {
				$pWPRemoteArgs['sslverify'] = FS_SDK__SSLVERIFY;
			}

			if ( false !== $pBeforeExecutionFunction &&
			     is_callable( $pBeforeExecutionFunction )
			) {
				$pWPRemoteArgs = call_user_func( $pBeforeExecutionFunction, $resource[0], $pWPRemoteArgs );
			}

			$result = self::ExecuteRequest( $request_url, $pWPRemoteArgs );

			if ( is_wp_error( $result ) ) {
				/**
				 * @var WP_Error $result
				 */
				if ( self::IsCurlError( $result ) ) {
					/**
					 * With dual stacked DNS responses, it's possible for a server to
					 * have IPv6 enabled but not have IPv6 connectivity.  If this is
					 * the case, cURL will try IPv4 first and if that fails, then it will
					 * fall back to IPv6 and the error EHOSTUNREACH is returned by the
					 * operating system.
					 */
					$matches = array();
					$regex   = '/Failed to connect to ([^:].*): Network is unreachable/';
					if ( preg_match( $regex, $result->get_error_message( 'http_request_failed' ), $matches ) ) {
						/**
						 * Validate IP before calling `inet_pton()` to avoid PHP un-catchable warning.
						 * @author Vova Feldman (@svovaf)
						 */
						if ( filter_var( $matches[1], FILTER_VALIDATE_IP ) ) {
							if ( strlen( inet_pton( $matches[1] ) ) === 16 ) {
								/**
								 * error_log('Invalid IPv6 configuration on server, Please disable or get native IPv6 on your server.');
								 * Hook to an action triggered just before cURL is executed to resolve the IP version to v4.
								 * 
								 * @phpstan-ignore-next-line
								 */
								add_action( 'http_api_curl', 'Freemius_Api_WordPress::CurlResolveToIPv4', 10, 1 );

								// Re-run request.
								$result = self::ExecuteRequest( $request_url, $pWPRemoteArgs );
							}
						}
					}
				}

				if ( is_wp_error( $result ) ) {
					self::ThrowWPRemoteException( $result );
				}
			}

			$response_body = $result['body'];

			if ( empty( $response_body ) ) {
				return null;
			}

			$decoded = json_decode( $response_body );

			if ( is_null( $decoded ) ) {
				if ( preg_match( '/Please turn JavaScript on/i', $response_body ) &&
				     preg_match( '/text\/javascript/', $response_body )
				) {
					self::ThrowCloudFlareDDoSException( $response_body );
				} else if ( preg_match( '/Access control configuration prevents your request from being allowed at this time. Please contact your service provider if you feel this is incorrect./', $response_body ) &&
				            preg_match( '/squid/', $response_body )
				) {
					self::ThrowSquidAclException( $response_body );
				} else {
					$decoded = (object) array(
						'error' => (object) array(
							'type'    => 'Unknown',
							'message' => $response_body,
							'code'    => 'unknown',
							'http'    => 402
						)
					);
				}
			}

			return $decoded;
		}


		/**
		 * Makes an HTTP request. This method can be overridden by subclasses if
		 * developers want to do fancier things or use something other than wp_remote_request()
		 * to make the request.
		 *
		 * @param string     $pCanonizedPath The URL to make the request to
		 * @param string     $pMethod        HTTP method
		 * @param array      $pParams        The parameters to use for the POST body
		 * @param null|array $pWPRemoteArgs  wp_remote_request options.
		 *
		 * @return object[]|object|null
		 *
		 * @throws Freemius_Exception
		 */
		public function MakeRequest(
			$pCanonizedPath,
			$pMethod = 'GET',
			$pParams = array(),
			$pWPRemoteArgs = null
		) {
			$resource = explode( '?', $pCanonizedPath );

			// Only sign request if not ping.json connectivity test.
			$sign_request = ( '/v1/ping.json' !== strtolower( substr( $resource[0], - strlen( '/v1/ping.json' ) ) ) );

			return self::MakeStaticRequest(
				$pCanonizedPath,
				$pMethod,
				$pParams,
				$pWPRemoteArgs,
				$this->_isSandbox,
				$sign_request ? array( &$this, 'SignRequest' ) : null
			);
		}

		/**
		 * Sets CURLOPT_IPRESOLVE to CURL_IPRESOLVE_V4 for cURL-Handle provided as parameter
		 *
		 * @param resource $handle A cURL handle returned by curl_init()
		 *
		 * @return resource $handle A cURL handle returned by curl_init() with CURLOPT_IPRESOLVE set to
		 *                  CURL_IPRESOLVE_V4
		 *
		 * @link https://gist.github.com/golderweb/3a2aaec2d56125cc004e
		 */
		static function CurlResolveToIPv4( $handle ) {
			curl_setopt( $handle, CURLOPT_IPRESOLVE, CURL_IPRESOLVE_V4 );

			return $handle;
		}

		#----------------------------------------------------------------------------------
		#region Connectivity Test
		#----------------------------------------------------------------------------------

        /**
         * This method exists only for backward compatibility to prevent a fatal error from happening when called from an outdated piece of code.
         *
         * @param mixed $pPong
         *
         * @return bool
         */
        public static function Test( $pPong = null ) {
            return (
                is_object( $pPong ) &&
                isset( $pPong->api ) &&
                'pong' === $pPong->api
            );
        }

		/**
		 * Ping API to test connectivity.
		 *
		 * @return object
		 */
		public static function Ping() {
			try {
				$result = self::MakeStaticRequest( '/v' . FS_API__VERSION . '/ping.json' );
			} catch ( Freemius_Exception $e ) {
				// Map to error object.
				$result = (object) $e->getResult();
			} catch ( Exception $e ) {
				// Map to error object.
				$result = (object) array(
					'error' => (object) array(
						'type'    => 'Unknown',
						'message' => $e->getMessage() . ' (' . $e->getFile() . ': ' . $e->getLine() . ')',
						'code'    => 'unknown',
						'http'    => 402
					)
				);
			}

			return $result;
		}

		#endregion

		#----------------------------------------------------------------------------------
		#region Connectivity Exceptions
		#----------------------------------------------------------------------------------

		/**
		 * @param \WP_Error $pError
		 *
		 * @return bool
		 */
		private static function IsCurlError( WP_Error $pError ) {
			$message = $pError->get_error_message( 'http_request_failed' );

			return ( 0 === strpos( $message, 'cURL' ) );
		}

		/**
		 * @param WP_Error $pError
		 *
		 * @throws Freemius_Exception
		 */
		private static function ThrowWPRemoteException( WP_Error $pError ) {
			if ( self::IsCurlError( $pError ) ) {
				$message = $pError->get_error_message( 'http_request_failed' );

				#region Check if there are any missing cURL methods.

				$curl_required_methods = array(
					'curl_version',
					'curl_exec',
					'curl_init',
					'curl_close',
					'curl_setopt',
					'curl_setopt_array',
					'curl_error',
				);

				// Find all missing methods.
				$missing_methods = array();
				foreach ( $curl_required_methods as $m ) {
					if ( ! function_exists( $m ) ) {
						$missing_methods[] = $m;
					}
				}

				if ( ! empty( $missing_methods ) ) {
					throw new Freemius_Exception( array(
						'error'           => (object) array(
							'type'    => 'cUrlMissing',
							'message' => $message,
							'code'    => 'curl_missing',
							'http'    => 402
						),
						'missing_methods' => $missing_methods,
					) );
				}

				#endregion

				// cURL error - "cURL error {{errno}}: {{error}}".
				$parts = explode( ':', substr( $message, strlen( 'cURL error ' ) ), 2 );

				$code    = ( 0 < count( $parts ) ) ? $parts[0] : 'http_request_failed';
				$message = ( 1 < count( $parts ) ) ? $parts[1] : $message;

				$e = new Freemius_Exception( array(
					'error' => (object) array(
						'code'    => $code,
						'message' => $message,
						'type'    => 'CurlException',
					),
				) );
			} else {
				$e = new Freemius_Exception( array(
					'error' => (object) array(
						'code'    => $pError->get_error_code(),
						'message' => $pError->get_error_message(),
						'type'    => 'WPRemoteException',
					),
				) );
			}

			throw $e;
		}

		/**
		 * @param string $pResult
		 *
		 * @throws Freemius_Exception
		 */
		private static function ThrowCloudFlareDDoSException( $pResult = '' ) {
			throw new Freemius_Exception( array(
				'error' => (object) array(
					'type'    => 'CloudFlareDDoSProtection',
					'message' => $pResult,
					'code'    => 'cloudflare_ddos_protection',
					'http'    => 402
				)
			) );
		}

		/**
		 * @param string $pResult
		 *
		 * @throws Freemius_Exception
		 */
		private static function ThrowSquidAclException( $pResult = '' ) {
			throw new Freemius_Exception( array(
				'error' => (object) array(
					'type'    => 'SquidCacheBlock',
					'message' => $pResult,
					'code'    => 'squid_cache_block',
					'http'    => 402
				)
			) );
		}

		#endregion
	}
    }
