<?php

namespace Smush\Core\Parser;

class Composite_Element {
	/**
	 * @var string
	 */
	private $markup;
	/**
	 * @var string
	 */
	private $tag;
	/**
	 * @var Element[]
	 */
	private $elements;

	public function __construct( $markup, $tag, $elements ) {
		$this->markup   = $markup;
		$this->tag      = $tag;
		$this->elements = $elements;
	}

	/**
	 * @return string
	 */
	public function get_markup(): string {
		return $this->markup;
	}

	/**
	 * @return string
	 */
	public function get_tag(): string {
		return $this->tag;
	}

	/**
	 * @return Element[]
	 */
	public function get_elements(): array {
		return $this->elements;
	}

	public function has_updates() {
		foreach ( $this->elements as $element ) {
			if ( $element->has_updates() ) {
				return true;
			}
		}
		return false;
	}

	public function get_updated() {
		$updated = $this->markup;
		foreach ( $this->elements as $element ) {
			if ( $element->has_updates() ) {
				$updated = str_replace(
					$element->get_markup(),
					$element->get_updated_markup(),
					$updated
				);
			}
		}

		return $updated;
	}
}
