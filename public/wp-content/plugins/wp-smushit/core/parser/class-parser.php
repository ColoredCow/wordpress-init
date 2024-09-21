<?php

namespace Smush\Core\Parser;

class Parser {
	public function get_base_url( $markup ) {
		$pattern = "/<base[^>]*?href\s*=\s*['\"](.*?)['\"]\s*\/\s*>/is";
		if ( preg_match( $pattern, $markup, $matches ) && ! empty( $matches[1] ) ) {
			return $matches[1];
		}

		return '';
	}

	public function get_inline_style_blocks( $markup ) {
		$pattern = '/<style\b[^>]*>(.*?)<\/style>/msi';
		if ( ! preg_match_all( $pattern, $markup, $matches ) ) {
			return array();
		}
		return $matches[1];
	}

	/**
	 * @param $markup
	 * @param $base_url
	 *
	 * @return Style[]
	 */
	public function get_inline_styles( $markup, $base_url ) {
		$styles              = array();
		$inline_style_blocks = $this->get_inline_style_blocks( $markup );
		foreach ( $inline_style_blocks as $inline_style_block ) {
			$image_urls = $this->get_image_urls( $inline_style_block, $base_url );
			$style      = new Style( $inline_style_block, $image_urls );
			$styles[]   = $style;
		}
		return $styles;
	}

	/**
	 * @param $markup string
	 * @param $base_url
	 *
	 * @return Image_URL[]
	 */
	public function get_image_urls( $markup, $base_url ) {
		$pattern = '@(?<src>(?:https?:/|\.+)?/[^\'",\s\(\)]+\.(?<ext>jpe?g|png|gif|webp|svg))\b@is';
		$pattern = apply_filters( 'wp_smush_image_urls_regex', $pattern );
		if ( ! preg_match_all( $pattern, $markup, $matches, PREG_SET_ORDER ) ) {
			return array();
		}

		$image_urls = array();
		foreach ( $matches as $match ) {
			if ( ! isset( $match['src'], $match['ext'] ) ) {
				continue;
			}
			$image_urls[] = new Image_URL(
				$this->remove_quote_entities( $match['src'] ),
				$match['ext'],
				$base_url
			);
		}

		return array_values( $image_urls );
	}

	public function get_tags( $markup, $tags ) {
		$tags_string = join( '|', $tags );

		$matches = array();
		if ( preg_match_all( '/<(' . $tags_string . ').*?\/\1>/s', $markup, $matches, PREG_PATTERN_ORDER ) ) {
			return $matches[0];
		}

		return array();
	}

	public function get_block_by_tag( $markup, $tag ) {
		$pattern = "/<$tag\b([^>]*>.*)<\/$tag>/is";
		if ( ! preg_match( $pattern, $markup, $matches ) ) {
			return $markup;
		}

		return $matches[1];
	}

	public function get_composite_elements( $markup, $base_url ) {
		$composite_elements = array();
		$tag_names          = array( 'picture' );
		foreach ( $tag_names as $tag_name ) {
			$html_elements = $this->get_tags( $markup, array( $tag_name ) );
			foreach ( $html_elements as $html_element ) {
				$elements = $this->get_elements_with_image_attributes( $html_element, $base_url );
				if ( ! empty( $elements ) ) {
					$composite_elements[] = new Composite_Element( $html_element, $tag_name, $elements );
				}
			}
		}
		return $composite_elements;
	}

	/**
	 * @param $markup
	 * @param $base_url
	 *
	 * @return Element[]
	 */
	public function get_elements_with_image_attributes( $markup, $base_url ) {
		$pattern = '@(?<element><(?:(?<img>img)\b[^>]+|(?<tag>[a-zA-Z]+)\b[^>]+\.(?:jpe?g|png|gif|webp|svg)[^>]+)>)@is';
		$pattern = apply_filters( 'wp_smush_images_from_content_regex', $pattern );
		if ( ! preg_match_all( $pattern, $markup, $matches, PREG_SET_ORDER ) ) {
			return array();
		}

		$elements = array();
		foreach ( $matches as $item ) {
			$element        = $item['element'];
			$tag_name       = ! empty( $item['img'] ) ? $item['img'] : $item['tag'];
			$attributes     = $this->get_element_attributes( $element, $base_url );
			$background     = $this->get_element_background_image( $element, $base_url );
			$css_properties = $background ? array( $background ) : array();

			// TODO: Support CSS variable with images.
			if ( empty( $attributes ) && empty( $css_properties ) ) {
				continue;
			}

			$elements[] = new Element( $element, $tag_name, $attributes, $css_properties );
		}

		return $elements;
	}

