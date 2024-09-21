<?php
/**
 * WebP disabled meta box.
 *
 * @since 3.8.0
 * @package WP_Smush
 */

if ( ! defined( 'WPINC' ) ) {
	die;
}

?>

<div class="sui-message sui-no-padding">
	<?php if ( ! apply_filters( 'wpmudev_branding_hide_branding', false ) ) : ?>
		<img src="<?php echo esc_url( WP_SMUSH_URL . 'app/assets/images/graphic-smush-webp-default.png' ); ?>"
		srcset="<?php echo esc_url( WP_SMUSH_URL . 'app/assets/images/graphic-smush-webp-default@2x.png' ); ?> 2x" alt="<?php esc_html_e( 'Smush WebP', 'wp-smushit' ); ?>" class="sui-image" />
	<?php endif; ?>
	<div class="sui-message-content">
		<p>
			<?php esc_html_e( 'Fix the "Serve images in next-gen format" Google PageSpeed recommendation with a single click! Serve WebP images directly from your server to supported browsers, while seamlessly switching to original images for those without WebP support. All without relying on a CDN or any server configuration.', 'wp-smushit' ); ?>
		</p>
		<?php if ( $this->settings->has_cdn_page() && $this->settings->is_cdn_active() ) : ?>
		<div class="sui-notice sui-notice-warning" style="text-align: left" >
			<div class="sui-notice-content">
				<div class="sui-notice-message">
					<span class="sui-notice-icon sui-icon-warning-alert sui-md" aria-hidden="true"></span>
					<p>
						<?php
						if ( $this->settings->is_cdn_webp_conversion_active() ) {
							printf( /* translators: 1: Opening a link, 2: Closing the link */
								esc_html__( 'It looks like your site is already serving WebP images via the CDN. Please %1$sdisable the CDN%2$s if you prefer to use Local WebP instead.', 'wp-smushit' ),
								'<a href="' . esc_url( $this->get_url( 'smush-cdn#smush-cancel-cdn' ) ) . '">',
								'</a>'
							);
						} else {
							printf( /* translators: 1: Opening a link, 2: Closing the link */
								esc_html__( 'It looks like the CDN is enabled. Please %1$senable WebP support%2$s on the CDN page to serve WebP images via the CDN. Or disable the CDN if you wish to use Local WebP.', 'wp-smushit' ),
								'<a href="' . esc_url( $this->get_url( 'smush-cdn#webp' ) ) . '">',
								'</a>'
							);
						}
						?>
					</p>
				</div>
			</div>
		</div>
		<button class="sui-button sui-button-blue" disabled="true" id="smush-toggle-webp-button" data-action="enable">
		<?php else : ?>
		<button class="sui-button sui-button-blue" id="smush-toggle-webp-button" data-action="enable">
		<?php endif; ?>
			<span class="sui-loading-text"><?php esc_html_e( 'Get started', 'wp-smushit' ); ?></span>
			<i class="sui-icon-loader sui-loading" aria-hidden="true"></i>
		</button>
	</div>
</div>
