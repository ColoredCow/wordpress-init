<?php
/**
 * WebP meta box.
 *
 * @since 3.8.0
 * @package WP_Smush
 *
 * @var Smush\App\Abstract_Page $this  Page.
 */
use Smush\Core\Webp\Webp_Configuration;

if ( ! defined( 'WPINC' ) ) {
	die;
}

$webp_configuration        = Webp_Configuration::get_instance();
$is_configured             = $webp_configuration->is_configured();
$direct_conversion_enabled = $webp_configuration->direct_conversion_enabled();
$header_desc               = $direct_conversion_enabled ?
					__( 'Serve WebP images directly from your server to supported browsers, with one click. All without relying on a CDN or any server configuration.', 'wp-smushit' ) :
					__( 'Serve WebP images directly from your server to supported browsers, while seamlessly switching to original images for those without WebP support. All without relying on a CDN. Uses a server-side setup.', 'wp-smushit' );
?>

<p>
	<?php echo esc_html( $header_desc ); ?>
</p>

<?php
if ( $is_configured ) {
	$this->view( 'webp/configured-meta-box' );
} else {
	$this->view(
		'webp/required-configuration-meta-box',
		array(
			'error_message' => $webp_configuration->server_configuration()->get_configuration_message(),
		)
	);
}
?>

<div class="sui-box-settings-row">
	<div class="sui-box-settings-col-1">
		<span class="sui-settings-label">
			<?php esc_html_e( 'Supported Media Types', 'wp-smushit' ); ?>
		</span>
		<span class="sui-description">
			<?php esc_html_e( 'Here\'s a list of the media types that will be converted to WebP format.', 'wp-smushit' ); ?>
		</span>
	</div>
	<div class="sui-box-settings-col-2">
		<span class="smush-filename-extension smush-extension-jpg">
			<?php esc_html_e( 'jpg', 'wp-smushit' ); ?>
		</span>
		<span class="smush-filename-extension smush-extension-png">
			<?php esc_html_e( 'png', 'wp-smushit' ); ?>
		</span>
		<span class="sui-description">
			<?php
			printf(
				/* translators: 1. opening 'a' tag to docs, 2. closing 'a' tag. */
				esc_html__( 'To verify if the JPG and PNG images are being served correctly as WebP files, please refer to our %1$sDocumentation%2$s.', 'wp-smushit' ),
				'<a href="https://wpmudev.com/docs/wpmu-dev-plugins/smush/#verifying-webp-output" target="_blank">',
				'</a>'
			);
			?>
		</span>
	</div>
</div>

<?php
if ( $direct_conversion_enabled ) :
	$webp_fallback_activated = $this->settings->is_webp_fallback_active();
	?>
<div class="sui-box-settings-row">
	<div class="sui-box-settings-col-1">
		<span class="sui-settings-label">
			<?php esc_html_e( 'Legacy Browser Support', 'wp-smushit' ); ?>
		</span>
		<span class="sui-description">
			<?php esc_html_e( 'Use JavaScript to serve original image files to unsupported browsers.', 'wp-smushit' ); ?>
		</span>
	</div>
	<div class="sui-box-settings-col-2">
		<div class="sui-form-field">
			<label for="webp-fallback" class="sui-toggle">
				<input
					type="checkbox"
					id="webp-fallback"
					name="webp-fallback"
					aria-labelledby="webp-fallback-label"
					aria-describedby="webp-fallback-description"
					<?php checked( $webp_fallback_activated ); ?>
				/>
				<span class="sui-toggle-slider" aria-hidden="true"></span>
				<span id="webp-fallback-label" class="sui-toggle-label">
					<?php esc_html_e( 'Enable JavaScript Fallback', 'wp-smushit' ); ?>
				</span>
				<span class="sui-description">
					<?php
					printf(
						/* translators: 1: Opening a link, 2: Closing a link */
						esc_html__( 'Enable this option to serve original files to unsupported browsers. %1$sCheck Browser Compatibility%2$s.', 'wp-smushit' ),
						'<a target="_blank" href="https://caniuse.com/webp">',
						'</a>'
					);
					?>
				</span>
			</label>
		</div>
	</div>
</div>
<?php endif; ?>

<div class="sui-box-settings-row">
	<div class="sui-box-settings-col-1">
		<span class="sui-settings-label">
			<?php esc_html_e( 'Revert WebP Conversion', 'wp-smushit' ); ?>
		</span>
		<span class="sui-description">
			<?php esc_html_e( 'If your server storage space is full, use this feature to revert the WebP conversions by deleting all generated files. The files will fall back to normal PNGs or JPEGs once you delete them.', 'wp-smushit' ); ?>
		</span>
	</div>

	<div class="sui-box-settings-col-2">
		<button
			type="button"
			class="sui-button sui-button-ghost"
			id="wp-smush-webp-delete-all-modal-open"
			data-modal-open="wp-smush-wp-delete-all-dialog"
			data-modal-close-focus="wp-smush-webp-delete-all-modal-open"
		>
			<span class="sui-loading-text">
				<i class="sui-icon-trash" aria-hidden="true"></i>
				<?php esc_html_e( 'Delete WebP Files', 'wp-smushit' ); ?>
			</span>
			<i class="sui-icon-loader sui-loading" aria-hidden="true"></i>
		</button>

		<span class="sui-description">
			<?php
			esc_html_e( 'This feature won’t delete the WebP files converted via CDN, only the files generated via the local WebP feature.', 'wp-smushit' );
			?>
		</span>
	</div>
</div>

<div class="sui-box-settings-row">
	<div class="sui-box-settings-col-1">
		<span class="sui-settings-label">
			<?php esc_html_e( 'Deactivate', 'wp-smushit' ); ?>
		</span>

		<span class="sui-description">
			<?php esc_html_e( 'If you no longer require your images to be served in WebP format, you can disable this feature.', 'wp-smushit' ); ?>
		</span>
	</div>

	<div class="sui-box-settings-col-2">

		<button class="sui-button sui-button-ghost" id="smush-toggle-webp-button" data-action="disable">
			<span class="sui-loading-text">
				<i class="sui-icon-power-on-off" aria-hidden="true"></i><?php esc_html_e( 'Deactivate', 'wp-smushit' ); ?>
			</span>
			<i class="sui-icon-loader sui-loading" aria-hidden="true"></i>
		</button>

		<span class="sui-description">
			<?php esc_html_e( 'Deactivation won’t delete existing WebP images.', 'wp-smushit' ); ?>
		</span>
	</div>
</div>
