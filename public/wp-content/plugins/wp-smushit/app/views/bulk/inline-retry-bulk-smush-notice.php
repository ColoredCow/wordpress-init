<?php
if ( ! defined( 'WPINC' ) ) {
	die;
}

$docs_link = $this->get_utm_link(
	array(
		'utm_campaign' => 'smush_troubleshooting_docs',
	),
	'https://wpmudev.com/docs/wpmu-dev-plugins/smush/#troubleshooting-guide'
);
?>
<div class="sui-notice sui-notice-error wp-smush-inline-retry-bulk-smush-notice">
	<div class="sui-notice-content">
		<div class="sui-notice-message">
			<i class="sui-notice-icon sui-icon-info sui-md" aria-hidden="true"></i>
			<p>
				<span style="display:block; margin-bottom:10px;">
					<?php
					printf(
						/* translators: 1: Open link, 2: Close the link */
						esc_html__( 'Bulk Smush failed due to problems on your site. Please retry or refer to our %1$stroubleshooting guide%2$s to help resolve this.', 'wp-smushit' ),
						'<a style="text-decoration:underline" target="_blank" href="' . esc_url( $docs_link ) . '">',
						'</a>'
					);
					?>
				</span>
				<a href="#" class="wp-smush-trigger-bulk-smush">
					<?php esc_html_e( 'Retry', 'wp-smushit' ); ?>
				</a>
			</p>
		</div>
		<div class="sui-notice-actions"><button class="sui-button-icon"  data-notice-close="smush-box-inline-retry-bulk-smush-notice" type="button"><span class="sui-icon-check" aria-hidden="true"></span><span class="sui-screen-reader-text"><?php esc_html_e( 'Close this notice', 'wp-smushit' ); ?></span></button></div>
	</div>
</div>
