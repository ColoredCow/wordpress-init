<?php
/**
 * Dashboard page class: Dashboard extends Abstract_Page.
 *
 * @since 3.8.6
 * @package Smush\App\Pages
 */

namespace Smush\App\Pages;

use Smush\App\Abstract_Summary_Page;
use Smush\App\Interface_Page;
use Smush\Core\Array_Utils;
use Smush\Core\CDN\CDN_Helper;
use Smush\Core\Settings;
use Smush\Core\Webp\Webp_Configuration;
use Smush\Core\Media_Library\Background_Media_Library_Scanner;
use WP_Smush;

if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Class Dashboard
 */
class Dashboard extends Abstract_Summary_Page implements Interface_Page {

	/**
	 * Function triggered when the page is loaded before render any content.
	 */
	public function on_load() {}

	/**
	 * Enqueue scripts.
	 *
	 * @since 3.9.0
	 *
	 * @param string $hook Hook from where the call is made.
	 */
	public function enqueue_scripts( $hook ) {
		// Scripts for Configs.
		$this->enqueue_configs_scripts();

		// Scripts for Tutorials.
		$this->enqueue_tutorials_scripts();
	}

	/**
	 * Register meta boxes.
	 */
	public function register_meta_boxes() {
		if ( ! is_multisite() || ( is_multisite() && ! is_network_admin() ) ) {
			$this->add_meta_box(
				'dashboard/summary',
				null,
				array( $this, 'summary_meta_box' ),
				null,
				null,
				'summary',
				array(
					'box_class'         => 'sui-box sui-summary sui-summary-smush-metabox',
					'box_content_class' => false,
				)
			);
		}

		/**
		 * Meta boxes on the left side.
		 */

		if ( self::should_render( 'bulk' ) ) {
			$this->add_meta_box(
				'dashboard/bulk',
				__( 'Bulk Smush', 'wp-smushit' ),
				array( $this, 'bulk_compress_meta_box' ),
				null,
				null,
				'box-dashboard-left'
			);
		}

		if ( self::should_render( 'integrations' ) ) {
			$this->add_meta_box(
				'dashboard/integrations',
				__( 'Integrations', 'wp-smushit' ),
				array( $this, 'integrations_meta_box' ),
				null,
				null,
				'box-dashboard-left'
			);
		}

		if ( self::should_render( 'cdn' ) ) {
			$this->add_meta_box(
				'dashboard/cdn',
				__( 'CDN', 'wp-smushit' ),
				array( $this, 'cdn_meta_box' ),
				array( $this, 'cdn_meta_box_header' ),
				null,
				'box-dashboard-left'
			);
		}

		/**
		 * Meta boxes on the right side.
		 */
		if ( ! WP_Smush::is_pro() ) {
			$this->add_meta_box(
				'dashboard/upsell/upsell',
				__( 'Smush Pro', 'wp-smushit' ),
				array( $this, 'upsell_meta_box' ),
				array( $this, 'upsell_meta_box_header' ),
				null,
				'box-dashboard-right'
			);
		}

		if ( self::should_render( 'directory' ) ) {
			$this->add_meta_box(
				'dashboard/directory',
				__( 'Directory Smush', 'wp-smushit' ),
				array( $this, 'directory_compress_meta_box' ),
				null,
				null,
				'box-dashboard-right'
			);
		}

		if ( self::should_render( 'lazy_load' ) ) {
			$this->add_meta_box(
				'dashboard/lazy-load',
				__( 'Lazy Load', 'wp-smushit' ),
				array( $this, 'lazy_load_meta_box' ),
				null,
				null,
				'box-dashboard-right'
			);
		}

		if ( self::should_render( 'webp' ) ) {
			$this->add_meta_box(
				'dashboard/webp',
				__( 'Local WebP', 'wp-smushit' ),
				array( $this, 'local_webp_meta_box' ),
				array( $this, 'local_webp_meta_box_header' ),
				null,
				'box-dashboard-right'
			);
		}
	}

