<?php
use Smush\Core\Helper;

$recheck_images_link = Helper::get_recheck_images_link();

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
					/* translators: 1: Open span tag <span>, 2: Open a link, 3: Close the link, 4: Close span tag </span>*/
					esc_html__( 'Scan failed due to limited resources on your site. We have adjusted the scan to use fewer resources the next time. %1$sPlease retry or refer to our %2$stroubleshooting guide%3$s to help resolve this.%4$s', 'wp-smushit' ),
					'<span style="display:block;margin-top:10px;margin-bottom:5px">',
					'<a style="text-decoration:underline" target="_blank" href="' . esc_url( $docs_link ) . '">',
					'</a>',
					'</span>'
				);
				?>
			</p>
		</div>
	</div>
</div>
<a href="<?php echo esc_url( $recheck_images_link ); ?>" class="sui-button sui-button-blue wp-smush-retry-scan-link">
	<?php esc_html_e( 'Re-check Images', 'wp-smushit' ); ?>
</a>
