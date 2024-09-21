<?php

namespace Smush\Core\Lazy_Load;

use Smush\Core\Array_Utils;
use Smush\Core\Parser\Composite_Element;
use Smush\Core\Parser\Element;
use Smush\Core\Parser\Element_Attribute;
use Smush\Core\Parser\Page;
use Smush\Core\Settings;
use Smush\Core\Transform\Transform;
use Smush\Core\Upload_Dir;
use Smush\Core\Url_Utils;

class Lazy_Load_Transform implements Transform {
	const LAZYLOAD_CLASS = 'lazyload';
	const TEMP_SRC = 'data:image/gif;base64,R0lGODlhAQABAAAAACH5BAEKAAEALAAAAAABAAEAAAICTAEAOw==';
	/**
	 * @var Settings
	 */
	private $settings;
	/**
	 * @var array
	 */
	private $lazy_load_options;
	/**
	 * @var array
	 */
	private $excluded_attributes;
	/**
	 * @var Lazy_Load_Helper
	 */
	private $helper;
	/**
	 * @var Array_Utils
	 */
	private $array_utils;
	private $upload_dir;
	/**
	 * @var Url_Utils
	 */
	private $url_utils;

	public function __construct() {
		$this->settings    = Settings::get_instance();
		$this->helper      = Lazy_Load_Helper::get_instance();
		$this->array_utils = new Array_Utils();
		$this->upload_dir  = new Upload_Dir();
		$this->url_utils   = new Url_Utils();
	}

	public function should_transform() {
		return ! $this->helper->should_skip_lazyload();
	}

	public function transform_page( $page ) {
		$this->transform_image_elements( $page );
		if ( ! $this->helper->is_format_excluded( 'iframe' ) ) {
			$this->transform_iframes( $page );
		}
	}

	/**
	 * @param $page Page
	 *
	 * @return void
	 */
	private function transform_iframes( $page ) {
		foreach ( $page->get_iframe_elements() as $iframe_element ) {
			$this->transform_iframe( $iframe_element );
		}
	}

	/**
	 * @param Element $iframe_element
	 *
	 * @return void
	 */
	private function transform_iframe( Element $iframe_element ) {
		$src_attribute = $iframe_element->get_attribute( 'src' );
		if ( ! $src_attribute ) {
			return;
		}

		$original_src_url       = $src_attribute->get_value();
		$original_iframe_markup = $iframe_element->get_markup();
		if (
			$this->is_element_excluded( $iframe_element )
			|| $this->is_iframe_skipped_through_filter( $original_src_url, $original_iframe_markup )
		) {
			return;
		}

		if ( esc_url_raw( $original_src_url ) !== $original_src_url ) {
			return;
		}

		if ( $this->helper->is_native_lazy_loading_enabled() ) {
			if ( ! $this->element_has_native_lazy_load_attribute( $iframe_element ) ) {
				$this->add_native_lazy_loading_attribute( $iframe_element );
			}
		} else {
			$this->update_element_attributes_for_lazy_load( $iframe_element, array( 'src' ) );
			$iframe_element->add_attribute( new Element_Attribute( 'data-load-mode', '1' ) );
		}
	}

	private function update_element_attributes_for_lazy_load( Element $element, $replace_attributes ) {
		$this->replace_attributes_with_data_attributes( $element, $replace_attributes );
		// We are adding a new src below, the original src is gone because we replaced it
		$element->add_attribute( new Element_Attribute( 'src', self::TEMP_SRC ) );
		$this->add_lazy_load_class( $element );
	}

	private function element_has_native_lazy_load_attribute( Element $element ) {
		$attribute_value = $element->get_attribute_value( 'loading' );
		return ! empty( $attribute_value );
	}

	private function is_element_excluded( Element $element ) {
		return $this->is_high_priority_element( $element )
		       || $this->element_has_excluded_attribute_values( $element );
	}

