<?php
/**
 * Integration with Envira Gallery
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
 * Class Envira
 */
class Envira extends Controller {
	/**
	 * Envira constructor.
	 *
	 * @since 3.3.0
	 */
	public function __construct() {

		$this->register_filter( 'wp_smush_get_image_attribute_names', array( $this, 'allow_envira_image_attributes_to_convert' ) );
		$this->register_filter( 'smush_skip_adding_srcset', array( $this, 'maybe_skip_generating_srcset' ), 10, 3 );
		$this->register_filter( 'wp_smush_lazyload_excluded_attributes', array( $this, 'skip_envira_image_lazyload_attribute_from_lazy_loading' ) );
	}

	public function should_run() {
		return class_exists( 'Envira_Gallery' ) || class_exists( 'Envira_Gallery_Lite' );
	}


	public function allow_envira_image_attributes_to_convert( $attribute_names ) {
		$attribute_names[] = 'data-envira-src';
		$attribute_names[] = 'data-envira-srcset';

		return $attribute_names;
	}

	public function maybe_skip_generating_srcset( $skip, $src_url, $element_markup ) {
		return $skip || strpos( $element_markup, 'data-envira-srcset' );
	}

	public function skip_envira_image_lazyload_attribute_from_lazy_loading( $attribute_names ) {
		$attribute_names[] = 'envira-lazy';

		return $attribute_names;
	}
}
