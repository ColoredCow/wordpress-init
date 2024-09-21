<?php
/**
 * NextGen meta box.
 *
 * @package WP_Smush
 *
 * @var int    $total_images_to_smush Resmush + unsmushed image count.
 * @var Admin  $ng                    NextGen admin class.
 * @var int    $total_count           Total count.
 * @var int    $unsmushed_count       Unsmushed images count.
 * @var array  $resmush_count         Resmush count.
 * @var string $url                   Media library URL.
 */

if ( ! defined( 'WPINC' ) ) {
	die;
}
?>

<?php if ( 0 !== absint( $total_count ) ) : ?>
	<p><?php esc_html_e( 'Bulk smush detects images that can be optimized and allows you to compress them in bulk.', 'wp-smushit' ); ?></p>
<?php else : ?>
	<div class="sui-message">
		<?php if ( ! apply_filters( 'wpmudev_branding_hide_branding', false ) ) : ?>
			<img src="<?php echo esc_url( WP_SMUSH_URL . 'app/assets/images/smush-no-media.png' ); ?>" alt="<?php esc_attr_e( 'No attachments found - Upload some images', 'wp-smushit' ); ?>" class="sui-image">
		<?php endif; ?>
		<div class="sui-message-content">
			<p>
				<?php
				printf( /* translators: %1$s: opening a tga, %2$s: closing a tag */
					esc_html__(
						'We haven\'t found any images in your %1$sgallery%2$s yet, so there\'s no smushing to be done! Once you upload images, reload this page and start playing!',
						'wp-smushit'
					),
					'<a href="' . esc_url( admin_url( 'admin.php?page=ngg_addgallery' ) ) . '">',
					'</a>'
				);
				?>
			</p>

			<a class="sui-button sui-button-blue" href="<?php echo esc_url( admin_url( 'admin.php?page=ngg_addgallery' ) ); ?>">
				<?php esc_html_e( 'UPLOAD IMAGES', 'wp-smushit' ); ?>
			</a>
		</div>
	</div>
	<?php return; ?>
<?php endif; ?>

<?php $this->view( 'all-images-smushed-notice', array( 'all_done' => empty( $total_images_to_smush ) ), 'common' ); ?>

<?php
$in_processing_notice = __( 'Bulk smush is currently running. You need to keep this page open for the process to complete.', 'wp-smushit' );
$this->view( 'progress-bar',
	array(
		'count'                           => $total_count,
		'background_processing_enabled'   => false,
		'background_in_processing_notice' => '',
		'in_processing_notice'            => $in_processing_notice,
	),
	'common'
);
?>

<div class="smush-final-log sui-hidden">
	<div class="smush-bulk-errors"></div>
	<div class="smush-bulk-errors-actions">
		<a href="<?php echo esc_url( admin_url( 'admin.php?page=nggallery-manage-gallery' ) ); ?>" class="sui-button sui-button-icon sui-button-ghost">
			<i class="sui-icon-photo-picture" aria-hidden="true"></i>
			<?php esc_html_e( 'View all', 'wp-smushit' ); ?>
		</a>
	</div>
</div>

<div class="wp-smush-bulk-wrapper sui-border-frame<?php echo empty( $total_images_to_smush ) ? ' sui-hidden' : ''; ?>">

	<div id="wp-smush-bulk-content">
		<?php WP_Smush::get_instance()->admin()->print_pending_bulk_smush_content( $total_images_to_smush, $resmush_count, $unsmushed_count ); ?>
	</div>

	<button type="button" class="sui-button sui-button-blue wp-smush-nextgen-bulk">
		<?php esc_html_e( 'BULK SMUSH', 'wp-smushit' ); ?>
	</button>
</div>
