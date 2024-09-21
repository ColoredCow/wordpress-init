<?php

namespace Smush\Core\Integrations;

use Smush\Core\Controller;

/**
 * Essential_Grid_Integration
 *
 * Note: If we disable lazyload from this plugin, this plugin will add data-no-lazy attribute,
 * and it still hide the image and use js to copy the source into div element instead
 * so we will not support lazyload for this case.
 */
class Essential_Grid_Integration extends Controller {
	public function __construct() {
		$this->register_filter( 'wp_smush_get_image_attribute_names', array( $this, 'allow_essential_grid_image_attributes_to_convert' ) );
	}

	public function should_run() {
		return class_exists( 'Essential_Grid' );
	}

	public function allow_essential_grid_image_attributes_to_convert( $attribute_names ) {
		$attribute_names[] = 'data-lazythumb';
		$attribute_names[] = 'data-lazysrc';
		$attribute_names[] = 'data-orig-src';

		return $attribute_names;
	}
}
