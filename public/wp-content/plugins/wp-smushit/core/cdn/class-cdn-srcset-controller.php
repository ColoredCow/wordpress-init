<?php
/**
 * CDN class: CDN
 *
 * @package Smush\Core\Modules
 * @version 3.0
 */

namespace Smush\Core\CDN;

use Smush\Core\Controller;
use Smush\Core\Modules\Helpers;
use Smush\Core\Settings;
use stdClass;
use WP_Error;

if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Class CDN
 * TODO: cleanup everything that has been moved to the transform class
 */
class CDN_Srcset_Controller extends Controller {

	/**
	 * Module slug.
	 *
	 * @var string
	 */
	protected $slug = 'cdn';

	/**
	 * Whether module is pro or not.
	 *
	 * @var string
	 */
	protected $is_pro = true;

	/**
	 * Site URL.
	 *
	 * @since 3.8.0
	 * @var string
	 */
	private $site_url;

	/**
	 * Home URL.
	 *
	 * @since 3.8.0
	 * @var string
	 */
	private $home_url;

	/**
	 * Supported file extensions.
	 *
	 * @var array $supported_extensions
	 */
	private $supported_extensions = array(
		'gif',
		'jpg',
		'jpeg',
		'png',
		'webp',
	);

	/**
	 * @var CDN_Helper
	 */
	private $cdn_helper;
	/**
	 * @var Settings|null
	 */
	private $settings;
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

	/**
	 * CDN constructor.
	 *
	 * @since 3.2.2
	 */
	public function __construct() {
		$this->settings   = Settings::get_instance();
		$this->cdn_helper = CDN_Helper::get_instance();

		// We do this to save extra checks when we load images later on in code.
		$this->site_url = get_site_url();

		if ( is_multisite() && ! is_subdomain_install() ) {
			$this->home_url = get_home_url( get_current_site()->id );
		} else {
			$this->home_url = get_home_url();
		}

		$priority = defined( 'WP_SMUSH_CDN_DELAY_SRCSET' ) && WP_SMUSH_CDN_DELAY_SRCSET ? 1000 : 99;
		// Update responsive image srcset and sizes if required.
		$this->register_filter( 'wp_calculate_image_srcset', array( $this, 'update_image_srcset' ), $priority, 5 );
		if ( $this->settings->get( 'auto_resize' ) ) {
			$this->register_filter( 'wp_calculate_image_sizes', array( $this, 'update_image_sizes' ), 1, 2 );
		}
	}

	public function should_run() {
		return ! is_admin() && $this->cdn_helper->is_cdn_active();
	}

	/**************************************
	 *
	 * PUBLIC METHODS CDN
	 *
	 * @see parse_image()
	 * @see parse_background_image()
	 * @see process_src()
	 * @see update_image_srcset()
	 * @see update_image_sizes()
	 * @see update_cdn_image_src_args()
	 * @see process_cdn_status()
	 */

