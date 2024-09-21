<?php
/**
 * Progress bar block.
 *
 * @package WP_Smush
 *
 * @var integer $count          Total number of images to smush.
 * @var string  $background_in_processing_notice
 * @var bool $background_processing_enabled
 */
use Smush\Core\Media_Library\Background_Media_Library_Scanner;

if ( ! defined( 'WPINC' ) ) {
	die;
}
$background_scan_status = Background_Media_Library_Scanner::get_instance()->get_background_process()->get_status();
$recheck_images_notice  = __( 'Some images might need to be rechecked to ensure statistical data is accurate.', 'wp-smushit' );
$docs_link              = $this->get_utm_link(
	array(
		'utm_campaign' => 'smush_troubleshooting_docs',
	),
	'https://wpmudev.com/docs/wpmu-dev-plugins/smush/#troubleshooting-guide'
);
?>
<div class="sui-notice sui-notice-warning wp-smush-recheck-images-notice-warning">
	<div class="sui-notice-content">
		<div class="sui-notice-message">
			<i class="sui-notice-icon sui-icon-info sui-md" aria-hidden="true"></i>
			<p>
				<?php if ( $background_scan_status->is_dead() ) : ?>
					<span data-original-text="<?php echo esc_attr( $recheck_images_notice ); ?>">
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
					</span>
				<?php else : ?>
					<span>
						<?php echo esc_html( $recheck_images_notice ); ?>
					</span>
				<?php endif; ?>
				<a href="#" class="wp-smush-trigger-background-scan">
					<?php esc_html_e( 'Re-check Now', 'wp-smushit' ); ?>
				</a>
			</p>
		</div>
		<div class="sui-notice-actions"><button class="sui-button-icon" type="button"><span class="sui-icon-check" aria-hidden="true"></span><span class="sui-screen-reader-text"><?php esc_html_e( 'Close this notice', 'wp-smushit' ); ?></span></button></div>
	</div>
</div>

<div class="sui-notice sui-notice-success wp-smush-recheck-images-notice-success sui-hidden">
	<div class="sui-notice-content">
		<div class="sui-notice-message">
			<i class="sui-notice-icon sui-icon-info sui-md" aria-hidden="true"></i>
			<p>
				<?php
					/* translators: %s: Resume Bulk Smush link */
					printf( esc_html__( 'Image re-check complete. %s', 'wp-smushit' ), '<a href="#" class="wp-smush-trigger-bulk-smush">' . esc_html__( 'Resume Bulk Smush', 'wp-smushit' ) . '</a>' );
				?>
			</p>
		</div>
		<div class="sui-notice-actions"><button class="sui-button-icon" type="button"><span class="sui-icon-check" aria-hidden="true"></span><span class="sui-screen-reader-text"><?php esc_html_e( 'Close this notice', 'wp-smushit' ); ?></span></button></div>
	</div>
</div>
