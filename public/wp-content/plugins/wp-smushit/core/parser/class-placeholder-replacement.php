<?php

namespace Smush\Core\Parser;

class Placeholder_Replacement {
	private $placeholders = array();
	/**
	 * @var Parser
	 */
	private $parser;

	public function __construct() {
		$this->parser = new Parser();
	}

	public function add_placeholders( $markup, $blocks ) {
		foreach ( $blocks as $block ) {
			$key                        = md5( $block );
			$this->placeholders[ $key ] = $block;

			$markup = str_replace( $block, $key, $markup );
		}

		return $markup;
	}

	public function remove_placeholders( $markup ) {
		foreach ( $this->placeholders as $key => $original ) {
			$markup = str_replace( $key, $original, $markup );
			unset( $this->placeholders[ $key ] );
		}

		return $markup;
	}
}