	/**
	 * Summary meta box.
	 *
	 * @since 3.8.6
	 */
	public function summary_meta_box() {
		$upsell_url_cdn = add_query_arg(
			array(
				'utm_source'   => 'smush',
				'utm_medium'   => 'plugin',
				'utm_campaign' => 'summary_cdn',
			),
			$this->upgrade_url
		);

		$upsell_url_webp = add_query_arg(
			array(
				'utm_source'   => 'smush',
				'utm_medium'   => 'plugin',
				'utm_campaign' => 'summary_local_webp',
			),
			$this->upgrade_url
		);

		$core         = WP_Smush::get_instance()->core();
		$array_utils  = new Array_Utils();
		$global_stats = $core->get_global_stats();
		$args         = array(
			'human_bytes'       => $array_utils->get_array_value( $global_stats, 'human_bytes' ),
			'cdn_status'        => CDN_Helper::get_instance()->get_cdn_status_string(),
			'is_cdn'            => $this->settings->get( 'cdn' ),
			'is_lazy_load'      => $this->settings->get( 'lazy_load' ),
			'is_local_webp'     => $this->settings->get( 'webp_mod' ),
			'resize_count'      => $array_utils->get_array_value( $global_stats, 'count_resize' ),
			'total_optimized'   => $array_utils->get_array_value( $global_stats, 'count_images' ),
			'stats_percent'     => $array_utils->get_array_value( $global_stats, 'savings_percent' ),
			'upsell_url_cdn'    => $upsell_url_cdn,
			'upsell_url_webp'   => $upsell_url_webp,
			'webp_configured'   => Webp_Configuration::get_instance()->is_configured(),
			'percent_grade'     => $array_utils->get_array_value( $global_stats, 'percent_grade' ),
			'percent_metric'    => $array_utils->get_array_value( $global_stats, 'percent_metric' ),
			'percent_optimized' => $array_utils->get_array_value( $global_stats, 'percent_optimized' ),
		);

		$this->view( 'dashboard/summary-meta-box', $args );
	}

	/**
	 * Bulk compress meta box.
	 *
	 * @since 3.8.6
	 */
	public function bulk_compress_meta_box() {
		$array_utils  = new Array_Utils();
		$core         = WP_Smush::get_instance()->core();
		$global_stats = $core->get_global_stats();
		$upsell_url   = add_query_arg(
			array(
				'utm_source'   => 'smush',
				'utm_medium'   => 'plugin',
				'utm_campaign' => 'dashboard_bulk_smush',
			),
			$this->upgrade_url
		);

		$bg_optimization               = WP_Smush::get_instance()->core()->mod->bg_optimization;
		$background_processing_enabled = $bg_optimization->should_use_background();
		$background_in_processing      = $background_processing_enabled && $bg_optimization->is_in_processing();
		$background_scan_status        = Background_Media_Library_Scanner::get_instance()->get_background_process()->get_status();

		$args = array(
			'total_count'                     => (int) $array_utils->get_array_value( $global_stats, 'count_total' ),
			'uncompressed'                    => (int) $array_utils->get_array_value( $global_stats, 'remaining_count' ),
			'upsell_url'                      => $upsell_url,
			'background_processing_enabled'   => $background_processing_enabled,
			'background_in_processing'        => $background_in_processing,
			'background_in_processing_notice' => $bg_optimization->get_in_process_notice(),
			'bulk_background_process_dead'    => $background_processing_enabled && $bg_optimization->is_dead(),
			'scan_background_process_dead'    => $background_scan_status->is_dead(),
		);

		$this->view( 'dashboard/bulk/meta-box', $args );
	}

	/**
	 * Integrations meta box.
	 *
	 * @since 3.8.6
	 */
	public function integrations_meta_box() {
		$upsell_url = add_query_arg(
			array(
				'utm_source'   => 'smush',
				'utm_medium'   => 'plugin',
				'utm_campaign' => 'dashboard_integrations',
			),
			$this->upgrade_url
		);

		$integration_fields = $this->settings->get_integrations_fields();

		$key = array_search( 'js_builder', $integration_fields, true );
		if ( $key ) {
			unset( $integration_fields[ $key ] );
		}

		$args = array(
			'basic_features' => Settings::$basic_features,
			'fields'         => $integration_fields,
			'is_pro'         => WP_Smush::is_pro(),
			'settings'       => $this->settings->get(),
			'upsell_url'     => $upsell_url,
		);

		$this->view( 'dashboard/integrations-meta-box', $args );
	}

