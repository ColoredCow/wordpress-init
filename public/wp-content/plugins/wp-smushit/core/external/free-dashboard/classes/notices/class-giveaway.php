<?php
/**
 * Giveaway notice class.
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

if ( ! class_exists( __NAMESPACE__ . '\\Giveaway' ) ) {
	/**
	 * Class Giveaway
	 *
	 * @since   2.0
	 * @package WPMUDEV\Notices
	 */
	class Giveaway extends Notice {

		/**
		 * Current notice type.
		 *
		 * @since 2.0
		 * @var string $type
		 */
		protected $type = 'giveaway';

		/**
		 * Show after 2 days
		 *
		 * @since 2.0
		 * @var string $type
		 */
		protected $time = DAY_IN_SECONDS * 2; // After 2 days.

		/**
		 * Allowed plugin IDs for the notice.
		 *
		 * @since 2.0
		 * @var string[] $allowed_plugins
		 */
		protected $allowed_plugins = array(
			'defender',
			'smartcrawl',
			'forminator',
			'hustle',
			'branda',
			'beehive',
		);

		/**
		 * Initializes and returns the notice instance.
		 *
		 * @since 2.0
		 *
		 * @param array $options Plugin options.
		 */
		protected function __construct( array $options ) {
			parent::__construct( $options );

			// Dismiss all plugin notices once giveaway is dismissed.
			add_action( 'wpmudev_notices_after_notice_action', array( $this, 'maybe_dismiss_all' ), 10, 3 );
		}

		/**
		 * Render a notice type content.
		 *
		 * @since 2.0
		 *
		 * @param string $plugin Plugin ID.
		 *
		 * @return void
		 */
		public function render( $plugin ) {
			$this->enqueue_assets( $plugin );

			echo '<div id="wpmudev-plugin-notices" class="wpmudev-plugin-notices sui-wrap"></div>';
		}

		/**
		 * Enqueue assets for a notice if required.
		 *
		 * @since 2.0
		 *
		 * @param string $plugin Plugin ID.
		 *
		 * @return void
		 */
		protected function enqueue_assets( $plugin ) {
			$script_handle = 'wpmudev-notices-giveaway';

			wp_enqueue_style(
				$script_handle,
				$this->assets_url( 'css/giveaway-banner.min.css' ),
				array(),
				Handler::instance()->version
			);

			wp_enqueue_script(
				$script_handle,
				$this->assets_url( 'js/giveaway-banner.min.js' ),
				array( 'wp-element', 'wp-i18n' ),
				Handler::instance()->version,
				true
			);

			// Script vars.
			wp_localize_script(
				$script_handle,
				'wpmudevNoticeGiveaway',
				array(
					'pluginId' => $plugin,
					'apiUrl'   => $this->api_url( 'giveaway/v1/plugin' ),
					'images'   => array(
						'form'      => $this->assets_url( 'images/giveaway/form/' . $plugin . '.png' ),
						'form2x'    => $this->assets_url( 'images/giveaway/form/' . $plugin . '@2x.png' ),
						'success'   => $this->assets_url( 'images/giveaway/success/common.png' ),
						'success2x' => $this->assets_url( 'images/giveaway/success/common@2x.png' ),
					),
					'nonce'    => wp_create_nonce( 'wpmudev_notices_action' ),
				)
			);
		}

		/**
		 * Check if current notice is allowed for the plugin.
		 *
		 * @since 2.0
		 *
		 * @param string $plugin Plugin ID.
		 *
		 * @return bool
		 */
		public function can_show( $plugin ) {
			// Should be a valid plugin and not on dashboard.
			$allowed_plugins = in_array( $plugin, $this->allowed_plugins, true );
			// Check if WPMUDEV Dashboard plugin is active.
			$dash_installed = class_exists( '\WPMUDEV_Dashboard' );

			return $allowed_plugins && ! $dash_installed && ! $this->is_dismissed();
		}

		/**
		 * Check if any of the plugins has already dismissed the notice.
		 *
		 * @since 2.0.1
		 *
		 * @return bool
		 */
		private function is_dismissed() {
			$option = Handler::instance()->get_option();

			if ( ! empty( $option['done'] ) ) {
				foreach ( $option['done'] as $notices ) {
					// Remove from the queue.
					if ( isset( $notices[ $this->type ] ) ) {
						// Make sure to dismiss all.
						$this->dismiss_all();

						return true;
					}
				}
			}

			return false;
		}

		/**
		 * Parse options for the notice.
		 *
		 * @since 2.0
		 *
		 * @param array $options Plugin options.
		 *
		 * @return array
		 */
		protected function parse_options( array $options ) {
			return wp_parse_args(
				$options,
				array(
					'installed_on' => time() + $this->time,
				)
			);
		}

		/**
		 * Remove a notice from the queue.
		 *
		 * If a giveaway notice is dismissed permanently, we need to hide
		 * all plugins' giveaway notices.
		 *
		 * @since 2.0.1
		 *
		 * @param string $plugin Plugin ID.
		 * @param string $type   Notice type.
		 *
		 * @param string $action Action.
		 *
		 * @return void
		 */
		public function maybe_dismiss_all( $action, $plugin, $type ) {
			// Not a giveaway notice.
			if ( $this->type === $type && 'dismiss_notice' === $action ) {
				$this->dismiss_all();
			}
		}

		/**
		 * Mark all plugins' giveaway notices as done.
		 *
		 * @since 2.0.1
		 *
		 * @return void
		 */
		private function dismiss_all() {
			$option = Handler::instance()->get_option();

			if ( ! empty( $option['queue'] ) ) {
				foreach ( $option['queue'] as $plugin_id => $notices ) {
					// Remove from the queue.
					if ( isset( $notices[ $this->type ] ) ) {
						unset( $option['queue'][ $plugin_id ][ $this->type ] );
						// Add to done list.
						if ( ! isset( $option['done'][ $plugin_id ] ) ) {
							$option['done'][ $plugin_id ] = array();
						}
						$option['done'][ $plugin_id ][ $this->type ] = time();
					}
				}

				// Update the queue.
				Handler::instance()->update_option( $option );
			}
		}

		/**
		 * Extend a notice to future time.
		 *
		 * If notice not found in queue, it will be added.
		 *
		 * @since 2.0
		 *
		 * @param string $plugin Plugin ID.
		 *
		 * @return void
		 */
		public function extend_notice( $plugin ) {
			$option = Handler::instance()->get_option();

			if (
				isset( $option['plugins'][ $plugin ] ) // Only if already registered.
				&& ! isset( $option['done'][ $plugin ][ $this->type ] ) // Should not be in done list.
			) {
				// Extend to future.
				$option['queue'][ $plugin ][ $this->type ] = $this->get_next_schedule(
					false,
					DAY_IN_SECONDS * 30 // Extend 30 days.
				);

				// Update queue.
				Handler::instance()->update_option( $option );
			}
		}
	}
}
