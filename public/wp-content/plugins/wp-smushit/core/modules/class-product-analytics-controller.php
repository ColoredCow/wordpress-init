<?php

namespace Smush\Core\Modules;

use Smush\Core\Array_Utils;
use Smush\Core\Media_Library\Background_Media_Library_Scanner;
use Smush\Core\Media_Library\Media_Library_Scan_Background_Process;
use Smush\Core\Media_Library\Media_Library_Scanner;
use Smush\Core\Media_Library\Media_Library_Last_Process;
use Smush\Core\Modules\Background\Background_Process;
use Smush\Core\Modules\Background\Background_Process_Status;
use Smush\Core\Server_Utils;
use Smush\Core\Settings;
use Smush\Core\Stats\Global_Stats;
use Smush\Core\Webp\Webp_Configuration;
use Smush\Core\Media\Media_Item_Query;
use Smush\Core\Media\Media_Item_Cache;
use Smush\Core\Helper;
use WP_Smush;
use WPMUDEV_Analytics;

class Product_Analytics_Controller {
	const PROJECT_TOKEN = '5d545622e3a040aca63f2089b0e6cae7';
	/**
	 * @var WPMUDEV_Analytics
	 */
	private $analytics;
	/**
	 * @var Settings
	 */
	private $settings;
	/**
	 * @var Server_Utils
	 */
	private $server_utils;
	/**
	 * @var Media_Library_Scan_Background_Process
	 */
	private $scan_background_process;
	private $scanner_slice_size;

	/**
	 * @var Media_Library_Last_Process
	 */
	private $media_library_last_process;

	/**
	 * @var bool
	 */
	private $scan_background_process_dead = false;

	public function __construct() {
		$this->settings                   = Settings::get_instance();
		$this->server_utils               = new Server_Utils();
		$this->scan_background_process    = Background_Media_Library_Scanner::get_instance()->get_background_process();
		$this->media_library_last_process = Media_Library_Last_Process::get_instance();

		$this->hook_actions();
	}

	private function hook_actions() {
		// Setting events.
		add_action( 'wp_smush_settings_updated', array( $this, 'track_opt_toggle' ), 10, 2 );
		add_action( 'wp_smush_settings_updated', array( $this, 'intercept_settings_update' ), 10, 2 );
		add_action( 'wp_smush_settings_deleted', array( $this, 'intercept_reset' ) );
		add_action( 'wp_smush_settings_updated', array( $this, 'track_integrations_saved' ), 10, 2 );
		add_action( 'wp_smush_settings_updated', array( $this, 'track_toggle_local_webp_fallback' ), 10, 2 );

		if ( ! $this->settings->get( 'usage' ) ) {
			return;
		}

		// Other events.
		add_action( 'wp_smush_directory_smush_start', array( $this, 'track_directory_smush' ) );
		add_action( 'wp_smush_bulk_smush_start', array( $this, 'track_bulk_smush_start' ), 20 );
		add_action( 'wp_smush_bulk_smush_completed', array( $this, 'track_background_bulk_smush_completed' ) );
		add_action( 'wp_smush_bulk_smush_dead', array( $this, 'track_bulk_smush_background_process_death' ) );
		add_action( 'wp_smush_config_applied', array( $this, 'track_config_applied' ) );
		add_action( 'wp_smush_webp_method_changed', array( $this, 'track_webp_method_changed' ) );
		add_action( 'wp_smush_webp_status_changed', array( $this, 'track_webp_status_changed' ) );
		add_action( 'wp_smush_after_delete_all_webp_files', array(
			$this,
			'track_webp_after_deleting_all_webp_files',
		) );
		add_action( 'wp_ajax_smush_toggle_webp_wizard', array( $this, 'track_webp_reconfig' ), - 1 );

		$identifier          = $this->scan_background_process->get_identifier();
		$scan_started_action = "{$identifier}_started";
		$scan_dead_action    = "{$identifier}_dead";

		add_action( "{$identifier}_before_start", array( $this, 'record_scan_death' ), 10, 2 );
		add_action( $scan_started_action, array( $this, 'track_background_scan_start' ), 10, 2 );
		add_action( "{$identifier}_completed", array( $this, 'track_background_scan_end' ), 10, 2 );

		add_action( $scan_dead_action, array( $this, 'track_background_scan_process_death' ) );

		add_action( 'wp_smush_plugin_activated', array( $this, 'track_plugin_activation' ) );
		if ( defined( 'WP_SMUSH_BASENAME' ) ) {
			$plugin_basename = WP_SMUSH_BASENAME;
			add_action( "deactivate_{$plugin_basename}", array( $this, 'track_plugin_deactivation' ) );
		}

		add_action( 'wp_ajax_smush_analytics_track_event', array( $this, 'ajax_handle_track_request' ) );

		add_action( 'wp_smush_bulk_smush_stuck', array( $this, 'track_bulk_smush_progress_stuck' ) );
	}

