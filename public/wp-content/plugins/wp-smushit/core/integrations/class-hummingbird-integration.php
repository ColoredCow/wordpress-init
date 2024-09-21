<?php

namespace Smush\Core\Integrations;

use Smush\Core\Controller;
use Smush\Core\Settings;
use Smush\Core\CDN\CDN_Helper;

class Hummingbird_Integration extends Controller {
	public function __construct() {
		$this->register_action( 'init', array( $this, 'ensure_hb_compatibility' ) );

		$this->register_filter( 'wphb_tracking_active_features', array( $this, 'get_smush_active_features' ) );
	}

	public function ensure_hb_compatibility() {
		// Doing this on init so the HB active check works
		if ( $this->is_hb_active() ) {
			add_action( 'wp_smush_clear_page_cache', array( $this, 'clear_cache' ) );
		}
	}

	private function is_hb_active() {
		return class_exists( '\\Hummingbird\\WP_Hummingbird' );
	}

	public function clear_cache() {
		// Clear HB page cache.
		do_action( 'wphb_clear_page_cache' );
	}

	public function get_smush_active_features( $active_features ) {
		$smush_settings        = Settings::get_instance();
		$lossy_level           = $smush_settings->get_lossy_level_setting();
		$cdn_module_activated  = CDN_Helper::get_instance()->is_cdn_active();
		$webp_module_activated = ! $cdn_module_activated && $smush_settings->is_webp_module_active();
		$webp_direct_activated = $webp_module_activated && $smush_settings->is_webp_direct_conversion_active();
		$webp_server_activated = $webp_module_activated && ! $webp_direct_activated;

		$smush_features = array(
			'smush_basic'       => Settings::LEVEL_LOSSLESS === $lossy_level,
			'smush_super'       => Settings::LEVEL_SUPER_LOSSY === $lossy_level,
			'smush_ultra'       => Settings::LEVEL_ULTRA_LOSSY === $lossy_level,
			'smush_lazy'        => $smush_settings->is_lazyload_active(),
			'smush_cdn'         => $cdn_module_activated,
			'smush_webp'        => $webp_module_activated,
			'smush_webp_direct' => $webp_direct_activated,
			'smush_webp_server' => $webp_server_activated,
		);

		$smush_active_features = array_keys( array_filter( $smush_features ) );
		$active_features       = array_merge( $active_features, $smush_active_features );

		return $active_features;
	}
}
