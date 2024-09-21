<?php

namespace Smush\Core\CDN;

use Smush\Core\Settings;
use Smush\Core\Url_Utils;
use WP_Smush;
use WPMUDEV_Dashboard;

class CDN_Helper {
	/**
	 * Static instance
	 *
	 * @var self
	 */
	private static $instance;
	/**
	 * @var Settings
	 */
	private $settings;

	/**
	 * Flag to check if CDN is active.
	 *
	 * @var bool
	 */
	private $cdn_active;
	/**
	 * @var bool|mixed
	 */
	private $status;
	/**
	 * @var string
	 */
	private $cdn_base_url;
	/**
	 * @var Url_Utils
	 */
	private $url_utils;

	private $is_pro;

	private $supported_extensions = array(
		'gif',
		'jpg',
		'jpeg',
		'png',
		'webp',
	);

	public function __construct() {
		$this->settings  = Settings::get_instance();
		$this->url_utils = new Url_Utils();
	}

	private function is_url_extension_supported( $url ) {
		$extension = $this->url_utils->get_extension( $url );
		if ( ! $extension ) {
			return false;
		}

		return in_array( $extension, $this->supported_extensions, true );
	}

	private function is_url_scheme_supported( $url ) {
		$url_scheme = $this->url_utils->get_url_scheme( $url );

		return $url_scheme === 'http' || $url_scheme === 'https';
	}

	public function is_supported_url( $url ) {
		if (
			empty( trim( $url ) ) ||
			! $this->is_url_scheme_supported( $url ) ||
			! $this->is_url_extension_supported( $url )
		) {
			return false;
		}

		if ( str_starts_with( $url, content_url() ) ) {
			return true;
		}

		$uploads            = $this->get_cdn_custom_uploads_dir();
		$base_url_available = isset( $uploads['baseurl'] );
		if ( $base_url_available && str_starts_with( $url, $uploads['baseurl'] ) ) {
			return true;
		}

		$mapped_domain = $this->check_mapped_domain();
		if ( $mapped_domain ) {
			$url           = set_url_scheme( $url, 'http' );
			$mapped_domain = set_url_scheme( $mapped_domain, 'http' );
			return str_starts_with( $url, $mapped_domain );
		}

		return false;
	}

	/**
	 * Support for domain mapping plugin.
	 *
	 * @since 3.1.1
	 */
	private function check_mapped_domain() {
		if ( ! is_multisite() ) {
			return false;
		}

		if ( ! defined( 'DOMAINMAP_BASEFILE' ) ) {
			return false;
		}

		$domain = wp_cache_get( 'smush_mapped_site_domain', 'smush' );

		if ( ! $domain ) {
			global $wpdb;

			$domain = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT domain FROM {$wpdb->base_prefix}domain_mapping WHERE blog_id = %d ORDER BY id LIMIT 1",
					get_current_blog_id()
				)
			); // Db call ok.

