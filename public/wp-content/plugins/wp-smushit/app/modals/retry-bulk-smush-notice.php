<?php
/**
 * Bulk Smush retry dialog.
 */
$docs_link = $this->get_utm_link(
	array(
		'utm_campaign' => 'smush_troubleshooting_docs',
	),
	'https://wpmudev.com/docs/wpmu-dev-plugins/smush/#troubleshooting-guide'
);
?>

<div class="sui-modal sui-modal-md">
	<div
		role="dialog"
		id="smush-retry-bulk-smush-notice"
		class="sui-modal-content smush-retry-modal smush-retry-bulk-smush-notice"
		aria-modal="true"
		aria-labelledby="smush-retry-bulk-smush-notice-title"
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
				<h3 class="sui-box-title sui-lg"><?php esc_html_e( 'Bulk Smush Failed!', 'wp-smushit' ); ?></h3>
			</div>
			<div class="sui-box-body sui-flatten sui-content-center sui-no-padding-top sui-spacing-bottom--30">
				<p class="sui-description">
				<?php
				printf(
					/* translators: 1: Open link, 2: Close the link */
					esc_html__( 'Bulk Smush failed due to problems on your site. Please retry or refer to our %1$stroubleshooting guide%2$s to help resolve this.', 'wp-smushit' ),
					'<a target="_blank" href="' . esc_url( $docs_link ) . '">',
					'</a>'
				);
				?>
				</p>
			</div>
			<div class="sui-box-footer sui-flatten sui-content-center sui-spacing-bottom--60">
				<a href="#wp-smush-bulk-content" class="sui-button smush-retry-bulk-smush-notice-button">
					<?php esc_html_e( 'Retry', 'wp-smushit' ); ?>
				</a>
			</div>
		</div>
	</div>
</div>
