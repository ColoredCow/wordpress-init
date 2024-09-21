<?php
/**
 * WebP meta box header.
 *
 * @package WP_Smush
 *
 * @var boolean $is_disabled   Whether the WebP module is disabled.
 * @var boolean $is_configured Whether WebP images are being served.
 */
use Smush\Core\Webp\Webp_Configuration;

if ( ! defined( 'WPINC' ) ) {
	die;
}

$direct_conversion_enabled = Webp_Configuration::get_instance()->direct_conversion_enabled();

?>

<h3 class="sui-box-title">
	<?php esc_html_e( 'Local WebP', 'wp-smushit' ); ?>
	<?php
	if ( ! $is_disabled ) {
		echo ' - ';
		echo $direct_conversion_enabled ? esc_html__( 'Direct Conversion method', 'wp-smushit' ) : esc_html__( 'Server Configuration method', 'wp-smushit' );
	}
	?>
</h3>

<?php if ( ! WP_Smush::is_pro() ) : ?>
	<div class="sui-actions-left">
		<span class="sui-tag sui-tag-pro sui-tooltip sui-tooltip-constrained" data-tooltip="<?php esc_attr_e( 'Join WPMU DEV to use this feature', 'wp-smushit' ); ?>">
			<?php esc_html_e( 'Pro', 'wp-smushit' ); ?>
		</span>
	</div>
<?php endif; ?>

<?php
if ( ! $is_disabled ) :
	$tooltip_message = $direct_conversion_enabled ? __( 'Uses server-side setup. Only try this if you are facing issues with the Direct Conversion method.', 'wp-smushit' ) : __( 'One-click method that works on all server types without requiring server configuration. (Recommended)', 'wp-smushit' );
	$method_name     = $direct_conversion_enabled ? 'rewrite_rule' : Webp_Configuration::DIRECT_CONVERSION_METHOD;
	?>
	<a class="sui-actions-right sui-sm sui-tooltip sui-tooltip-constrained sui-tooltip-bottom-left" href="javascript:void(0);"
		id="smush-switch-webp-method" data-method="<?php echo esc_attr( $method_name ); ?>" 
		data-tooltip="<?php echo esc_html( $tooltip_message ); ?>"
		aria-hidden="true"
		>
		<i class="sui-notice-icon sui-icon-info sui-sm"
			data-tooltip="<?php echo esc_html( $tooltip_message ); ?>"
			aria-hidden="true"
		></i>
		<span class="sui-description" style="margin: 0 0 0 5px;">
		<?php
			$method_title = $direct_conversion_enabled ? __( 'Server Configuration', 'wp-smushit' ) : __( 'Direct Conversion', 'wp-smushit' );
			$style_attr   = $direct_conversion_enabled ? 'font-weight:700; color:#888;' : 'font-weight:700; color:#17A8E3';
			printf(
				/* translators: Switch WebP method link */
				esc_html__( 'Switch to %s method', 'wp-smushit' ),
				'<span style="' . esc_attr( $style_attr ) . '">' . esc_html( $method_title ) . '</span>'
			);
		?>
		</span>
		<?php
		if ( ! $direct_conversion_enabled ) {
			echo '<span class="sui-tag smush-sui-tag-new" style="margin-left:5px">' . esc_html__( 'New', 'wp-smushit' ) . '</span>';
		}
		?>
	</a>
<?php endif; ?>
