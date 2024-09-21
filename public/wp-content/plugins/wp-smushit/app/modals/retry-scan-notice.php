<?php
/**
 * Scan error notice for lower resource site on background dead.
 */

use Smush\Core\Helper;

$recheck_images_link = Helper::get_recheck_images_link();
$docs_link           = $this->get_utm_link(
	array(
		'utm_campaign' => 'smush_troubleshooting_docs',
	),
	'https://wpmudev.com/docs/wpmu-dev-plugins/smush/#troubleshooting-guide'
);
?>

<div class="sui-modal sui-modal-md">
	<div
		role="dialog"
		id="smush-retry-scan-notice"
		class="sui-modal-content smush-retry-modal smush-retry-scan-notice"
		aria-modal="true"
		aria-labelledby="smush-retry-scan-notice-title"
	>
	<div class="sui-box">
			<div class="sui-box-header sui-flatten sui-content-center sui-spacing-top--60 sui-spacing-bottom--10">
				<button type="button" class="sui-button-icon sui-button-float--right" data-modal-close="">
					<span class="sui-icon-close sui-md" aria-hidden="true"></span>
					<span class="sui-screen-reader-text">
						<?php esc_html_e( 'Close this dialog.', 'wp-smushit' ); ?>
					</span>
				</button>
				<i class="sui-notice-icon sui-icon-info sui-lg" aria-hidden="true"></i>
				<h3 class="sui-box-title sui-lg"><?php esc_html_e( 'Scan Failed!', 'wp-smushit' ); ?></h3>
			</div>
			<div class="sui-box-body sui-flatten sui-content-center sui-no-padding-top sui-spacing-bottom--30">
				<p class="sui-description">
				<?php
				printf(
					/* translators: 1: Open link, 2: Close the link */
					esc_html__( 'Scan failed due to limited resources on your site. We have adjusted the scan to use fewer resources the next time. Please retry or refer to our %1$stroubleshooting guide%2$s to help resolve this.', 'wp-smushit' ),
					'<a target="_blank" href="' . esc_url( $docs_link ) . '">',
					'</a>'
				);
				?>
				</p>
			</div>
			<div class="sui-box-footer sui-flatten sui-content-center sui-spacing-bottom--60">
				<a href="<?php echo esc_url( $recheck_images_link ); ?>" class="sui-button smush-retry-scan-notice-button">
					<?php esc_html_e( 'Retry', 'wp-smushit' ); ?>
				</a>
			</div>
		</div>
	</div>
</div>
