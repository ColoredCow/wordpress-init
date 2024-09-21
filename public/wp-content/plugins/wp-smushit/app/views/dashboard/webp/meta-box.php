<?php
/**
 * Local WebP meta box.
 *
 * @since 3.8.6
 * @package WP_Smush
 *
 * @var bool          $is_configured     Is local WebP module configured. Error message if it's not.
 * @var string        $error_message     Server configuration error message.
 * @var bool          $is_webp_active    Is local WebP module enabled.
 * @var string        $upsell_url        Upsell URL.
 */

if ( ! defined( 'WPINC' ) ) {
	die;
}

?>

<p>
	<?php esc_html_e( "Serve WebP versions of your images to supported browsers, and gracefully fall back on JPEGs and PNGs for browsers that don't support WebP.", 'wp-smushit' ); ?>
</p>

<?php if ( ! WP_Smush::is_pro() ) : ?>
	<a href="<?php echo esc_url( $upsell_url ); ?>" target="_blank" class="sui-button sui-button-purple">
		<?php esc_html_e( 'Upgrade to Pro', 'wp-smushit' ); ?>
	</a>
<?php elseif ( ! $is_webp_active ) : ?>
	<button class="sui-button sui-button-blue" id="smush-toggle-webp-button" data-action="enable">
		<span class="sui-loading-text"><?php esc_html_e( 'Activate', 'wp-smushit' ); ?></span>
		<i class="sui-icon-loader sui-loading" aria-hidden="true"></i>
	</button>
<?php else : ?>
	<?php
	if ( $is_configured ) {
		$this->view( 'webp/configured-meta-box' );
	} else {
		$this->view(
			'webp/required-configuration-meta-box',
			array(
				'error_message' => $error_message,
			)
		);
	}
	?>
	<a href="<?php echo esc_url( $this->get_url( 'smush-webp' ) ); ?>" class="sui-button sui-button-ghost">
		<span class="sui-icon-wrench-tool" aria-hidden="true"></span>
		<?php esc_html_e( 'Configure', 'wp-smushit' ); ?>
	</a>
<?php endif; ?>
