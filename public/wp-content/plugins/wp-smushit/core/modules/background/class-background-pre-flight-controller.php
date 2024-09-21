<?php

namespace Smush\Core\Modules\Background;

use Smush\Core\Array_Utils;
use Smush\Core\Controller;
use Smush\Core\Helper;

class Background_Pre_Flight_Controller extends Controller {
	const BACKGROUND_PRE_FLIGHT_OPTION = 'wp_smush_background_pre_flight';
	/**
	 * @var Array_Utils
	 */
	private $array_utils;

	/**
	 * Static instance
	 *
	 * @var self
	 */
	private static $instance;

	/**
	 * @var Loopback_Request_Tester
	 */
	private $loopback_tester;

	/**
	 * Static instance getter
	 */
	public static function get_instance() {
		if ( empty( self::$instance ) ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	private function __construct() {
		$this->array_utils = new Array_Utils();
		// the constructor for Loopback_Request_Tester needs to be called in all requests because it adds some ajax hooks
		$this->loopback_tester = new Loopback_Request_Tester();

		$this->register_action( 'wp_ajax_smush_start_background_pre_flight_check', array(
			$this,
			'start_pre_flight_check_ajax',
		) );
		$this->register_action( 'wp_ajax_smush_get_background_pre_flight_status', array(
			$this,
			'get_background_pre_flight_status_ajax',
		) );
		$this->register_action( 'wp_smush_refresh_cron_status', array( $this, 'set_cron_healthy' ) );
	}

	public function start_pre_flight_check_ajax() {
		check_ajax_referer( 'wp-smush-ajax' );

		if ( Helper::is_user_allowed() ) {
			$this->start_pre_flight_check();
			wp_send_json_success();
		} else {
			wp_send_json_error();
		}
	}

	public function get_background_pre_flight_status_ajax() {
		check_ajax_referer( 'wp-smush-ajax' );
		if ( Helper::is_user_allowed() && $this->is_test_performed() ) {
			wp_send_json_success( array(
				'cron'     => $this->is_cron_healthy(),
				'loopback' => $this->is_loopback_healthy(),
			) );
		} else {
			wp_send_json_error();
		}
	}

	private function start_pre_flight_check() {
		$this->reset_pre_flight_option();

		$this->loopback_tester->test();

		wp_schedule_single_event( time(), 'wp_smush_refresh_cron_status' );
	}

	public function set_cron_healthy() {
		$this->set_item_healthy( 'cron' );
	}

	public function is_cron_healthy() {
		return $this->is_item_healthy( 'cron' );
	}

	public function is_loopback_healthy() {
		return $this->is_item_healthy( 'loopback' );
	}

	public function set_loopback_healthy() {
		$this->set_item_healthy( 'loopback' );
	}

	public function set_item_healthy( $item ) {
		$background_pre_flight          = $this->get_pre_flight_option();
		$background_pre_flight[ $item ] = time();
		$this->update_pre_flight_option( $background_pre_flight );
	}

	private function is_item_healthy( $item ) {
		$background_pre_flight = $this->get_pre_flight_option();
		$item_timestamp        = (int) $this->array_utils->get_array_value( $background_pre_flight, $item );
		$cutoff                = time() - DAY_IN_SECONDS;
		return $item_timestamp > ( $cutoff );
	}

	private function reset_pre_flight_option() {
		delete_option( self::BACKGROUND_PRE_FLIGHT_OPTION );
		wp_cache_delete( self::BACKGROUND_PRE_FLIGHT_OPTION, 'options' );
	}

	private function is_test_performed() {
		return ! empty( $this->get_pre_flight_option() );
	}

	/**
	 * @return false|mixed|null
	 */
	private function get_pre_flight_option() {
		return get_option( self::BACKGROUND_PRE_FLIGHT_OPTION, array() );
	}

	/**
	 * @param $background_pre_flight
	 *
	 * @return void
	 */
	private function update_pre_flight_option( $background_pre_flight ) {
		update_option( self::BACKGROUND_PRE_FLIGHT_OPTION, $background_pre_flight, false );
	}
}