	/**
	 * Parse image for CDN.
	 *
	 * @param string $src Image URL.
	 * @param string $image Image tag (<img>).
	 * @param string $srcset Image srcset content.
	 * @param string $type Element type. Accepts: 'img', 'source' or 'iframe'. Default: 'img'.
	 *
	 * @return string
	 * @since 3.5.0  Added $srcset and $type params.
	 *
	 * @since 3.2.2  Moved out to a separate function.
	 */
	public function parse_image( $src, $image, $srcset = '', $type = 'img' ) {
		/**
		 * Filter to skip a single image from cdn.
		 *
		 * @param bool $skip Should skip? Default: false.
		 * @param string $src Image url.
		 * @param array|bool $image Image tag or false.
		 */
		if ( apply_filters( 'smush_skip_image_from_cdn', false, $src, $image ) ) {
			return $image;
		}

		$new_image = $image;

		/**
		 * Support for source in picture element.
		 */
		if ( 'source' === $type && $srcset ) {
			$links = Helpers\Parser::get_links_from_content( $srcset );
			if ( ! isset( $links[0] ) || ! is_array( $links[0] ) ) {
				return $new_image;
			}

			foreach ( $links[0] as $link ) {
				if ( ! $this->cdn_helper->is_supported_url( $link ) ) {
					continue;
				}

				// Replace the data-envira-srcset of the image with CDN link.
				$src = $link;
				$src = $this->cdn_helper->generate_cdn_url( $src );
				if ( $src ) {
					// Replace the src of the image with CDN link.
					$new_image = str_replace( $link, $src, $new_image );
				}
			}

			// We can exit early, to avoid additional parsing.
			return $new_image;
		}

		// Store the original $src to be used later on.
		$original_src = $src;

		/**
		 * Filter hook to alter image src at the earliest.
		 *
		 * @param string $src Image src.
		 * @param string $image Image tag.
		 */
		$src = apply_filters( 'wp_smush_cdn_before_process_src', $src, $image );

		// Make sure this image is inside a supported directory. Try to convert to valid path.
		if ( $this->cdn_helper->is_supported_url( $src ) ) {
			$src = $this->process_src( $image, $src, false );

			// Replace the src of the image with CDN link.
			if ( ! empty( $src ) ) {
				$new_image = preg_replace( '#(src=["|\'])' . $original_src . '(["|\'])#i', '\1' . $src . '\2', $new_image, 1 );
			}

			/**
			 * See if srcset is already set.
			 *
			 * The preg_match is required to make sure that srcset is not already defined.
			 * For the majority of images, srcset will be parsed as part of the wp_calculate_image_srcset filter.
			 * But some images, for example, logos in Avada - will add their own srcset. For such images - generate our own.
			 *
			 * @since 3.9.10 Add 2 new parameters `$original_src, $image`  for filter `smush_skip_adding_srcset` to allow user disable auto-resize for specific image.
			 */
			if ( ! preg_match( '/srcset=["\'](.*?smushcdn\.com[^"\']+)["\']/i', $image ) ) {
				if ( $this->settings->get( 'auto_resize' ) && ! apply_filters( 'smush_skip_adding_srcset', false, $original_src, $image ) ) {
					list( $srcset, $sizes ) = $this->generate_srcset( $original_src );

					if ( ! is_null( $srcset ) && false !== $srcset ) {
						// Remove possibly empty srcset attribute.
						Helpers\Parser::remove_attribute( $new_image, 'srcset' );
						Helpers\Parser::add_attribute( $new_image, 'srcset', $srcset );
					}

					if ( ! is_null( $srcset ) && false !== $sizes ) {
						// Remove possibly empty sizes attribute.
						Helpers\Parser::remove_attribute( $new_image, 'sizes' );
						Helpers\Parser::add_attribute( $new_image, 'sizes', $sizes );
					}
				} else {
					$data_attributes = array( 'srcset', 'data-srcset' );
					foreach ( $data_attributes as $attribute ) {
						$links = Helpers\Parser::get_attribute( $new_image, $attribute );
						$links = Helpers\Parser::get_links_from_content( $links );
						if ( isset( $links[0] ) && is_array( $links[0] ) ) {
							foreach ( $links[0] as $link ) {
								if ( ! $this->cdn_helper->is_supported_url( $link ) ) {
									continue;
								}

								// Replace the data-envira-srcset of the image with CDN link.
								$src = $link;
								$src = $this->cdn_helper->generate_cdn_url( $src );
								if ( $src ) {
									// Replace the src of the image with CDN link.
									$new_image = str_replace( $link, $src, $new_image );
								}
							}
						}
					}
				}
			}
		}

		// Support for 3rd party lazy loading plugins.
		$lazy_attributes = array( 'data-src', 'data-lazy-src', 'data-lazyload', 'data-original' );
		foreach ( $lazy_attributes as $attr ) {
			$data_src = Helpers\Parser::get_attribute( $new_image, $attr );
			if ( $this->cdn_helper->is_supported_url( $data_src ) ) {
				$cdn_image = $this->process_src( $image, $data_src );
				Helpers\Parser::remove_attribute( $new_image, $attr );
				Helpers\Parser::add_attribute( $new_image, $attr, $cdn_image );
			}
		}

		/**
		 * Filter hook to alter image tag before replacing the image in content.
		 *
		 * @param string $image Image tag.
		 */
		return apply_filters( 'smush_cdn_image_tag', $new_image );
	}

