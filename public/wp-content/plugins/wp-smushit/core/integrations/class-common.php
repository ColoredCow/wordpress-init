<?php
/**
 * Smush integration with various plugins: Common class
 *
 * @package Smush\Core\Integrations
 * @since 2.8.0
 *
 * @author Anton Vanyukov <anton@incsub.com>
 *
 * @copyright (c) 2018, Incsub (http://incsub.com)
 */

namespace Smush\Core\Integrations;

use Smush\Core\CDN\CDN_Helper;
use Smush\Core\Helper;
use Smush\Core\Modules\Helpers\Parser;
use Smush\Core\Modules\Smush;
use WP_Smush;

if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Singleton class class Common
 *
 * @since 2.8.0
 */
class Common {
	private $cdn_helper;

	/**
	 * Common constructor.
	 */
	public function __construct() {
		if ( is_admin() ) {
			// AJAX Thumbnail Rebuild integration.
			add_filter( 'wp_smush_media_image', array( $this, 'skip_images' ), 10, 2 );

			// Optimise WP retina 2x images.
			add_action( 'wr2x_retina_file_added', array( $this, 'smush_retina_image' ), 20, 3 );

			// Remove any pre_get_posts_filters added by WP Media Folder plugin.
			add_action( 'wp_smush_remove_filters', array( $this, 'remove_filters' ) );
		}

		// WPML integration.
		add_action( 'wpml_updated_attached_file', array( $this, 'wpml_undo_ignore_attachment' ) );
		add_action( 'wpml_after_duplicate_attachment', array( $this, 'wpml_ignore_duplicate_attachment' ), 10, 2 );
		add_action( 'wpml_after_copy_attached_file_postmeta', array( $this, 'wpml_ignore_duplicate_attachment' ), 10, 2 );

		// ReCaptcha lazy load.
		add_filter( 'smush_skip_iframe_from_lazy_load', array( $this, 'exclude_recaptcha_iframe' ), 10, 2 );

		// Soliloquy slider CDN support.
		add_filter( 'soliloquy_image_src', array( $this, 'soliloquy_image_src' ) );

		// Translate Press integration.
		add_filter( 'smush_skip_image_from_lazy_load', array( $this, 'trp_translation_editor' ) );

		// WP Maintenance Plugin integration.
		add_action( 'template_redirect', array( $this, 'wp_maintenance_mode' ) );

		// Buddyboss theme and its platform plugin integration.
		add_filter( 'wp_smush_cdn_before_process_src', array( $this, 'buddyboss_platform_modify_image_src' ), 10, 2 );

		// GiveWP donation form load lazyload images in iframe.
		add_action( 'give_donation_form_top', array( $this, 'givewp_skip_image_lazy_load' ), 0 );

		// Thumbnail regeneration handler.
		add_filter( 'wp_generate_attachment_metadata', array( $this, 'maybe_handle_thumbnail_generation' ) );
		$this->cdn_helper = CDN_Helper::get_instance();

		add_filter( 'wp_smush_should_transform_page', array( $this, 'should_transform_page' ) );
	}

	/**
	 * Remove any pre_get_posts_filters added by WP Media Folder plugin.
	 */
	public function remove_filters() {
		// Remove any filters added b WP media Folder plugin to get the all attachments.
		if ( class_exists( 'Wp_Media_Folder' ) ) {
			global $wp_media_folder;
			if ( is_object( $wp_media_folder ) ) {
				remove_filter( 'pre_get_posts', array( $wp_media_folder, 'wpmf_pre_get_posts1' ) );
				remove_filter( 'pre_get_posts', array( $wp_media_folder, 'wpmf_pre_get_posts' ), 0, 1 );
			}
		}

		global $wpml_query_filter;

		// If WPML is not installed, return.
		if ( ! is_object( $wpml_query_filter ) ) {
			return;
		}

		// Remove language filter and let all the images be smushed at once.
		if ( has_filter( 'posts_join', array( $wpml_query_filter, 'posts_join_filter' ) ) ) {
			remove_filter( 'posts_join', array( $wpml_query_filter, 'posts_join_filter' ), 10, 2 );
			remove_filter( 'posts_where', array( $wpml_query_filter, 'posts_where_filter' ), 10, 2 );
		}
	}