	private function track( $event, $properties = array() ) {
		$debug_mode = defined( 'WP_SMUSH_MIXPANEL_DEBUG' ) && WP_SMUSH_MIXPANEL_DEBUG;
		if ( $debug_mode ) {
			Helper::logger()->track()->info( sprintf( 'Track Event %1$s: %2$s', $event, print_r( $properties, true ) ) );
			return;
		}

		return $this->get_analytics()->track( $event, $properties );
	}

	/**
	 * @return WPMUDEV_Analytics
	 */
	private function get_analytics() {
		if ( is_null( $this->analytics ) ) {
			$this->analytics = $this->prepare_analytics_instance();
		}

		return $this->analytics;
	}

	public function intercept_settings_update( $old_settings, $settings ) {
		if ( empty( $settings['usage'] ) ) {
			// Use the most up-to-data value of 'usage'
			return;
		}

		$settings = $this->remove_unchanged_settings( $old_settings, $settings );
		$handled  = $this->maybe_track_feature_toggle( $settings );

		if ( ! $handled ) {
			$handled = $this->maybe_track_cdn_update( $settings );
		}
	}

	private function maybe_track_feature_toggle( array $settings ) {
		foreach ( $settings as $setting_key => $setting_value ) {
			$handler = "track_{$setting_key}_feature_toggle";
			if ( method_exists( $this, $handler ) ) {
				call_user_func( array( $this, $handler ), $setting_value );

				return true;
			}
		}

		return false;
	}

	private function remove_unchanged_settings( $old_settings, $settings ) {
		$changed = array();
		foreach ( $settings as $setting_key => $setting_value ) {
			$old_setting_value = isset( $old_settings[ $setting_key ] ) ? $old_settings[ $setting_key ] : '';
			$setting_value     = isset( $setting_value ) ? $setting_value : '';
			if ( $old_setting_value !== $setting_value ) {
				$changed[ $setting_key ] = $setting_value;
			}
		}

		return $changed;
	}

	public function get_bulk_properties() {
		$bulk_property_labels = array(
			'auto'       => 'Automatic Compression',
			'strip_exif' => 'Metadata',
			'resize'     => 'Resize Original Images',
			'original'   => 'Compress original images',
			'backup'     => 'Backup original images',
			'png_to_jpg' => 'Auto-convert PNGs to JPEGs (lossy)',
			'no_scale'   => 'Disable scaled images',
		);

		$image_sizes     = Settings::get_instance()->get_setting( 'wp-smush-image_sizes' );
		$bulk_properties = array(
			'Image Sizes'         => empty( $image_sizes ) ? 'All' : 'Custom',
			'Mode'                => $this->get_current_lossy_level_label(),
			'Parallel Processing' => $this->get_parallel_processing_status(),
			'Smush Type'          => $this->get_smush_type(),
		);
		foreach ( $bulk_property_labels as $bulk_setting => $bulk_property_label ) {
			$property_value                          = Settings::get_instance()->get( $bulk_setting )
				? 'Enabled'
				: 'Disabled';
			$bulk_properties[ $bulk_property_label ] = $property_value;
		}

		return $bulk_properties;
	}

	private function get_parallel_processing_status() {
		return defined( 'WP_SMUSH_PARALLEL' ) && WP_SMUSH_PARALLEL ? 'Enabled' : 'Disabled';
	}

	private function get_smush_type() {
		return $this->settings->is_webp_module_active() ? 'WebP' : 'Classic';
	}

	private function get_current_lossy_level_label() {
		$lossy_level = $this->settings->get_lossy_level_setting();
		$smush_modes = array(
			Settings::LEVEL_LOSSLESS    => 'Basic',
			Settings::LEVEL_SUPER_LOSSY => 'Super',
			Settings::LEVEL_ULTRA_LOSSY => 'Ultra',
		);
		if ( ! isset( $smush_modes[ $lossy_level ] ) ) {
			$lossy_level = Settings::LEVEL_LOSSLESS;
		}

		return $smush_modes[ $lossy_level ];
	}

	private function track_detection_feature_toggle( $setting_value ) {
		return $this->track_feature_toggle( $setting_value, 'Image Resize Detection' );
	}

	private function track_webp_mod_feature_toggle( $setting_value ) {
		return $this->track_feature_toggle( $setting_value, 'Local WebP' );
	}

	private function track_cdn_feature_toggle( $setting_value ) {
		return $this->track_feature_toggle( $setting_value, 'CDN' );
	}

	private function track_lazy_load_feature_toggle( $setting_value ) {
		return $this->track_feature_toggle( $setting_value, 'Lazy Load' );
	}

