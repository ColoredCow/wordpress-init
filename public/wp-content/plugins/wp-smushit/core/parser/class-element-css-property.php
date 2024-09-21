<?php

namespace Smush\Core\Parser;

class Element_CSS_Property {
	private $full;

	private $property;

	/**
	 * @var Value
	 */
	private $value;

	/**
	 * @var Image_URL[]
	 */
	private $image_urls;

	public function __construct( $full, $property, $value, $image_urls ) {
		$this->full       = $full;
		$this->property   = $property;
		$this->value      = new Value( $value );
		$this->image_urls = $image_urls;
	}

	/**
	 * @return mixed
	 */
	public function get_full() {
		return $this->full;
	}

	/**
	 * @return mixed
	 */
	public function get_property() {
		return $this->property;
	}

	/**
	 * @return mixed
	 */
	public function get_value() {
		return $this->value->get();
	}

	public function set_value( $new_value ) {
		$this->value->set( $new_value );
	}

	/**
	 * @return Image_URL[]
	 */
	public function get_image_urls() {
		return $this->image_urls;
	}

	public function has_updates() {
		if ( $this->value->has_updates() ) {
			return true;
		}

		foreach ( $this->image_urls as $image_url ) {
			if ( $image_url->has_updates() ) {
				return true;
			}
		}

		return false;
	}

	public function get_updated() {
		$updated = $this->full;

		if ( $this->value->has_updates() ) {
			// Replace whole value
			$updated = $this->replace_value( $updated );
		} else {
			// Replace the image URLs within the value
			$updated = $this->replace_image_urls( $updated );
		}

		return $updated;
	}

	/**
	 * @param $updated
	 *
	 * @return string
	 */
	private function replace_image_urls( $updated ) {
		foreach ( $this->image_urls as $image_url ) {
			if ( $image_url->has_updates() ) {
				$updated = str_replace(
					$image_url->get_previous_url(),
					esc_url_raw( $image_url->get_url() ),
					$updated
				);
			}
		}

		return $updated;
	}

	public function get_single_image_url() {
		$image_urls = $this->get_image_urls();
		return empty( $image_urls )
			? null
			: $image_urls[0];
	}

	private function replace_value( $updated ) {
		return str_replace(
			$this->value->get_previous(),
			esc_attr( $this->value->get() ),
			$updated
		);
	}
}
