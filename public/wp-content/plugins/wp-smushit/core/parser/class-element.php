<?php

namespace Smush\Core\Parser;

class Element {
	private $markup;

	private $tag;

	/**
	 * @var Element_Attribute[]
	 */
	private $attributes;

	/**
	 * @var Element_CSS_Property[]
	 */
	private $css_properties;

	private $has_updates = false;

	/**
	 * @var Element_Attribute[]
	 */
	private $added_attributes = array();

	/**
	 * @var Element_Attribute[]
	 */
	private $replaced_attributes = array();
	/**
	 * @var Parser
	 */
	private $parser;
	private $postfix;
	/**
	 * @var Element_Attribute[]
	 */
	private $image_attributes;

	public function __construct( $markup, $tag, $attributes, $css_properties ) {
		$this->markup         = $markup;
		$this->tag            = $tag;
		$this->attributes     = $attributes;
		$this->css_properties = $css_properties;
		$this->parser         = new Parser();
	}

	/**
	 * @return mixed
	 */
	public function get_markup() {
		return $this->markup;
	}

	/**
	 * @return mixed
	 */
	public function get_tag() {
		return $this->tag;
	}

	/**
	 * @return Element_Attribute[]
	 */
	public function get_attributes() {
		return $this->attributes;
	}

	public function get_image_attributes() {
		if ( is_null( $this->image_attributes ) ) {
			$this->image_attributes = $this->prepare_image_attributes();
		}

		return $this->image_attributes;
	}

	private function prepare_image_attributes() {
		$image_attributes = array();
		foreach ( $this->get_image_attribute_names() as $image_attribute_name ) {
			$image_attribute = $this->get_attribute( $image_attribute_name );
			if ( $image_attribute ) {
				$image_attributes[] = $image_attribute;
			}
		}

		return $image_attributes;
	}

	private function get_image_attribute_names() {
		$attribute_names = apply_filters(
			'wp_smush_get_image_attribute_names',
			/**
			 * TODO: break down this list and move to integration classes, only keep the bare minimum here
			 */
			array(
				'href',
				'data-href',
				'src',
				'data-src',
				'srcset',
				'data-srcset',
				'data-thumb',
				'data-thumbnail',
				'data-back',
				'data-lazyload',
				// WP Rocket lazy loading:
				'data-lazy-src',
				'data-lazy-srcset',
				'data-original',
				// We need the following to support webp *after* lazy load.
				'data-bg',
				'data-bg-image',
			)
		);

		$attribute_names = array_filter(
			(array) $attribute_names,
			function ( $attribute_names ) {
				return $attribute_names && is_string( $attribute_names );
			}
		);

		return array_unique( $attribute_names );
	}

	/**
	 * @param Element_Attribute $attribute
	 */
	public function add_attribute( $attribute ) {
		$this->added_attributes[ $attribute->get_name() ] = $attribute;

		$this->set_has_updates( true );
	}

	public function get_attribute( $name ) {
		foreach ( $this->attributes as $attribute ) {
			if ( $attribute->get_name() === $name ) {
				return $attribute;
			}
		}
		return null;
	}

	/**
	 * @param $original_name string
	 * @param $new_attribute Element_Attribute
	 *
	 * @return void
	 */
	public function replace_attribute( $original_name, $new_attribute ) {
		$this->replaced_attributes[ $original_name ] = $new_attribute;

		$this->set_has_updates( true );
	}

	/**
	 * @param $attribute Element_Attribute
	 *
	 * @return void
	 */
	public function add_or_update_attribute( $attribute ) {
		$attribute_exists = $this->get_attribute( $attribute->get_name() );
		if ( $attribute_exists ) {
			$this->replace_attribute( $attribute->get_name(), $attribute );
		} else {
			$this->add_attribute( $attribute );
		}
	}

	private function set_has_updates( $has_updates ) {
		$this->has_updates = $has_updates;
	}

	/**
	 * @return Element_CSS_Property[]
	 */
	public function get_css_properties() {
		return $this->css_properties;
	}

	public function get_background_css_property() {
		foreach ( $this->get_css_properties() as $css_property ) {
			if ( strpos( $css_property->get_property(), 'background' ) !== false ) {
				return $css_property;
			}
		}
		return null;
	}