	/**************************************
	 *
	 * AJAX Thumbnail Rebuild
	 *
	 * @since 2.8
	 */

	/**
	 * AJAX Thumbnail Rebuild integration.
	 *
	 * If this is a thumbnail regeneration - only continue for selected thumbs
	 * (no need to regenerate everything else).
	 *
	 * @since 2.8.0
	 *
	 * @param string $smush_image  Image size.
	 * @param string $size_key     Thumbnail size.
	 *
	 * @return bool
	 */
	public function skip_images( $smush_image, $size_key ) {
		if ( empty( $_POST['regen'] ) || ! is_array( $_POST['regen'] ) ) { // Input var ok.
			return $smush_image;
		}

		$smush_sizes = wp_unslash( $_POST['regen'] ); // Input var ok.

		if ( in_array( $size_key, $smush_sizes, true ) ) {
			return $smush_image;
		}

		// Do not regenerate other thumbnails for regenerate action.
		return false;
	}

	/**************************************
	 *
	 * WP Retina 2x
	 */

	/**
	 * Smush Retina images for WP Retina 2x, Update Stats.
	 *
	 * @param int    $id           Attachment ID.
	 * @param string $retina_file  Retina image.
	 * @param string $image_size   Image size.
	 */
	public function smush_retina_image( $id, $retina_file, $image_size ) {
		$smush = WP_Smush::get_instance()->core()->mod->smush;

		/**
		 * Allows to Enable/Disable WP Retina 2x Integration
		 */
		$smush_retina_images = apply_filters( 'smush_retina_images', true );

		// Check if Smush retina images is enabled.
		if ( ! $smush_retina_images ) {
			return;
		}
		// Check for Empty fields.
		if ( empty( $id ) || empty( $retina_file ) || empty( $image_size ) ) {
			return;
		}

		// Do not smush if auto smush is turned off.
		if ( ! $smush->should_auto_smush( $id ) ) {
			return;
		}

		/**
		 * Allows to skip a image from smushing
		 *
		 * @param bool   $smush_image Smush image or not
		 * @param string $image_size  Size of image being smushed
		 * @param string $retina_file Retina file path.
		 * @param int    $id          Attachment ID.
		 *
		 * @since 3.9.6 Add two parameters for the filter.
		 */
		$smush_image = apply_filters( 'wp_smush_media_image', true, $image_size, $retina_file, $id );

		if ( ! $smush_image ) {
			return;
		}

		$stats = $smush->do_smushit( $retina_file );
		// If we squeezed out something, Update stats.
		if ( ! is_wp_error( $stats ) && ! empty( $stats['data'] ) && isset( $stats['data'] ) && $stats['data']->bytes_saved > 0 ) {
			$image_size = $image_size . '@2x';

			$this->update_smush_stats_single( $id, $stats, $image_size );
		}
	}

	/**
	 * Updates the smush stats for a single image size.
	 *
	 * @param int    $id           Attachment ID.
	 * @param array  $smush_stats  Smush stats.
	 * @param string $image_size   Image size.
	 */
	private function update_smush_stats_single( $id, $smush_stats, $image_size = '' ) {
		// Return, if we don't have image id or stats for it.
		if ( empty( $id ) || empty( $smush_stats ) || empty( $image_size ) ) {
			return;
		}

		$smush = WP_Smush::get_instance()->core()->mod->smush;
		$data  = $smush_stats['data'];
		// Get existing Stats.
		$stats = get_post_meta( $id, Smush::$smushed_meta_key, true );

		// Update existing Stats.
		if ( ! empty( $stats ) ) {
			// Update stats for each size.
			if ( isset( $stats['sizes'] ) ) {
				// if stats for a particular size doesn't exists.
				if ( empty( $stats['sizes'][ $image_size ] ) ) {
					// Update size wise details.
					$stats['sizes'][ $image_size ] = (object) $smush->array_fill_placeholders( $smush->get_size_signature(), (array) $data );
				} else {
					// Update compression percent and bytes saved for each size.
					$stats['sizes'][ $image_size ]->bytes   = $stats['sizes'][ $image_size ]->bytes + $data->bytes_saved;
					$stats['sizes'][ $image_size ]->percent = $stats['sizes'][ $image_size ]->percent + $data->compression;
				}
			}
		} else {
			// Create new stats.
			$stats = array(
				'stats' => array_merge(
					$smush->get_size_signature(),
					array(
						'api_version' => - 1,
						'lossy'       => - 1,
					)
				),
				'sizes' => array(),
			);

			$stats['stats']['api_version'] = $data->api_version;
			$stats['stats']['lossy']       = $data->lossy;
			$stats['stats']['keep_exif']   = ! empty( $data->keep_exif ) ? $data->keep_exif : 0;

			// Update size wise details.
			$stats['sizes'][ $image_size ] = (object) $smush->array_fill_placeholders( $smush->get_size_signature(), (array) $data );
		}

		// Calculate the total compression.
		$stats = WP_Smush::get_instance()->core()->total_compression( $stats );

		update_post_meta( $id, Smush::$smushed_meta_key, $stats );
	}