	/**
	 * @param $element
	 * @param $base_url
	 *
	 * @return Element_Attribute[]
	 */
	public function get_element_attributes( $element, $base_url ) {
		$image_attributes = array();

		$pattern = '#\b(?<name>(?:data-(?:[a-z0-9_-]+-)?)?(?:[a-z0-9_-]+))\s*=\s*(["\'])(?<value>[^\'"]+)\2#is';
		$pattern = apply_filters( 'wp_smush_image_attributes_regex', $pattern );

		if ( ! preg_match_all( $pattern, $element, $matches, PREG_SET_ORDER ) ) {
			return $image_attributes;
		}

		foreach ( $matches as $attr_data ) {
			if ( ! isset( $attr_data['name'], $attr_data['value'] ) ) {
				continue;
			}
			$attr_name  = $attr_data['name'];
			$attr_value = trim( $attr_data['value'] );
			if ( ! $this->is_safe( $attr_name ) || ! $this->is_safe( $attr_value ) ) {
				continue;
			}
			$image_urls = $this->get_image_urls( $attr_value, $base_url );

			$image_attributes[ $attr_name ] = new Element_Attribute(
				$attr_name,
				$attr_value,
				$attr_data[0],
				$image_urls
			);
		}

		return $image_attributes;
	}

	public function get_element_background_image( $element, $base_url ) {
		if ( ! strpos( $element, 'background' ) ) {
			return null;
		}

		$style = $this->get_element_attribute_value( $element, 'style' );
		if ( empty( $style ) ) {
			return null;
		}

		/**
		 * Background image regex supports:
		 *
		 * 1. background or background-image.
		 * 2. Multiple background images.
		 *
		 * background: url(img_flwr.gif) right bottom no-repeat, url(paper.gif) left top repeat;
		 * background-image: url(&quot;image1.png&quot;), url(https://sample.com/image2.png?lossy=2&amp;strip=1&amp;webp=1), linear-gradient(to right, rgba(30, 75, 115, 1), rgba(255, 255, 255, 0));
		 */

		// Regex rule to get all inline style after background(-image) property.
		$pattern = '#(?<!-)\b(?<property>background(?:-image)?):(?<value>[^;:]*?url\s*\([^>]+\)[^=:;]*);{0,1}#is';
		$pattern = apply_filters( 'wp_smush_background_images_regex', $pattern );

		if ( ! preg_match_all( $pattern, $style, $matches, PREG_SET_ORDER ) ) {
			return null;
		}

		$bg_property = null;
		foreach ( $matches as $bg_image_data ) {
			if ( ! isset( $bg_image_data['property'], $bg_image_data['value'] ) ) {
				continue;
			}
			$bg_image_property = $bg_image_data['property'];
			$bg_image_value    = $bg_image_data['value'];
			if ( ! $this->is_safe( $bg_image_property ) || ! $this->is_safe( $bg_image_value ) ) {
				continue;
			}
			$image_urls = $this->get_image_urls( $bg_image_value, $base_url );
			if ( empty( $image_urls ) ) {
				continue;
			}

			// background-image:url(&quot;image1.png&quot;); background-repeat: no-repeat.
			if ( substr_count( $bg_image_value, ';' ) ) {
				$bg_image_value = $this->extract_background_image( $bg_image_value );
			}

			// An element only has one background, so let try to get the latest one.
			$bg_property = new Element_CSS_Property(
				$bg_image_data[0],
				$bg_image_property,
				$bg_image_value,
				$image_urls
			);
		}

		return $bg_property;
	}

	/**
	 * Extract background image value from the inline style.
	 *
	 * Input: linear-gradient(to right, rgba(30, 75, 115, 1), rgba(255, 255, 255, 0)), url(&quot;image1.png&quot;) right bottom no-repeat,
	 *                          url(https://sample.com/image2.png?lossy=2&amp;strip=1&amp;webp=1) left top repeat; background-color: #fff;
	 * Output: linear-gradient(to right, rgba(30, 75, 115, 1), rgba(255, 255, 255, 0)), url(&quot;image1.png&quot;) right bottom no-repeat,
	 *                          url(https://sample.com/image2.png?lossy=2&amp;strip=1&amp;webp=1) left top repeat;
	 */
	public function extract_background_image( $bg_image_value ) {
		$urls                       = array();
		$bg_image_value_without_url = preg_replace_callback(
			'#url\s*\(([^\)]+)\)#is',
			function ( $matches ) use ( &$urls ) {
				if ( ! empty( $matches[1] ) ) {
					$url        = $matches[1];
					$urls[]     = $url;
					$count      = count( $urls );
					$matches[0] = str_replace( $url, "[URL{$count}]", $matches[0] );
				}
				return $matches[0];
			},
			$bg_image_value
		);

		if ( ! empty( $urls ) && preg_match( '/[^;]+/is', $bg_image_value_without_url, $matches ) ) {
			$bg_image_value = $matches[0];
			foreach ( $urls as $index => $url ) {
				$index ++;
				$bg_image_value = str_replace( "[URL{$index}]", $url, $bg_image_value );
			}
		}

		return $bg_image_value;
	}

