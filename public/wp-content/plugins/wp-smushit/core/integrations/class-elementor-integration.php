<?php

namespace Smush\Core\Integrations;

use Smush\Core\Controller;
use Smush\Core\Server_Utils;
use Smush\Core\Url_Utils;
use Smush\Core\Parser\Image_URL;
use Smush\Core\Transform\Transformer;
use Smush\Core\Media\Media_Item_Size;
/**
 * Elementor_Integration
 */
class Elementor_Integration extends Controller {
	/**
	 * @var Url_Utils
	 */
	private $url_utils;

	/**
	 * @var string
	 */
	private $current_url;

	/**
	 * @var Transformer
	 */
	private $transformer;

	public function __construct() {
		$this->url_utils   = new Url_Utils();
		$this->transformer = new Transformer();

		$this->register_filter( 'elementor/frontend/builder_content_data', array( $this, 'transform_elementor_settings_attribute' ) );
		$this->register_filter( 'wp_smush_media_item_size', array( $this, 'initialize_elementor_custom_size' ), 10, 4 );
	}

	public function should_run() {
		return class_exists( '\\Elementor\Plugin' );
	}

	public function initialize_elementor_custom_size(  $size, $key, $metadata, $media_item ) {
		if ( false === strpos( $key, 'elementor_custom_' ) ) {
			return $size;
		}

		$uploads_dir = wp_get_upload_dir();
		if ( ! isset( $uploads_dir['basedir'], $uploads_dir['baseurl'] ) ) {
			return $size;
		}

		$base_dir = $uploads_dir['basedir'];
		$base_url = $uploads_dir['baseurl'];

		return new Media_Item_Size( $key, $media_item->get_id(), $base_dir, $base_url, $metadata );
	}

	public function transform_elementor_settings_attribute( $element_data ) {
		if ( ! is_array( $element_data ) ) {
			return $element_data;
		}

		$image_property_names = array(
			'background_slideshow_gallery',
		);

		foreach ( $element_data as $container_key => $container_data ) {
			if ( empty( $container_data['settings'] ) || ! is_array( $container_data['settings'] ) ) {
				continue;
			}

			$element_settings = $container_data['settings'];

			foreach ( $image_property_names as $image_property_name ) {
				if ( ! isset( $element_settings[ $image_property_name ] ) || ! is_array( $element_settings[ $image_property_name ] ) ) {
					continue;
				}

				foreach ( $element_settings[ $image_property_name ] as $image_key => $image_data ) {
					if ( isset( $image_data['url'] ) ) {
						$element_data[ $container_key ]['settings'][ $image_property_name ][ $image_key ]['url'] = $this->transform_url( $image_data['url'] );
					}
				}
			}
		}
		return $element_data;
	}

	private function transform_url( $url ) {
		if ( empty( $url ) || ! is_string( $url ) ) {
			return $url;
		}

		$extension = $this->url_utils->get_extension( $url );
		$image_url = new Image_URL( $url, $extension, $this->get_current_url() );

		return $this->transformer->transform_url( $image_url->get_absolute_url() );
	}

	private function get_current_url() {
		if ( ! $this->current_url ) {
			$this->current_url = ( new Server_Utils() )->get_current_url();
		}

		return $this->current_url;
	}
}
