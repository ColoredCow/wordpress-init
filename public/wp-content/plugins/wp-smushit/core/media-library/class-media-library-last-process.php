<?php

namespace Smush\Core\Media_Library;

use Smush\Core\Array_Utils;
use Smush\Core\Controller;
use Smush\Core\Helper;
use Smush\Core\Modules\Background\Mutex;
use Smush\Core\Modules\Bulk\Background_Bulk_Smush;
use Smush\Core\Settings;

class Media_Library_Last_Process extends Controller {
	const PROCESS_KEY = 'wp_smush_media_library_last_process';
	const START_TIME = 'start_time';
	const END_TIME = 'end_time';
	const LAST_ATTACHMENT = 'last_attachment';
	const FIRST_STUCK_ATTACHMENT = 'first_stuck_attachment';
	const PROCESS_TIME_OUT = 120;// 2 mins.

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

	public static function get_instance() {
		if ( empty( self::$instance ) ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	public function __construct() {
		$this->array_utils             = new Array_Utils();
		$scan_background_process       = Background_Media_Library_Scanner::get_instance()->get_background_process();
		$bulk_smush_background_process = Background_Bulk_Smush::get_instance()->get_background_process();

		$this->register_action( $scan_background_process->action_name( 'started' ), array( $this, 'record_process_start_time' ), 5 );
		$this->register_action( $scan_background_process->action_name( 'dead' ), array( $this, 'record_process_end_time' ), 5 );
		$this->register_action( 'wp_smush_bulk_smush_start', array( $this, 'record_process_start_time' ), 5 );
		$this->register_action( 'wp_smush_bulk_smush_dead', array( $this, 'record_process_end_time' ), 5 );

		$this->register_action( $bulk_smush_background_process->action_name( 'cron' ), array( $this, 'check_bulk_smush_process' ), 5 );
		$this->register_action( 'wp_smush_before_smush_file', array( $this, 'record_bulk_smush_last_processed_attachment' ), 5 );
		$this->register_action( 'wp_ajax_bulk_smush_get_status', array( $this, 'check_bulk_smush_process_stuck_on_ajax_get_status' ), 5 );

		$this->register_action( 'wp_smush_after_smush_file', array( $this, 'record_last_processed_attachment_elapsed_time' ), 5 );
	}

	public function should_run() {
		return Settings::get_instance()->get( 'usage' );
	}

	private function get_ajax_nonce( $query_arg = '_ajax_nonce' ) {
		$nonce = '';
		if ( $query_arg && isset( $_REQUEST[ $query_arg ] ) ) {
			$nonce = wp_unslash( $_REQUEST[ $query_arg ] );
		} elseif ( isset( $_REQUEST['_ajax_nonce'] ) ) {
			$nonce = wp_unslash( $_REQUEST['_ajax_nonce'] );
		} elseif ( isset( $_REQUEST['_wpnonce'] ) ) {
			$nonce = wp_unslash( $_REQUEST['_wpnonce'] );
		}

		return $nonce;
	}


	public function record_bulk_smush_last_processed_attachment( $attachment_id ) {
		$this->set_last_processed_attachment( $attachment_id );
	}

	public function check_bulk_smush_process() {
		if ( $this->should_check_stuck() && $this->is_process_stuck() ) {
			$this->set_first_stuck_attachment();

			do_action( 'wp_smush_bulk_smush_stuck', $this );

			Helper::logger()->warning(
				sprintf(
					'The Bulk Smush process has been stuck for %1$s minutes at image %2$d ( %3$s minutes )',
					round( $this->get_seconds_since_last_image_processing_started() / 60, 2 ),
					$this->get_last_process_attachment_id(),
					round( $this->get_last_process_attachment_elapsed_time() / 60, 2 )
				)
			);
		}
	}

	private function should_check_stuck() {
		$first_stuck_attachment = $this->get_process_item( self::FIRST_STUCK_ATTACHMENT );
		return empty( $first_stuck_attachment );
	}


	public function check_bulk_smush_process_stuck_on_ajax_get_status() {
		$nonce = $this->get_ajax_nonce();

		// Check capability.
		if ( empty( $nonce ) || ! wp_verify_nonce( $nonce, 'wp-smush-ajax' ) || ! Helper::is_user_allowed( 'manage_options' ) ) {
			return;
		}

		$this->check_bulk_smush_process();
	}

	private function set_last_processed_attachment( $attachment_id ) {
		$this->set_process_item(
			self::LAST_ATTACHMENT,
			array(
				'id'         => $attachment_id,
				'start_time' => time(),
			)
		);
	}

	private function set_first_stuck_attachment() {
		$last_process_attachment                 = $this->get_last_processed_attachment();
		$last_process_attachment['elapsed_time'] = $this->get_seconds_since_last_image_processing_started();

		$this->set_process_item(
			self::FIRST_STUCK_ATTACHMENT,
			$last_process_attachment
		);
	}

	private function is_process_stuck() {
		$elapsed_time = $this->get_seconds_since_last_image_processing_started();

		return $elapsed_time > self::PROCESS_TIME_OUT;
	}

	public function record_last_processed_attachment_elapsed_time() {
		$last_process_attachment                            = $this->get_last_processed_attachment();
		$last_process_attachment['attachment_elapsed_time'] = $this->get_last_process_attachment_elapsed_time();

		$this->set_process_item( self::LAST_ATTACHMENT, $last_process_attachment );
	}

	public function get_last_process_attachment_elapsed_time() {
		$last_process_attachment = $this->get_last_processed_attachment();
		$attachment_elapsed_time = (int) $this->array_utils->get_array_value( $last_process_attachment, 'attachment_elapsed_time', - 1 );

		if ( $attachment_elapsed_time > - 1 ) {
			return $attachment_elapsed_time;
		}

		return $this->get_seconds_since_last_image_processing_started();
	}

	public function get_seconds_since_last_image_processing_started() {
		$last_process_attachment = $this->get_last_processed_attachment();
		$start_time              = (int) $this->array_utils->get_array_value( $last_process_attachment, 'start_time' );

		if ( empty( $start_time ) ) {
			return 0;
		}

		$end_time = time();

		return $end_time - $start_time;
	}

	public function get_last_process_attachment_id() {
		$last_process_attachment = $this->get_last_processed_attachment();

		return $this->array_utils->get_array_value( $last_process_attachment, 'id', 0 );
	}

	private function get_last_processed_attachment() {
		return $this->get_process_item( self::LAST_ATTACHMENT );
	}

	public function record_process_start_time() {
		$this->reset_process_option();
		$this->set_process_start_time();
	}

	public function record_process_end_time() {
		$this->set_process_end_time();
	}

	private function reset_process_option() {
		delete_option( self::PROCESS_KEY );
		wp_cache_delete( self::PROCESS_KEY, 'options' );
	}

	private function set_process_start_time() {
		$this->set_process_item( self::START_TIME, microtime( true ) );
	}

	private function set_process_end_time() {
		$this->set_process_item( self::END_TIME, microtime( true ) );
	}

	public function get_process_elapsed_time() {
		$start_time = $this->get_process_start_time();
		$end_time   = $this->get_process_end_time();

		return (int) ( $end_time - $start_time );
	}

	public function get_process_start_time() {
		return $this->get_process_item( self::START_TIME );
	}

	private function get_process_end_time() {
		return $this->get_process_item( self::END_TIME, time() );
	}

	private function get_process_item( $item, $default_value = false ) {
		$process_option = $this->get_process_option();

		return $this->array_utils->get_array_value( $process_option, $item, $default_value );
	}

	private function set_process_item( $item, $value ) {
		( new Mutex( self::PROCESS_KEY ) )->execute( function () use ( $item, $value ) {
			$process_option          = $this->get_process_option();
			$process_option[ $item ] = $value;
			$this->update_process_option( $process_option );
		} );
	}

	private function get_process_option() {
		$last_process = get_option( self::PROCESS_KEY, array() );

		return $this->array_utils->ensure_array( $last_process );
	}

	private function update_process_option( $last_process_option ) {
		update_option( self::PROCESS_KEY, $last_process_option, false );
	}
}