	private function remove_quote_entities( $image ) {
		// Quote entities.
		$quotes = apply_filters( 'wp_smush_background_image_quotes', array( '&quot;', '&#034;', '&#039;', '&apos;' ) );

		$image = trim( $image );
		if ( empty( $image ) || strlen( $image ) < 6 ) {
			return $image;
		}

		// Remove the starting quotes.
		if ( in_array( substr( $image, 0, 6 ), $quotes, true ) ) {
			$image = substr( $image, 6 );
		}

		// Remove the ending quotes.
		if ( in_array( substr( $image, - 6 ), $quotes, true ) ) {
			$image = substr( $image, 0, - 6 );
		}

		return $image;
	}

	public function add_element_attribute( $element, $name, $value = null ) {
		$closing = false === strpos( $element, '/>' ) ? '>' : ' />';
		$quotes  = false === strpos( $element, '"' ) ? '\'' : '"';

		if ( ! is_null( $value ) ) {
			$element = rtrim( $element, $closing ) . " {$name}={$quotes}{$value}{$quotes}{$closing}";
		} else {
			$element = rtrim( $element, $closing ) . " {$name}{$closing}";
		}

		return $element;
	}

	public function remove_element_attribute( $element, $attribute ) {
		return preg_replace( '/\s' . $attribute . '=[\'"](.*?)[\'"]/i', '', $element );
	}

	/**
	 * @param $markup
	 * @param $base_url
	 *
	 * @return Element[]
	 */
	public function get_iframe_elements( $markup, $base_url ) {
		if ( strpos( $markup, '<iframe' ) === false ) {
			return array();
		}

		// Iframe tag has srcdocs attribute which might contains HTML code.
		$pattern = '#<iframe\b[^>]*\s(?<attr>src\s*=\s*(\'|")(?<src>[^\'"]+)\2)[^>]*>#is';
		$pattern = apply_filters( 'wp_smush_iframes_regex', $pattern );
		$iframes = array();

		if ( ! preg_match_all( $pattern, $markup, $matches, PREG_SET_ORDER ) ) {
			return $iframes;
		}

		foreach ( $matches as $iframe_data ) {
			if ( empty( $iframe_data['attr'] ) || empty( $iframe_data['src'] ) ) {
				continue;
			}

			$attributes     = $this->get_element_attributes( $iframe_data[0], $base_url );
			$iframe_element = new Element( $iframe_data[0], 'iframe', $attributes, array() );

			$iframes[] = $iframe_element;
		}

		return $iframes;
	}

	public function get_element_attribute_value( $element_markup, $attribute_name ) {
		if ( strpos( $element_markup, $attribute_name ) === false ) {
			return '';
		}

		$pattern = '#' . $attribute_name . '\s*=\s*([\'|"])(?<value>(?:(?!\1).)*)\1#is';
		if ( ! preg_match_all( $pattern, $element_markup, $matches, PREG_SET_ORDER ) ) {
			return '';
		}

		return empty( $matches[0]['value'] )
			? ''
			: $matches[0]['value'];
	}

	private function is_safe( $str ) {
		$str = trim( $str );
		return $this->sanitize_value( $str ) === $str;
	}

	/**
	 * This is almost the same as {@see _sanitize_text_fields()} but it doesn't remove percent encoded values because they are valid.
	 */
	private function sanitize_value( $str ) {
		if ( is_object( $str ) || is_array( $str ) ) {
			return '';
		}

		$str = (string) $str;

		$filtered = wp_check_invalid_utf8( $str );

		if ( str_contains( $filtered, '<' ) ) {
			$filtered = wp_pre_kses_less_than( $filtered );
			// This will strip extra whitespace for us.
			$filtered = wp_strip_all_tags( $filtered );

			/*
			 * Use HTML entities in a special case to make sure that
			 * later newline stripping stages cannot lead to a functional tag.
			 */
			$filtered = str_replace( "<\n", "&lt;\n", $filtered );
		}
		/**
		 * Skip removal of percent encoded values {@see _sanitize_text_fields}
		 */
		return trim( $filtered );
	}
}
