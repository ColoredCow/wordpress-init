<?php
/**
 * Smush page parser that is used by CDN and Lazy load modules.
 *
 * @since 3.2.2
 * @package Smush\Core\Modules\Helpers
 */

namespace Smush\Core\Modules\Helpers;

use Smush\Core\Server_Utils;
use Smush\Core\Settings;
use Smush\Core\Transform\Transformer;
use WP_Smush;

/**
 * Class Parser
 */
class Parser {

	/**
	 * CDN module status.
	 *
	 * @var bool $cdn
	 */
	private $cdn = false;

	/**
	 * Lazy load module status.
	 *
	 * @var bool $lazy_load
	 */
	private $lazy_load = false;

	/**
	 * Process background images.
	 *
	 * @since 3.2.2
	 * @var bool $background_images
	 */
	private $background_images = false;

	/**
	 * Server utils instance.
	 *
	 * @var Server_Utils
	 */
	private $server_utils;

	/**
	 * Transformer instance.
	 *
	 * @var Transformer
	 */
	private $transformer;

	public function __construct() {
		$this->server_utils = new Server_Utils();
		$this->transformer  = new Transformer();
	}

	/**
	 * Smush will __construct this class multiple times, but only once does it need to be initialized.
	 *
	 * @since 3.5.0  Moved from __construct().
	 */
	public function init() {
		if ( is_admin() || is_customize_preview() || wp_doing_ajax() || wp_doing_cron() ) {
			return;
		}

		$settings          = Settings::get_instance();
		$background_images = $settings->get( 'background_images' );
		if ( $background_images ) {
			$this->enable( 'background_images' );
		}
		if ( $settings->is_cdn_active() ) {
			$this->enable( 'cdn' );
		}

		$lazy_load_options = $settings->get_setting( 'wp-smush-lazy_load' );
		$this->enable( 'lazy_load' );
		if ( isset( $lazy_load_options['format']['iframe'] ) && $lazy_load_options['format']['iframe'] ) {
			$this->enable( 'iframes' );
		}

		// Start an output buffer before any output starts.
		add_action(
			'template_redirect',
			function () {
				ob_start( array( $this, 'parse_page' ) );
			},
			1
		);
	}

	/**
	 * Enable parser for selected module.
	 *
	 * @since 3.2.2
	 * @param string $module  Module ID.
	 */
	public function enable( $module ) {
		if ( ! in_array( $module, array( 'cdn', 'lazy_load', 'background_images' ), true ) ) {
			return;
		}

		$this->$module = true;
	}

	/**
	 * Disable parser for selected module.
	 *
	 * @since 3.2.2
	 * @param string $module  Module ID.
	 */
	public function disable( $module ) {
		if ( ! in_array( $module, array( 'cdn', 'lazy_load' ), true ) ) {
			return;
		}

		$this->$module = false;
	}

	/**
	 * Process images from current buffer content.
	 *
	 * Use DOMDocument class to find all available images in current HTML content and set attachment ID attribute.
	 *
	 * @since 3.0
	 * @since 3.2.2  Moved from \Smush\Core\Modules\CDN.
	 *
	 * @param string $content  Current buffer content.
	 *
	 * @return string
	 */
	public function parse_page( $content ) {
		// Do not parse page if CDN and Lazy load modules are disabled.
		if ( ! $this->cdn && ! $this->lazy_load ) {
			return $content;
		}

		if ( is_customize_preview() ) {
			return $content;
		}

		if ( empty( $content ) ) {
			return $content;
		}

		$content = $this->transformer->transform_content(
			$content,
			$this->server_utils->get_current_url()
		);

		return $content;
	}

