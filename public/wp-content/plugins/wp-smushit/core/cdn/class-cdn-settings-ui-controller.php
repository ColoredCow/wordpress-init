<?php

namespace Smush\Core\CDN;

use Smush\Core\Controller;
use Smush\Core\Settings;

class CDN_Settings_Ui_Controller extends Controller {
	/**
	 * @var Settings|null
	 */
	private $settings;

	public function __construct() {
		$this->settings = Settings::get_instance();

		$this->register_filter( 'wp_smush_settings', array( $this, 'register_cdn_settings' ) );
		$this->register_action( 'smush_setting_column_right_inside', array( $this, 'settings_desc' ), 10, 2 );
	}

	/**
	 * Add settings to settings array.
	 *
	 * @param array $settings Current settings array.
	 *
	 * @return array
	 * @since 3.0
	 *
	 */
	public function register_cdn_settings( $settings ) {
		return array_merge(
			$settings,
			array(
				'background_images' => array(
					'label'       => __( 'Serve background images from the CDN', 'wp-smushit' ),
					'short_label' => __( 'Background Images', 'wp-smushit' ),
					'desc'        => __( 'Where possible we will serve background images declared with CSS directly from the CDN.', 'wp-smushit' ),
				),
				'auto_resize'       => array(
					'label'       => __( 'Enable automatic resizing of my images', 'wp-smushit' ),
					'short_label' => __( 'Automatic Resizing', 'wp-smushit' ),
					'desc'        => __( 'If your images don’t match their containers, we’ll automatically serve a correctly sized image.', 'wp-smushit' ),
				),
				'webp'              => array(
					'label'       => __( 'Enable WebP conversion', 'wp-smushit' ),
					'short_label' => __( 'WebP Conversion', 'wp-smushit' ),
					'desc'        => __( 'Smush can automatically convert and serve your images as WebP from the WPMU DEV CDN to compatible browsers.', 'wp-smushit' ),
				),
				'rest_api_support'  => array(
					'label'       => __( 'Enable REST API support', 'wp-smushit' ),
					'short_label' => __( 'REST API', 'wp-smushit' ),
					'desc'        => __( 'Smush can automatically replace image URLs when fetched via REST API endpoints.', 'wp-smushit' ),
				),
			)
		);
	}

	/**
	 * Show additional descriptions for settings.
	 *
	 * @param string $setting_key Setting key.
	 *
	 * @since 3.0
	 *
	 */
	public function settings_desc( $setting_key = '' ) {
		if ( empty( $setting_key ) || ! in_array( $setting_key, $this->settings->get_cdn_fields(), true ) ) {
			return;
		}
		?>
		<span class="sui-description sui-toggle-description"
		      id="<?php echo esc_attr( 'wp-smush-' . $setting_key . '-desc' ); ?>">
			<?php
			switch ( $setting_key ) {
				case 'webp':
					esc_html_e(
						'Note: We’ll detect and serve WebP images to browsers that will accept them by checking Accept Headers, and gracefully fall back to normal PNGs or JPEGs for non-compatible browsers.',
						'wp-smushit'
					);
					break;
				case 'auto_resize':
					esc_html_e( 'Having trouble with Google PageSpeeds ‘properly size images’ suggestion? This feature will fix this without any coding needed!', 'wp-smushit' );
					echo '<br>';
					printf(
					/* translators: %1$s - opening tag, %2$s - closing tag */
						esc_html__( 'Note: Smush will pre-fill the srcset attribute with missing image sizes so for this feature to work, those must be declared properly by your theme and page builder using the %1$scontent width%2$s variable.', 'wp-smushit' ),
						'<a href="https://developer.wordpress.com/themes/content-width/" target="_blank">',
						'</a>'
					);
					break;
				case 'background_images':
					printf(
					/* translators: %1$s - Open the link <a>, %2$s - Closing link tag */
						esc_html__( 'Note: For this feature to work your theme’s background images must be declared correctly using the default %1$swp_attachment%2$s functions.', 'wp-smushit' ),
						'<a href="https://developer.wordpress.org/reference/functions/wp_get_attachment_image/" target="_blank">',
						'</a>'
					);
					echo '<br>';
					printf(
					/* translators: %1$s - Open the link <a>, %2$s - closing link tag */
						esc_html__( 'For any non-media library uploads, you can still use the %1$sDirectory Smush%2$s feature to compress them, they just won’t be served from the CDN.', 'wp-smushit' ),
						'<a href="' . esc_url( network_admin_url( 'admin.php?page=smush-directory' ) ) . '">',
						'</a>'
					);
					break;
				case 'rest_api_support':
					printf(
					/* translators: %1$s - Open a link <a>, %2$s - closing link tag */
						esc_html__( 'Note: Smush will use the %1$srest_pre_echo_response%2$s hook to filter images in REST API responses.', 'wp-smushit' ),
						'<a href="https://developer.wordpress.org/reference/hooks/rest_pre_echo_response/" target="_blank">',
						'</a>'
					);
					break;
				default:
					break;
			}
			?>
		</span>
		<?php
	}
}
