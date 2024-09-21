<?php

namespace Smush\Core\CDN;

use Smush\Core\Controller;
use Smush\Core\Helper;
use Smush\Core\Settings;
use WP_Error;
use WP_Smush;

class CDN_Controller extends Controller {
	const CDN_TRANSFORM_PRIORITY = 10;
	/**
	 * @var CDN_Helper
	 */
	private $cdn_helper;
	/**
	 * @var Settings|null
	 */
	private $settings;
	/**
	 * Static instance
	 *
	 * @var self
	 */
	private static $instance;

	public function __construct() {
		$this->cdn_helper = CDN_Helper::get_instance();
		$this->settings   = Settings::get_instance();

		$this->register_filter( 'wp_smush_content_transforms', array(
			$this,
			'register_cdn_transform',
		), self::CDN_TRANSFORM_PRIORITY );
		$this->register_action( 'wp_ajax_get_cdn_stats', array( $this, 'ajax_update_stats' ) );
		$this->register_action( 'smush_update_cdn_stats', array( $this, 'cron_update_stats' ) );
		$this->register_action( 'wp_ajax_smush_toggle_cdn', array( $this, 'ajax_toggle_cdn' ) );
		$this->register_filter( 'wp_resource_hints', array( $this, 'dns_prefetch' ), 99, 2 );

		if ( $this->cdn_helper->is_cdn_active() ) {
			$this->register_action( 'admin_init', array( $this, 'schedule_cron' ) );
		}
	}

	/**
	 * Static instance getter
	 */
	public static function get_instance() {
		if ( empty( self::$instance ) ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	public function ajax_update_stats() {
		$status = $this->cdn_helper->get_cdn_status_setting();
		$smush  = WP_Smush::get_instance();
		if ( isset( $status->cdn_enabling ) && $status->cdn_enabling ) {
			$new_status = $this->process_cdn_status_response( $smush->api()->enable() );

			if ( is_wp_error( $new_status ) ) {
				$code = is_numeric( $new_status->get_error_code() ) ? $new_status->get_error_code() : null;
				wp_send_json_error( array(
					'message' => $new_status->get_error_message(),
				), $code );
			} else {
				$this->settings->set_setting( 'wp-smush-cdn_status', $new_status );
				wp_send_json_success( $new_status );
			}
		} else {
			wp_send_json_success( $status );
		}
	}

	public function cron_update_stats() {
		$status           = $this->cdn_helper->get_cdn_status_setting();
		$smush            = WP_Smush::get_instance();
		$cdn_enabling     = isset( $status->cdn_enabling ) && $status->cdn_enabling;
		$raw_api_response = $cdn_enabling ? $smush->api()->enable() : $smush->api()->check();
		$new_status       = $this->process_cdn_status_response( $raw_api_response );

		if ( $new_status && ! is_wp_error( $new_status ) ) {
			$this->settings->set_setting( 'wp-smush-cdn_status', $new_status );
		}
	}

	public function process_cdn_status_response( $status ) {
		if ( is_wp_error( $status ) ) {
			return $status;
		}

		$status = json_decode( $status['body'] );

		// Too many requests.
		if ( is_null( $status ) ) {
			return new WP_Error( 'too_many_requests', __( 'Too many requests, please try again in a moment.', 'wp-smushit' ) );
		}

		// Some other error from API.
		if ( ! $status->success ) {
			return new WP_Error( $status->data->error_code, $status->data->message );
		}

		return $status->data;
	}

	public function schedule_cron() {
		if ( ! wp_next_scheduled( 'smush_update_cdn_stats' ) ) {
			// Schedule first run for next day, as we've already checked just now.
			wp_schedule_event( time() + DAY_IN_SECONDS, 'daily', 'smush_update_cdn_stats' );
		}
	}

	public static function unschedule_cron() {
		$timestamp = wp_next_scheduled( 'smush_update_cdn_stats' );
		wp_unschedule_event( $timestamp, 'smush_update_cdn_stats' );
	}

	public function toggle_cdn( $enable ) {
		$this->settings->set( 'cdn', $enable );

		if ( $enable ) {
			$smush  = WP_Smush::get_instance();
			$status = $this->cdn_helper->get_cdn_status_setting();
			if ( ! $status ) {
				$check_response = $this->process_cdn_status_response( $smush->api()->check() );
				if ( is_wp_error( $check_response ) ) {
					return $check_response;
				}

				$this->settings->set_setting( 'wp-smush-cdn_status', $check_response );
			} elseif ( empty( $status->endpoint_url ) ) {
				$enable_response = $this->process_cdn_status_response( $smush->api()->enable( true ) );
				if ( is_wp_error( $enable_response ) ) {
					return $enable_response;
				}

				$this->settings->set_setting( 'wp-smush-cdn_status', $enable_response );
			}

			$this->schedule_cron();
		} else {
			// Remove CDN settings if disabling.
			$this->settings->delete_setting( 'wp-smush-cdn_status' );

			self::unschedule_cron();
		}

		do_action( 'wp_smush_cdn_status_changed' );

		return true;
	}

	public function ajax_toggle_cdn() {
		check_ajax_referer( 'save_wp_smush_options' );

		if ( ! Helper::is_user_allowed() ) {
			wp_send_json_error( array(
				'message' => __( 'User can not modify options', 'wp-smushit' ),
			), 403 );
		}

		$enable  = filter_input( INPUT_POST, 'param', FILTER_VALIDATE_BOOLEAN );
		$toggled = $this->toggle_cdn( $enable );

		if ( is_wp_error( $toggled ) ) {
			wp_send_json_error( array(
				'message' => $toggled->get_error_message(),
			) );
		}

		wp_send_json_success();
	}

	public function register_cdn_transform( $transforms ) {
		$transforms['cdn'] = new CDN_Transform();

		return $transforms;
	}

	/**
	 * Add CDN url to header for better speed.
	 *
	 * @param array $urls URLs to print for resource hints.
	 * @param string $relation_type The relation type the URLs are printed.
	 *
	 * @return array
	 * @since 3.0
	 *
	 */
	public function dns_prefetch( $urls, $relation_type ) {
		// Add only if CDN active.
		if ( 'dns-prefetch' === $relation_type && $this->cdn_helper->is_cdn_active() && ! empty( $this->cdn_helper->get_cdn_base_url() ) ) {
			$urls[] = $this->cdn_helper->get_cdn_base_url();
		}

		return $urls;
	}
}
