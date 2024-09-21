<?php

namespace Smush\Core\Modules;

use Smush\Core\Lazy_Load\Lazy_Load_Controller;

class Lazy extends Abstract_Module {
	protected $slug = 'lazy_load';

	public function __call( $method_name, $arguments ) {
		$new_controller = Lazy_Load_Controller::get_instance();
		if ( method_exists( $new_controller, $method_name ) ) {
			_deprecated_function( $method_name, '3.16.0', "\Smush\Core\Lazy_Load\Lazy_Load_Controller::$method_name" );
			call_user_func_array( array( $new_controller, $method_name ), $arguments );
		} else {
			_deprecated_function( $method_name, '3.16.0' );
		}
	}
}