	/**************************************
	 *
	 * WPML
	 *
	 * @since 3.0
	 */

	/**
	 * Ignore WPML duplicated images from Smush.
	 *
	 * If WPML is duplicating images, we need to mark them as ignored for Smushing
	 * because the image is same for all those duplicated attachment posts. This is
	 * required to avoid wrong Smush stats.
	 *
	 * @param int $attachment_id            Original attachment ID.
	 * @param int $duplicated_attachment_id Duplicated attachment ID.
	 *
	 * @since 3.9.4
	 */
	public function wpml_ignore_duplicate_attachment( $attachment_id, $duplicated_attachment_id ) {
		// Ignore the image from Smush if duplicate.
		update_post_meta( $duplicated_attachment_id, 'wp-smush-ignore-bulk', true );
	}

	/**
	 * Remove an image from the ignored list.
	 *
	 * When a new image is added instead of duplicate, we need to remove it
	 * from the ignored list to make it available for Smushing.
	 *
	 * @param int $attachment_id Attachment ID.
	 *
	 * @since 3.9.4
	 */
	public function wpml_undo_ignore_attachment( $attachment_id ) {
		// Delete ignore flag.
		delete_post_meta( $attachment_id, 'wp-smush-ignore-bulk' );
	}

	/**
	 * Skip ReCaptcha iframes from lazy loading.
	 *
	 * @since 3.4.2
	 *
	 * @param bool   $skip  Should skip? Default: false.
	 * @param string $src   Iframe url.
	 *
	 * @return bool
	 */
	public function exclude_recaptcha_iframe( $skip, $src ) {
		return false !== strpos( $src, 'recaptcha/api' );
	}

	/**************************************
	 *
	 * Soliloquy slider
	 *
	 * @since 3.6.2
	 */

	/**
	 * Replace slider image links with CDN links.
	 *
	 * @param string $src  Image source.
	 *
	 * @return string
	 */
	public function soliloquy_image_src( $src ) {
		if ( ! $this->cdn_helper->is_cdn_active() || empty( $src ) ) {
			return $src;
		}

		if ( $this->cdn_helper->is_supported_url( $src ) ) {
			return $this->cdn_helper->generate_cdn_url( $src );
		}

		return $src;
	}

	/**************************************
	 *
	 * Translate Press
	 *
	 * @since 3.6.3
	 */

	/**
	 * Disables "Lazy Load" on Translate Press translate editor
	 *
	 * @param bool $skip  Should skip? Default: false.
	 *
	 * @return bool
	 */
	public function trp_translation_editor( $skip ) {
		if ( ! class_exists( '\TRP_Translate_Press' ) || ! isset( $_GET['trp-edit-translation'] ) ) {
			return $skip;
		}

		return true;
	}

	/**************************************
	 *
	 * WP Maintenance Plugin
	 *
	 * @since 3.8.0
	 */

	/**
	 * Disable page parsing when "Maintenance" is enabled
	 *
	 * @since 3.8.0
	 */
	public function wp_maintenance_mode() {
		if ( ! class_exists( '\MTNC' ) ) {
			return;
		}

		global $mt_options;

		if ( ! is_user_logged_in() && ! empty( $mt_options['state'] ) ) {
			add_filter( 'wp_smush_should_skip_lazy_load', '__return_true' );
		}
	}

	/**************************************
	 *
	 * Various modules
	 *
	 * @since 3.5
	 */

