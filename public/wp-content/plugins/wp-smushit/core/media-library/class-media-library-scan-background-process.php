<?php

namespace Smush\Core\Media_Library;

use Smush\Core\Modules\Background\Background_Process;

class Media_Library_Scan_Background_Process extends Background_Process {
	/**
	 * Cron Interval.
	 *
	 * @overwrite parent.
	 * @var int
	 */
	protected $cron_interval = 2;

	/**
	 * @var Media_Library_Scanner
	 */
	private $scanner;

	public function __construct( $identifier, $scanner ) {
		parent::__construct( $identifier );
		$this->scanner = $scanner;
	}

	protected function task( $slice_id ) {
		$this->scanner->scan_library_slice( $slice_id );

		return true;
	}

	protected function should_update_queue_after_task() {
		return true;
	}

	protected function get_instance_expiry_duration_seconds() {
		return MINUTE_IN_SECONDS;
	}

	protected function get_revival_limit() {
		$constant_value = $this->get_revival_limit_constant();

		return $constant_value ? $constant_value : parent::get_revival_limit();
	}

	private function get_revival_limit_constant() {
		if ( ! defined( 'WP_SMUSH_SCAN_REVIVAL_LIMIT' ) ) {
			return 0;
		}

		$constant_value = (int) WP_SMUSH_SCAN_REVIVAL_LIMIT;

		return max( $constant_value, 0 );
	}
}
