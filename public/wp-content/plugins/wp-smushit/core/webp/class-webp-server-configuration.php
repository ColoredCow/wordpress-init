<?php

namespace Smush\Core\Webp;

use Smush\Core\File_System;
use Smush\Core\Helper;
use Smush\Core\Server_Utils;

class Webp_Server_Configuration {
	/**
	 * @var File_System
	 */
	private $fs;

	/**
	 * @var Webp_Dir
	 */
	private $webp_dir;

	/**
	 * @var Server_Utils
	 */
	private $server_utils;

	/**
	 * @var array
	 */
	private $configuration_status;

	/**
	 * @var Webp_Nginx
	 */
	private $nginx;

	/**
	 * @var Webp_Apache
	 */
	private $apache;

	public function __construct() {
		$this->fs           = new File_System();
		$this->webp_dir     = new Webp_Dir();
		$this->server_utils = new Server_Utils();
		$this->nginx        = new Webp_Nginx();
		$this->apache       = new Webp_Apache();
	}

	public function enable() {
		$this->remove_lock_file();
	}

	public function disable() {
		$this->add_lock_file();
	}

	public function is_configured() {
		$status = $this->get_configuration_status();
		return ! empty( $status['success'] );
	}

	public function get_configuration_message() {
		$configuration_status = $this->get_configuration_status();
		return isset( $configuration_status['message'] ) ? $configuration_status['message'] : '';
	}

	public function get_configuration_error_code() {
		$configuration_status = $this->get_configuration_status();
		return isset( $configuration_status['error_code'] ) ? $configuration_status['error_code'] : '';
	}

	private function get_configuration_status() {
		if ( ! $this->configuration_status ) {
			$this->configuration_status = $this->recheck_server_configuration_status();
		}

		return $this->configuration_status;
	}

	private function reset_configuration_status() {
		$this->configuration_status = null;
	}

	private function recheck_server_configuration_status() {
		$test_png_file_prepared = $this->prepare_test_png_file();
		if ( is_wp_error( $test_png_file_prepared ) ) {
			return array(
				'success'    => false,
				'message'    => $test_png_file_prepared->get_error_message(),
				'error_code' => $test_png_file_prepared->get_error_code(),
			);
		}

		$test_webp_file_prepared = $this->prepare_test_webp_file();
		if ( is_wp_error( $test_webp_file_prepared ) ) {
			return array(
				'success'    => false,
				'message'    => $test_webp_file_prepared->get_error_message(),
				'error_code' => $test_webp_file_prepared->get_error_code(),
			);
		}
		$config_status    = $this->check_server_config();
		$code             = wp_remote_retrieve_response_code( $config_status );
		$content_type     = wp_remote_retrieve_header( $config_status, 'content-type' );
		$has_error        = 200 !== $code;
		$is_rules_applied = 'apache' === $this->get_server_type() && $this->apache->is_htaccess_written();
		$success          = 'image/webp' === $content_type;
		$error_code       = '';

		if ( $has_error ) {
			$success    = false;
			$error_code = 'hosting_error';
			$message    = is_wp_error( $config_status ) ?
				$config_status->get_error_message() :
				sprintf(
				/* translators: 1. error code, 2. error message. */
					__( "We couldn't check the WebP server rules status because there was an error with the test request. Please contact support for assistance. Code %1\$s: %2\$s.", 'wp-smushit' ),
					$code,
					wp_remote_retrieve_response_message( $config_status )
				);
		} else {
			if ( $success ) {
				$message = __( 'The images are served in WebP format.', 'wp-smushit' );
			} else {
				$error_code = $is_rules_applied ? 'htaccess_error' : 'config_pending';
				$message    = $is_rules_applied ?
					__( 'The rules have been applied, but the images are still not being served in WebP format. We recommend that you contact your hosting provider to learn more about the cause of this problem.', 'wp-smushit' ) :
					__( "Server configurations haven't been applied yet. Configure now to start serving images in WebP format.", 'wp-smushit' );
			}
		}

		return array(
			'success'    => $success,
			'message'    => $message,
			'error_code' => $error_code,
		);
	}

	private function check_server_config() {
		$test_image = $this->get_test_png_file_url();

		$args = array(
			'timeout' => 10,
			'headers' => array(
				'Accept' => 'image/webp',
			),
		);

		// Add support for basic auth in WPMU DEV staging.
		if ( isset( $_SERVER['PHP_AUTH_USER'], $_SERVER['PHP_AUTH_PW'] ) ) {
			$args['headers']['Authorization'] = 'Basic ' . base64_encode( wp_unslash( $_SERVER['PHP_AUTH_USER'] ) . ':' . wp_unslash( $_SERVER['PHP_AUTH_PW'] ) );
		}

		return wp_remote_get( $test_image, $args );
	}

