<?php

namespace Smush\Core;

class Server_Utils {
	const FIREFOX_AGENT = '#Firefox/(?<version>[0-9]{2,})#i';
	const IPAD_IPHONE_AGENT = '#(?:iPad|iPhone)(.*)Version/(?<version>[0-9]{2,})#i';
	const SAFARI_AGENT = '#Version/(?<version>[0-9]{2,})(?:.*)Safari#i';
	const MSIE_TRIDENT = '/MSIE|Trident/i';
	/**
	 * @var string
	 */
	private $mysql_version;

	private $browser_webp_support = array(
		self::FIREFOX_AGENT     => array( 'version' => 66, 'operator' => '>' ),
		self::IPAD_IPHONE_AGENT => array( 'version' => 14, 'operator' => '>=' ),
		self::SAFARI_AGENT      => array( 'version' => 14, 'operator' => '>=' ),
		self::MSIE_TRIDENT      => false,
	);

	public function get_server_type() {
		if ( empty( $_SERVER['SERVER_SOFTWARE'] ) ) {
			return '';
		}

		$server_software = wp_unslash( $_SERVER['SERVER_SOFTWARE'] );
		if ( ! is_array( $server_software ) ) {
			$server_software = array( $server_software );
		}

		$server_software = array_map( 'strtolower', $server_software );
		$is_nginx        = $this->array_has_needle( $server_software, 'nginx' );
		if ( $is_nginx ) {
			return 'nginx';
		}

		$is_apache = $this->array_has_needle( $server_software, 'apache' );
		if ( $is_apache ) {
			return 'apache';
		}

		return '';
	}

	public function get_memory_limit() {
		if ( function_exists( 'ini_get' ) ) {
			$memory_limit = ini_get( 'memory_limit' );
		} else {
			// Sensible default.
			$memory_limit = '128M';
		}

		if ( ! $memory_limit || - 1 === (int) $memory_limit ) {
			// Unlimited, set to 32GB.
			$memory_limit = '32000M';
		}

		return intval( $memory_limit ) * 1024 * 1024;
	}

	public function get_memory_usage() {
		return memory_get_usage( true );
	}

	private function array_has_needle( $array, $needle ) {
		foreach ( $array as $item ) {
			if ( strpos( $item, $needle ) !== false ) {
				return true;
			}
		}

		return false;
	}

	public function get_mysql_version() {
		if ( ! $this->mysql_version ) {
			global $wpdb;
			/**
			 * MariaDB version prefix 5.5.5- is not stripped when using $wpdb->db_version() to get the DB version:
			 * https://github.com/php/php-src/issues/7972
			 */
			$this->mysql_version = $wpdb->get_var( 'SELECT VERSION()' );
		}
		return $this->mysql_version;
	}

	public function get_max_execution_time() {
		return (int) ini_get( 'max_execution_time' );
	}

	public function get_user_agent() {
		return ! empty( $_SERVER['HTTP_USER_AGENT'] ) ? wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) : '';
	}

	public function get_document_root() {
		return ! empty( $_SERVER['DOCUMENT_ROOT'] ) ? wp_unslash( $_SERVER['DOCUMENT_ROOT'] ) : '';
	}

	public function get_http_accept_header() {
		if ( ! empty( $_SERVER['HTTP_ACCEPT'] ) ) {
			return wp_unslash( $_SERVER['HTTP_ACCEPT'] );
		}

		if ( function_exists( 'apache_request_headers' ) ) {
			$headers = apache_request_headers();
			if ( ! empty( $headers['Accept'] ) ) {
				return $headers['Accept'];
			}
		}

		return '';
	}

	public function browser_supports_webp() {
		$http_accept = $this->get_http_accept_header();
		if ( ! empty( $http_accept ) && false !== strpos( $http_accept, 'webp' ) ) {
			return true;
		}

		return $this->check_user_agent_version( $this->browser_webp_support );
	}

	private function check_user_agent_version( $allowed, $default = false ) {
		$user_agent = $this->get_user_agent();

		foreach ( $allowed as $user_agenet_regex => $data ) {
			$version  = isset( $data['version'] ) ? $data['version'] : 0;
			$operator = isset( $data['operator'] ) ? $data['operator'] : '';

			$matches = array();
			if ( preg_match( $user_agenet_regex, $user_agent, $matches ) ) {
				if ( $version && $operator && $matches['version'] ) {
					return version_compare( (int) $matches['version'], $version, $operator );
				} else {
					return $data;
				}
			}
		}

		return $default;
	}

	public function get_request_uri() {
		return rawurldecode( sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) ) );
	}

	public function get_current_url() {
		$protocol = is_ssl() ? 'https:' : 'http:';
		$domain   = parse_url( site_url(), PHP_URL_HOST );
		$path     = parse_url( $this->get_request_uri(), PHP_URL_PATH );

		return $protocol . '//' . $domain . $path;
	}

	public function get_request_method() {
		if ( empty( $_SERVER['REQUEST_METHOD'] ) ) {
			return '';
		}
		return strtoupper( sanitize_text_field( wp_unslash( $_SERVER['REQUEST_METHOD'] ) ) );
	}
}