	/**
	 * @param Element_CSS_Property $css_property
	 */
	public function add_css_property( $css_property ) {
		// TODO: this won't work as of now
		$this->css_properties[] = $css_property;

		$this->set_has_updates( true );
	}

	public function has_updates() {
		$has_updates = $this->has_updates;

		foreach ( $this->attributes as $attribute ) {
			$has_updates = $has_updates || $attribute->has_updates();
		}

		foreach ( $this->css_properties as $css_property ) {
			$has_updates = $has_updates || $css_property->has_updates();
		}

		return $has_updates;
	}

	public function get_updated_markup() {
		$updated = $this->get_markup();
		$updated = $this->update_attributes( $updated );
		$updated = $this->update_css_properties( $updated );
		$updated = $this->replace_attributes( $updated );
		$updated = $this->add_new_attributes( $updated );
		$updated = $this->add_postfix( $updated );

		// TODO: this is a temporary way to support the old filters, remove this in the release that comes after 3.16.0
		$updated = apply_filters_deprecated( 'smush_cdn_image_tag', array( $updated ), '3.16.0', 'wp_smush_updated_element_markup' );
		$updated = apply_filters_deprecated( 'smush_cdn_bg_image_tag', array( $updated ), '3.16.0', 'wp_smush_updated_element_markup' );

		return apply_filters( 'wp_smush_updated_element_markup', $updated );
	}

	public function set_postfix( $postfix ) {
		$this->postfix = $postfix;
	}

	private function add_postfix( $markup ) {
		return $markup . $this->postfix;
	}

	private function replace_attributes( $markup ) {
		foreach ( $this->replaced_attributes as $original_attribute_name => $replaced_attribute ) {
			$original = $this->get_attribute( $original_attribute_name );
			if ( $original ) {
				$replaced_attribute_string = $this->change_attribute_quote_character(
					$replaced_attribute->get_attribute(),
					$this->find_quote_character( $original->get_attribute() )
				);

				$markup = str_replace(
					$original->get_attribute(),
					$replaced_attribute_string,
					$markup
				);
			}
		}

		return $markup;
	}

	private function find_quote_character( $string ) {
		return false === strpos( $string, '"' ) ? '\'' : '"';
	}

	private function change_attribute_quote_character( $full_attribute, $new_quote_character ) {
		$current_quote_character = $this->find_quote_character( $full_attribute );
		if ( $current_quote_character === $new_quote_character ) {
			return $full_attribute;
		}

		return str_replace( $current_quote_character, $new_quote_character, $full_attribute );
	}

	private function update_attributes( $markup ) {
		foreach ( $this->get_attributes() as $attribute ) {
			if ( $attribute->has_updates() ) {
				$markup = str_replace(
					$attribute->get_attribute(),
					$attribute->get_updated(),
					$markup
				);
			}
		}
		return $markup;
	}

	private function update_css_properties( $markup ) {
		foreach ( $this->get_css_properties() as $css_property ) {
			if ( $css_property->has_updates() ) {
				$markup = str_replace(
					$css_property->get_full(),
					$css_property->get_updated(),
					$markup
				);
			}
		}
		return $markup;
	}

	private function add_new_attributes( $markup ) {
		foreach ( $this->added_attributes as $added_attribute ) {
			$attribute_name = $added_attribute->get_name();
			// Remove the attribute first, important for removing any empty or invalid values before adding again
			$markup = $this->parser->remove_element_attribute( $markup, $attribute_name );
			$markup = $this->parser->add_element_attribute( $markup, $attribute_name, esc_attr( $added_attribute->get_value() ) );
		}

		return $markup;
	}

	public function get_attribute_value( $attribute_name ) {
		$attribute = $this->get_attribute( $attribute_name );
		return $attribute
			? $attribute->get_value()
			: '';
	}

	public function append_attribute_value( $attribute_name, $appendage ) {
		$attribute = $this->get_attribute( $attribute_name );
		if ( $attribute ) {
			$attribute->set_value( $attribute->get_value() . " " . $appendage );
		} else {
			$this->add_attribute( new Element_Attribute( $attribute_name, $appendage ) );
		}
	}

	public function is_image_element() {
		return 'img' === $this->get_tag();
	}
}
