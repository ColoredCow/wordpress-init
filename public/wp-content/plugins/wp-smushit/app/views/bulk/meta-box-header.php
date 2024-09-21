<?php
/**
 * Bulk Smush meta box header.
 *
 * @package WP_Smush
 *
 * @var string $title  Title.
 */

if ( ! defined( 'WPINC' ) ) {
	die;
}

$start_bulk_webp_conversion = ! empty( $_GET['smush-action'] ) && 'start-bulk-webp-conversion' === wp_unslash( $_GET['smush-action'] );

?>

<h3 class="sui-box-title">
	<?php echo esc_html( $title ); ?>
	<?php
	if ( $start_bulk_webp_conversion && $this->settings->is_webp_module_active() ) {
		echo ' - ';
		esc_html_e( 'WebP Conversion', 'wp-smushit' );
	}
	?>
</h3>

<div class="sui-actions-right">
	<small>
		<?php
		printf(
			/* translators: %1$s - a href opening tag, %2$s - a href closing tag */
			esc_html__( 'Smush individual images via your %1$sMedia Library%2$s', 'wp-smushit' ),
			'<a href="' . esc_url( admin_url( 'upload.php' ) ) . '" title="' . esc_html__( 'Media Library', 'wp-smushit' ) . '">',
			'</a>'
		);
		?>
	</small>
</div>
