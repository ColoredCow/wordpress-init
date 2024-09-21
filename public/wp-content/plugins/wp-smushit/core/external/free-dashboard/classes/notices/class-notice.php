<?php
/**
 * Plugin notice base class.
 *
 * @since      2.0
 * @author     Incsub (Joel James)
 * @license    http://www.gnu.org/licenses/old-licenses/gpl-2.0.html
 * @copyright  Copyright (c) 2022, Incsub
 * @package    WPMUDEV\Notices\Notices
 */

namespace WPMUDEV\Notices\Notices;

// If this file is called directly, abort.
defined( 'WPINC' ) || die;

use WPMUDEV\Notices\Handler;

if ( ! class_exists( __NAMESPACE__ . '\\Notice' ) ) {
	/**
	 * Class Notice
	 *
	 * @since   2.0
	 * @package WPMUDEV\Notices
	 */
	abstract class Notice {

		/**
		 * Current notice type (Should override in sub class).
		 *
		 * @var string $type
		 *
		 * @since 2.0
		 */
		protected $type;

		/**
		 * Time to start showing notice.
		 *
		 * This will be added to the current time.
		 *
		 * @var string $type
		 *
		 * @since 2.0
		 */
		protected $time = 0; // Right now.

		/**
		 * Current plugin's notice options.
		 *
		 * @var string $plugin_id
		 *
		 * @since 2.0
		 */
		protected $options = array();

		/**
		 * Initializes and returns the notice instance.
		 *
		 * @param array $options Plugin options.
		 *
		 * @since 2.0
		 */
		protected function __construct( array $options ) {
			// Set options.
			$this->options = $this->parse_options( $options );
		}

		/**
		 * Initializes and returns the singleton instance.
		 *
		 * @param array $options Plugin options.
		 *
		 * @since 2.0
		 *
		 * @return static
		 */
		public static function instance( array $options = array() ) {
			static $instance = null;

			if ( null === $instance ) {
				$called_class = get_called_class();
				$instance     = new $called_class( $options );
			}

			return $instance;
		}

		/**
		 * Render a notice type content.
		 *
		 * @param string $plugin Plugin ID.
		 *
		 * @since 2.0
		 *
		 * @return void
		 */
		abstract public function render( $plugin );

		/**
		 * Check if current notice is allowed for the plugin.
		 *
		 * @param string $plugin Plugin ID.
		 *
		 * @since 2.0
		 *
		 * @return bool
		 */
		public function can_show( $plugin ) {
			return true;
		}

		/**
		 * Enqueue assets for a notice if required.
		 *
		 * @param string $plugin Plugin ID.
		 *
		 * @since 2.0
		 *
		 * @return void
		 */
		protected function enqueue_assets( $plugin ) {
			// Override to enqueue.
		}

		/**
		 * Parse options for the notice.
		 *
		 * @param array $options Plugin options.
		 *
		 * @since 2.0
		 *
		 * @return array
		 */
		protected function parse_options( array $options ) {
			return $options;
		}

		/**
		 * Get a notice option value.
		 *
		 * @param string $key     Option name.
		 * @param mixed  $default Default value.
		 *
		 * @since 2.0
		 *
		 * @return array
		 */
		protected function get_option( $key, $default = '' ) {
			if ( isset( $this->options[ $key ] ) ) {
				return $this->options[ $key ];
			}

			return $default;
		}

		/**
		 * Get next scheduled time to show notice.
		 *
		 * @param int $time   Current time.
		 * @param int $extend How many days to extend.
		 *
		 * @since 2.0
		 *
		 * @return int
		 */
		public function get_next_schedule( $time = false, $extend = false ) {
			// Use current time.
			if ( ! is_int( $time ) ) {
				$time = time();
			}

			// Use extension time.
			if ( ! is_int( $extend ) ) {
				$extend = $this->time;
			}

			return $time + $extend;
		}

		/**
		 * Get full url to an asset.
		 *
		 * @param string $path Path to append.
		 *
		 * @since 2.0
		 *
		 * @return string
		 */
		protected function assets_url( $path ) {
			return plugin_dir_url( WPMUDEV_NOTICES_FILE ) . 'assets/' . $path;
		}

		/**
		 * Get the full url to the API endpoint.
		 *
		 * @param string $endpoint API endpoint.
		 *
		 * @since 2.0
		 *
		 * @return string
		 */
		protected function api_url( $endpoint ) {
			$base = 'https://wpmudev.com/';

			// Support custom API base.
			if ( defined( 'WPMUDEV_CUSTOM_API_SERVER' ) && ! empty( WPMUDEV_CUSTOM_API_SERVER ) ) {
				$base = trailingslashit( WPMUDEV_CUSTOM_API_SERVER );
			}

			// Append endpoint.
			return $base . 'api/' . $endpoint;
		}

		/**
		 * Get current screen id.
		 *
		 * @since 2.0
		 *
		 * @return string
		 */
		protected function screen_id() {
			// Screen not defined yet.
			if ( ! function_exists( 'get_current_screen' ) ) {
				return '';
			}

			// Get current screen.
			$screen = get_current_screen();

			// Return current screen id.
			return empty( $screen->id ) ? '' : $screen->id;
		}
	}
}
