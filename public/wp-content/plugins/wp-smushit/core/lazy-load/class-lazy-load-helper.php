<?php

namespace Smush\Core\Lazy_Load;

use Smush\Core\Array_Utils;
use Smush\Core\Server_Utils;
use Smush\Core\Settings;

class Lazy_Load_Helper {
	private $settings;
	/**
	 * @var array
	 */
	private $lazy_load_options;
	/**
	 * @var Array_Utils
	 */
	private $array_utils;
	/**
	 * @var Server_Utils
	 */
	private $server_utils;

	/**
	 * Static instance
	 *
	 * @var self
	 */
	private static $instance;

	/**
	 * Static instance getter
	 */
	public static function get_instance() {
		if ( empty( self::$instance ) ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	public function __construct() {
		$this->settings     = Settings::get_instance();
		$this->array_utils  = new Array_Utils();
		$this->server_utils = new Server_Utils();
	}

	public function should_skip_lazyload() {
		return is_admin() ||
				is_feed() ||
				is_preview() ||
				is_embed() ||
				! $this->settings->is_module_active( 'lazy_load' ) ||
				$this->skip_lazy_load() ||
				$this->is_excluded_uri() ||
				$this->is_excluded_wp_location();
	}



	/**
	 * @return mixed|null
	 */
	private function skip_lazy_load() {
		/**
		 * Internal filter to disable page parsing.
		 *
		 * Because the page parser module is universal, we need to make sure that all modules have the ability to skip
		 * parsing of certain pages. For example, lazy loading should skip if_preview() pages. In order to achieve this
		 * functionality, I've introduced this filter. Filter priority can be used to overwrite the $skip param.
		 *
		 * @param bool $skip Skip status.
		 *
		 * @since 3.2.2
		 *
		 * Note: This is named weirdly, but we are keeping it like it is for backward compatibility.
		 */
		$skip_lazyload = apply_filters_deprecated( 'wp_smush_should_skip_parse', array( false ), '3.16.1', 'wp_smush_should_skip_lazy_load' );

		return apply_filters( 'wp_smush_should_skip_lazy_load', $skip_lazyload );
	}

	private function get_lazy_load_options() {
		if ( ! $this->lazy_load_options ) {
			$setting                 = $this->settings->get_setting( 'wp-smush-lazy_load' );
			$this->lazy_load_options = $this->array_utils->ensure_array( $setting );
		}

		return $this->lazy_load_options;
	}

	public function set_lazy_load_options( $options ) {
		$this->lazy_load_options = $options;
	}

	public function is_native_lazy_loading_enabled() {
		$options = $this->get_lazy_load_options();
		return ! empty( $options['native'] );
	}

	public function get_excluded_classes() {
		$exclude_classes = $this->array_utils->get_array_value( $this->get_lazy_load_options(), 'exclude-classes' );
		return $this->array_utils->ensure_array( $exclude_classes );
	}

	public function is_noscript_fallback_enabled() {
		$noscript = $this->array_utils->get_array_value( $this->get_lazy_load_options(), 'noscript' );
		return empty( $noscript );
	}

	private function get_excluded_pages() {
		$exclude_pages = $this->array_utils->get_array_value( $this->get_lazy_load_options(), 'exclude-pages' );
		return array_filter( $this->array_utils->ensure_array( $exclude_pages ) );
	}

	public function is_excluded_uri() {
		$excluded_page_urls = $this->get_excluded_pages();
		if ( empty( $excluded_page_urls ) ) {
			return false;
		}

		$request_uri = $this->server_utils->get_request_uri();
		$uri_pattern = implode( '|', $excluded_page_urls );
		return ! ! preg_match( "#{$uri_pattern}#i", $request_uri );
	}

	private function get_wp_location() {
		$blog_is_frontpage = ( 'posts' === get_option( 'show_on_front' ) && ! is_multisite() ) ? true : false;

		if ( is_front_page() ) {
			return 'frontpage';
		} elseif ( is_home() && ! $blog_is_frontpage ) {
			return 'home';
		} elseif ( is_page() ) {
			return 'page';
		} elseif ( is_single() ) {
			return 'single';
		} elseif ( is_category() ) {
			return 'category';
		} elseif ( is_tag() ) {
			return 'tag';
		} elseif ( is_archive() ) {
			return 'archive';
		} else {
			return get_post_type();
		}
	}

	private function get_included_locations() {
		$include = $this->array_utils->get_array_value( $this->get_lazy_load_options(), 'include' );
		return $this->array_utils->ensure_array( $include );
	}

	public function is_excluded_wp_location() {
		$included_locations = $this->get_included_locations();
		if ( empty( $included_locations ) ) {
			// If not settings are set, probably, all are disabled.
			return true;
		}

		// Check if location is disabled.
		$wp_location = $this->get_wp_location();
		return isset( $included_locations[ $wp_location ] ) && empty( $included_locations[ $wp_location ] );
	}

	public function is_image_extension_supported( $ext, $src ) {
		if ( empty( $ext ) ) {
			return $this->is_image_without_extension_supported( $src );
		}

		$ext = strtolower( $ext );

		if ( ! in_array( $ext, array( 'jpg', 'jpeg', 'gif', 'png', 'svg', 'webp' ), true ) ) {
			return false;
		}

		return ! $this->is_format_excluded( $ext );
	}

	private function is_image_without_extension_supported( $src ) {
		$pattern = '#(gravatar.com|googleusercontent.com)#is';
		$pattern = apply_filters( 'wp_smush_without_extension_supported_regex', $pattern );
		return preg_match( $pattern, $src );
	}

	public function is_format_excluded( $needle ) {
		$supported_formats = $this->array_utils->get_array_value( $this->get_lazy_load_options(), 'format' );
		$supported_formats = $this->array_utils->ensure_array( $supported_formats );
		return in_array( false, $supported_formats, true ) && isset( $supported_formats[ $needle ] ) && ! $supported_formats[ $needle ];
	}
}