	/**
	 * Process all images within <img> tags.
	 *
	 * @since 3.2.2
	 *
	 * @param string $content  Current buffer content.
	 *
	 * @return string
	 */
	private function process_images( $content ) {
		$images = $this->get_images_from_content( $content );

		if ( empty( $images ) ) {
			return $content;
		}

		foreach ( $images[0] as $key => $image ) {
			$img_src   = $images['src'][ $key ];
			$new_image = $image;

			// Update the image with correct CDN links.
			if ( $this->cdn ) {
				$new_image = WP_Smush::get_instance()->core()->mod->cdn->parse_image( $img_src, $new_image, $images['srcset'][ $key ], $images['type'][ $key ] );
			}

			$content = str_replace( $image, $new_image, $content );
		}

		return $content;
	}

	/**
	 * Process all images that are contained as background-images.
	 *
	 * @since 3.2.2
	 *
	 * @param string $content  Current buffer content.
	 *
	 * @return string
	 */
	private function process_background_images( $content ) {
		$images = self::get_background_images( $content );

		if ( empty( $images ) ) {
			return $content;
		}

		// Try to sort out the duplicate entries.
		$elements = array_unique( $images[0] );
		// TODO: check what this is and whether or not we are doing the same thing in the new framework
		$urls     = array_unique( $images['img_url'] );
		if ( count( $elements ) === count( $urls ) ) {
			$images[0]         = $elements;
			$images['img_url'] = $urls;
		}

		foreach ( $images[0] as $key => $image ) {
			$img_src   = $images['img_url'][ $key ];
			$new_image = $image;

			// Update the image with correct CDN links.
			$new_image = WP_Smush::get_instance()->core()->mod->cdn->parse_background_image( $img_src, $new_image );

			$content = str_replace( $image, $new_image, $content );
			/**
			 * Filter the current page content after process background images.
			 *
			 * @param string $content Current Page content.
			 * @param string $image   Backround Image tag without src.
			 * @param string $img_src Image src.
			 */
			$content = apply_filters( 'smush_after_process_background_images', $content, $image, $img_src );
		}

		return $content;
	}

	/**
	 * Get image tags from page content.
	 *
	 * @since 3.1.0
	 * @since 3.2.0  Moved to WP_Smush_Content from \Smush\Core\Modules\CDN
	 * @since 3.2.2  Moved to Parser from WP_Smush_Content
	 *
	 * Performance test: auto generated page with ~900 lines of HTML code, 84 images.
	 * - Smush 2.4.0: 82 matches, 104359 steps (~80 ms) <- does not match <source> images in <picture>.
	 * - Smush 2.5.0: 84 matches, 63791 steps (~51 ms).
	 *
	 * @param string $content  Page content.
	 *
	 * @return array
	 */
	public function get_images_from_content( $content ) {
		$images = array();

		/**
		 * Filter out only <body> content. As this was causing issues with escaped JS strings in <head>.
		 *
		 * @since 3.6.2
		 */
		if ( preg_match( '/(?=<body).*<\/body>/is', $content, $body ) ) {
			$content = $body[0];
		}

		$pattern = '/<(?P<type>img|source|iframe)\b(?>\s+(?:src=[\'"](?P<src>[^\'"]*)[\'"]|srcset=[\'"](?P<srcset>[^\'"]*)[\'"])|[^\s>]+|\s+)*>/is';
		// TODO: Deprecate in favor of wp_smush_images_from_content_regex
		$pattern = apply_filters( 'smush_images_from_content_regex', $pattern );

		if ( preg_match_all( $pattern, $content, $images ) ) {
			foreach ( $images as $key => $unused ) {
				// Simplify the output as much as possible, mostly for confirming test results.
				if ( is_numeric( $key ) && $key > 0 ) {
					unset( $images[ $key ] );
				}
			}
		}

		return $images;
	}

