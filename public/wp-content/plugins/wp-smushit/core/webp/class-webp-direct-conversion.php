<?php
namespace Smush\Core\Webp;

use Smush\Core\Settings;

class Webp_Direct_Conversion {

	/**
	 * @var Settings
	 */
	private $settings;

	public function __construct() {
		$this->settings = Settings::get_instance();
	}

	public function enable() {
		$this->settings->set( 'webp_direct_conversion', true );
	}

	public function disable() {
		$this->settings->set( 'webp_direct_conversion', false );
	}

	public function is_enabled() {
		return $this->settings->is_webp_direct_conversion_active();
	}
}