	/**
	 * Parse background image for CDN.
	 *
	 * @param string $src Image URL.
	 * @param string $image Image tag (<img>).
	 *
	 * @return string
	 * @since 3.2.2
	 *
	 */
	public function parse_background_image( $src, $image ) {
		/**
		 * Filter to skip a single image from cdn.
		 *
		 * @param bool $skip Should skip? Default: false.
		 * @param string $src Image url.
		 * @param array|bool $image Image tag or false.
		 */
		if ( apply_filters( 'smush_skip_background_image_from_cdn', false, $src, $image ) ) {
			return $image;
		}

		$new_image = $image;

		// Store the original $src to be used later on.
		$original_src = $src;

		/**
		 * Filter hook to alter background image src at the earliest.
		 *
		 * @param string $src Image src.
		 * @param string $image Image tag.
		 */
		$src = apply_filters( 'smush_cdn_before_process_background_src', $src, $image );

		// Make sure this image is inside a supported directory. Try to convert to valid path.
		if ( $this->cdn_helper->is_supported_url( $src ) ) {
			$src = $this->process_src( $image, $src );

			// Replace the src of the image with CDN link.
			if ( ! empty( $src ) ) {
				$new_image = str_replace( $original_src, $src, $new_image );
			}
		}

		/**
		 * Filter hook to alter image tag before replacing the background image in content.
		 *
		 * @param string $image Image tag.
		 */
		return apply_filters( 'smush_cdn_bg_image_tag', $new_image );
	}

	/**
	 * Process src link and convert to CDN link.
	 *
	 * @param string $image Image tag.
	 * @param string $src Image src attribute.
	 * @param bool $resizing Add resizing arguments. Defaults to true.
	 *                          We should never add resize arguments to the images from src. But we can and should
	 *                          add them to the srcset and other possible attributes.
	 *
	 * @return string
	 * @since 3.2.1
	 *
	 */
	private function process_src( $image, $src, $resizing = true ) {
		$args = array();

		// Don't need to auto resize - return default args.
		if ( $resizing && $this->settings->get( 'auto_resize' ) ) {
			/**
			 * Filter hook to alter image src arguments before going through cdn.
			 *
			 * @param array $args Arguments.
			 * @param string $src Image src.
			 * @param string $image Image tag.
			 */
			$args = apply_filters( 'smush_image_cdn_args', array(), $image );
		}

		/**
		 * Filter hook to alter image src before going through cdn.
		 *
		 * @param string $src Image src.
		 * @param string $image Image tag.
		 */
		$src = apply_filters( 'smush_image_src_before_cdn', $src, $image );

		// Generate cdn url from local url.
		$src = $this->cdn_helper->generate_cdn_url( $src, $args );

		/**
		 * Filter hook to alter image src after replacing with CDN base.
		 *
		 * @param string $src Image src.
		 * @param string $image Image tag.
		 */
		return apply_filters( 'smush_image_src_after_cdn', $src, $image );
	}

