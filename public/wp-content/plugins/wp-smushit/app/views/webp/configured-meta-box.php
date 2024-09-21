<?php
/**
 * WebP configured status meta box.
 *
 * @package WP_Smush
 */
use Smush\Core\Stats\Global_Stats;

if ( ! defined( 'WPINC' ) ) {
	die;
}

$required_bulk_smush = Global_Stats::get()->is_outdated() || Global_Stats::get()->get_remaining_count() > 0;
if ( is_multisite() ) {
	$notice_type    = 'success';
	$icon_name      = 'check-tick';
	$status_message = __( 'Local WebP is active.', 'wp-smushit' );
} elseif ( $required_bulk_smush ) {
	$notice_type    = 'warning';
	$icon_name      = 'warning-alert';
	$status_message = __( 'Local WebP is active.', 'wp-smushit' );
} else {
	$notice_type    = 'success';
	$icon_name      = 'check-tick';
	$status_message = __( 'Local WebP is active and working well.', 'wp-smushit' );
}
?>
<div class="sui-notice sui-notice-<?php echo esc_attr( $notice_type ); ?>">
	<div class="sui-notice-content">
		<div class="sui-notice-message">
			<i class="sui-notice-icon sui-icon-<?php echo esc_attr( $icon_name ); ?> sui-md" aria-hidden="true"></i>
			<p>
				<?php echo esc_html( $status_message ); ?>
			</p>
			<p>
				<?php
				if ( is_multisite() ) {
					esc_html_e( 'Please run Bulk Smush on each subsite to serve existing images as WebP.', 'wp-smushit' );
				} elseif ( $required_bulk_smush ) {
					printf(
						/* translators: 1. opening 'a' tag, 2. closing 'a' tag */
						esc_html__( '%1$sBulk Smush%2$s now to serve existing images as WebP.', 'wp-smushit' ),
						'<a href="' . esc_url( $this->get_url( 'smush-bulk&smush-action=start-bulk-webp-conversion#smush-box-bulk' ) ) . '">',
						'</a>'
					);
				} elseif ( ! $this->settings->is_automatic_compression_active() ) {
					printf(
						/* translators: 1. opening 'a' tag, 2. closing 'a' tag */
						esc_html__( 'If you wish to automatically convert all new uploads to WebP, please enable the %1$sAutomatic Compression%2$s setting on the Bulk Smush page.', 'wp-smushit' ),
						'<a href="' . esc_url( $this->get_url( 'smush-bulk' ) ) . '#column-auto">',
						'</a>'
					);
				} else {
					esc_html_e( 'Newly uploaded images will be automatically converted to WebP.', 'wp-smushit' );
				}
				?>
			</p>
		</div>
	</div>
</div>