	/**
	 * Local WebP meta box.
	 *
	 * @since 3.8.6
	 */
	public function local_webp_meta_box() {
		$upsell_url = add_query_arg(
			array(
				'utm_source'   => 'smush',
				'utm_medium'   => 'plugin',
				'utm_campaign' => 'smush-dashboard-local-webp-upsell',
			),
			$this->upgrade_url
		);

		$webp_configuration = Webp_Configuration::get_instance();
		$is_webp_configured = $webp_configuration->is_configured();
		$error_message      = $is_webp_configured ? '' : $webp_configuration->server_configuration()->get_configuration_message();

		$args = array(
			'is_configured'  => $is_webp_configured,
			'error_message'  => $error_message,
			'is_webp_active' => $this->settings->get( 'webp_mod' ),
			'upsell_url'     => $upsell_url,
		);

		$this->view( 'dashboard/webp/meta-box', $args );
	}

	/**
	 * Local WebP meta box footer.
	 *
	 * @since 3.8.6
	 */
	public function local_webp_meta_box_header() {
		$title = __( 'Local WebP', 'wp-smushit' );
		$this->view( 'dashboard/webp/meta-box-header', compact( 'title' ) );
	}

	/**
	 * Toole meta box.
	 *
	 * @since 3.8.6
	 */
	public function tools_meta_box() {
		$is_resize_detection = $this->settings->get( 'detection' );
		$this->view( 'dashboard/tools-meta-box', compact( 'is_resize_detection' ) );
	}

	/**
	 * Directory compress meta box.
	 *
	 * @since 3.8.6
	 */
	public function directory_compress_meta_box() {
		$images = WP_Smush::get_instance()->core()->mod->dir->get_image_errors();

		$args = array(
			'images' => array_slice( $images, 0, 4 ),
			'errors' => WP_Smush::get_instance()->core()->mod->dir->get_image_errors_count(),
		);

		$this->view( 'dashboard/directory-meta-box', $args );
	}

	/**
	 * Upsell meta box.
	 *
	 * @since 3.8.6
	 */
	public function upsell_meta_box() {
		$upsell_url = add_query_arg(
			array(
				'utm_source'   => 'smush',
				'utm_medium'   => 'plugin',
				'utm_campaign' => 'smush-dashboard-upsell',
			),
			$this->upgrade_url
		);

		$this->view( 'dashboard/upsell/meta-box', compact( 'upsell_url' ) );
	}

	/**
	 * Upsell meta box header.
	 *
	 * @since 3.8.6
	 */
	public function upsell_meta_box_header() {
		$title = esc_html__( 'Smush Pro', 'wp-smushit' );
		$this->view( 'dashboard/upsell/meta-box-header', compact( 'title' ) );
	}

	/**
	 * Lazy load meta box.
	 *
	 * @since 3.8.6
	 */
	public function lazy_load_meta_box() {
		$settings = $this->settings->get_setting( 'wp-smush-lazy_load' );

		$args = array(
			'is_lazy_load' => $this->settings->get( 'lazy_load' ),
			'media_types'  => isset( $settings['format'] ) ? $settings['format'] : array(),
		);

		$this->view( 'dashboard/lazy-load-meta-box', $args );
	}

	/**
	 * CDN meta box.
	 *
	 * @since 3.8.6
	 */
	public function cdn_meta_box() {
		$upsell_url = add_query_arg(
			array(
				'utm_source'   => 'smush',
				'utm_medium'   => 'plugin',
				'utm_campaign' => 'smush-dashboard-cdn-upsell',
			),
			$this->upgrade_url
		);

		$args = array(
			'cdn_status' => CDN_Helper::get_instance()->get_cdn_status_string(),
			'is_webp'    => $this->settings->get( 'webp' ),
			'upsell_url' => $upsell_url,
		);

		$this->view( 'dashboard/cdn/meta-box', $args );
	}

	/**
	 * CDN meta box header.
	 *
	 * @since 3.8.6
	 */
	public function cdn_meta_box_header() {
		$title = esc_html__( 'CDN', 'wp-smushit' );
		$this->view( 'dashboard/cdn/meta-box-header', compact( 'title' ) );
	}
}
