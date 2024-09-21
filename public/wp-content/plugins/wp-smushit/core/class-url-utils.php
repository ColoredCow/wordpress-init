<?php

namespace Smush\Core;

class Url_Utils {
	/**
	 * @var Upload_Dir
	 */
	private $upload_dir;

	public function __construct() {
		$this->upload_dir = new Upload_Dir();
	}

	public function get_extension( $url ) {
		$path = wp_parse_url( $url, PHP_URL_PATH );
		if ( empty( $path ) ) {
			return false;
		}

		return strtolower( pathinfo( $path, PATHINFO_EXTENSION ) );
	}

	public function get_url_scheme( $url ) {
		$url_parts = wp_parse_url( $url );

		return empty( $url_parts['scheme'] )
			? false
			: $url_parts['scheme'];
	}

	/**
	 * @param $url
	 *
	 * @return string
	 * @see attachment_url_to_postid()
	 */
	public function make_media_url_relative( $url ) {
		$upload_url = $this->upload_dir->get_upload_url();
		$path       = $url;

		$site_url   = parse_url( $upload_url );
		$image_path = parse_url( $path );

		// Force the protocols to match if needed.
		if ( isset( $image_path['scheme'] ) && ( $image_path['scheme'] !== $site_url['scheme'] ) ) {
			$path = str_replace( $image_path['scheme'], $site_url['scheme'], $path );
		}

		if ( str_starts_with( $path, $upload_url . '/' ) ) {
			$path = substr( $path, strlen( $upload_url . '/' ) );
		}

		return $path;
	}

	public function guess_dimensions_from_image_url( $url ) {
		$width_height_string = array();

		if ( preg_match( '#-(\d+)x(\d+)\.(?:jpe?g|png|gif|webp|svg)#i', $url, $width_height_string ) ) {
			$width  = (int) $width_height_string[1];
			$height = (int) $width_height_string[2];

			if ( $width && $height ) {
				return array( $width, $height );
			}
		}

		return array( false, false );
	}
}
