<?php

namespace Smush\Core\Modules;

use Smush\Core\CDN\CDN_Controller;
use Smush\Core\CDN\CDN_Helper;
use Smush\Core\CDN\CDN_Srcset_Controller;

if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Class CDN
 */
class CDN extends Abstract_Module {
	/**
	 * @var CDN_Helper
	 */
	private $cdn_helper;
	/**
	 * @var CDN_Controller
	 */
	private $cdn_controller;
	private $srcset_controller;

	public function __construct() {
		parent::__construct();

		$this->cdn_helper        = CDN_Helper::get_instance();
		$this->cdn_controller    = CDN_Controller::get_instance();
		$this->srcset_controller = CDN_Srcset_Controller::get_instance();
	}

	public function __call( $method_name, $arguments ) {
		_deprecated_function( $method_name, '3.16.0' );
	}

	private function deprecated( $method, $replacement = '' ) {
		_deprecated_function( __CLASS__ . "::$method", '3.16.0', $replacement );
	}

	public function get_status() {
		$this->deprecated( __METHOD__, 'CDN_Helper::is_cdn_active' );

		return $this->cdn_helper->is_cdn_active();
	}

	public function status() {
		$this->deprecated( __METHOD__, 'CDN_Helper::get_cdn_status_string' );

		return $this->cdn_helper->get_cdn_status_string();
	}

	public function generate_cdn_url( $src, $args = array() ) {
		$this->deprecated( __METHOD__, 'CDN_Helper::generate_cdn_url' );

		return $this->cdn_helper->generate_cdn_url( $src, $args );
	}

	public function toggle_cdn( $enable ) {
		$this->deprecated( __METHOD__, 'CDN_Controller::toggle_cdn' );

		$this->cdn_controller->toggle_cdn( $enable );
	}

	public function update_image_srcset( $sources, $size_array, $image_src, $image_meta, $attachment_id = 0 ) {
		$this->deprecated( __METHOD__, 'CDN_Srcset_Controller::update_image_srcset' );

		return $this->srcset_controller->update_image_srcset( $sources, $size_array, $image_src, $image_meta, $attachment_id );
	}

	public function update_image_sizes( $sizes, $size ) {
		$this->deprecated( __METHOD__, 'CDN_Srcset_Controller::update_image_sizes' );

		return $this->srcset_controller->update_image_sizes( $sizes, $size );
	}

	public function update_cdn_image_src_args( $args, $image ) {
		$this->deprecated( __METHOD__, 'CDN_Srcset_Controller::update_cdn_image_src_args' );

		return $this->srcset_controller->update_cdn_image_src_args( $args, $image );
	}

	public static function unschedule_cron() {
		_deprecated_function( __CLASS__ . "::unschedule_cron", '3.16.0' );
	}

	public function is_supported_path( $src ) {
		$this->deprecated( __METHOD__, 'CDN_Helper::is_supported_path' );

		return $this->cdn_helper->is_supported_url( $src );
	}
}
