<?php

namespace Smush\Core\Transform;

use Smush\Core\Array_Utils;
use Smush\Core\Parser\Page_Parser;
use Smush\Core\Parser\Parser;
use Smush\Core\Settings;

class Transformer {
	/**
	 * @var Array_Utils
	 */
	private $array_utils;
	/**
	 * @var Transform[]
	 */
	private $transforms;
	/**
	 * @var Parser
	 */
	private $parser;
	/**
	 * @var Settings|null
	 */
	private $settings;

	public function __construct() {
		$this->array_utils = new Array_Utils();
		$this->parser      = new Parser();
		$this->settings    = Settings::get_instance();
	}

	public function transform_content( $page_markup, $page_url ) {
		if ( empty( $page_markup ) || ! is_string( $page_markup ) ) {
			return $page_markup;
		}
		$transforms = $this->get_transforms();
		if ( empty( $transforms ) ) {
			// Nothing to do
			return $page_markup;
		}

		foreach ( $transforms as $transform ) {
			$page_markup = $this->apply_transform_to_content( $transform, $page_markup, $page_url );
		}

		return $page_markup;
	}

	private function apply_transform_to_content( $transform, $page_markup, $page_url ) {
		$parser      = new Page_Parser( $page_url, $page_markup );
		$parsed_page = $parser->parse_page();
		$transform->transform_page( $parsed_page );

		return $parsed_page->has_updates()
			? $parsed_page->get_updated_markup()
			: $parsed_page->get_page_markup();
	}

	/**
	 * @param $response array
	 * @param $request \WP_REST_Request
	 *
	 * @return array The $response with content replaced.
	 */
	public function transform_rest_response( $response, $request ) {
		if ( empty( $this->get_transforms() ) ) {
			return $response;
		}

		if ( wp_is_numeric_array( $response ) ) {
			foreach ( $response as $index => $item ) {
				$response[ $index ] = $this->transform_single_rest_item( $item, $request );
			}
		} else {
			$response = $this->transform_single_rest_item( $response, $request );
		}

		return apply_filters( 'wp_smush_transform_rest_response', $response, $request, $this );
	}

	private function transform_single_rest_item( $item, $request ) {
		if (
			$request->get_method() === 'GET'
			&& str_starts_with( $request->get_route(), '/wp/v2/media' )
		) {
			return $this->transform_rest_media_response( $item );
		}

		$item = $this->transform_rest_content_fields( $item );

		return apply_filters( 'wp_smush_transform_rest_response_item', $item, $request, $this );
	}

	/**
	 * @param array $response
	 *
	 * @return array
	 */
	private function transform_rest_content_fields( $response ) {
		if ( ! $this->is_array_post_like( $response ) ) {
			return $response;
		}

		foreach ( $this->get_rest_content_fields() as $rest_content_field ) {
			$response = $this->transform_rest_content_field( $rest_content_field, $response );
		}

		return $response;
	}

	private function transform_rest_content_field( $field, $response ) {
		if ( empty( $response[ $field ] ) ) {
			return $response;
		}

		$keys  = array( $field );
		$value = $response[ $field ];
		if ( isset( $value['rendered'] ) ) {
			$value  = $value['rendered'];
			$keys[] = 'rendered';
		}

		if ( ! is_string( $value ) ) {
			return $response;
		}

		$link = empty( $response['link'] ) ? '' : $response['link'];
		$this->array_utils->put_array_value(
			$response,
			$this->transform_content( $value, $link ),
			$keys
		);

		return $response;
	}

	private function get_rest_content_fields() {
		$fields = apply_filters( 'wp_smush_rest_content_fields', array( 'excerpt', 'content' ) );

		return array_unique( $fields );
	}

	private function is_array_post_like( $array ) {
		return is_array( $array )
		       && isset( $array['id'] )
		       && isset( $array['title'] )
		       && isset( $array['slug'] );
	}

	private function transform_rest_media_response( $response ) {
		$sizes = $this->array_utils->get_array_value( $response, array( 'media_details', 'sizes' ) );
		if ( ! empty( $sizes ) ) {
			foreach ( $sizes as $size_key => $size ) {
				if ( ! empty( $size['source_url'] ) ) {
					$response['media_details']['sizes'][ $size_key ]['source_url'] = $this->transform_url( $size['source_url'] );
				}
			}
		}

		if ( ! empty( $response['source_url'] ) ) {
			$response['source_url'] = $this->transform_url( $response['source_url'] );
		}

		return $this->transform_rest_content_field( 'description', $response );
	}

	public function transform_url( $url ) {
		foreach ( $this->get_transforms() as $transform ) {
			$url = $transform->transform_image_url( $url );
		}
		return $url;
	}

	/**
	 * @return Transform[]
	 */
	public function get_transforms() {
		if ( is_null( $this->transforms ) ) {
			$this->transforms = $this->prepare_transforms();
		}

		return $this->transforms;
	}

	private function prepare_transforms() {
		$transforms = $this->array_utils->ensure_array( apply_filters( 'wp_smush_content_transforms', array() ) );
		$filtered   = array();
		foreach ( $transforms as $key => $transform ) {
			if ( is_a( $transform, '\Smush\Core\Transform\Transform' ) && $transform->should_transform() ) {
				$filtered[ $key ] = $transform;
			}
		}

		return $filtered;
	}
}