	/**
	 * Filters an array of image srcset values, replacing each URL with resized CDN urls.
	 *
	 * Keep the existing srcset sizes if already added by WP, then calculate extra sizes
	 * if required.
	 *
	 * @param array $sources One or more arrays of source data to include in the 'srcset'.
	 * @param array $size_array Array of width and height values in pixels.
	 * @param string $image_src The 'src' of the image.
	 * @param array $image_meta The image metadata as returned by 'wp_get_attachment_metadata()'.
	 * @param int $attachment_id Image attachment ID or 0.
	 *
	 * @return array $sources
	 * @since 3.0
	 *
	 */
	public function update_image_srcset( $sources, $size_array, $image_src, $image_meta, $attachment_id = 0 ) {
		if ( ! is_array( $sources ) || ! $this->cdn_helper->is_supported_url( $image_src ) ) {
			return $sources;
		}

		$main_image_url = false;

		// Try to get image URL from attachment ID.
		if ( empty( $attachment_id ) ) {
			$url            = wp_get_attachment_url( $attachment_id );
			$main_image_url = $url;
		}

		foreach ( $sources as $i => $source ) {
			if ( ! $this->is_valid_url( $source['url'] ) ) {
				continue;
			}

			if ( apply_filters( 'smush_cdn_skip_image', false, $source['url'], $source ) ) {
				continue;
			}

			list( $width, $height ) = $this->get_size_from_file_name( $source['url'] );

			// The file already has a resized version as a thumbnail.
			if ( 'w' === $source['descriptor'] && $width === (int) $source['value'] ) {
				$sources[ $i ]['url'] = $this->cdn_helper->generate_cdn_url( $source['url'] );
				continue;
			}

			// If don't have attachment id, get original image by removing dimensions from url.
			if ( empty( $url ) ) {
				$url = $this->get_url_without_dimensions( $source['url'] );
			}

			$args = array();
			// If we got size from url, add them.
			if ( ! empty( $width ) && ! empty( $height ) ) {
				// Set size arg.
				$args = array(
					'size' => "{$width}x{$height}",
				);
			}

			// Replace with CDN url.
			$sources[ $i ]['url'] = $this->cdn_helper->generate_cdn_url( $url, $args );
		}

		// Set additional sizes if required.
		if ( $this->settings->get( 'auto_resize' ) ) {
			$sources = $this->set_additional_srcset( $sources, $size_array, $main_image_url, $image_meta, $image_src );

			// Make it look good.
			ksort( $sources );
		}

		return $sources;
	}

	/**
	 * Update image sizes for responsive size.
	 *
	 * @param string $sizes A source size value for use in a 'sizes' attribute.
	 * @param array $size Requested size.
	 *
	 * @return string
	 * @since 3.0
	 *
	 */
	public function update_image_sizes( $sizes, $size ) {
		if ( ! doing_filter( 'the_content' ) ) {
			return $sizes;
		}

		// Get maximum content width.
		$content_width = $this->max_content_width();

		if ( is_array( $size ) && $size[0] < $content_width ) {
			return $sizes;
		}

		return sprintf( '(max-width: %1$dpx) 100vw, %1$dpx', $content_width );
	}

	/**
	 * Add resize arguments to content image src.
	 *
	 * @param array $args Current arguments.
	 * @param object $image Image tag object from DOM.
	 *
	 * @return array $args
	 * @since 3.0
	 *
	 */
	public function update_cdn_image_src_args( $args, $image ) {
		$dimensions = $this->cdn_helper->guess_dimensions_from_image_markup( $image );
		if ( $dimensions ) {
			$args['size'] = $dimensions;
		}

		return $args;
	}

	/**
	 * Process CDN status.
	 *
	 * @param array|WP_Error $status Status in JSON format.
	 *
	 * @return stdClass|WP_Error
	 * @since 3.0
	 * @since 3.1  Moved from Ajax class.
	 *
	 */
	public function process_cdn_status( $status ) {
		if ( is_wp_error( $status ) ) {
			return $status;
		}

		$status = json_decode( $status['body'] );

		// Too many requests.
		if ( is_null( $status ) ) {
			return new WP_Error( 'too_many_requests', __( 'Too many requests, please try again in a moment.', 'wp-smushit' ) );
		}

		// Some other error from API.
		if ( ! $status->success ) {
			return new WP_Error( $status->data->error_code, $status->data->message );
		}

		return $status->data;
	}