	/**
	 * CDN compatibility with Buddyboss platform
	 *
	 * @param string $src   Image source.
	 * @param string $image Actual image element.
	 *
	 * @return string Original or modified image source.
	 */
	public function buddyboss_platform_modify_image_src( $src, $image ) {
		if ( ! defined( 'BP_PLATFORM_VERSION' ) ) {
			return $src;
		}

		/**
		 * Compatibility with buddyboss theme and it's platform plugin.
		 *
		 * Buddyboss platform plugin uses the placeholder image as it's main src.
		 * And process_src() method below uses the same placeholder.png to create
		 * the srcset when "Automatic resizing" options is enabled for CDN.
		 * ---------
		 * Replacing the placeholder with actual image source as early as possible.
		 * Checks:
		 *   1. The image source contains buddyboss-platform in its string
		 *   2. The image source contains placeholder.png and is crucial because there are other
		 *      images as well which doesn't uses placeholder.
		 */
		if ( false !== strpos( $src, 'buddyboss-platform' ) && false !== strpos( $src, 'placeholder.png' ) ) {
			$new_src = Parser::get_attribute( $image, 'data-src' );

			if ( ! empty( $new_src ) ) {
				$src = $new_src;
			}
		}

		return $src;
	}

	/**
	 * Skip images from lazy loading on GiveWP forms.
	 *
	 * @since 3.8.8
	 */
	public function givewp_skip_image_lazy_load() {
		add_filter( 'wp_smush_should_skip_lazy_load', '__return_true' );
	}

	/**
	 * Add method to handle  thumbnail generation.
	 *
	 * We use this trick to call self::thumbnail_regenerate_handler()
	 * to avoid it called several times while calling wp_generate_attachment_metadata().
	 * 1. wp_generate_attachment_metadata -> wp_create_image_subsizes -> wp_update_attachment_metadata().
	 * 2. wp_generate_attachment_metadata -> _wp_make_subsizes        -> wp_update_attachment_metadata().
	 * 3. After calling wp_generate_attachment_metadata() => We should only add our filter here.
	 *
	 * @param  array $metadata Image metadata.
	 * @return array The provided metadata.
	 */
	public function maybe_handle_thumbnail_generation( $metadata ) {
		/**
		 * Add filter to handle thumbnail generation.
		 * We use a big priority because it seems WP has an issue for this case,
		 * after we remove this filter, all registered filters after this priority of this hook will not call.
		 */
		add_filter( 'wp_update_attachment_metadata', array( $this, 'thumbnail_regenerate_handler' ), 99999, 2 ); // S3 is using priority 110.
		return $metadata;
	}

