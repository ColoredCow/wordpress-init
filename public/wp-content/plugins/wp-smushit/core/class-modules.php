<?php
/**
 * Class Modules.
 *
 * Used in Core to type hint the $mod variable. For example, this way any calls to
 * \Smush\WP_Smush::get_instance()->core()->mod->settings will be typehinted as a call to Settings module.
 *
 * @package Smush\Core
 */

namespace Smush\Core;

use Smush\Core\Backups\Backups_Controller;
use Smush\Core\CDN\CDN_Controller;
use Smush\Core\CDN\CDN_Settings_Ui_Controller;
use Smush\Core\CDN\CDN_Srcset_Controller;
use Smush\Core\Lazy_Load\Lazy_Load_Controller;
use Smush\Core\Media\Media_Item_Controller;
use Smush\Core\Media_Library\Ajax_Media_Library_Scanner;
use Smush\Core\Media_Library\Background_Media_Library_Scanner;
use Smush\Core\Media_Library\Media_Library_Last_Process;
use Smush\Core\Media_Library\Media_Library_Slice_Data_Fetcher;
use Smush\Core\Media_Library\Media_Library_Watcher;
use Smush\Core\Modules\Background\Background_Pre_Flight_Controller;
use Smush\Core\Modules\CDN;
use Smush\Core\Photon\Photon_Controller;
use Smush\Core\Png2Jpg\Png2Jpg_Controller;
use Smush\Core\Resize\Resize_Controller;
use Smush\Core\S3\S3_Controller;
use Smush\Core\Smush\Smush_Controller;
use Smush\Core\Stats\Global_Stats_Controller;
use Smush\Core\Transform\Transformation_Controller;
use Smush\Core\Webp\Webp_Controller;

if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Class Modules
 */
class Modules {

	/**
	 * Directory Smush module.
	 *
	 * @var Modules\Dir
	 */
	public $dir;

	/**
	 * Main Smush module.
	 *
	 * @var Modules\Smush
	 */
	public $smush;

	/**
	 * Backup module.
	 *
	 * @var Modules\Backup
	 */
	public $backup;

	/**
	 * PNG 2 JPG module.
	 *
	 * @var Modules\Png2jpg
	 */
	public $png2jpg;

	/**
	 * Resize module.
	 *
	 * @var Modules\Resize
	 */
	public $resize;

	/**
	 * CDN module.
	 *
	 * @var CDN
	 */
	public $cdn;

	/**
	 * Image lazy load module.
	 *
	 * @since 3.2
	 *
	 * @var \Smush\Core\Modules\Lazy
	 */
	public $lazy;

	/**
	 * Webp module.
	 *
	 * @var Modules\Webp
	 */
	public $webp;

	/**
	 * Cache background optimization controller - Bulk_Smush_Controller
	 *
	 * @var Modules\Bulk\Background_Bulk_Smush
	 */
	public $bg_optimization;

	/**
	 * @var Modules\Product_Analytics_Controller
	 */
	public $product_analytics;

	public $backward_compatibility;

	/**
	 * Modules constructor.
	 */
	public function __construct() {
		new Deprecated_Hooks();// Handle deprecated hooks.

		new Api\Hub(); // Init hub endpoints.

		new Modules\Resize_Detection();
		new Rest();

		if ( is_admin() ) {
			$this->dir = new Modules\Dir();
		}

		$this->smush   = new Modules\Smush();
		$this->backup  = new Modules\Backup();
		$this->png2jpg = new Modules\Png2jpg();
		$this->resize  = new Modules\Resize();

		$transformation_controller = new Transformation_Controller();
		$transformation_controller->init();

		$this->cdn = new CDN();

		$cdn_srcset_controller = CDN_Srcset_Controller::get_instance();
		$cdn_srcset_controller->init();

		$this->webp              = new Modules\WebP();
		$this->lazy              = new Modules\Lazy();
		$this->product_analytics = new Modules\Product_Analytics_Controller();

		$this->bg_optimization = Modules\Bulk\Background_Bulk_Smush::get_instance();

		$smush_controller = new Smush_Controller();
		$smush_controller->init();

		$png2jpg_controller = Png2Jpg_Controller::get_instance();
		$png2jpg_controller->init();

		$webp_controller = new Webp_Controller();
		$webp_controller->init();

		$resize_controller = new Resize_Controller();
		$resize_controller->init();

		$s3_controller = new S3_Controller();
		$s3_controller->init();

		$backups_controller = new Backups_Controller();
		$backups_controller->init();

		$library_scanner = new Ajax_Media_Library_Scanner();
		$library_scanner->init();

		$background_lib_scanner = Background_Media_Library_Scanner::get_instance();
		$background_lib_scanner->init();

		$media_library_watcher = new Media_Library_Watcher();
		$media_library_watcher->init();

		$global_stats_controller = new Global_Stats_Controller();
		$global_stats_controller->init();

		$plugin_settings_watcher = new Plugin_Settings_Watcher();
		$plugin_settings_watcher->init();

		$animated_status_controller = new Animated_Status_Controller();
		$animated_status_controller->init();

		$media_library_slice_data_fetcher = new Media_Library_Slice_Data_Fetcher( is_multisite(), get_current_blog_id() );
		$media_library_slice_data_fetcher->init();

		$media_item_controller = new Media_Item_Controller();
		$media_item_controller->init();

		$optimization_controller = new Optimization_Controller();
		$optimization_controller->init();

		$photon_controller = new Photon_Controller();
		$photon_controller->init();

		// CDN
		$cdn_controller = new CDN_Controller();
		$cdn_controller->init();

		$cdn_settings_ui_controller = new CDN_Settings_Ui_Controller();
		$cdn_settings_ui_controller->init();

		$cache_controller = new Cache_Controller();
		$cache_controller->init();

		$lazy_load_controller = Lazy_Load_Controller::get_instance();
		$lazy_load_controller->init();

		$background_health = Background_Pre_Flight_Controller::get_instance();
		$background_health->init();

		$media_lib_last_process = Media_Library_Last_Process::get_instance();
		$media_lib_last_process->init();
	}

}
