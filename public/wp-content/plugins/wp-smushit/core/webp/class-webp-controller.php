<?php

namespace Smush\Core\Webp;

use Smush\Core\Controller;
use Smush\Core\File_System;
use Smush\Core\Helper;
use Smush\Core\Media\Media_Item_Cache;
use Smush\Core\Settings;
use Smush\Core\Stats\Global_Stats;

class Webp_Controller extends Controller {
	const WEBP_OPTIMIZATION_ORDER = 20;
	const WEBP_TRANSFORM_PRIORITY = 30;
	/**
	 * @var Webp_Helper
	 */
	private $helper;
	/**
	 * @var Global_Stats
	 */
	private $global_stats;
	/**
	 * @var Media_Item_Cache
	 */
	private $media_item_cache;
	/**
	 * @var \WDEV_Logger|null
	 */
	private $logger;
	/**
	 * @var File_System
	 */
	private $fs;
	/**
	 * @var Settings
	 */
	private $settings;

	/**
	 * @var Webp_Configuration
	 */
	private $configuration;


	public function __construct() {
		$this->helper           = new Webp_Helper();
		$this->global_stats     = Global_Stats::get();
		$this->media_item_cache = Media_Item_Cache::get_instance();
		$this->logger           = Helper::logger();
		$this->fs               = new File_System();
		$this->configuration    = Webp_Configuration::get_instance();
		$this->settings         = Settings::get_instance();

		$this->register_action( 'wp_smush_png_jpg_converted', array( $this, 'delete_webp_versions_of_pngs' ), 10, 4 );
		$this->register_action( 'delete_attachment', array( $this, 'delete_webp_versions_before_delete' ) );
		$this->register_filter( 'wp_smush_optimizations', array(
			$this,
			'add_webp_optimization',
		), self::WEBP_OPTIMIZATION_ORDER, 2 );
		$this->register_filter( 'wp_smush_global_optimization_stats', array( $this, 'add_webp_global_stats' ) );
		$this->register_action( 'wp_smush_before_restore_backup', array(
			$this,
			'delete_webp_versions_on_restore',
		), 10, 2 );
		$this->register_action( 'wp_smush_settings_updated', array(
			$this,
			'maybe_mark_global_stats_as_outdated',
		), 10, 2 );
		$this->register_filter( 'wp_smush_content_transforms', array(
			$this,
			'add_webp_transform',
		), self::WEBP_TRANSFORM_PRIORITY );

		/** Ajax actions */
		$this->register_action( 'wp_ajax_smush_webp_toggle', array( $this, 'ajax_webp_toggle' ) );
		$this->register_action( 'wp_ajax_webp_switch_method', array( $this, 'ajax_switch_webp_method' ) );
		$this->register_action( 'wp_ajax_smush_webp_get_status', array(
			$this,
			'ajax_get_server_configuration_status',
		) );
		$this->register_action( 'wp_ajax_smush_webp_apply_htaccess_rules', array(
			$this,
			'ajax_apply_htaccess_rules',
		) );
		$this->register_action( 'wp_ajax_smush_webp_delete_all', array( $this, 'ajax_delete_all_webp_files' ) );
		$this->register_action( 'wp_ajax_smush_toggle_webp_wizard', array( $this, 'ajax_toggle_wizard' ) );
		// TODO: clean rules from .htaccess on deactivate plugin.

		$this->register_action( 'wp_enqueue_scripts', array( $this, 'maybe_enqueue_fallback_js' ) );

		$this->register_action( 'wp_smush_after_delete_all_webp_files', array( $this, 'maybe_revert_lock_file_on_delete_webp_files' ) );
	}

	/**
	 * @param $backup_full_path
	 * @param $attachment_id
	 *
	 * @return bool
	 */
	public function delete_webp_versions_on_restore( $backup_full_path, $attachment_id ) {
		$media_item = Media_Item_Cache::get_instance()->get( $attachment_id );
		if ( ! $media_item->is_valid() ) {
			return false;
		}

		$this->helper->delete_media_item_webp_versions( $media_item );

		return true;
	}

	public function delete_webp_versions_before_delete( $attachment_id ) {
		$media_item = $this->media_item_cache->get( $attachment_id );
		if ( $media_item->is_valid() ) {
			foreach ( $media_item->get_size_paths() as $size_path ) {
				$this->delete_webp_version( $size_path );
			}
		} else {
			$this->logger->error( sprintf( 'Count not delete webp versions of the media item [%d]', $attachment_id ) );
		}
	}

	public function delete_webp_versions_of_pngs( $attachment_id, $meta, $stats, $png_paths ) {
		foreach ( $png_paths as $png_path ) {
			$this->delete_webp_version( $png_path );
		}

		$this->helper->unset_webp_flag( $attachment_id );
	}

	public function delete_webp_version( $original_path ) {
		$webp_file_path = $this->helper->get_webp_file_path( $original_path );
		if ( $this->fs->file_exists( $webp_file_path ) ) {
			$this->fs->unlink( $webp_file_path );
		}
	}

