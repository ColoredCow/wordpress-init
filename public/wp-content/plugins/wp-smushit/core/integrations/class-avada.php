<?php
/**
 * Avada integration module.
 *
 * @since 3.3.0
 * @package Smush\Core\Integrations
 */

namespace Smush\Core\Integrations;

use Smush\Core\Controller;

if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Class Avada
 */
class Avada extends Controller {
	public function __construct() {
		$this->register_filter( 'wp_smush_get_image_attribute_names', array( $this, 'maybe_allow_avada_image_attributes_to_convert' ) );
		$this->register_filter( 'wp_smush_should_skip_lazy_load', array( $this, 'maybe_skip_lazyload' ) );
		// TODO: Add conflict notice with Avada theme.
	}

	public function maybe_allow_avada_image_attributes_to_convert( $attribute_names ) {
		if ( $this->is_avada_active() ) {
			$attribute_names[] = 'data-orig-src';
			$attribute_names[] = 'data-bg-url';
		}

		return $attribute_names;
	}

	public function maybe_skip_lazyload( $skip ) {
		return $skip || $this->avada_lazyload_active();
	}

	private function avada_lazyload_active() {
		if (
			$this->is_avada_active() &&
			class_exists( 'Fusion' ) &&
			is_callable( array( \Fusion::get_instance(), 'get_images_obj' ) )
		) {
			$fussion_image_obj = \Fusion::get_instance()->get_images_obj();
			return ! empty( $fussion_image_obj::$is_avada_lazy_load_images );
		}

		return false;
	}

	/**
	 * Avada is a them so we cannot use this method as should_run.
	 */
	private function is_avada_active() {
		return defined( 'AVADA_VERSION' ) && AVADA_VERSION || defined( 'FUSION_BUILDER_VERSION' ) && FUSION_BUILDER_VERSION;
	}
}