	/**
	 * Filters the API response.
	 *
	 * Allows modification of the response data after inserting
	 * embedded data (if any) and before echoing the response data.
	 *
	 * @param array $response Response data to send to the client.
	 *
	 * @return array
	 * @since 3.6.0
	 *
	 */
	public function filter_rest_api_response( $response ) {
		if ( ! $this->settings->get( 'rest_api_support' ) ) {
			return $response;
		}

		if ( ! is_array( $response ) || ! isset( $response['content']['rendered'] ) ) {
			return $response;
		}

		$images = Helpers\Parser::get_links_from_content( $response['content']['rendered'] );

		if ( ! isset( $images[0] ) || empty( $images[0] ) ) {
			return $response;
		}

		foreach ( $images[0] as $key => $image ) {
			if ( ! $this->cdn_helper->is_supported_url( $image ) ) {
				continue;
			}

			// Replace the data-envira-srcset of the image with CDN link.
			$image = $this->cdn_helper->generate_cdn_url( $image );
			if ( $image ) {
				// Replace the src of the image with CDN link.
				$response['content']['rendered'] = str_replace( $images[0][ $key ], $image, $response['content']['rendered'] );
			}
		}

		return $response;
	}

	/**************************************
	 *
	 * PRIVATE METHODS
	 *
	 * Functions that are used by the public methods of this CDN class.
	 *
	 * @since 3.0.0:
	 *
	 * @see is_valid_url()
	 * @see get_size_from_file_name()
	 * @see get_url_without_dimensions()
	 * @see max_content_width()
	 * @see set_additional_srcset()
	 * @see generate_srcset()
	 * @see maybe_generate_srcset()
	 * @see is_supported_path()
	 */

	/**
	 * Check if we can use the image URL in CDN.
	 *
	 * @param string $url Image URL.
	 *
	 * @return bool
	 * @since 3.0
	 *
	 */
	private function is_valid_url( $url ) {
		$parsed_url = wp_parse_url( $url );

		if ( ! $parsed_url ) {
			return false;
		}

		// No host or path found.
		if ( ! isset( $parsed_url['host'] ) || ! isset( $parsed_url['path'] ) ) {
			return false;
		}

		// If not supported extension - return false.
		if ( ! in_array( strtolower( pathinfo( $parsed_url['path'], PATHINFO_EXTENSION ) ), $this->supported_extensions, true ) ) {
			return false;
		}

		return true;
	}

	/**
	 * Try to determine height and width from strings WP appends to resized image filenames.
	 *
	 * @param string $src The image URL.
	 *
	 * @return array An array consisting of width and height.
	 * @since 3.0
	 *
	 */
	private function get_size_from_file_name( $src ) {
		$size = array();

		if ( preg_match( '/-(\d+)x(\d+)\.(?:' . implode( '|', $this->supported_extensions ) . ')$/i', $src, $size ) ) {
			// Get size and width.
			$width  = (int) $size[1];
			$height = (int) $size[2];

			// Handle retina images.
			if ( strpos( $src, '@2x' ) ) {
				$width  = 2 * $width;
				$height = 2 * $height;
			}

			// Return width and height as array.
			if ( $width && $height ) {
				return array( $width, $height );
			}
		}

		return array( false, false );
	}

	/**
	 * Get full size image url from resized one.
	 *
	 * @param string $src Image URL.
	 *
	 * @return string
	 * @since 3.0
	 *
	 */
	private function get_url_without_dimensions( $src ) {
		if ( ! preg_match( '/(-\d+x\d+)\.(' . implode( '|', $this->supported_extensions ) . ')(?:\?.+)?$/i', $src, $src_parts ) ) {
			return $src;
		}

		// Remove WP's resize string to get the original image.
		$original_src = str_replace( $src_parts[1], '', $src );

		// Upload directory.
		$upload_dir = wp_get_upload_dir();

		// Extracts the file path to the image minus the base url.
		$file_path = substr( $original_src, strlen( $upload_dir['baseurl'] ) );

		// Continue only if the file exists.
		if ( file_exists( $upload_dir['basedir'] . $file_path ) ) {
			return $original_src;
		}

		// Revert to source if file does not exist.
		return $src;
	}

