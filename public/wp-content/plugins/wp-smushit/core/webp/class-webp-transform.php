<?php

namespace Smush\Core\Webp;

use Smush\Core\CDN\CDN_Helper;
use Smush\Core\Parser\Element;
use Smush\Core\Parser\Element_Attribute;
use Smush\Core\Parser\Element_CSS_Property;
use Smush\Core\Parser\Image_URL;
use Smush\Core\Parser\Page;
use Smush\Core\Server_Utils;
use Smush\Core\Settings;
use Smush\Core\Transform\Transform;

class Webp_Transform implements Transform {
	/**
	 * @var Webp_Helper
	 */
	private $webp_helper;
	/**
	 * @var Server_Utils
	 */
	private $server_utils;
	/**
	 * @var CDN_Helper
	 */
	private $cdn_helper;
	/**
	 * @var Settings
	 */
	private $settings;
	/**
	 * @var Webp_Configuration
	 */
	private $configuration;

	public function __construct() {
		$this->webp_helper   = new Webp_Helper();
		$this->server_utils  = new Server_Utils();
		$this->cdn_helper    = CDN_Helper::get_instance();
		$this->settings      = Settings::get_instance();
		$this->configuration = Webp_Configuration::get_instance();
	}

	public function should_transform() {
		$is_cdn_active             = $this->settings->is_cdn_active(); // CDN takes precedence because it handles webp anyway
		$is_webp_active            = $this->settings->is_webp_module_active();
		$direct_conversion_enabled = $this->configuration->direct_conversion_enabled();

		return ! $is_cdn_active && $is_webp_active && $direct_conversion_enabled;
	}

	/**
	 * @param $page Page
	 *
	 * @return void
	 */
	public function transform_page( $page ) {
		foreach ( $page->get_styles() as $style ) {
			$this->update_image_urls( $style->get_image_urls() );
		}

		foreach ( $page->get_composite_elements() as $composite_element ) {
			$this->transform_elements( $composite_element->get_elements() );
		}

		$this->transform_elements( $page->get_elements() );
	}

	private function add_fallback_attribute( Element $element ) {
		$fallback_values = array();

		foreach ( $element->get_image_attributes() as $fallback_attribute ) {
			if ( $fallback_attribute->has_updates() ) {
				$fallback_values[ $fallback_attribute->get_name() ] = $fallback_attribute->get_value();
			}
		}

		$background_property = $element->get_background_css_property();
		if ( $background_property && $background_property->has_updates() ) {
			$property_key                     = str_replace( 'background', 'bg', $background_property->get_property() );
			$fallback_values[ $property_key ] = $background_property->get_value();
		}

		if ( ! empty( $fallback_values ) ) {
			$element->add_attribute( new Element_Attribute( 'data-smush-webp-fallback', json_encode( $fallback_values ) ) );
		}
	}

	/**
	 * @param Element $element
	 *
	 * @return void
	 */
	private function transform_image_element_attributes( $element ) {
		foreach ( $element->get_image_attributes() as $attribute ) {
			$this->update_image_urls( $attribute->get_image_urls() );
		}
	}

	/**
	 * @param Element $element
	 *
	 * @return void
	 */
	private function transform_image_element_css_properties( $element ) {
		foreach ( $element->get_css_properties() as $css_property ) {
			$this->update_image_urls( $css_property->get_image_urls() );
		}
	}

	/**
	 * @param $image_urls Image_URL[]
	 */
	private function update_image_urls( $image_urls ) {
		foreach ( $image_urls as $image_url ) {
			$image_url_original = $image_url->get_absolute_url();
			$image_url_webp     = $this->webp_helper->get_webp_file_url( $image_url_original );
			// TODO: find a way to convert the URL back to a relative one so multidomain sites will work
			if ( $image_url_webp ) {
				$image_url->set_url( $image_url_webp );
			}
		}
	}

	public function transform_image_url( $url ) {
		$webp_url = $this->webp_helper->get_webp_file_url( $url );
		return $webp_url ? $webp_url : $url;
	}

	/**
	 * @param Element $element
	 *
	 * @return void
	 */
	private function transform_element( Element $element ) {
		$this->transform_image_element_attributes( $element );

		$this->transform_image_element_css_properties( $element );

		if ( $this->settings->is_webp_fallback_active() ) {
			$this->add_fallback_attribute( $element );
		}
	}

	/**
	 * @param array $elements
	 *
	 * @return void
	 */
	private function transform_elements( array $elements ) {
		foreach ( $elements as $element ) {
			$this->transform_element( $element );
		}
	}
}