	private function element_has_excluded_attribute_values( Element $element ) {
		$excluded_attributes = $this->get_excluded_attributes();
		if ( empty( $excluded_attributes ) ) {
			return false;
		}

		if ( $this->markup_has_excluded_attribute_values( $element->get_markup(), $excluded_attributes ) ) {
			// Exact match found
			return true;
		}

		// Now try with # and . for id and class respectively
		$id_attribute = $element->get_attribute_value( 'id' );
		if ( $id_attribute ) {
			$element_id = '#' . $id_attribute;
			if ( in_array( $element_id, $excluded_attributes, true ) ) {
				return true;
			}
		}

		$class_attribute = $element->get_attribute_value( 'class' );
		if ( $class_attribute ) {
			$element_classes = explode( ' ', $class_attribute );
			foreach ( $element_classes as $class ) {
				if ( in_array( ".{$class}", $excluded_attributes, true ) ) {
					return true;
				}
			}
		}

		return false;
	}

	private function markup_has_excluded_attribute_values( $string, $excluded_values ) {
		if ( empty( $excluded_values ) ) {
			return false;
		}

		$excluded_values = (array) $excluded_values;

		foreach ( $excluded_values as $excluded_value ) {
			if ( empty( $excluded_value ) || ! is_string( $excluded_value ) ) {
				continue;
			}

			if ( strpos( $string, $excluded_value ) !== false ) {
				return true;
			}
		}

		return false;
	}

	private function is_iframe_skipped_through_filter( $src, $iframe ) {
		return apply_filters( 'smush_skip_iframe_from_lazy_load', false, $src, $iframe );
	}

	private function get_lazy_load_options() {
		if ( ! $this->lazy_load_options ) {
			$setting                 = $this->settings->get_setting( 'wp-smush-lazy_load' );
			$this->lazy_load_options = empty( $setting ) ? array() : $setting;
		}

		return $this->lazy_load_options;
	}

	private function get_excluded_attributes() {
		if ( ! $this->excluded_attributes ) {
			$this->excluded_attributes = $this->prepare_excluded_attributes();
		}
		return $this->excluded_attributes;
	}

	private function prepare_excluded_attributes() {
		$exclude_attributes = $this->get_default_excluded_attributes();
		$exclude_classes    = $this->helper->get_excluded_classes();
		$exclude_attributes = array_merge(
			$exclude_attributes,
			$exclude_classes
		);

		return apply_filters( 'wp_smush_lazyload_excluded_attributes', array_unique( $exclude_attributes ) );
	}

	private function replace_attributes_with_data_attributes( Element $element, $attribute_names ) {
		foreach ( $attribute_names as $attribute_name ) {
			$this->replace_attribute_with_data_attribute( $element, $attribute_name );
		}
	}

	/**
	 * @param Element $element
	 * @param $original_attribute_name
	 *
	 * @return void
	 */
	private function replace_attribute_with_data_attribute( Element $element, $original_attribute_name ) {
		$attribute = $element->get_attribute( $original_attribute_name );
		if ( $attribute ) {
			$original_value = $attribute->get_value();
			$data_attribute = new Element_Attribute( "data-$original_attribute_name", $original_value );
			$element->replace_attribute( $original_attribute_name, $data_attribute );
		}
	}

	private function get_default_excluded_attributes() {
		return array(
			'data-lazyload=',
			'soliloquy-preload', // Soliloquy slider.
			'no-lazyload', // Internal class to skip images.
			'data-src=',
			'data-no-lazy=',
			'base64,R0lGOD',
			'data-lazy-original=',
			'data-lazy-src=',
			'data-lazysrc=',
			'data-bgposition=',
			'fullurl=',
			'jetpack-lazy-image',
			'lazy-slider-img=',
			'data-srcset=',
			'class="ls-l',
			'class="ls-bg',
			'soliloquy-image',
			'swatch-img',
			'data-height-percentage',
			'data-large_image',
			'avia-bg-style-fixed',
			'data-skip-lazy',
			'skip-lazy',
			'image-compare__',
			'gform_ajax_frame',
			'recaptcha/api/',
			'google_ads_iframe_',
		);
	}

	private function is_high_priority_element( Element $element ) {
		/**
		 * An image should not be lazy-loaded and marked as high priority at the same time.
		 *
		 * @see wp_img_tag_add_loading_optimization_attrs()
		 */
		$fetch_priority = $element->get_attribute_value( 'fetchpriority' );

		return $fetch_priority === 'high';
	}

