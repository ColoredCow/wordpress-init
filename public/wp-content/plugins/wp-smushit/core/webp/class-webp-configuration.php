<?php

namespace Smush\Core\Webp;

use Smush\Core\Settings;

class Webp_Configuration {
	const DIRECT_CONVERSION_METHOD = 'direct_conversion';
	const HIDE_WIZARD_OPTION_KEY = 'wp-smush-webp_hide_wizard';

	/**
	 * @var self
	 */
	private static $instance;

	/**
	 * @var Settings
	 */
	private $settings;

	/**
	 * @var Webp_Server_Configuration
	 */
	private $server_configuration;

	/**
	 * @var Webp_Direct_Conversion
	 */
	private $direct_conversion;

	public function __construct() {
		$this->server_configuration = new Webp_Server_Configuration();
		$this->direct_conversion    = new Webp_Direct_Conversion();
		$this->settings             = Settings::get_instance();
	}

	public static function get_instance() {
		if ( ! self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	public function is_configured() {
		if ( ! $this->settings->is_webp_module_active() ) {
			return false;
		}

		return $this->direct_conversion_enabled() || $this->is_server_configured();
	}

	public function direct_conversion_enabled() {
		return $this->direct_conversion->is_enabled();
	}

	public function is_server_configured() {
		return $this->server_configuration->is_configured();
	}

	public function switch_method( $webp_method ) {
		$direct_conversion_activated = self::DIRECT_CONVERSION_METHOD === $webp_method;
		if ( $direct_conversion_activated ) {
			$this->server_configuration->disable();
			$this->direct_conversion->enable();
		} else {
			$this->direct_conversion->disable();
			$this->server_configuration->enable();
			if ( ! $this->should_show_wizard() ) {
				$this->toggle_wizard();
			}
		}

		do_action( 'wp_smush_webp_method_changed' );
	}

	public function toggle_module( $enable_webp ) {
		if ( $enable_webp ) {
			$this->activate();
		} else {
			$this->deactivate();
		}

		do_action( 'wp_smush_webp_status_changed' );
	}

	private function activate() {
		$this->settings->set( 'webp_mod', true );

		if ( $this->direct_conversion_enabled() ) {
			return;
		}

		// Try server configuation method first since it has proven to be faster.
		$this->server_configuration()->enable();

		if ( ! $this->is_configured() ) {
			$this->direct_conversion->enable();
			$this->server_configuration()->disable();
		}
	}

	private function deactivate() {
		$this->settings->set( 'webp_mod', false );
		// Required to add lock file for sever configuration.
		$this->server_configuration->disable();
	}

	public function server_configuration() {
		return $this->server_configuration;
	}

	public function toggle_wizard() {
		$is_hidden = get_site_option( self::HIDE_WIZARD_OPTION_KEY );
		update_site_option( self::HIDE_WIZARD_OPTION_KEY, ! $is_hidden );
	}

	public function should_show_wizard() {
		if ( ! $this->settings->is_webp_module_active() || $this->is_configured() ) {
			return false;
		}

		$hide_wizard = get_site_option( self::HIDE_WIZARD_OPTION_KEY );
		return ! $hide_wizard;
	}
}