	private function track_feature_toggle( $active, $feature ) {
		$event = $active
			? 'Feature Activated'
			: 'Feature Deactivated';

		$this->track( $event, array(
			'Feature'        => $feature,
			'Triggered From' => $this->identify_referrer(),
		) );

		return true;
	}

	private function get_webp_referer() {
		$page                   = $this->get_referer_page();
		$webp_configuration     = Webp_Configuration::get_instance();
		$is_user_on_wizard_webp = 'smush-webp' === $page
		                          && $webp_configuration->should_show_wizard()
		                          && ! $webp_configuration->direct_conversion_enabled();

		if ( $is_user_on_wizard_webp ) {
			return 'Wizard';
		}

		return $this->identify_referrer();
	}

	private function identify_referrer() {
		$onboarding_request = ! empty( $_REQUEST['action'] ) && 'smush_setup' === $_REQUEST['action'];
		if ( $onboarding_request ) {
			return 'Wizard';
		}

		$page           = $this->get_referer_page();
		$triggered_from = array(
			'smush'              => 'Dashboard',
			'smush-bulk'         => 'Bulk Smush',
			'smush-directory'    => 'Directory Smush',
			'smush-lazy-load'    => 'Lazy Load',
			'smush-cdn'          => 'CDN',
			'smush-webp'         => 'Local WebP',
			'smush-integrations' => 'Integrations',
			'smush-settings'     => 'Settings',
		);

		return empty( $triggered_from[ $page ] )
			? ''
			: $triggered_from[ $page ];
	}

	private function prepare_analytics_instance() {
		if ( ! class_exists( 'WPMUDEV_Analytics' ) ) {
			require_once WP_SMUSH_DIR . 'core/external/wpmudev-analytics/autoload.php';
		}

		$mixpanel = new WPMUDEV_Analytics( 'smush', 'Smush', 55, $this->get_token() );
		$mixpanel->identify( $this->get_unique_id() );
		$mixpanel->registerAll( $this->get_super_properties() );

		return $mixpanel;
	}

	public function get_super_properties() {
		global $wp_version;

		return array(
			'active_theme'       => get_stylesheet(),
			'locale'             => get_locale(),
			'mysql_version'      => $this->server_utils->get_mysql_version(),
			'php_version'        => phpversion(),
			'plugin'             => 'Smush',
			'plugin_type'        => WP_Smush::is_pro() ? 'pro' : 'free',
			'plugin_version'     => WP_SMUSH_VERSION,
			'server_type'        => $this->server_utils->get_server_type(),
			'memory_limit'       => $this->convert_to_megabytes( $this->server_utils->get_memory_limit() ),
			'max_execution_time' => $this->server_utils->get_max_execution_time(),
			'wp_type'            => is_multisite() ? 'multisite' : 'single',
			'wp_version'         => $wp_version,
			'device'             => $this->get_device(),
			'user_agent'         => $this->server_utils->get_user_agent(),
		);
	}

	private function get_device() {
		if ( ! $this->is_mobile() ) {
			return 'desktop';
		}

		if ( $this->is_tablet() ) {
			return 'tablet';
		}

		return 'mobile';
	}

