<?php

namespace Smush\Core\Integrations;

use Smush\Core\Controller;

class AMP_Integration extends Controller {
	public function __construct() {
		$this->register_action( 'wp_smush_should_skip_lazy_load', array( $this, 'skip_for_amp_pages' ) );
	}

	public function skip_for_amp_pages( $skip ) {
		return $this->is_amp_endpoint() ? true : $skip;
	}

	public function is_amp_endpoint() {
		return function_exists( 'is_amp_endpoint' ) && is_amp_endpoint();
	}
}
