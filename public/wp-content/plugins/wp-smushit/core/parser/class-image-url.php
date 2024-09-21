<?php

namespace Smush\Core\Parser;

class Image_URL {
	/**
	 * @var Value
	 */
	private $url;

	private $ext;

	private $base_url;

	private $absolute_url;

	private $scheme;

	public function __construct( $url, $ext, $base_url ) {
		$this->url      = new Value( $url );
		$this->ext      = $ext;
		$this->base_url = $base_url;
	}

	/**
	 * @return string
	 */
	public function get_url() {
		return $this->url->get();
	}

	/**
	 * @param $url
	 *
	 * @return bool
	 */
	public function set_url( $url ) {
		/**
		 * If the new value matches the absolute URL then there is no need to update.
		 * The url class {@see Value::set()} also internally checks if the value is the same as before.
		 */
		$current_absolute_url = $this->get_absolute_url();
		if ( $url === $current_absolute_url ) {
			return false;
		}

		return $this->url->set( $url );
	}

	public function get_base_url() {
		return $this->base_url;
	}

	public function get_previous_url() {
		return $this->url->get_previous();
	}

	/**
	 * @return mixed
	 */
	public function get_ext() {
		return $this->ext;
	}

	public function has_updates() {
		return $this->url->has_updates();
	}

	public function get_scheme() {
		if ( is_null( $this->scheme ) ) {
			$this->scheme = $this->prepare_scheme();
		}
		return $this->scheme;
	}

	private function prepare_scheme() {
		$url_parts = wp_parse_url( $this->get_absolute_url() );

		return $url_parts
			? $url_parts['scheme']
			: '';
	}

	public function get_absolute_url() {
		if ( empty( $this->get_base_url() ) ) {
			// If a base URL is not provided we don't try to make an absolute URL
			return $this->get_url();
		}

		if ( is_null( $this->absolute_url ) ) {
			$this->absolute_url = $this->prepare_absolute_url();
		}

		return $this->absolute_url;
	}

	private function prepare_absolute_url() {
		if ( $this->is_scheme_missing_from_original() ) {
			$scheme   = is_ssl() ? 'https:' : 'http:';
			$full_url = $scheme . $this->url->get();
		} else if ( $this->is_original_url_absolute() ) {
			$full_url = $this->url->get();
		} else if ( $this->original_url_starts_with_slash() ) {
			$full_url = $this->make_url_relative_to_host();
		} else {
			$full_url = $this->make_url_relative_to_base();
		}

		return $this->resolve_relative_url( $full_url );
	}

	private function is_original_url_absolute() {
		$scheme = parse_url( $this->url->get(), PHP_URL_SCHEME );

		return ! empty( $scheme );
	}

	private function is_scheme_missing_from_original() {
		return str_starts_with( $this->url->get(), '//' );
	}

	/**
	 * @param $full_url
	 *
	 * @return string
	 */
	private function resolve_relative_url( $full_url ) {
		$path          = parse_url( $full_url, PHP_URL_PATH );
		$resolved_path = str_replace( '/./', '/', $path );

		$pattern = '@/[a-zA-Z0-9-_.]*/\.{2}/@i';
		while ( preg_match( $pattern, $resolved_path ) ) {
			$resolved_path = preg_replace( $pattern, '/', $resolved_path );
		}

		return str_replace( $path, $resolved_path, $full_url );
	}

	/**
	 * @return bool
	 */
	private function original_url_starts_with_slash() {
		return str_starts_with( $this->url->get(), '/' );
	}

	/**
	 * @return string
	 */
	private function make_url_relative_to_host() {
		$scheme = parse_url( $this->base_url, PHP_URL_SCHEME );
		$host   = parse_url( $this->base_url, PHP_URL_HOST );

		return trailingslashit( "$scheme://$host" ) . ltrim( $this->url->get(), '/' );
	}

	/**
	 * @return string
	 */
	private function make_url_relative_to_base() {
		return trailingslashit( $this->base_url ) . ltrim( $this->url->get(), '/' );
	}
}