	/**
	 * Get $content_width global var value.
	 *
	 * @return bool|string
	 * @since 3.0
	 *
	 */
	private function max_content_width() {
		// Get global content width (if content width is empty, set 1900).
		$content_width = isset( $GLOBALS['content_width'] ) ? (int) $GLOBALS['content_width'] : 1920;

		// Avoid situations, when themes misuse the global.
		if ( 0 === $content_width ) {
			$content_width = 1920;
		}

		// Check to see if we are resizing the images (can not go over that value).
		$resize_sizes = $this->settings->get_setting( 'wp-smush-resize_sizes' );

		if ( isset( $resize_sizes['width'] ) && $resize_sizes['width'] < $content_width ) {
			return $resize_sizes['width'];
		}

		return $content_width;
	}

	/**
	 * Filters an array of image srcset values, and add additional values.
	 *
	 * @param array $sources An array of image urls and widths.
	 * @param array $size_array Array of width and height values in pixels.
	 * @param string $url Image URL.
	 * @param array $image_meta The image metadata.
	 * @param string $image_src The src of the image.
	 *
	 * @return array $sources
	 * @since 3.0
	 *
	 */
	private function set_additional_srcset( $sources, $size_array, $url, $image_meta, $image_src = '' ) {
		$content_width = $this->max_content_width();

		// If url is empty, try to get from src.
		if ( empty( $url ) ) {
			$url = $this->get_url_without_dimensions( $image_src );
		}

		// We need to add additional dimensions.
		$full_width     = $image_meta['width'];
		$full_height    = $image_meta['height'];
		$current_width  = $size_array[0];
		$current_height = $size_array[1];
		// Get width and height calculated by WP.
		list( $constrained_width, $constrained_height ) = wp_constrain_dimensions( $full_width, $full_height, $current_width, $current_height );

		// Calculate base width.
		// If $constrained_width sizes are smaller than current size, set maximum content width.
		if ( abs( $constrained_width - $current_width ) <= 1 && abs( $constrained_height - $current_height ) <= 1 ) {
			$base_width = $content_width;
		} else {
			$base_width = $current_width;
		}

		$current_widths = array_keys( $sources );
		$new_sources    = array();

		/**
		 * Filter to add/update/bypass additional srcsets.
		 *
		 * If empty value or false is retured, additional srcset
		 * will not be generated.
		 *
		 * @param array|bool $additional_multipliers Additional multipliers.
		 */
		$additional_multipliers = apply_filters(
			'smush_srcset_additional_multipliers',
			array(
				0.2,
				0.4,
				0.6,
				0.8,
				1,
				2,
				3,
			)
		);

		// Continue only if additional multipliers found or not skipped.
		// Filter already documented in class-cdn.php.
		if ( $this->cdn_helper->skip_image( $url, false ) || empty( $additional_multipliers ) ) {
			return $sources;
		}

		// Loop through each multipliers and generate image.
		foreach ( $additional_multipliers as $multiplier ) {
			// New width by multiplying with original size.
			$new_width = (int) ( $base_width * $multiplier );

			// In most cases - going over the current width is not recommended and probably not what the user is expecting.
			if ( $new_width > $current_width ) {
				continue;
			}

			// If a nearly sized image already exist, skip.
			foreach ( $current_widths as $_width ) {
				// If +- 50 pixel difference - skip.
				if ( abs( $_width - $new_width ) < 50 || ( $new_width > $full_width ) ) {
					continue 2;
				}
			}

			// We need the width as well...
			$dimensions = wp_constrain_dimensions( $current_width, $current_height, $new_width );

			// Arguments for cdn url.
			$args = array(
				'size' => "{$new_width}x{$dimensions[1]}",
			);

			// Add new srcset item.
			$new_sources[ $new_width ] = array(
				'url'        => $this->cdn_helper->generate_cdn_url( $url, $args ),
				'descriptor' => 'w',
				'value'      => $new_width,
			);
		}

		// Assign new srcset items to existing ones.
		if ( ! empty( $new_sources ) ) {
			// Loop through each items and replace/add.
			foreach ( $new_sources as $_width_key => $_width_values ) {
				$sources[ $_width_key ] = $_width_values;
			}
		}

		return $sources;
	}