			if ( null !== $domain ) {
				wp_cache_add( 'smush_mapped_site_domain', $domain, 'smush' );
			}
		}

		return $domain;
	}

	private function get_cdn_custom_uploads_dir() {
		/**
		 * There are chances for a custom uploads directory using UPLOADS constant.
		 *
		 * But some security plugins (for example, WP Hide & Security Enhance) will allow replacing paths via Nginx/Apache
		 * rules. So for this reason, we don't want the path to be replaced everywhere with the custom UPLOADS constant,
		 * we just want to let the user redefine it here, in the CDN.
		 *
		 * @param array $uploads {
		 *     Array of information about the upload directory.
		 *
		 * @type string $path Base directory and subdirectory or full path to upload directory.
		 * @type string $url Base URL and subdirectory or absolute URL to upload directory.
		 * @type string $subdir Subdirectory if uploads use year/month folders option is on.
		 * @type string $basedir Path without subdir.
		 * @type string $baseurl URL path without subdir.
		 * @type string|false $error False or error message.
		 * }
		 *
		 * Usage (replace /wp-content/uploads/ with /media/ directory):
		 *
		 * add_filter(
		 *     'smush_cdn_custom_uploads_dir',
		 *     function( $uploads ) {
		 *         $uploads['baseurl'] = 'https://example.com/media';
		 *         return $uploads;
		 *     }
		 * );
		 * @since 3.4.0
		 *
		 */
		return apply_filters( 'smush_cdn_custom_uploads_dir', wp_get_upload_dir() );
	}

	/**
	 * Static instance getter
	 */
	public static function get_instance() {
		if ( empty( self::$instance ) ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	public function is_cdn_active() {
		if ( is_null( $this->cdn_active ) ) {
			$this->cdn_active = $this->check_is_cdn_active();
		}
		return $this->cdn_active;
	}

	private function check_is_cdn_active() {
		$status = $this->get_cdn_status();

		return isset( $status->cdn_enabled ) && $status->cdn_enabled;
	}

	/**
	 * Only for tests, don't use in actual code
	 */
	public function set_cdn_active( $cdn_active ) {
		$this->cdn_active = $cdn_active;
	}

	public function get_cdn_status() {
		if ( is_null( $this->status ) ) {
			$this->status = $this->check_cdn_status();
		}
		return $this->status;
	}

	private function check_cdn_status() {
		// The CDN module needs to be active
		if ( ! $this->settings->is_cdn_active() ) {
			return false;
		}

		// All these are members only feature.
		if ( ! $this->is_pro() ) {
			return false;
		}

		// Disable CDN on staging.
		if ( $this->is_wpmudev_staging_environment() ) {
			return false;
		}

		// CDN is not enabled and not active.
		$status = $this->get_cdn_status_setting();
		if ( ! $status ) {
			return false;
		}

		return $status;
	}

	/**
	 * Only for tests, don't use in actual code
	 */
	public function set_cdn_status( $cdn_status ) {
		$this->status = $cdn_status;
	}

	/**
	 * @return bool
	 */
	private function is_wpmudev_staging_environment() {
		return isset( $_SERVER['WPMUDEV_HOSTING_ENV'] ) && 'staging' === $_SERVER['WPMUDEV_HOSTING_ENV'];
	}

	/**
	 * @return bool
	 */
	private function is_pro() {
		if ( is_null( $this->is_pro ) ) {
			$this->is_pro = $this->check_if_pro();
		}

		return $this->is_pro;
	}

	/**
	 * Only for tests, don't use in actual code
	 */
	public function set_is_pro( $is_pro ) {
		$this->is_pro = $is_pro;
	}

	private function check_if_pro() {
		/**
		 * Do not allow enabling CDN for sites that are not registered on the Hub
		 * Taken from 6e13e2f0
		 */
		return WP_Smush::is_pro()
			   // CDN will not work if there is no dashboard plugin installed.
			   && class_exists( 'WPMUDEV_Dashboard' )
			   // CDN will not work if site is not registered with the dashboard.
			   && WPMUDEV_Dashboard::$api->has_key();
	}

	public function get_cdn_base_url() {
		if ( is_null( $this->cdn_base_url ) ) {
			$this->cdn_base_url = $this->prepare_cdn_base_url();
		}

		return $this->cdn_base_url;
	}

	/**
	 * @return bool|mixed
	 */
	public function get_cdn_status_setting() {
		return $this->settings->get_setting( 'wp-smush-cdn_status' );
	}

	private function prepare_cdn_base_url() {
		$status  = $this->get_cdn_status();
		$site_id = absint( $status->site_id );

		return trailingslashit( "https://{$status->endpoint_url}/{$site_id}" );
	}

	public function get_cdn_status_string() {
		if ( ! $this->settings->is_cdn_active() ) {
			return 'disabled';
		}

		$cdn = $this->get_cdn_status_setting();
		if ( ! $cdn ) {
			return 'disabled';
		}

		if ( isset( $cdn->cdn_enabling ) && $cdn->cdn_enabling ) {
			return 'activating';
		}

		$plan      = isset( $cdn->bandwidth_plan ) ? $cdn->bandwidth_plan : 10;
		$bandwidth = isset( $cdn->bandwidth ) ? $cdn->bandwidth : 0;

		$percentage = round( 100 * $bandwidth / 1024 / 1024 / 1024 / $plan );

		if ( $percentage > 100 || 100 === (int) $percentage ) {
			return 'overcap';
		} elseif ( 90 <= (int) $percentage ) {
			return 'upgrade';
		}

		return 'enabled';
	}

	/**
	 * Generate CDN url from given image url.
	 *
	 * @param string $src Image url.
	 * @param array $args Query parameters.
	 *
	 * @return string
	 * @since 3.0
	 *
	 */
	public function generate_cdn_url( $original_url, $args = array() ) {
		// Do not continue in case we try this when cdn is disabled.
		if ( ! $this->is_cdn_active() ) {
			return $original_url;
		}

		/**
		 * Filter hook to alter image src before going through cdn.
		 *
		 * @param string $original_url Image src.
		 *
		 * @see smush_image_src_before_cdn filter if you need earlier access with the image element.
		 *
		 * @since 3.4.0
		 */
		$original_url = apply_filters( 'smush_filter_generate_cdn_url', $original_url );

		// Support for WP installs in subdirectories: remove the site url and leave only the file path.
		$path = str_replace( $this->get_site_url(), '', $original_url );

		// Parse url to get all parts.
		$url_parts = wp_parse_url( $path );

		// If path not found, do not continue.
		if ( empty( $url_parts['path'] ) ) {
			return $original_url;
		}

		$args = wp_parse_args( $this->get_cdn_parameters(), $args );

		// Replace base url with cdn base.
		$url = $this->get_cdn_base_url() . ltrim( $url_parts['path'], '/' );

		// Now we need to add our CDN parameters for resizing.
		return add_query_arg( $args, $url );
	}

	private function get_site_url() {
		$site_url = get_site_url();
		$home_url = get_home_url();
		if ( $site_url === $home_url ) {
			return $site_url;
		}

		$content_url = content_url();
		$root_url    = trailingslashit( dirname( $content_url ) );
		if (
			false === strpos( $root_url, $site_url ) &&
			false !== strpos( $root_url, $home_url )
		) {
			$site_url = $home_url;
		}

		return $site_url;
	}

	private function get_cdn_parameters() {
		$webp_cdn            = $this->settings->get( 'webp' );
		$lossy_level_setting = $this->settings->get_lossy_level_setting();
		$strip_exif          = $this->settings->get( 'strip_exif' );
		return array(
			'lossy' => $lossy_level_setting,
			'strip' => (int) $strip_exif,
			'webp'  => (int) $webp_cdn,
		);
	}

	/**
	 * @param $image_markup
	 *
	 * @return string|false String in the form of width x height e.g. "400x400" or false if it couldn't be guessed.
	 */
	public function guess_dimensions_from_image_markup( $image_markup ) {
		// Get registered image sizes.
		$image_sizes = WP_Smush::get_instance()->core()->image_dimensions();

		// Find the width and height attributes.
		$width  = false;
		$height = false;

		// Try to get the width and height from img tag.
		if ( preg_match( '/width=["|\']?(\b[[:digit:]]+(?!%)\b)["|\']?/i', $image_markup, $width_string ) ) {
			$width = $width_string[1];
		}

		if ( preg_match( '/height=["|\']?(\b[[:digit:]]+(?!%)\b)["|\']?/i', $image_markup, $height_string ) ) {
			$height = $height_string[1];
		}

		$size = array();

		// Detect WP registered image size from HTML class.
		if ( preg_match( '/size-([^"\'\s]+)[^"\']*["|\']?/i', $image_markup, $size ) ) {
			$size = array_pop( $size );

			if ( ! array_key_exists( $size, $image_sizes ) ) {
				return false;
			}

			// This is probably a correctly sized thumbnail - no need to resize.
			if ( (int) $width === $image_sizes[ $size ]['width'] || (int) $height === $image_sizes[ $size ]['height'] ) {
				return false;
			}

			// If this size exists in registered sizes, add argument.
			if ( 'full' !== $size ) {
				return (int) $image_sizes[ $size ]['width'] . 'x' . (int) $image_sizes[ $size ]['height'];
			}
		} else {
			// It's not a registered thumbnail size.
			if ( $width && $height ) {
				return (int) $width . 'x' . (int) $height;
			}
		}

		return false;
	}

	public function set_settings( $settings ) {
		$this->settings = $settings;
	}

	public function skip_image( $url, $image_markup ) {
		return apply_filters( 'smush_skip_image_from_cdn', false, $url, $image_markup );
	}
}
