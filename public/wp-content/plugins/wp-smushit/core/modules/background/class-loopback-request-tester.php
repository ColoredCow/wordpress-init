<?php

namespace Smush\Core\Modules\Background;

class Loopback_Request_Tester extends Async_Request {
	const ID = 'wp_smush_loopback_request_tester';

	public function __construct() {
		parent::__construct( self::ID );
	}

	protected function handle( $instance_id ) {
		Background_Pre_Flight_Controller::get_instance()->set_loopback_healthy();
	}

	public function test() {
		$this->dispatch( self::ID );
	}
}