	private function prepare_test_png_file() {
		$test_png_file = $this->get_test_png_file_path();

		// Create the png file to be requested if it doesn't exist. Bail out if it fails.
		$created_test_file = file_exists( $test_png_file ) || copy( WP_SMUSH_DIR . 'app/assets/images/smush-webp-test.png', $test_png_file );
		if ( ! $created_test_file ) {
			$uploads_path = $this->webp_dir->get_upload_path();

			$error_message = sprintf(
			/* translators: path that couldn't be written */
				__( 'We couldn\'t create the WebP test files. This is probably due to your current folder permissions. Please adjust the permissions for "%s" to 755 and try again.', 'wp-smushit' ),
				$uploads_path
			);

			return new \WP_Error( 'cannot_create_test_png_file', $error_message );
		}

		return true;
	}

	private function prepare_test_webp_file() {
		$test_webp_file = $this->get_test_webp_file_path();
		if ( file_exists( $test_webp_file ) ) {
			return true;
		}

		$webp_path         = $this->webp_dir->get_webp_path();
		$directory_created = $this->webp_directory_created();

		if ( $directory_created ) {
			$created_test_file = copy( WP_SMUSH_DIR . 'app/assets/images/smush-webp-test.png.webp', $test_webp_file );

			if ( $created_test_file ) {
				return true;
			}

			$error_message = sprintf(
			/* translators: path that couldn't be written */
				__( 'We couldn\'t create the WebP test files. This is probably due to your current folder permissions. Please adjust the permissions for "%s" to 755 and try again.', 'wp-smushit' ),
				$webp_path
			);

		} else {
			$error_message = sprintf(
			/* translators: path that couldn't be written */
				__( 'We couldn\'t create the WebP directory "%s". This is probably due to your current folder permissions. You can also try to create this directory manually and try again.', 'wp-smushit' ),
				$webp_path
			);
		}

		return new \WP_Error( 'cannot_create_test_webp_file', $error_message );
	}

	private function webp_directory_created() {
		$webp_path = $this->webp_dir->get_webp_path();
		return is_dir( $webp_path ) || wp_mkdir_p( $webp_path );
	}

	private function get_test_png_file_path() {
		$uploads_path = $this->webp_dir->get_upload_path();
		return trailingslashit( $uploads_path ) . 'smush-webp-test.png';
	}

	private function get_test_png_file_url() {
		$uploads_url = $this->webp_dir->get_upload_url();
		return trailingslashit( $uploads_url ) . 'smush-webp-test.png';
	}

	private function get_test_webp_file_path() {
		$webp_path = $this->webp_dir->get_webp_path();
		return trailingslashit( $webp_path ) . 'smush-webp-test.png.webp';
	}

	private function add_lock_file() {
		if ( $this->webp_directory_created() ) {
			$lock_file_path = $this->get_lock_file_path();
			$this->fs->get_wp_filesystem()->put_contents( $lock_file_path, '' );
		}
	}

	private function remove_lock_file() {
		$lock_file_path = $this->get_lock_file_path();
		$this->fs->get_wp_filesystem()->delete( $lock_file_path, true );
	}

	private function get_lock_file_path() {
		$webp_path = $this->webp_dir->get_webp_path();
		return trailingslashit( $webp_path ) . 'disable_smush_webp';
	}

	public function get_nginx_code() {
		return $this->nginx->get_rewrite_rules();
	}

	public function get_apache_code() {
		return $this->apache->get_rewrite_rules();
	}

	public function apply_apache_rewrite_rules() {
		$cannot_write_message = __( 'Automatic updation of .htaccess rules failed. Please ensure the file permissions on your .htaccess file are set to 644, or switch to manual mode to add the rules yourself.', 'wp-smushit' );
		$last_error           = __( 'The rules have been applied, but the images are still not being served in WebP format. We recommend that you contact your hosting provider to learn more about the cause of this problem.', 'wp-smushit' );

		$locations = $this->apache->get_htaccess_locations();

		foreach ( $locations as $location ) {
			if ( ! $this->apache->save_htaccess( $location ) ) {
				$last_error = $cannot_write_message;
				continue;
			}

			$this->reset_configuration_status();
			if ( $this->is_configured() ) {
				$last_error = null;
			} else {
				// TODO: if $is_configured is a wp error, display the message.
				$last_error = $this->get_configuration_message();
				if ( ! empty( $last_error ) ) {
					$this->logger()->error( sprintf( 'Server config error: %s.', $last_error ) );
				}

				$this->apache->unsave_htaccess( $location );
			}
		}

		return $last_error;
	}

	public function get_server_type() {
		return $this->server_utils->get_server_type();
	}

	private function logger() {
		// Logger is a dynamic object, we will switch to another log file when point to another module,
		// so keep it as a function instead of a fixed variable to log into correct log file.
		return Helper::logger()->webp();
	}
}