	/**
	 * @param Element $element
	 *
	 * @return void
	 */
	private function add_native_lazy_loading_attribute( Element $element ) {
		$element->add_attribute( new Element_Attribute( 'loading', 'lazy' ) );
	}

	private function transform_image_elements( Page $page ) {
		foreach ( $page->get_composite_elements() as $composite_element ) {
			if ( ! $this->is_composite_element_excluded( $composite_element ) ) {
				$this->transform_elements( $composite_element->get_elements() );
			}
		}

		$this->transform_elements( $page->get_elements() );
	}

	private function transform_image_element( Element $element ) {
		if ( $element->get_tag() === 'source' ) {
			$this->maybe_lazy_load_source_element( $element );
		} else {
			$attributes_updated = $this->maybe_lazy_load_image_element( $element );
			if ( ! $attributes_updated ) {
				$this->maybe_lazy_load_background( $element );
			}
		}
	}

	private function maybe_lazy_load_source_element( Element $element ) {
		$srcset_attribute = $element->get_attribute( 'srcset' );
		if ( ! $srcset_attribute || empty( $srcset_attribute->get_image_urls() ) ) {
			return false;
		}

		$srcset_url       = $srcset_attribute->get_single_image_url();
		$srcset_image_url = $srcset_url->get_absolute_url();
		$srcset_extension = $srcset_url->get_ext();
		$original_markup  = $element->get_markup();
		if (
			! $srcset_image_url
			|| ! $this->helper->is_image_extension_supported( $srcset_extension, $srcset_image_url )
			|| $this->is_element_excluded( $element )
			|| $this->is_image_element_skipped_through_filter( $srcset_image_url, $original_markup )
			|| $this->helper->is_native_lazy_loading_enabled()
		) {
			return false;
		}

		$this->replace_attributes_with_data_attributes( $element, array(
			'src',
			'srcset',
			'sizes',
		) );
		return true;
	}

	private function maybe_lazy_load_image_element( Element $element ) {
		$src_attribute = $element->get_attribute( 'src' );
		if ( ! $src_attribute ) {
			return false;
		}
		$src_image_url = ! empty( $src_attribute->get_single_image_url() )
			? $src_attribute->get_single_image_url()->get_absolute_url()
			: $src_attribute->get_value();
		$src_extension = ! empty( $src_attribute->get_single_image_url() )
			? $src_attribute->get_single_image_url()->get_ext()
			: '';

		$original_markup = $element->get_markup();
		if (
			! $src_image_url
			|| ! $this->helper->is_image_extension_supported( $src_extension, $src_image_url )
			|| $this->is_element_excluded( $element )
			|| $this->is_image_element_skipped_through_filter( $src_image_url, $original_markup )
		) {
			return false;
		}

		$is_tag_supported = in_array( $element->get_tag(), $this->get_lazy_load_image_tag_names(), true );
		if ( ! $is_tag_supported ) {
			return false;
		}

		if ( $this->helper->is_native_lazy_loading_enabled() ) {
			if ( ! $this->element_has_native_lazy_load_attribute( $element ) ) {
				$this->add_native_lazy_loading_attribute( $element );
			}
		} else {
			$this->update_element_attributes_for_lazy_load( $element, array(
				'src',
				'srcset',
				'sizes',
			) );

			$this->set_placeholder_width_and_height_in_style_attribute( $element, $src_image_url );

			if ( $element->is_image_element() && $this->helper->is_noscript_fallback_enabled() ) {
				// TODO: Remove the duplicate <noscript> if it already exists before.
				$element->set_postfix( "<noscript>$original_markup</noscript>" );
			}
		}
		return true;
	}

	private function maybe_lazy_load_background( Element $element ) {
		$background_property  = $element->get_background_css_property();
		$background_image_url = $background_property && ! empty( $background_property->get_single_image_url()->get_absolute_url() )
			? $background_property->get_single_image_url()->get_absolute_url()
			: '';
		$background_extension = $background_property && ! empty( $background_property->get_single_image_url()->get_ext() )
			? $background_property->get_single_image_url()->get_ext()
			: '';

		$original_markup = $element->get_markup();
		if (
			! $background_image_url
			|| ! $this->helper->is_image_extension_supported( $background_extension, $background_image_url )
			|| $this->is_element_excluded( $element )
			|| $this->is_image_element_skipped_through_filter( $background_image_url, $original_markup )
			|| $this->helper->is_native_lazy_loading_enabled()
		) {
			return;
		}

		$data_attribute_name = 'data-' . str_replace( 'background', 'bg', $background_property->get_property() );  // data-bg|data-bg-image.
		$element->add_attribute( new Element_Attribute( $data_attribute_name, trim( $background_property->get_value() ) ) );
		$background_property->set_value( 'inherit' );
		$this->add_lazy_load_class( $element );
	}