	/**
	 * Callback for 'wp_update_attachment_metadata' WordPress hook used by Smush to detect
	 * regenerated thumbnails and mark them as pending for (re)smush.
	 *
	 * @since 3.9.2
	 *
	 * @param array $new_meta New metadata.
	 * @param int   $attachment_id The attachment ID.
	 *
	 * @since 3.9.6 Disable this filter while async uploading,
	 * and update compatible with S3, and only call it after generated metadata.
	 *
	 * @return array
	 */
	public function thumbnail_regenerate_handler( $new_meta, $attachment_id ) {
		// Remove the filter as we are no longer need it.
		remove_filter( 'wp_update_attachment_metadata', array( $this, 'thumbnail_regenerate_handler' ), 99999 );

		/**
		 * Skip if there is WP uploading a new image,
		 * or the attachment is not an image or does not have thumbnails.
		 */
		if (
			empty( $new_meta['sizes'] )
			// Async uploading.
			|| isset( $_POST['post_id'] ) || isset( $_FILES['async-upload'] )
			// Smushed it, don't need to check it again.
			|| did_action( 'wp_smush_before_smush_file' )
			// Disable when restoring.
			|| did_action( 'wp_smush_before_restore_backup' )
			// Only support Image.
			|| ! Helper::is_smushable( $attachment_id )
		) {
			return $new_meta;
		}

		// Skip if the attachment has an active smush operation or in being restored by Smush or ignored.
		if ( get_transient( 'smush-in-progress-' . $attachment_id ) || get_transient( 'wp-smush-restore-' . $attachment_id ) || get_post_meta( $attachment_id, 'wp-smush-ignore-bulk', true ) ) {
			return $new_meta;
		}

		$smush_meta = get_post_meta( $attachment_id, Smush::$smushed_meta_key, true );

		// Skip attachments without Smush meta key.
		if ( empty( $smush_meta ) ) {
			return $new_meta;
		}

		$size_increased = false;
		/**
		 * Get attached file
		 * If there is generating the image, S3 also downloaded it,
		 * so we don't need to download it if it doesn't exist.
		 */
		$attached_file = Helper::get_attached_file( $attachment_id, 'original' );// S3+.
		// If the main file does not exist, there is not generating the thumbnail, return.
		if ( ! file_exists( $attached_file ) ) {
			return $new_meta;
		}

		// We need only the last $new_meta['sizes'] element of each subsequent call
		// to wp_update_attachment_metadata() made by wp_create_image_subsizes().
		$size = array_keys( $new_meta['sizes'] )[ count( $new_meta['sizes'] ) - 1 ];

		$file_dir  = dirname( $attached_file );
		$file_name = $file_dir . '/' . $new_meta['sizes'][ $size ]['file'];

		$actual_size = is_file( $file_name ) ? filesize( $file_name ) : false;
		$stored_size = isset( $smush_meta['sizes'][ $size ]->size_after ) ? $smush_meta['sizes'][ $size ]->size_after : false;

		// Only do the comparison if we have both the actual and the database stored size.
		if ( $actual_size && $stored_size ) {
			$size_increased = $actual_size > 1.01 * $stored_size;// Not much we can do if save less than 1%.
		}

		// File size increased? Let's remove all
		// Smush related meta keys for this attachment.
		if ( $size_increased ) {
			/**
			 * When regenerate an image, we only generate the sub-sizes,
			 * so we don't need to delete the saving data of PNG2JPG.
			 * And similar for resizing, we also added filter for big_image_size_threshold,
			 * and we don't use the resizing meta for any conditional, so it's ok to keep it.
			 */
			// Remove stats and update cache.
			WP_Smush::get_instance()->core()->remove_stats( $attachment_id );
		}

		return $new_meta;
	}

	public function should_transform_page( $should_transform ) {
		return $should_transform
		       && ! $this->is_smartcrawl_analysis_request()
		       && ! $this->is_page_builder_request();
	}

	/**
	 * Compatibility with SmartCrawl readability analysis.
	 * Do not process page on analysis.
	 *
	 * @since 3.3.0
	 */
	private function is_smartcrawl_analysis_request() {
		$wds_analysis = filter_input( INPUT_POST, 'action', FILTER_SANITIZE_SPECIAL_CHARS );
		if ( ! is_null( $wds_analysis ) && 'wds-analysis-recheck' === $wds_analysis ) {
			return true;
		}

		if ( null !== filter_input( INPUT_GET, 'wds-frontend-check', FILTER_SANITIZE_SPECIAL_CHARS ) ) {
			return true;
		}

		return false;
	}

	/**
	 * Check if this is one of the known page builders.
	 *
	 * @return bool
	 * @since 3.5.1
	 *
	 */
	private function is_page_builder_request() {
		// Oxygen builder.
		if ( defined( 'SHOW_CT_BUILDER' ) && SHOW_CT_BUILDER ) {
			return true;
		}

		// Oxygen builder as well.
		if ( null !== filter_input( INPUT_GET, 'ct_builder' ) ) {
			return true;
		}

		// Divi builder.
		if ( null !== filter_input( INPUT_GET, 'et_fb' ) ) {
			return true;
		}

		// Beaver builder.
		if ( null !== filter_input( INPUT_GET, 'fl_builder' ) ) {
			return true;
		}

		// Thrive Architect Builder.
		if ( null !== filter_input( INPUT_GET, 'tve' ) && null !== filter_input( INPUT_GET, 'tcbf' ) ) {
			return true;
		}

		// Tatsu page builder.
		if ( null !== filter_input( INPUT_GET, 'tatsu' ) ) {
			return true;
		}

		// BuddyBoss' AJAX requests. They do something strange and end up defining
		// DOING_AJAX on template_redirect after self::parse_page() runs. That makes
		// our lazy load page parsing break some of their AJAX requests.
		if ( function_exists( 'bbp_is_ajax' ) && bbp_is_ajax() ) {
			return true;
		}

		return false;
	}
}