	private function is_tablet() {
		if ( empty( $_SERVER['HTTP_USER_AGENT'] ) ) {
			return false;
		}
		/**
		 * It doesn't work with IpadOS due to of this:
		 * https://stackoverflow.com/questions/62323230/how-can-i-detect-with-php-that-the-user-uses-an-ipad-when-my-user-agent-doesnt-c
		 */
		$tablet_pattern = '/(tablet|ipad|playbook|kindle|silk)/i';
		return preg_match( $tablet_pattern, wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) );
	}

	private function is_mobile() {
		if ( empty( $_SERVER['HTTP_USER_AGENT'] ) ) {
			return false;
		}
		// Do not use wp_is_mobile() since it doesn't detect ipad/tablet.
		$mobile_patten = '/Mobile|iP(hone|od|ad)|Android|BlackBerry|tablet|IEMobile|Kindle|NetFront|Silk|(hpw|web)OS|Fennec|Minimo|Opera M(obi|ini)|Blazer|Dolfin|Dolphin|Skyfire|Zune|playbook/i';
		return preg_match( $mobile_patten, wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) );
	}

	private function normalize_url( $url ) {
		$url = str_replace( array( 'http://', 'https://', 'www.' ), '', $url );

		return untrailingslashit( $url );
	}

	private function maybe_track_cdn_update( $settings ) {
		$cdn_properties      = array();
		$cdn_property_labels = $this->cdn_property_labels();
		foreach ( $settings as $setting_key => $setting_value ) {
			if ( array_key_exists( $setting_key, $cdn_property_labels ) ) {
				$property_label                    = $cdn_property_labels[ $setting_key ];
				$property_value                    = $setting_value ? 'Enabled' : 'Disabled';
				$cdn_properties[ $property_label ] = $property_value;
			}
		}

		if ( $cdn_properties ) {
			$this->track( 'CDN Updated', $cdn_properties );

			return true;
		}

		return false;
	}

	private function cdn_property_labels() {
		return array(
			'background_images' => 'Background Images',
			'auto_resize'       => 'Automatic Resizing',
			'webp'              => 'WebP Conversions',
			'rest_api_support'  => 'Rest API',
		);
	}

	public function track_directory_smush() {
		$this->track( 'Directory Smushed' );
	}

	public function track_bulk_smush_start() {
		$properties = $this->get_bulk_properties();
		$properties = array_merge(
			$properties,
			array(
				'process_id'              => $this->get_process_id(),
				'Background Optimization' => $this->get_background_optimization_status(),
				'Cron'                    => $this->get_cron_healthy_status(),
			)
		);
		$this->track( 'Bulk Smush Started', $properties );
	}

	private function get_process_id() {
		return md5( $this->media_library_last_process->get_process_start_time() );
	}

	/**
	 * Track the event on background optimization completed.
	 * Note: For ajax Bulk Smush, we will track it via js.
	 *
	 * @return void
	 */
	public function track_background_bulk_smush_completed() {
		$bg_optimization    = WP_Smush::get_instance()->core()->mod->bg_optimization;
		$total_items        = $bg_optimization->get_total_items();
		$failed_items       = $bg_optimization->get_failed_items();
		$failure_percentage = $total_items > 0 ? round( $failed_items * 100 / $total_items ) : 0;

		$properties = array_merge(
			$this->get_bulk_smush_stats(),
			array(
				'Total Enqueued Images' => $total_items,
				'Failure Percentage'    => $failure_percentage,
			)
		);
		$properties = $this->filter_bulk_smush_completed_properties( $properties );

		$this->track( 'Bulk Smush Completed', $properties );
	}

	/**
	 * Add extra properties to the bulk smush completed event for Bulk Smush include ajax method.
	 *
	 * @param array $properties Bulk Smush completed properties.
	 */
	protected function filter_bulk_smush_completed_properties( $properties ) {
		return array_merge(
			$properties,
			array(
				'process_id'              => $this->get_process_id(),
				'Background Optimization' => $this->get_background_optimization_status(),
				'Cron'                    => $this->get_cron_healthy_status(),
				'Time Elapsed'            => $this->media_library_last_process->get_process_elapsed_time(),
				'Smush Type'              => $this->get_smush_type(),
				'Mode'                    => $this->get_current_lossy_level_label(),
			)
		);
	}

	private function get_bulk_smush_stats() {
		$global_stats = WP_Smush::get_instance()->core()->get_global_stats();
		$array_util   = new Array_Utils();

		return array(
			'Total Savings'                 => $this->convert_to_megabytes( (int) $array_util->get_array_value( $global_stats, 'savings_bytes' ) ),
			'Total Images'                  => (int) $array_util->get_array_value( $global_stats, 'count_images' ),
			'Media Optimization Percentage' => (float) $array_util->get_array_value( $global_stats, 'percent_optimized' ),
			'Percentage of Savings'         => (float) $array_util->get_array_value( $global_stats, 'savings_percent' ),
			'Images Resized'                => (int) $array_util->get_array_value( $global_stats, 'count_resize' ),
			'Resize Savings'                => $this->convert_to_megabytes( (int) $array_util->get_array_value( $global_stats, 'savings_resize' ) ),
		);
	}

	public function track_config_applied( $config_name ) {
		$properties = $config_name
			? array( 'Config Name' => $config_name )
			: array();

		$properties['Triggered From'] = $this->identify_referrer();

		$this->track( 'Config Applied', $properties );
	}

	public function get_unique_id() {
		$site_url         = home_url();
		$has_valid_domain = $this->has_valid_domain( $site_url );
		if ( ! $has_valid_domain ) {
			$site_url         = site_url();
			$has_valid_domain = $this->has_valid_domain( $site_url );
		}
		return $has_valid_domain ? $this->normalize_url( $site_url ) : '';
	}

	public function get_token() {
		if ( empty( $this->get_unique_id() ) ) {
			return '';
		}
		return self::PROJECT_TOKEN;
	}

	private function has_valid_domain( $url ) {
		$pattern = '/^(https?:\/\/)?([a-z0-9-]+\.)*[a-z0-9-]+(\.[a-z]{2,})/i';
		return preg_match( $pattern, $url );
	}

	public function track_opt_toggle( $old_settings, $settings ) {
		$settings = $this->remove_unchanged_settings( $old_settings, $settings );

		if ( isset( $settings['usage'] ) ) {
			// Following the new change, the location for Opt In/Out is lowercase and none whitespace.
			// @see SMUSH-1538.
			$location = str_replace( ' ', '_', $this->identify_referrer() );
			$location = strtolower( $location );
			$this->track(
				$settings['usage'] ? 'Opt In' : 'Opt Out',
				array(
					'Location'       => $location,
					'active_plugins' => $this->get_active_plugins(),
				)
			);
		}
	}

	public function track_integrations_saved( $old_settings, $settings ) {
		if ( empty( $settings['usage'] ) ) {
			return;
		}

		$settings = $this->remove_unchanged_settings( $old_settings, $settings );
		if ( empty( $settings ) ) {
			return;
		}

		$this->maybe_track_integrations_toggle( $settings );
	}

	private function maybe_track_integrations_toggle( $settings ) {
		$integrations = array(
			'gutenberg'  => 'Gutenberg',
			'gform'      => 'Gravity Forms',
			'js_builder' => 'WP Bakery',
			's3'         => 'Amazon S3',
			'nextgen'    => 'NextGen Gallery',
		);

		foreach ( $settings as $integration_slug => $is_activated ) {
			if ( ! array_key_exists( $integration_slug, $integrations ) ) {
				continue;
			}

			if ( $is_activated ) {
				$this->track(
					'Integration Activated',
					array(
						'Integration' => $integrations[ $integration_slug ],
					)
				);
			} else {
				$this->track(
					'Integration Deactivated',
					array(
						'Integration' => $integrations[ $integration_slug ],
					)
				);
			}
		}
	}

	public function intercept_reset() {
		if ( $this->settings->get( 'usage' ) ) {
			$this->track(
				'Opt Out',
				array(
					'Location'       => 'reset',
					'active_plugins' => $this->get_active_plugins(),
				)
			);
		}
	}

	public function record_scan_death() {
		$this->scan_background_process_dead = $this->scan_background_process->get_status()->is_dead();
	}

	public function track_background_scan_start( $identifier, $background_process ) {
		$type = $this->scan_background_process_dead
			? 'Retry'
			: 'New';

		$this->_track_background_scan_start( $type, $background_process );
	}

	private function _track_background_scan_start( $type, $background_process ) {
		$properties = array(
			'Scan Type' => $type,
		);

		$this->track( 'Scan Started', array_merge(
			$properties,
			$this->get_bulk_properties(),
			$this->get_scan_properties()
		) );
	}

	/**
	 * @param $identifier
	 * @param $background_process Background_Process
	 *
	 * @return void
	 */
	public function track_background_scan_end( $identifier, $background_process ) {
		$properties = array(
			'Retry Attempts' => $background_process->get_revival_count(),
			'Time Elapsed'   => $this->media_library_last_process->get_process_elapsed_time(),
		);
		$this->track( 'Scan Ended', array_merge(
			$properties,
			$this->get_bulk_properties(),
			$this->get_scan_properties()
		) );
	}

	public function track_background_scan_process_death() {
		$this->track(
			'Background Process Dead',
			array_merge(
				array(
					'Process Type' => 'Scan',
					'Slice Size'   => $this->get_scanner_slice_size(),
					'Time Elapsed' => $this->media_library_last_process->get_process_elapsed_time(),
					'Smush Type'   => $this->get_smush_type(),
					'Mode'         => $this->get_current_lossy_level_label(),
				),
				$this->get_scan_background_process_properties()
			)
		);
	}

	/**
	 * @param $identifier string
	 * @param $background_process Background_Process
	 *
	 * @return void
	 */
	public function track_bulk_smush_background_process_death() {
		$this->track(
			'Background Process Dead',
			array_merge(
				array(
					'Process Type' => 'Smush',
					'Slice Size'   => 0,
					'Time Elapsed' => $this->media_library_last_process->get_process_elapsed_time(),
					'Smush Type'   => $this->get_smush_type(),
					'Mode'         => $this->get_current_lossy_level_label(),
				),
				$this->get_bulk_background_process_properties()
			)
		);
	}

	private function get_scan_properties() {
		$global_stats       = Global_Stats::get();
		$global_stats_array = $global_stats->to_array();
		$properties         = array(
			'process_id' => $this->get_process_id(),
			'Slice Size' => $this->get_scanner_slice_size(),
		);

		$labels = array(
			'image_attachment_count' => 'Image Attachment Count',
			'optimized_images_count' => 'Optimized Images Count',
			'optimize_count'         => 'Optimize Count',
			'reoptimize_count'       => 'Reoptimize Count',
			'ignore_count'           => 'Ignore Count',
			'animated_count'         => 'Animated Count',
			'error_count'            => 'Error Count',
			'percent_optimized'      => 'Percent Optimized',
			'size_before'            => 'Size Before',
			'size_after'             => 'Size After',
			'savings_percent'        => 'Savings Percent',
		);

		$savings_keys = array(
			'size_before',
			'size_after',
		);

		foreach ( $labels as $key => $label ) {
			if ( isset( $global_stats_array[ $key ] ) ) {
				$properties[ $label ] = $global_stats_array[ $key ];

				if ( in_array( $key, $savings_keys, true ) ) {
					$properties[ $label ] = $this->convert_to_megabytes( $properties[ $label ] );
				}
			}
		}

		return $properties;
	}

	private function get_bulk_background_process_properties() {
		$bg_optimization = WP_Smush::get_instance()->core()->mod->bg_optimization;
		$process_id      = $this->get_process_id();

		if ( ! $bg_optimization->is_background_enabled() ) {
			return array(
				'process_id' => $process_id,
			);
		}

		$total_items     = $bg_optimization->get_total_items();
		$processed_items = $bg_optimization->get_processed_items();

		return array(
			'process_id'             => $process_id,
			'Retry Attempts'         => $bg_optimization->get_revival_count(),
			'Total Enqueued Images'  => $total_items,
			'Completion Percentage'  => $this->get_background_process_completion_percentage( $total_items, $processed_items ),
			'Total Processed Images' => $processed_items,
		);
	}

	private function get_scan_background_process_properties() {
		$query                  = new Media_Item_Query();
		$total_enqueued_images  = $query->get_image_attachment_count();
		$total_items            = $this->scan_background_process->get_status()->get_total_items();
		$processed_items        = $this->scan_background_process->get_status()->get_processed_items();
		$scanner_slice_size     = $this->get_scanner_slice_size();
		$total_processed_images = $processed_items * $scanner_slice_size;
		$total_processed_images = $total_processed_images > $total_enqueued_images ? $total_enqueued_images : $total_processed_images;

		return array(
			'process_id'             => $this->get_process_id(),
			'Retry Attempts'         => $this->scan_background_process->get_revival_count(),
			'Total Enqueued Images'  => $total_enqueued_images,
			'Completion Percentage'  => $this->get_background_process_completion_percentage( $total_items, $processed_items ),
			'Total Processed Images' => $total_processed_images,
		);
	}

	private function get_background_process_completion_percentage( $total_items, $processed_items ) {
		if ( $total_items < 1 ) {
			return 0;
		}

		return ceil( $processed_items * 100 / $total_items );
	}

	private function convert_to_megabytes( $size_in_bytes ) {
		if ( empty( $size_in_bytes ) ) {
			return 0;
		}
		$unit_mb = pow( 1024, 2 );
		return round( $size_in_bytes / $unit_mb, 2 );
	}

	private function get_scanner_slice_size() {
		if ( is_null( $this->scanner_slice_size ) ) {
			$this->scanner_slice_size = ( new Media_Library_Scanner() )->get_slice_size();
		}

		return $this->scanner_slice_size;
	}

	public function track_toggle_local_webp_fallback( $old_settings, $settings ) {
		if (
			empty( $settings['usage'] ) ||
			empty( $settings['webp_mod'] ) ||
			did_action( 'wp_smush_webp_status_changed' ) // Tracked.
		) {
			return;
		}

		$modified_settings = $this->remove_unchanged_settings( $old_settings, $settings );
		if ( ! isset( $modified_settings['webp_fallback'] ) ) {
			return;
		}

		$modify_type               = ! empty( $modified_settings['webp_fallback'] ) ? 'browser_support_on' : 'browser_support_off';
		$direct_conversion_enabled = ! empty( $settings['webp_direct_conversion'] );// WebP method might or might not be changed.
		$webp_method               = $direct_conversion_enabled ? 'direct' : 'server_redirect';
		$local_webp_properites     = $this->get_local_webp_properties();
		$this->track(
			'local_webp_updated',
			array_merge(
				$local_webp_properites,
				array(
					'update_type' => 'modify',
					'modify_type' => $modify_type,
					'Method'      => $webp_method,
				)
			)
		);
	}

	public function track_webp_after_deleting_all_webp_files() {
		$local_webp_properites = $this->get_local_webp_properties();
		$this->track(
			'local_webp_updated',
			array_merge(
				$local_webp_properites,
				array(
					'update_type' => 'modify',
					'modify_type' => 'delete_files',
				)
			)
		);
	}

	public function track_webp_method_changed() {
		$local_webp_properites = $this->get_local_webp_properties();
		$this->track(
			'local_webp_updated',
			array_merge(
				$local_webp_properites,
				array(
					'update_type' => 'switch_method',
					'modify_type' => 'na',
				)
			)
		);
	}

	public function track_webp_status_changed() {
		$local_webp_properites = $this->get_local_webp_properties();
		$update_type           = $this->settings->is_webp_module_active() ? 'activate' : 'deactivate';
		$this->track(
			'local_webp_updated',
			array_merge(
				$local_webp_properites,
				array(
					'update_type' => $update_type,
					'modify_type' => 'na',
				)
			)
		);
	}

	private function get_local_webp_properties() {
		$location = $this->get_webp_referer();
		// Directly check webp_direct_conversion option to identify webp method even webp module is disabled.
		$direct_conversion_enabled = $this->settings->get( 'webp_direct_conversion' );
		$webp_method               = $direct_conversion_enabled ? 'direct' : 'server_redirect';
		$webp_status_notice        = $this->get_webp_status_notice();

		return array(
			'Location'      => $location,
			'Method'        => $webp_method,
			'status_notice' => $webp_status_notice,
		);
	}

	private function get_webp_status_notice() {
		if ( ! $this->settings->is_webp_module_active() ) {
			return 'na';
		}

		$webp_configuration = Webp_Configuration::get_instance();
		if ( ! $webp_configuration->is_configured() ) {
			return $webp_configuration->server_configuration()->get_configuration_error_code();
		}

		if ( is_multisite() ) {
			return 'active_subsite';// Activated but required run Bulk Smush on subsites.
		}

		$required_bulk_smush = Global_Stats::get()->is_outdated() || Global_Stats::get()->get_remaining_count() > 0;
		if ( $required_bulk_smush ) {
			return 'active_need_smush';
		}

		$auto_smush_enabled = $this->settings->is_automatic_compression_active();
		if ( $auto_smush_enabled ) {
			return 'active_automatic_enabled';
		}

		return 'active_automatic_disabled';
	}

	public function track_webp_reconfig() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$local_webp_properites = $this->get_local_webp_properties();
		$this->track(
			'local_webp_updated',
			array_merge(
				$local_webp_properites,
				array(
					'update_type' => 'modify',
					'modify_type' => 'reconfig',
				)
			)
		);
	}

	private function get_referer_page() {
		$path       = parse_url( wp_get_referer(), PHP_URL_QUERY );
		$query_vars = array();
		parse_str( $path, $query_vars );

		return empty( $query_vars['page'] ) ? '' : $query_vars['page'];
	}

	public function track_plugin_activation() {
		$this->track(
			'Opt In',
			array(
				'Location'       => 'reactivate',
				'active_plugins' => $this->get_active_plugins(),
			)
		);
	}

	public function track_plugin_deactivation() {
		$location = $this->get_deactivation_location();
		$this->track(
			'Opt Out',
			array(
				'Location'       => $location,
				'active_plugins' => $this->get_active_plugins(),
			)
		);
	}

	private function get_deactivation_location() {
		$is_hub_request = ! empty( $_REQUEST['wpmudev-hub'] );
		if ( $is_hub_request ) {
			return 'deactivate_hub';
		}

		$is_dashboard_request = wp_doing_ajax() &&
		                        ! empty( $_REQUEST['action'] ) &&
		                        'wdp-project-deactivate' === wp_unslash( $_REQUEST['action'] );

		if ( $is_dashboard_request ) {
			return 'deactivate_dashboard';
		}

		return 'deactivate_pluginlist';
	}

	private function get_active_plugins() {
		$active_plugins      = array();
		$active_plugin_files = $this->get_active_and_valid_plugin_files();
		foreach ( $active_plugin_files as $plugin_file ) {
			$plugin_name = $this->get_plugin_name( $plugin_file );
			if ( $plugin_name ) {
				$active_plugins[] = $plugin_name;
			}
		}

		return $active_plugins;
	}

	private function get_active_and_valid_plugin_files() {
		$active_plugins = is_multisite() ? wp_get_active_network_plugins() : array();
		$active_plugins = array_merge( $active_plugins, wp_get_active_and_valid_plugins() );

		return array_unique( $active_plugins );
	}

	private function get_plugin_name( $plugin_file ) {
		$plugin_data = get_plugin_data( $plugin_file );

		return ! empty( $plugin_data['Name'] ) ? $plugin_data['Name'] : '';
	}

	private function get_cron_healthy_status() {
		return $this->is_cron_healthy() ? 'Enabled' : 'Disabled';
	}

	private function is_cron_healthy() {
		$wp_core_cron_hooks = array(
			'wp_privacy_delete_old_export_files',
			'wp_version_check',
			'wp_update_plugins',
			'wp_update_themes',
		);

		foreach ( $wp_core_cron_hooks as $hook ) {
			$next_scheduled_time = wp_next_scheduled( $hook );
			if ( ! $next_scheduled_time ) {
				continue;
			}

			$delayed_time = time() - $next_scheduled_time;

			// If any of the core cron hooks are delayed by more than 30 minutes, then cron is unhealthy.
			return $delayed_time < ( HOUR_IN_SECONDS / 2 );
		}

		return false;
	}

	private function get_background_optimization_status() {
		$bg_optimization = WP_Smush::get_instance()->core()->mod->bg_optimization;
		return $bg_optimization->is_background_enabled() ? 'Enabled' : 'Disabled';
	}

	public function ajax_handle_track_request() {
		$event_name = $this->get_event_name();
		if ( ! check_ajax_referer( 'wp-smush-ajax' ) || ! Helper::is_user_allowed() || empty( $event_name ) ) {
			wp_send_json_error();
		}

		$this->track(
			$event_name,
			$this->get_event_properties( $event_name )
		);

		wp_send_json_success();
	}

	private function get_event_name() {
		return isset( $_POST['event'] ) ? sanitize_text_field( wp_unslash( $_POST['event'] ) ) : '';
	}

	private function get_event_properties( $event_name ) {
		$properties = isset( $_POST['properties'] ) ? wp_unslash( $_POST['properties'] ) : array();
		$properties = array_map( 'sanitize_text_field', $properties );

		$filter_callback = $this->get_filter_properties_callback( $event_name );
		if ( method_exists( $this, $filter_callback ) ) {
			$properties = call_user_func( array( $this, $filter_callback ), $properties );
		}

		return $properties;
	}

	private function get_filter_properties_callback( $event_name ) {
		$event_name = str_replace( ' ', '_', $event_name );
		$event_name = sanitize_key( $event_name );
		return "filter_{$event_name}_properties";
	}

	/**
	 * Filter properties for Scan Interrupted event.
	 *
	 * @param array $properties JS properties.
	 */
	protected function filter_scan_interrupted_properties( $properties ) {
		$properties = array_merge(
			$properties,
			array(
				'Slice Size'              => $this->get_scanner_slice_size(),
				'Background Optimization' => $this->get_background_optimization_status(),
				'Cron'                    => $this->get_cron_healthy_status(),
				'Time Elapsed'            => $this->media_library_last_process->get_process_elapsed_time(),
				'Smush Type'              => $this->get_smush_type(),
				'Mode'                    => $this->get_current_lossy_level_label(),
			),
			$this->get_scan_background_process_properties(),
			$this->get_last_image_process_properties()
		);

		return $properties;
	}


	private function get_last_image_process_properties() {
		$last_image_id = $this->media_library_last_process->get_last_process_attachment_id();
		if ( ! $last_image_id ) {
			return array();
		}

		$media_item              = Media_Item_Cache::get_instance()->get( $last_image_id );
		$last_image_time_elapsed = $this->media_library_last_process->get_last_process_attachment_elapsed_time();
		$properties              = array(
			'Last Image Time Elapsed' => $last_image_time_elapsed,
		);

		if ( ! $media_item->is_valid() ) {
			return $properties;
		}

		$full_size = $media_item->get_full_or_scaled_size();
		if ( ! $full_size ) {
			return $properties;
		}

		$file_size    = $this->convert_to_megabytes( $full_size->get_filesize() );
		$image_width  = $full_size->get_width();
		$image_height = $full_size->get_height();
		$image_type   = strtoupper( $full_size->get_extension() );

		return array(
			'Last Image Time Elapsed' => $last_image_time_elapsed,
			'Last Image Size'         => $file_size,
			'Last Image Width'        => $image_width,
			'Last Image Height'       => $image_height,
			'Last Image Type'         => $image_type,
		);
	}

	/**
	 * Filter properties for Bulk Smush interrupted event.
	 *
	 * @param array $properties JS properties.
	 */
	protected function filter_bulk_smush_interrupted_properties( $properties ) {
		return array_merge(
			$properties,
			array(
				'Background Optimization' => $this->get_background_optimization_status(),
				'Cron'                    => $this->get_cron_healthy_status(),
				'Parallel Processing'     => $this->get_parallel_processing_status(),
				'Time Elapsed'            => $this->media_library_last_process->get_process_elapsed_time(),
				'Smush Type'              => $this->get_smush_type(),
				'Mode'                    => $this->get_current_lossy_level_label(),
			),
			$this->get_bulk_background_process_properties(),
			$this->get_last_image_process_properties()
		);
	}

	public function track_bulk_smush_progress_stuck() {
		$properties = array(
			'Trigger'      => 'stuck_notice',
			'Modal Action' => 'na',
			'Troubleshoot' => 'na',
		);

		$properties = $this->filter_bulk_smush_interrupted_properties( $properties );

		$this->track( 'Bulk Smush Interrupted', $properties );
	}
}
