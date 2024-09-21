<?php
/**
 * Rating notice class.
 *
 * Rating notice is almost same as email notice. Only the notice
 * content is different.
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

if ( ! class_exists( __NAMESPACE__ . '\\Rating' ) ) {
	/**
	 * Class Rating
	 *
	 * @since   2.0
	 * @package WPMUDEV\Notices
	 */
	class Rating extends Email {

		/**
		 * Current notice type.
		 *
		 * @since 2.0
		 * @var string $type
		 */
		protected $type = 'rate';

		/**
		 * Show after 1 week.
		 *
		 * @since 2.0
		 * @var string $time
		 */
		protected $time = WEEK_IN_SECONDS; // After 1 week.

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

			/* translators: %s - plugin name */
			$title = __( 'Enjoying %s? We’d love to hear your feedback!', 'wdev_frash' );
			$title = apply_filters( 'wdev_rating_title_' . $plugin, $title );

			/* translators: %s - plugin name */
			$message = __( 'You’ve been using %s for over a week now, and we’d love to hear about your experience! We’ve spent countless hours developing it for you, and your feedback is important to us. We’d really appreciate your rating.', 'wdev_frash' );
			$message = apply_filters( 'wdev_rating_message_' . $plugin, $message );

			// Plugin title.
			$plugin_title = $this->get_option( 'title', __( 'Plugin', 'wdev_frash' ) );

			?>
			<div class="notice notice-info frash-notice frash-notice-<?php echo esc_attr( $this->type ); ?> hidden">
				<?php $this->render_hidden_fields( $plugin ); // Render hidden fields. ?>

				<div class="frash-notice-logo <?php echo esc_attr( $plugin ); ?>"><span></span></div>
				<div class="frash-notice-message">
					<p class="notice-title"><?php printf( esc_html( $title ), esc_html( $plugin_title ) ); ?></p>
					<p><?php printf( esc_html( $message ), esc_html( $plugin_title ) ); ?></p>
					<div class="frash-notice-actions">
						<a href="#" class="frash-notice-act frash-stars" data-msg="<?php esc_attr_e( 'Thanks :)', 'wdev_frash' ); ?>">
							<span>★</span><span>★</span><span>★</span><span>★</span><span>★</span>
						</a>
						<span class="frash-notice-cta-divider">|</span>
						<a href="#" class="frash-notice-dismiss" data-msg="<?php esc_attr_e( 'Saving', 'wdev_frash' ); ?>">
							<?php esc_html_e( 'Dismiss', 'wdev_frash' ); ?>
						</a>
					</div>
				</div>
			</div>
			<?php
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
			// Mailchimp list id is required.
			$wp_slug = $this->get_option( 'wp_slug' );

			// Show only on dashboard.
			return 'dashboard' === $this->screen_id() && ! empty( $wp_slug );
		}
	}
}
