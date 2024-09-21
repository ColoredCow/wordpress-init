<?php

namespace Smush\Core\Parser;


class Page_Parser {
	/**
	 * @var string
	 */
	private $page_url;
	/**
	 * @var string
	 */
	private $page_markup;
	/**
	 * @var Parser
	 */
	private $parser;

	public function __construct( $page_url, $page_markup ) {
		$this->page_url    = $page_url;
		$this->page_markup = $page_markup;
		$this->parser      = new Parser();
	}

	public function parse_page() {
		$page_markup  = $this->page_markup;
		$base_tag_url = $this->parser->get_base_url( $page_markup );
		$base_url     = $base_tag_url ?: $this->page_url;
		$styles       = $this->parser->get_inline_styles( $page_markup, $base_url );

		$composite_elements = $this->parser->get_composite_elements( $page_markup, $base_url );
		$page_markup        = $this->replace_composites_with_placeholders( $page_markup, $composite_elements );

		$elements        = $this->parser->get_elements_with_image_attributes( $page_markup, $base_url );
		$iframe_elements = $this->parser->get_iframe_elements( $page_markup, $base_url );

		return new Page(
			$this->page_url,
			$this->page_markup,
			$styles,
			$composite_elements,
			$elements,
			$iframe_elements
		);
	}

	/**
	 * @param $markup
	 * @param $composite_elements Composite_Element[]
	 *
	 * @return string
	 */
	private function replace_composites_with_placeholders( $markup, $composite_elements ) {
		$placeholder_replacement = new Placeholder_Replacement();
		if ( empty( $composite_elements ) ) {
			return $markup;
		}

		$html_elements = array_map( function ( $composite_element ) {
			return $composite_element->get_markup();
		}, $composite_elements );
		return $placeholder_replacement->add_placeholders( $markup, $html_elements );
	}
}
