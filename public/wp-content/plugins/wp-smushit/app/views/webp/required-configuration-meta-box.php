<?php
/**
 * WebP status meta box when the server configurations is not configured.
 *
 * @package WP_Smush
 *
 * @var string $error_message  Notice when S3 integration enabled.
 */
use Smush\Core\Webp\Webp_Configuration;

if ( ! defined( 'WPINC' ) ) {
	die;
}

$is_local_webp_page = 'smush-webp' === $this->get_slug();
?>
<div class="sui-notice sui-notice-warning">
	<div class="sui-notice-content">
		<div class="sui-notice-message">
			<i class="sui-notice-icon sui-icon-warning-alert sui-md" aria-hidden="true"></i>
			<p><?php echo esc_html( $error_message ); ?></p>

			<p>
				<?php
				printf(
					/* translators: 1. opening 'a' tag switch to Direct Conversion, 2. Closing 'a' tag, 3. opening 'a' tag to premium support. */
					esc_html__( 'Please try the %1$sDirect Conversion%2$s method if you don\'t have server access or %3$scontact support%2$s for further assistance.', 'wp-smushit' ),
					'<a href="javascript:void(0);" onclick="window?.WP_Smush?.WebP && window.WP_Smush.WebP.switchMethod(\'' . esc_attr( Webp_Configuration::DIRECT_CONVERSION_METHOD ) . '\') ;">',
					'</a>',
					'<a href="https://wpmudev.com/hub2/support/#get-support" target="_blank">',
				)
				?>
			</p>

			<?php if ( $is_local_webp_page ) : ?>
			<div style="margin-top:15px">
				<button type="button" id="smush-webp-recheck" class="sui-button" data-is-configured="0">
					<span class="sui-loading-text"><i class="sui-icon-update"></i><?php esc_html_e( 'Re-check status', 'wp-smushit' ); ?></span>
					<i class="sui-icon-loader sui-loading" aria-hidden="true"></i>
				</button>
				<button id="smush-webp-toggle-wizard" type="button" class="sui-button sui-button-ghost" style="margin-left: 0;">
					<span class="sui-loading-text">
						<?php esc_html_e( 'Reconfigure', 'wp-smushit' ); ?>
					</span>

					<span class="sui-icon-loader sui-loading" aria-hidden="true"></span>
				</button>
			</div>
			<?php endif; ?>
		</div>
	</div>
</div>