	public function add_webp_optimization( $optimizations, $media_item ) {
		$optimization                              = new Webp_Optimization( $media_item );
		$optimizations[ $optimization->get_key() ] = $optimization;

		return $optimizations;
	}

	public function add_webp_global_stats( $stats ) {
		$stats[ Webp_Optimization::OPTIMIZATION_KEY ] = new Webp_Optimization_Global_Stats_Persistable();

		return $stats;
	}

	public function maybe_mark_global_stats_as_outdated( $old_settings, $settings ) {
		$old_webp_status = ! empty( $old_settings['webp_mod'] );
		$new_webp_status = ! empty( $settings['webp_mod'] );
		if ( $old_webp_status !== $new_webp_status ) {
			$this->global_stats->mark_as_outdated();
		}
	}

	public function ajax_switch_webp_method() {
		if ( ! check_ajax_referer( 'wp-smush-ajax', '_nonce', false ) ) {
			wp_send_json_error(
				array(
					'error_msg' => esc_html__( 'Nonce verification failed', 'wp-smushit' ),
				)
			);
		}

		if ( ! Helper::is_user_allowed( 'manage_options' ) || empty( $_POST['method'] ) ) {
			wp_send_json_error(
				array(
					'message' => __( 'User can not modify options', 'wp-smushit' ),
				),
				403
			);
		}

		$webp_method = wp_unslash( $_POST['method'] );
		$this->configuration->switch_method( $webp_method );

		wp_send_json_success();
	}

	public function ajax_webp_toggle() {
		check_ajax_referer( 'save_wp_smush_options' );

		$capability = is_multisite() ? 'manage_network' : 'manage_options';
		if ( ! Helper::is_user_allowed( $capability ) ) {
			wp_send_json_error(
				array(
					'message' => __( "You don't have permission to do this.", 'wp-smushit' ),
				),
				403
			);
		}

		$param       = isset( $_POST['param'] ) ? sanitize_text_field( wp_unslash( $_POST['param'] ) ) : '';
		$enable_webp = 'true' === $param;

		$this->configuration->toggle_module( $enable_webp );

		wp_send_json_success();
	}

	/**
	 * Check server configuration status and other info for WebP.
	 *
	 * Handles "Re-Check Status" button press on the WebP meta box.
	 */
	public function ajax_get_server_configuration_status() {
		if ( ! check_ajax_referer( 'wp-smush-webp-nonce', false, false ) || ! Helper::is_user_allowed( 'manage_options' ) ) {
			wp_send_json_error( esc_html__( "Either the nonce expired or you can't modify options. Please reload the page and try again.", 'wp-smushit' ) );
		}

		if ( $this->configuration->is_configured() ) {
			wp_send_json_success();
		}

		$error_message = $this->configuration->server_configuration()->get_configuration_message();
		wp_send_json_error( esc_html( $error_message ) );
	}

	/**
	 * Write apache rules for WebP support from .htaccess file.
	 * Handles the "Apply Rules" button press on the WebP meta box.
	 */
	public function ajax_apply_htaccess_rules() {
		if ( ! check_ajax_referer( 'wp-smush-webp-nonce', false, false ) || ! Helper::is_user_allowed( 'manage_options' ) ) {
			wp_send_json_error( "Either the nonce expired or you can't modify options. Please reload the page and try again." );
		}

		$last_error = $this->configuration->server_configuration()->apply_apache_rewrite_rules();

		if ( ! empty( $last_error ) ) {
			wp_send_json_error( wp_kses_post( $last_error ) );
		}

		wp_send_json_success();
	}

	/**
	 * Delete all webp images.
	 * Triggered by the "Delete WebP images" button in the webp tab.
	 */
	public function ajax_delete_all_webp_files() {
		check_ajax_referer( 'save_wp_smush_options' );

		$capability = is_multisite() ? 'manage_network' : 'manage_options';

		if ( ! Helper::is_user_allowed( $capability ) ) {
			wp_send_json_error(
				array(
					'message' => __( 'This user can not delete all WebP images.', 'wp-smushit' ),
				),
				403
			);
		}

		$this->helper->delete_all_webp_files();

		wp_send_json_success();
	}

	public function ajax_toggle_wizard() {
		if ( check_ajax_referer( 'wp-smush-webp-nonce', false, false ) && Helper::is_user_allowed( 'manage_options' ) ) {
			$this->configuration->toggle_wizard();

			wp_send_json_success();
		}
	}

	public function add_webp_transform( $transforms ) {
		$transforms['webp'] = new Webp_Transform();

		return $transforms;
	}

	public function maybe_enqueue_fallback_js() {
		if ( ! $this->settings->is_webp_fallback_active() ) {
			return;
		}
		wp_enqueue_script(
			'smush-webp-fallback',
			WP_SMUSH_URL . 'app/assets/js/smush-webp-fallback.min.js',
			array(),
			WP_SMUSH_VERSION,
			true
		);
	}

	public function maybe_revert_lock_file_on_delete_webp_files() {
		if ( $this->configuration->direct_conversion_enabled() ) {
			$this->configuration->server_configuration()->disable();
		}
	}
}
