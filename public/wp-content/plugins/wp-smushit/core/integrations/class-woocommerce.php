<?php

namespace Smush\Core\Integrations;

use Smush\Core\Controller;
use Smush\Core\Transform\Transformer;
use Smush\Core\Settings;

class WooCommerce extends Controller {
	public function __construct() {
		$this->register_filter( 'wp_smush_transform_rest_response_item', array(
			$this,
			'transform_rest_woo_product',
		), 10, 3 );

		$this->register_filter( 'wp_smush_get_image_attribute_names', array( $this, 'allow_woo_image_attributes_to_convert' ) );

		// WooCommerce's product gallery thumbnail CDN support.
		$this->register_filter( 'woocommerce_single_product_image_thumbnail_html', array( $this, 'maybe_native_lazyload_woo_product_gallery' ) );

		$this->register_filter( 'wp_smush_should_skip_auto_smush', array( $this, 'maybe_skip_auto_smush' ) );
	}

	public function should_run() {
		return function_exists( 'is_woocommerce' )
		       && class_exists( 'WooCommerce' );
	}

	/**
	 * @param $item array
	 * @param $request \WP_REST_Request
	 * @param $transformer Transformer
	 *
	 * @return array
	 */
	public function transform_rest_woo_product( $item, $request, $transformer ) {
		if ( ! str_starts_with( $request->get_route(), '/wc/v3/products' ) ) {
			return $item;
		}

		$product_url = empty( $item['permalink'] ) ? '' : $item['permalink'];
		if ( ! empty( $item['description'] ) ) {
			$item['description'] = $transformer->transform_content( $item['description'], $product_url );
		}

		if ( ! empty( $item['short_description'] ) ) {
			$item['short_description'] = $transformer->transform_content( $item['short_description'], $product_url );
		}

		$images = empty( $item['images'] ) ? array() : $item['images'];
		foreach ( $images as $index => $image ) {
			if ( ! empty( $image['src'] ) ) {
				$item['images'][ $index ]['src'] = $transformer->transform_url( $image['src'] );
			}
		}

		return $item;
	}

	public function allow_woo_image_attributes_to_convert( $attribute_names ) {
		$attribute_names[] = 'data-large_image';
		return $attribute_names;
	}

	public function maybe_native_lazyload_woo_product_gallery( $thumbnail_html ) {
		if ( ! Settings::get_instance()->is_lazyload_active() || strpos( $thumbnail_html, ' loading=' ) ) {
			return $thumbnail_html;
		}

		// Woocommerce product gallery used `data-src` attribute which we excluded by default from lazyload
		// so we will always use native lazyload for it.
		$thumbnail_html = str_replace( '<img ', '<img loading="lazy" ', $thumbnail_html );

		return $thumbnail_html;
	}

	public function maybe_skip_auto_smush( $skip_auto_smush ) {
		if ( $skip_auto_smush ) {
			return true;
		}

		// Skip auto Smush when woocommerce regenrate thumbnails via filter wp_get_attachment_image_src.
		$skip_auto_smush = doing_filter( 'wp_get_attachment_image_src' );

		return $skip_auto_smush;
	}
}
