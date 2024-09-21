<?php
/**
 * Plugin notices handler.
 *
 * This class will take care of registering, queuing and showing different
 * notices across WP pages.
 *
 * @since      2.0
 * @author     Incsub (Joel James)
 * @license    http://www.gnu.org/licenses/old-licenses/gpl-2.0.html
 * @copyright  Copyright (c) 2022, Incsub
 * @package    WPMUDEV\Notices
 * @subpackage Handler
 */

namespace WPMUDEV\Notices;

// If this file is called directly, abort.
defined( 'WPINC' ) || die;

if ( ! class_exists( __NAMESPACE__ . '\\Handler' ) ) {
	/**
	 * Class Module
	 *
	 * @since   2.0
	 * @package WPMUDEV\Notices
	 */
	final class Handler {

		/**
		 * Current version.
		 *
		 * @since 2.0
		 * @var string $version
		 */
		public $version = '2.0.5';

		/**
		 * Option name to store data.
		 *
		 * @since 2.0
		 * @var string $option_name
		 */
		protected $option_name = 'wpmudev_notices';

		/**
		 * Registered plugins for the opt-ins.
		 *
		 * @since 2.0
		 * @var array[] $plugins
		 */
		private $plugins = array();

		/**
		 * WordPress screen IDs to show notices on.
		 *
		 * @since 2.0
		 * @var array[] $screens
		 */
		private $screens = array();

		/**
		 * Disabled notice types.
		 *
		 * @since 2.0
		 * @var string[] $disabled
		 */
		private $disabled = array( 'email', 'giveaway' );

		/**
		 * Registered plugin notices data from db.
		 *
		 * @since 2.0
		 * @var array|null $queue
		 */
		private $stored = null;

		/**
		 * Notice types that are shown on WP Dashboard.
		 *
		 * @since 2.0
		 * @var array $wp_notices
		 */
		private $wp_notices = array(
			'email' => '\WPMUDEV\Notices\Notices\Email',
			'rate'  => '\WPMUDEV\Notices\Notices\Rating',
		);

		/**
		 * Notice type that are shown within plugin pages.
		 *
		 * @since 2.0
		 * @var array $plugin_notices
		 */
		private $plugin_notices = array(
			'giveaway' => '\WPMUDEV\Notices\Notices\Giveaway',
		);

		/**
		 * Construct handler class.
		 *
		 * @since 2.0
		 */
		protected function __construct() {
			// Register plugins.
			add_action( 'wpmudev_register_notices', array( $this, 'register' ), 10, 2 );

			// Always setup ajax actions.
			add_action( 'wp_ajax_wpmudev_notices_action', array( $this, 'process_action' ) );

			// Render admin notices.
			add_action( 'load-index.php', array( $this, 'admin_notice' ) );
		}

		/**
		 * Initializes and returns the singleton instance.
		 *
		 * @since 2.0
		 *
		 * @return static
		 */
		public static function instance() {
			static $instance = null;

			if ( null === $instance ) {
				$instance = new self();
			}

			return $instance;
		}

		/**
		 * Register an active plugin for notices.
		 *
		 * ```php
		 * do_action(
		 *      'wpmudev_register_notices',
		 *      'beehive', // Plugin id.
		 *      array(
		 *          'basename'     => plugin_basename( BEEHIVE_PLUGIN_FILE ), // Required: Plugin basename (for backward compat).
		 *          'title'        => 'Beehive', // Required: Plugin title.
		 *          'wp_slug'      => 'beehive-analytics', // Required: wp.org slug of the plugin.
		 *          'cta_email'    => __( 'Get Fast!', 'ga_trans' ), // Email button CTA.
		 *          'installed_on' => time(), // Optional: Plugin activated time.
		 *          'screens'      => array( // Required: Plugin screen ids.
		 *                  'toplevel_page_beehive',
		 *                  'dashboard_page_beehive-accounts',
		 *                  'dashboard_page_beehive-settings',
		 *                  'dashboard_page_beehive-tutorials',
		 *                  'dashboard_page_beehive-google-analytics',
		 *                  'dashboard_page_beehive-google-tag-manager',
		 *          ),
		 *      )
		 * );
		 * ```
		 *
		 * @since 2.0
		 *
		 * @param array  $options   Options.
		 *
		 * @param string $plugin_id Plugin ID.
		 */
		public function register( $plugin_id, array $options = array() ) {
			// Plugin ID can't be empty.
			if ( empty( $plugin_id ) ) {
				return;
			}

			// Add to the plugins list.
			$this->plugins[ $plugin_id ] = $options;

			// Setup screens.
			if ( ! empty( $options['screens'] ) ) {
				$this->add_to_screens( $plugin_id, $options['screens'] );
			}
		}

		/**
		 * Show an admin notice on WP page (not plugin's SUI pages).
		 *
		 * @since 2.0
		 *
		 * @return void
		 */
		public function admin_notice() {
			if ( is_super_admin() ) {
				add_action( 'all_admin_notices', array( $this, 'render_admin_notice' ) );
			}
		}

		/**
		 * Show a notice on current plugin page.
		 *
		 * @since 2.0
		 *
		 * @return void
		 */
		public function plugin_notice() {
			if ( is_super_admin() ) {
				add_action( 'all_admin_notices', array( $this, 'render_plugin_notice' ) );
			}
		}

		/**
		 * Render admin notice content.
		 *
		 * @since 2.0
		 *
		 * @return void
		 */
		public function render_admin_notice() {
			$this->render( false, array_keys( $this->wp_notices ) );
		}

		/**
		 * Render a plugin notice content.
		 *
		 * @since 2.0
		 *
		 * @return void
		 */
		public function render_plugin_notice() {
			// Get current screen.
			$screen = get_current_screen();

			// Continue only if registered screen.
			if ( empty( $screen->id ) || empty( $this->screens[ $screen->id ] ) ) {
				return;
			}

			$this->render(
				$this->screens[ $screen->id ],
				array_keys( $this->plugin_notices )
			);
		}

		/**
		 * Process a notice action.
		 *
		 * All ajax requests from the notice are processed here.
		 * After nonce verification the action will be processed if a matching
		 * method is already defined.
		 *
		 * @since 2.0
		 */
		public function process_action() {
			// Check required fields.
			if ( ! isset( $_POST['plugin_id'], $_POST['notice_action'], $_POST['notice_type'], $_POST['nonce'] ) ) {
				wp_die( esc_html__( 'Required fields are missing.', 'wdev_frash' ) );
			}

			// Only admins can do this.
			if ( ! is_super_admin() ) {
				wp_die( esc_html__( 'Access check failed.', 'wdev_frash' ) );
			}

			// Get request data.
			$plugin = sanitize_text_field( wp_unslash( $_POST['plugin_id'] ) );
			$action = sanitize_text_field( wp_unslash( $_POST['notice_action'] ) );
			$type   = sanitize_text_field( wp_unslash( $_POST['notice_type'] ) );
			$nonce  = sanitize_text_field( wp_unslash( $_POST['nonce'] ) );

			// Verify nonce.
			if ( ! wp_verify_nonce( $nonce, 'wpmudev_notices_action' ) ) {
				wp_die( esc_html__( 'Nonce verification failed.', 'wdev_frash' ) );
			}

			// Initialize the options.
			$this->init_option();

			// Get the notice class.
			$notice = $this->get_notice( $plugin, $type );

			// Process action if defined on this class.
			if ( method_exists( $this, $action ) ) {
				call_user_func( array( $this, $action ), $plugin, $type );
			} elseif ( is_object( $notice ) && method_exists( $notice, $action ) ) {
				// Process action if defined on the notice class.
				call_user_func( array( $notice, $action ), $plugin );
			}

			/**
			 * Action hook to do something after a notice action is performed.
			 *
			 * @since 2.0
			 *
			 * @param string $plugin Plugin ID.
			 * @param string $type   Notice type.
			 *
			 * @param string $action Action.
			 */
			do_action( 'wpmudev_notices_after_notice_action', $action, $plugin, $type );

			wp_die();
		}

		/**
		 * Remove a notice from the queue.
		 *
		 * @since 2.0
		 *
		 * @param string $type   Notice type.
		 *
		 * @param string $plugin Plugin ID.
		 *
		 * @return void
		 */
		public function dismiss_notice( $plugin, $type ) {
			// Remove from the queue.
			if ( isset( $this->stored['queue'][ $plugin ][ $type ] ) ) {
				unset( $this->stored['queue'][ $plugin ][ $type ] );
			}

			// Setup done list.
			if ( ! isset( $this->stored['done'][ $plugin ] ) ) {
				$this->stored['done'][ $plugin ] = array();
			}

			// Mark as done.
			$this->stored['done'][ $plugin ][ $type ] = time();

			// Update the queue.
			$this->update_option();
		}

		/**
		 * Getter for the queue data.
		 *
		 * @since 2.0
		 *
		 * @return array
		 */
		public function get_option() {
			$this->init_option();

			return $this->stored;
		}

		/**
		 * Update the notices stored data in db.
		 *
		 * @since 2.0
		 *
		 * @param array $data Option data (optional).
		 *
		 * @return bool
		 */
		public function update_option( $data = false ) {
			// If new data is provided use it.
			if ( ! empty( $data ) ) {
				$this->stored = $data;
			}

			// Update the data.
			return update_site_option( $this->option_name, $this->stored );
		}

		/**
		 * Render notice for the current screen.
		 *
		 * @since 2.0
		 *
		 * @param array        $types     Notice types to render.
		 *
		 * @param string|false $plugin_id Plugin id (false to check all plugins).
		 *
		 * @return void|string
		 */
		protected function render( $plugin_id = false, $types = array() ) {
			// Setup queue when required.
			$this->setup_queue();

			if ( empty( $plugin_id ) ) {
				// Get a random notice.
				$notice = $this->get_random_notice( $types, $plugin_id );
			} else {
				// Get a plugin's notice.
				$notice = $this->get_plugin_notice( $plugin_id, $types );
			}

			// Render if notice found.
			if ( ! empty( $notice ) && method_exists( $notice, 'render' ) ) {
				return call_user_func( array( $notice, 'render' ), $plugin_id );
			}
		}

		/**
		 * Set screen IDs for the notices.
		 *
		 * NOTE: Only one plugin can use one screen id.
		 *
		 * @since 2.0
		 *
		 * @param array  $screens   Screen IDs.
		 *
		 * @param string $plugin_id Plugin ID.
		 *
		 * @return void
		 */
		protected function add_to_screens( $plugin_id, array $screens ) {
			// Set the screens.
			if ( ! empty( $screens ) ) {
				foreach ( $screens as $screen_id ) {
					$this->screens[ $screen_id ] = $plugin_id;

					// Remove network suffix for page hook.
					if ( is_multisite() ) {
						$screen_id = str_replace( '-network', '', $screen_id );
					}

					// Register screen notice.
					add_action( "load-$screen_id", array( $this, 'plugin_notice' ) );
				}
			}
		}

		/**
		 * Setup the notices queue when ready.
		 *
		 * To avoid calling db queries we need to do this only before
		 * a notice is being rendered.
		 *
		 * @since 2.0
		 *
		 * @return void
		 */
		protected function setup_queue() {
			// Initialize data.
			$this->init_option();

			// Setup all registered plugins to in queue.
			foreach ( $this->plugins as $plugin_id => $options ) {
				$this->add_to_queue( $plugin_id, $options );
			}
		}

		/**
		 * Set the queue for the plugin if required.
		 *
		 * We should always schedule all notice types even if they
		 * are disabled. Then only we can enable it later easily.
		 * Disabled notices won't be considered when taken from the queue.
		 *
		 * @since 2.0
		 *
		 * @param array  $options   Options.
		 *
		 * @param string $plugin_id Plugin ID.
		 *
		 * @return void
		 */
		protected function add_to_queue( $plugin_id, array $options ) {
			// Store to notice queue if not saved already.
			if ( ! isset( $this->stored['plugins'][ $plugin_id ] ) ) {
				// Register plugin.
				$this->stored['plugins'][ $plugin_id ] = time();
				$this->stored['queue'][ $plugin_id ]   = array();

				// Add notices to queue.
				foreach ( $this->get_types() as $type => $class_name ) {
					// Notice class.
					$notice = $this->get_notice( $plugin_id, $type );
					// Schedule notice.
					if ( ! empty( $notice ) ) {
						$this->stored['queue'][ $plugin_id ][ $type ] = $notice->get_next_schedule( $options['installed_on'] );
					}
				}

				// Upgrade if required.
				if ( ! empty( $options['basename'] ) ) {
					$this->maybe_upgrade( $plugin_id, $options['basename'] );
				}

				// Update the stored data.
				$this->update_option();
			}
		}

		/**
		 * Init the notices stored data.
		 *
		 * Get from the db only if not already initialized.
		 *
		 * @since 2.0
		 */
		protected function init_option() {
			if ( null === $this->stored ) {
				$queue = (array) get_site_option( $this->option_name, array() );

				$this->stored = wp_parse_args(
					$queue,
					array(
						'plugins' => array(),
						'queue'   => array(),
						'done'    => array(),
					)
				);
			}
		}

		/**
		 * Get a notice object from the entire due list.
		 *
		 * This is usually used for a common WP page where all plugins'
		 * notices are shown. Eg: WP Dashboard page.
		 *
		 * @since 2.0
		 *
		 * @param string|bool $plugin_id Plugin ID.
		 *
		 * @param array       $types     Notice types.
		 *
		 * @return object|false
		 */
		protected function get_random_notice( array $types = array(), &$plugin_id = false ) {
			if ( ! empty( $this->stored['queue'] ) ) {
				// Check all due items.
				foreach ( $this->stored['queue'] as $plugin => $notices ) {
					if ( ! empty( $notices ) ) {
						// Chose one with priority.
						$notice = $this->choose_notice( $plugin, $notices, $types );
						// Return only if a valid notice is selected.
						if ( ! empty( $notice ) ) {
							// Set the plugin id.
							$plugin_id = $plugin;

							return $notice;
						}
					}
				}
			}

			return false;
		}

		/**
		 * Get a notice object for the plugin.
		 *
		 * Select one with priority from the due list for the plugin.
		 *
		 * @since 2.0
		 *
		 * @param array  $types     Notice types.
		 *
		 * @param string $plugin_id Plugin ID.
		 *
		 * @return object|false
		 */
		protected function get_plugin_notice( $plugin_id, array $types = array() ) {
			// Choose one notice from the due list.
			if ( ! empty( $this->stored['queue'][ $plugin_id ] ) ) {
				return $this->choose_notice(
					$plugin_id,
					$this->stored['queue'][ $plugin_id ],
					$types
				);
			}

			return false;
		}

		/**
		 * Choose a notice from the due list.
		 *
		 * Notice will be selected based on the order it's defined
		 * in the $types property of this class.
		 *
		 * @since 2.0
		 *
		 * @param array  $notices   Notices array.
		 * @param array  $types     Notice types.
		 *
		 * @param string $plugin_id Plugin ID.
		 *
		 * @return object|false
		 */
		protected function choose_notice( $plugin_id, array $notices, array $types = array() ) {
			foreach ( $this->get_types() as $type => $class ) {
				// Not in the list.
				if ( ! isset( $notices[ $type ] ) ) {
					continue;
				}

				// Not a desired type, skip.
				if ( ! empty( $types ) && ! in_array( $type, $types, true ) ) {
					continue;
				}

				// Disabled type, skip.
				if ( $this->is_disabled( $type, $plugin_id ) ) {
					continue;
				}

				// Due time reached or passed.
				if ( $this->get_current_time() >= (int) $notices[ $type ] ) {
					// Get the notice class instance.
					$notice = $this->get_notice( $plugin_id, $type );

					// Return the notice object.
					if ( ! empty( $notice ) && $notice->can_show( $plugin_id ) ) {
						return $notice;
					}
				}
			}

			return false;
		}

		/**
		 * Get the notice type class instance.
		 *
		 * @since 2.0
		 *
		 * @param string $type      Notice type.
		 *
		 * @param string $plugin_id Plugin ID.
		 *
		 * @return bool|object
		 */
		protected function get_notice( $plugin_id, $type ) {
			$types = $this->get_types();

			// If a valid class found for the type.
			if (
				isset( $types[ $type ] )
				&& isset( $this->plugins[ $plugin_id ] )
				&& class_exists( $types[ $type ] )
			) {
				/**
				 * Notice class name.
				 *
				 * @var Notices\Notice $class_name
				 */
				$class_name = $types[ $type ];

				return $class_name::instance( $this->plugins[ $plugin_id ] );
			}

			return false;
		}

		/**
		 * Get available notice types and classes.
		 *
		 * @since 2.0
		 *
		 * @return array
		 */
		protected function get_types() {
			return array_merge(
				$this->wp_notices,
				$this->plugin_notices
			);
		}

		/**
		 * Get current time to use for due date check.
		 *
		 * This is used to enable custom time fot testing.
		 *
		 * @since 2.0
		 *
		 * @return int
		 */
		protected function get_current_time() {
			// Get custom time.
			$time = filter_input( INPUT_GET, 'wpmudev_notice_time', FILTER_SANITIZE_SPECIAL_CHARS );

			return empty( $time ) ? time() : (int) $time;
		}

		/**
		 * Check if a notice type is disabled.
		 *
		 * @since 2.0.3
		 *
		 * @param string $type   Notice type.
		 * @param string $plugin Plugin ID.
		 *
		 * @return bool
		 */
		protected function is_disabled( $type, $plugin ) {
			/**
			 * Filter to modify disabled notices list.
			 *
			 * @param array  $disabled Disabled list.
			 * @param string $plugin   Plugin ID.
			 */
			$disabled = apply_filters( 'wpmudev_notices_disabled_notices', $this->disabled, $plugin );

			// Check if notice type is disabled.
			$is_disabled = in_array( $type, $disabled, true );

			/**
			 * Filter to enable/disable a notice type.
			 *
			 * @param bool   $is_disabled Is disabled.
			 * @param string $type        Notice type.
			 * @param string $plugin      Plugin ID.
			 */
			return apply_filters( 'wpmudev_notices_is_disabled', $is_disabled, $type, $plugin );
		}

		/**
		 * Optional upgrade from old version (WDEV Frash).
		 *
		 * If old data exist, we need to use it before registering new time.
		 * Used only when registering for the first time.
		 *
		 * @since      2.0
		 * @deprecated 2.0 We may remove this in future.
		 *
		 * @param string $plugin_id Plugin ID.
		 * @param string $basename  Plugin basename (used in old plugins).
		 *
		 * @return void
		 */
		protected function maybe_upgrade( $plugin_id, $basename ) {
			// Old settings data.
			$deprecated = get_site_option( 'wdev-frash' );

			// Old notice exists, upgrade it.
			if ( ! empty( $deprecated ) ) {
				$deprecated = wp_parse_args(
					(array) $deprecated,
					array(
						'plugins' => array(),
						'queue'   => array(),
						'done'    => array(),
					)
				);

				// Not found in old settings.
				if ( ! isset( $deprecated['plugins'][ $basename ] ) ) {
					return;
				}

				// Use old registered time.
				$this->stored['plugins'][ $plugin_id ] = $deprecated['plugins'][ $basename ];
				// Existing plugin, so show giveaway right away.
				$this->stored['queue'][ $plugin_id ]['giveaway'] = time();

				// Only email and rate types.
				foreach ( array( 'email', 'rate' ) as $type ) {
					// Old key hash.
					$hash = md5( $basename . '-' . $type );

					if ( isset( $deprecated['queue'][ $hash ]['show_at'] ) ) {
						// Use the existing time.
						$this->stored['queue'][ $plugin_id ][ $type ] = $deprecated['queue'][ $hash ]['show_at'];
						// Remove from old settings.
						unset( $deprecated['queue'][ $hash ] );
					} else {
						// Check if notice type found in dismissed list.
						$dismissed = array_filter(
							$deprecated['done'],
							function ( $item ) use ( $basename, $type ) {
								return $item['plugin'] === $basename && $item['type'] === $type;
							}
						);

						// Already shown and dismissed, remove it.
						if ( ! empty( $dismissed[0]['handled_at'] ) ) {
							// Remove from queue.
							unset( $this->stored['queue'][ $plugin_id ][ $type ] );
							// Move to done list.
							$this->stored['done'][ $plugin_id ][ $type ] = $dismissed[0]['handled_at'];
						}
					}
				}

				// Do not delete it yet for backward compatibility.
				update_site_option( 'wdev-frash', $deprecated );
			}
		}
	}
}
