<?php

namespace Smush\Core\Parser;

class Element_Attribute {
	private $attribute;

	private $name;

	private $value;

	/**
	 * @var Image_URL[]
	 */
	private $image_urls;

	public function __construct( $name, $value, $attribute = '', $image_urls = array() ) {
		$this->name       = $name;
		$this->value      = new Value( $value );
		$this->image_urls = $image_urls;

		$this->attribute = empty( $attribute )
			? sprintf( '%s="%s"', $name, $value )
			: $attribute;
	}

	/**
	 * @return mixed
	 */
	public function get_attribute() {
		return $this->attribute;
	}

	/**
	 * @return mixed
	 */
	public function get_name() {
		return $this->name;
	}

	/**
	 * @return mixed
	 */
	public function get_value() {
		return $this->value->get();
	}

	public function set_value( $value ) {
		$this->value->set( $value );
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
		$updated = $this->attribute;
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
	 * @return Image_URL[]
	 */
	public function get_image_urls() {
		return $this->image_urls;
	}

	public function get_single_image_url() {
		$image_urls = $this->get_image_urls();
		return empty( $image_urls )
			? null
			: $image_urls[0];
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

	/**
	 * @param $updated
	 *
	 * @return string
	 */
	private function replace_value( $updated ) {
		return str_replace(
			$this->value->get_previous(),
			esc_attr( $this->value->get() ),
			$updated
		);
	}
}