	/**
	 * Try to generate the srcset for the image.
	 *
	 * @param string $src Image source.
	 *
	 * @return array|bool
	 * @since 3.0
	 *
	 */
	public function generate_srcset( $src ) {
		/**
		 * Try to get the attachment URL.
		 *
		 * TODO: attachment_url_to_postid() can be resource intensive and cause 100% CPU spikes.
		 *
		 * @see https://core.trac.wordpress.org/ticket/41281
		 */
		$attachment_id = attachment_url_to_postid( $src );
		$image_meta    = array();
		$width         = 0;
		$height        = 0;

		// Try to get width and height from image.
		if ( $attachment_id ) {
			list( $src, $width, $height ) = wp_get_attachment_image_src( $attachment_id, 'full' );
			$image_meta = wp_get_attachment_metadata( $attachment_id );
		}

		// Revolution slider fix: images will always return 0 height and 0 width.
		if ( $src && ( empty( $width ) || empty( $height ) ) ) {
			// Try to get the dimensions directly from the file.
			list( $width, $height ) = $this->get_image_size( $src );
		}

		if ( empty( $width ) || empty( $height ) ) {
			return false;
		}

		// This is an image placeholder - do not generate srcset.
		if ( $width === $height && 1 === $width ) {
			return false;
		}

		if ( empty( $image_meta ) ) {
			$image_meta = array(
				'width'  => $width,
				'height' => $height,
			);
		}

		$size_array = array( absint( $width ), absint( $height ) );
		$srcset     = wp_calculate_image_srcset( $size_array, $src, $image_meta, $attachment_id );

		/**
		 * In some rare cases, the wp_calculate_image_srcset() will not generate any srcset, because there are
		 * not image sizes defined. If that is the case, try to revert to our custom maybe_generate_srcset() to
		 * generate the srcset string.
		 *
		 * Also srcset will not be generated for images that are not part of the media library (no $attachment_id).
		 */
		if ( ! $srcset ) {
			$srcset = $this->maybe_generate_srcset( $width, $height, $src, $image_meta );
		}

		$sizes = $srcset ? wp_calculate_image_sizes( $size_array, $src, $image_meta, $attachment_id ) : false;

		return array( $srcset, $sizes );
	}

	/**
	 * Try to generate srcset.
	 *
	 * @param int $width Attachment width.
	 * @param int $height Attachment height.
	 * @param string $src Image source.
	 * @param array $meta Image meta.
	 *
	 * @return bool|string
	 * @since 3.0
	 *
	 */
	private function maybe_generate_srcset( $width, $height, $src, $meta ) {
		$sources[ $width ] = array(
			'url'        => $this->cdn_helper->generate_cdn_url( $src ),
			'descriptor' => 'w',
			'value'      => $width,
		);

		$sources = $this->set_additional_srcset(
			$sources,
			array( absint( $width ), absint( $height ) ),
			$src,
			$meta
		);

		$srcsets = array();

		if ( 1 < count( $sources ) ) {
			foreach ( $sources as $source ) {
				$srcsets[] = str_replace( ' ', '%20', $source['url'] ) . ' ' . $source['value'] . $source['descriptor'];
			}
			return implode( ',', $srcsets );
		}

		return false;
	}

	/**
	 * Try to get the image dimensions from a local file.
	 *
	 * @param string $url Image URL.
	 *
	 * @return array|false
	 * @since 3.4.0
	 */
	private function get_image_size( $url ) {
		if ( $this->site_url !== $this->home_url ) {
			$url = str_replace( $this->site_url, $this->home_url, $url );
		}

		$path = wp_make_link_relative( $url );
		$path = wp_normalize_path( ABSPATH . $path );

		if ( ! file_exists( $path ) ) {
			return false;
		}

		return wp_getimagesize( $path );
	}

}