	/**
	 * Get background images from content.
	 *
	 * @since 3.2.2
	 *
	 * Performance test: auto generated page with ~900 lines of HTML code, 84 images (only 1 with background image).
	 * - Smush 2.4.0: 1 match, 522510 steps (~355 ms)
	 * - Smush 2.5.0: 1 match, 12611 steps, (~12 ms)
	 *
	 * @param string $content  Page content.
	 *
	 * @return array
	 */
	private static function get_background_images( $content ) {
		$images = array();

		$pattern = '/(?:background-image:\s*?url\(\s*[\'"]?(?P<img_url>.*?[^)\'"]+)[\'"]?\s*\))/i';
		// TODO: deprecate the following in favor of wp_smush_background_images_regex
		$pattern = apply_filters( 'smush_background_images_regex', $pattern );

		if ( preg_match_all( $pattern, $content, $images ) ) {
			foreach ( $images as $key => $unused ) {
				// Simplify the output as much as possible, mostly for confirming test results.
				if ( is_numeric( $key ) && $key > 0 ) {
					unset( $images[ $key ] );
				}
			}
		}

		/**
		 * Make sure that the image doesn't start and end with &quot;.
		 *
		 * @since 3.5.0
		 */
		$images['img_url'] = array_map(
			function ( $image ) {
				// Quote entities.
				$quotes = apply_filters( 'wp_smush_background_image_quotes', array( '&quot;', '&#034;', '&#039;', '&apos;' ) );

				$image = trim( $image );

				// Remove the starting quotes.
				if ( in_array( substr( $image, 0, 6 ), $quotes, true ) ) {
					$image = substr( $image, 6 );
				}

				// Remove the ending quotes.
				if ( in_array( substr( $image, -6 ), $quotes, true ) ) {
					$image = substr( $image, 0, -6 );
				}

				return $image;
			},
			$images['img_url']
		);

		return $images;
	}

	/**
	 * Add attribute to selected tag.
	 *
	 * @since 3.1.0
	 * @since 3.2.0  Moved to WP_Smush_Content from \Smush\Core\Modules\CDN
	 * @since 3.2.2  Moved to Parser from WP_Smush_Content
	 *
	 * @param string $element  Image element.
	 * @param string $name     Img attribute name (srcset, size, etc).
	 * @param string $value    Attribute value.
	 */
	public static function add_attribute( &$element, $name, $value = null ) {
		$closing = false === strpos( $element, '/>' ) ? '>' : ' />';
		$quotes  = false === strpos( $element, '"' ) ? '\'' : '"';

		if ( ! is_null( $value ) ) {
			$element = rtrim( $element, $closing ) . " {$name}={$quotes}{$value}{$quotes}{$closing}";
		} else {
			$element = rtrim( $element, $closing ) . " {$name}{$closing}";
		}
	}

	/**
	 * Get attribute from an HTML element.
	 *
	 * @since 3.2.0
	 * @since 3.2.2  Moved to Parser from WP_Smush_Content
	 *
	 * @param string $element  HTML element.
	 * @param string $name     Attribute name.
	 *
	 * @return string
	 */
	public static function get_attribute( $element, $name ) {
		preg_match( "/{$name}=['\"]([^'\"]+)['\"]/is", $element, $value );
		return isset( $value['1'] ) ? $value['1'] : '';
	}

	/**
	 * Remove attribute from selected tag.
	 *
	 * @since 3.2.0
	 * @since 3.2.2  Moved to Parser from WP_Smush_Content
	 *
	 * @param string $element    Image element.
	 * @param string $attribute  Img attribute name (srcset, size, etc).
	 */
	public static function remove_attribute( &$element, $attribute ) {
		$element = preg_replace( '/' . $attribute . '=[\'"](.*?)[\'"]/i', '', $element );
	}

	/**
	 * Get URLs from a string of content.
	 *
	 * This is mostly used to get the URLs from srcset and parse each single URL to use in CDN.
	 *
	 * Performance test: auto generated page with ~900 lines of HTML code, 84 images
	 * - Smush 2.4.0: 11957 matches, 237227 steps (~169 ms) <- many false positive matches.
	 * - Smush 2.5.0: 278 matches, 14509 steps, (~15 ms).
	 *
	 * @since 3.3.0
	 *
	 * @param string $content  Content.
	 *
	 * @return array
	 */
	public static function get_links_from_content( $content ) {
		$images = array();
		preg_match_all( '/(?:https?[^\s\'"]*)/is', $content, $images );
		return $images;
	}

}
