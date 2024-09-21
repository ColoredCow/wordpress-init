<?php
$docs_link = $this->get_utm_link(
	array(
		'utm_campaign' => 'smush_troubleshooting_docs',
	),
	'https://wpmudev.com/docs/wpmu-dev-plugins/smush/#troubleshooting-guide'
);
?>
<div class="sui-notice sui-notice-error">
	<div class="sui-notice-content">
		<div class="sui-notice-message">
			<span class="sui-notice-icon sui-icon-warning-alert sui-md" aria-hidden="true"></span>
			<p>
				<?php
				printf(
					/* translators: 1: Open a link, 2: Close the link */
					esc_html__( 'Bulk Smush failed due to problems on your site. Please retry or refer to our %1$stroubleshooting guide%2$s to help resolve this.', 'wp-smushit' ),
					'<a style="text-decoration:underline" target="_blank" href="' . esc_url( $docs_link ) . '">',
					'</a>'
				);
				?>
			</p>
		</div>
	</div>
</div>
<a href="<?php echo esc_url( $this->get_url( 'smush-bulk&smush-action=start-bulk-smush' ) ); ?>" class="sui-button sui-button-blue wp-smush-retry-bulk-smush-link">
	<?php esc_html_e( 'Retry', 'wp-smushit' ); ?>
</a>
