<?php
/**
 * We also show up it via js after clicking on Bulk Smush Now.
 *
 * @see bulk-smush.js - background-process.js
 *
 * @var bool  $background_in_processing Whether background is in processing or not.
 * @var string $bulk_upgrade_url        CDN upgrade url.
 */
use Smush\App\Admin;
?>
<div class="sui-box-body sui-margin-top wp-smush-upsell-cdn <?php echo ! $background_in_processing ? ' sui-hidden' : ''; ?>">
	<div class="smush-box-image">
		<img class="sui-image-icon" src="<?php echo esc_url( WP_SMUSH_URL . 'app/assets/images/bulk-smush/cdn-upsell-icon.png' ); ?>"
		srcset="<?php echo esc_url( WP_SMUSH_URL . 'app/assets/images/bulk-smush/cdn-upsell-icon@2x.png' ); ?> 2x"
		alt="<?php esc_html_e( 'Smush CDN Icon', 'wp-smushit' ); ?>">
		</div>
	<div class="sui-box-content">
		<p>
		<?php
		printf(
			/* translators: %d: Number of CDN PoP locations */
			esc_html__( 'Want to serve images even faster? Get up to 2x more speed with Smush Pro’s CDN, which spans %d servers worldwide.', 'wp-smushit' ),
			Admin::CDN_POP_LOCATIONS
		);
		?>
		</p>
		<a href="<?php echo esc_url( $bulk_upgrade_url ); ?>" class="smush-upsell-link" target="_blank">
			<?php
			printf(
				/* translators: %s: Discount */
				esc_html__( 'Unlock now with Pro and get %s off.', 'wp-smushit' ),
				esc_html( WP_Smush::get_instance()->admin()->get_plugin_discount() )
			);
			?>
		</a>
	</div>
</div>
