<?php

namespace Smush\Core\Parser;

class Value {
	private $previous_value;

	private $value;

	private $has_updates = false;

	public function __construct( $value ) {
		$this->previous_value = '';
		$this->value          = $value;
	}

	/**
	 * @param $value
	 *
	 * @return bool
	 */
	public function set( $value ) {
		if ( $value === $this->value ) {
			/**
			 * Don't do anything if the value hasn't changed.
			 * We don't want to do unnecessary string replacements.
			 */
			return false;
		}

		$this->previous_value = $this->value;
		$this->value          = $value;
		$this->has_updates    = true;
		return true;
	}

	public function get() {
		return $this->value;
	}

	public function get_previous() {
		return $this->previous_value;
	}

	public function has_updates() {
		return $this->has_updates;
	}
}
