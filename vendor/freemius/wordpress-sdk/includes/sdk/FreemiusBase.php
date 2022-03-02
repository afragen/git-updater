<?php
	/**
	 * Copyright 2014 Freemius, Inc.
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

	if ( ! defined( 'FS_API__VERSION' ) ) {
		define( 'FS_API__VERSION', '1' );
	}
	if ( ! defined( 'FS_SDK__PATH' ) ) {
		define( 'FS_SDK__PATH', dirname( __FILE__ ) );
	}
	if ( ! defined( 'FS_SDK__EXCEPTIONS_PATH' ) ) {
		define( 'FS_SDK__EXCEPTIONS_PATH', FS_SDK__PATH . '/Exceptions/' );
	}

	if ( ! function_exists( 'json_decode' ) ) {
		throw new Exception( 'Freemius needs the JSON PHP extension.' );
	}

	// Include all exception files.
	$exceptions = array(
		'Exception',
		'InvalidArgumentException',
		'ArgumentNotExistException',
		'EmptyArgumentException',
		'OAuthException'
	);

	foreach ( $exceptions as $e ) {
		require_once FS_SDK__EXCEPTIONS_PATH . $e . '.php';
	}

	if ( class_exists( 'Freemius_Api_Base' ) ) {
		return;
	}

	abstract class Freemius_Api_Base {
		const VERSION = '1.0.4';
		const FORMAT = 'json';

		protected $_id;
		protected $_public;
		protected $_secret;
		protected $_scope;
		protected $_isSandbox;

		/**
		 * @param string $pScope     'app', 'developer', 'plugin', 'user' or 'install'.
		 * @param number $pID        Element's id.
		 * @param string $pPublic    Public key.
		 * @param string $pSecret    Element's secret key.
		 * @param bool   $pIsSandbox Whether or not to run API in sandbox mode.
		 */
		public function Init( $pScope, $pID, $pPublic, $pSecret, $pIsSandbox = false ) {
			$this->_id        = $pID;
			$this->_public    = $pPublic;
			$this->_secret    = $pSecret;
			$this->_scope     = $pScope;
			$this->_isSandbox = $pIsSandbox;
		}

		public function IsSandbox() {
			return $this->_isSandbox;
		}

		function CanonizePath( $pPath ) {
			$pPath     = trim( $pPath, '/' );
			$query_pos = strpos( $pPath, '?' );
			$query     = '';

			if ( false !== $query_pos ) {
				$query = substr( $pPath, $query_pos );
				$pPath = substr( $pPath, 0, $query_pos );
			}

			// Trim '.json' suffix.
			$format_length = strlen( '.' . self::FORMAT );
			$start         = $format_length * ( - 1 ); //negative
			if ( substr( strtolower( $pPath ), $start ) === ( '.' . self::FORMAT ) ) {
				$pPath = substr( $pPath, 0, strlen( $pPath ) - $format_length );
			}

			switch ( $this->_scope ) {
				case 'app':
					$base = '/apps/' . $this->_id;
					break;
				case 'developer':
					$base = '/developers/' . $this->_id;
					break;
				case 'user':
					$base = '/users/' . $this->_id;
					break;
				case 'plugin':
					$base = '/plugins/' . $this->_id;
					break;
				case 'install':
					$base = '/installs/' . $this->_id;
					break;
				default:
					throw new Freemius_Exception( 'Scope not implemented.' );
			}

			return '/v' . FS_API__VERSION . $base .
			       ( ! empty( $pPath ) ? '/' : '' ) . $pPath .
			       ( ( false === strpos( $pPath, '.' ) ) ? '.' . self::FORMAT : '' ) . $query;
		}

		abstract function MakeRequest( $pCanonizedPath, $pMethod = 'GET', $pParams = array() );

		/**
		 * @param string $pPath
		 * @param string $pMethod
		 * @param array  $pParams
		 *
		 * @return object[]|object|null
		 */
		private function _Api( $pPath, $pMethod = 'GET', $pParams = array() ) {
			$pMethod = strtoupper( $pMethod );

			try {
				$result = $this->MakeRequest( $pPath, $pMethod, $pParams );
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

		public function Api( $pPath, $pMethod = 'GET', $pParams = array() ) {
			return $this->_Api( $this->CanonizePath( $pPath ), $pMethod, $pParams );
		}

		/**
		 * Base64 decoding that does not need to be urldecode()-ed.
		 *
		 * Exactly the same as PHP base64 encode except it uses
		 *   `-` instead of `+`
		 *   `_` instead of `/`
		 *   No padded =
		 *
		 * @param string $input Base64UrlEncoded() string
		 *
		 * @return string
		 */
		protected static function Base64UrlDecode( $input ) {
			/**
			 * IMPORTANT NOTE:
			 * This is a hack suggested by @otto42 and @greenshady from
			 * the theme's review team. The usage of base64 for API
			 * signature encoding was approved in a Slack meeting
			 * held on Tue (10/25 2016).
			 *
			 * @todo Remove this hack once the base64 error is removed from the Theme Check.
			 *
			 * @since 1.2.2
			 * @author Vova Feldman (@svovaf)
			 */
			$fn = 'base64' . '_decode';
			return $fn( strtr( $input, '-_', '+/' ) );
		}

		/**
		 * Base64 encoding that does not need to be urlencode()ed.
		 *
		 * Exactly the same as base64 encode except it uses
		 *   `-` instead of `+
		 *   `_` instead of `/`
		 *
		 * @param string $input string
		 *
		 * @return string Base64 encoded string
		 */
		protected static function Base64UrlEncode( $input ) {
			/**
			 * IMPORTANT NOTE:
			 * This is a hack suggested by @otto42 and @greenshady from
			 * the theme's review team. The usage of base64 for API
			 * signature encoding was approved in a Slack meeting
			 * held on Tue (10/25 2016).
			 *
			 * @todo Remove this hack once the base64 error is removed from the Theme Check.
			 *
			 * @since 1.2.2
			 * @author Vova Feldman (@svovaf)
			 */
			$fn = 'base64' . '_encode';
			$str = strtr( $fn( $input ), '+/', '-_' );
			$str = str_replace( '=', '', $str );

			return $str;
		}
	}
