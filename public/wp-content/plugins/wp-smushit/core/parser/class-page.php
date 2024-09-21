<?php

namespace Smush\Core\Parser;

class Page {
	/**
	 * @var string
	 */
	private $page_url;
	/**
	 * @var string
	 */
	private $page_markup;
	/**
	 * @var Style[]
	 */
	private $styles;
	/**
	 * @var Element[]
	 */
	private $elements;
	/**
	 * @var Parser
	 */
	private $parser;
	/**
	 * @var Element[]
	 */
	private $iframe_elements;
	/**
	 * @var Composite_Element[]
	 */
	private $composite_elements;

	/**
	 * @param $page_url string
	 * @param $page_markup string
	 * @param $styles Style[]
	 * @param $elements Element[]
	 */
	public function __construct( $page_url, $page_markup, $styles, $composite_elements, $elements, $iframe_elements ) {
		$this->page_url           = $page_url;
		$this->page_markup        = $page_markup;
		$this->styles             = $styles;
		$this->composite_elements = $composite_elements;
		$this->elements           = $elements;
		$this->iframe_elements    = $iframe_elements;
		$this->parser             = new Parser();
	}

	/**
	 * @return Style[]
	 */
	public function get_styles() {
		return $this->styles;
	}

	/**
	 * @return Composite_Element[]
	 */
	public function get_composite_elements() {
		return $this->composite_elements;
	}

	/**
	 * @return Element[]
	 */
	public function get_elements() {
		return $this->elements;
	}

	public function has_updates() {
		foreach ( $this->styles as $style ) {
			if ( $style->has_updates() ) {
				return true;
			}
		}

		foreach ( $this->composite_elements as $composite_element ) {
			if ( $composite_element->has_updates() ) {
				return true;
			}
		}

		foreach ( $this->elements as $element ) {
			if ( $element->has_updates() ) {
				return true;
			}
		}

		foreach ( $this->iframe_elements as $iframe_element ) {
			if ( $iframe_element->has_updates() ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * @return string
	 */
	public function get_page_markup() {
		return $this->page_markup;
	}

	public function get_updated_markup() {
		$updated = $this->page_markup;

		$placeholders = new Placeholder_Replacement();
		$updated      = $placeholders->add_placeholders( $updated, $this->parser->get_tags( $updated, array(
			'script',
			'noscript',
		) ) );

		foreach ( $this->styles as $style ) {
			$updated = str_replace( $style->get_css(), $style->get_updated(), $updated );
		}

		foreach ( $this->composite_elements as $composite_element ) {
			if ( $composite_element->has_updates() ) {
				$updated = str_replace(
					$composite_element->get_markup(),
					$composite_element->get_updated(),
					$updated
				);
			}
		}

		foreach ( $this->elements as $element ) {
			if ( $element->has_updates() ) {
				$updated = str_replace(
					$element->get_markup(),
					$element->get_updated_markup(),
					$updated
				);
			}
		}

		foreach ( $this->iframe_elements as $iframe_element ) {
			if ( $iframe_element->has_updates() ) {
				$updated = str_replace(
					$iframe_element->get_markup(),
					$iframe_element->get_updated_markup(),
					$updated
				);
			}
		}

		$updated = $placeholders->remove_placeholders( $updated );

		return $updated;
	}

	public function get_iframe_elements() {
		return $this->iframe_elements;
	}
}
