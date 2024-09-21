<?php

namespace Smush\Core\Parser;

class Rest_Content {
	/**
	 * @var string
	 */
	private $content;
	/**
	 * @var Image_URL[]
	 */
	private $image_urls;

	public function __construct( $content, $image_urls ) {
		$this->content    = $content;
		$this->image_urls = $image_urls;
	}

	/**
	 * @return string
	 */
	public function get_content() {
		return $this->content;
	}

	public function get_image_urls() {
		return $this->image_urls;
	}

	public function has_updates() {
		foreach ( $this->image_urls as $image_url ) {
			if ( $image_url->has_updates() ) {
				return true;
			}
		}

		return false;
	}

	public function get_updated() {
		$updated = $this->content;
		foreach ( $this->image_urls as $image_url ) {
			if ( $image_url->has_updates() ) {
				$updated = str_replace(
					$image_url->get_previous_url(),
					$image_url->get_url(),
					$updated
				);
			}
		}

		return $updated;
	}
}