	private function get_lazy_load_image_tag_names() {
		$image_tag_names = array(
			'img',
		);
		if ( ! $this->helper->is_native_lazy_loading_enabled() ) {
			$image_tag_names[] = 'source';
		}
		return (array) apply_filters( 'wp_smush_lazyload_image_tag_names', $image_tag_names );
	}

	private function is_image_element_skipped_through_filter( $src_url, $markup ) {
		/**
		 * Filter to skip a single image from lazy load.
		 *
		 * @param bool $skip Should skip? Default: false.
		 * @param string $src_url Image url.
		 * @param string $image Image.
		 *
		 * @since 3.3.0 Added $image param.
		 *
		 */
		return apply_filters( 'smush_skip_image_from_lazy_load', false, $src_url, $markup );
	}

	public function transform_image_url( $url ) {
		return $url;
	}

	/**
	 * @param Element $element
	 *
	 * @return void
	 */
	private function add_lazy_load_class( Element $element ) {
		$class_attr = $element->get_attribute_value( 'class' );
		if ( ! empty( $class_attr ) && strpos( $class_attr, self::LAZYLOAD_CLASS ) !== false ) {
			return;
		}

		$new_class_attr = empty( $class_attr )
			? self::LAZYLOAD_CLASS
			: $class_attr . ' ' . self::LAZYLOAD_CLASS;

		$new_class_attr = apply_filters( 'wp_smush_lazy_load_classes', $new_class_attr );

		$element->add_or_update_attribute( new Element_Attribute( 'class', $new_class_attr ) );
	}

	/**
	 * @param Element $element
	 * @param $src_image_url
	 *
	 * @return void
	 */
	private function set_placeholder_width_and_height_in_style_attribute( Element $element, $src_image_url ) {
		// We need explicit values for width and height. First try attribute values.
		$width  = (int) $element->get_attribute_value( 'width' );
		$height = (int) $element->get_attribute_value( 'height' );

		// If attributes are missing, check if the image file name has dimensions in it
		if ( empty( $width ) || empty( $height ) ) {
			list( $width, $height ) = $this->url_utils->guess_dimensions_from_image_url( $src_image_url );
		}

		// If all else fails, use getimagesize for local images
		if ( empty( $width ) || empty( $height ) ) {
			$image_dimensions = $this->get_image_dimensions( $src_image_url );
			if ( ! empty( $image_dimensions ) ) {
				list( $width, $height ) = $image_dimensions;
			}
		}

		if ( $width && $height ) {
			$original_style = $element->get_attribute_value( 'style' );
			$new_style      = "--smush-placeholder-width: {$width}px; --smush-placeholder-aspect-ratio: $width/$height;$original_style";

			$element->add_or_update_attribute( new Element_Attribute( 'style', $new_style ) );
		}
	}

	private function get_image_dimensions( $image_url ) {
		$upload_url = $this->upload_dir->get_upload_url();
		if ( ! str_starts_with( $image_url, $upload_url ) ) {
			return array();
		}

		$upload_path = $this->upload_dir->get_upload_path();
		$image_path  = str_replace( $upload_url, $upload_path, $image_url );
		if ( ! file_exists( $image_path ) ) {
			return array();
		}

		return getimagesize( $image_path );
	}

	/**
	 * @param Composite_Element $composite_element
	 *
	 * @return bool
	 */
	private function is_composite_element_excluded( Composite_Element $composite_element ): bool {
		foreach ( $composite_element->get_elements() as $sub_element ) {
			if ( $this->is_element_excluded( $sub_element ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * @param array $elements
	 *
	 * @return void
	 */
	private function transform_elements( array $elements ) {
		foreach ( $elements as $element ) {
			$this->transform_image_element( $element );
		}
	}
}
