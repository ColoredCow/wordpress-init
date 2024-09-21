<?php
/**
 * Smush upgrade page class: Upgrade extends Abstract_Page.
 *
 * @since 3.2.3
 * @package Smush\App\Pages
 */

namespace Smush\App\Pages;

use Smush\App\Abstract_Page;

if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Class Upgrade
 */
class Upgrade extends Abstract_Page {
	/**
	 * Parent slug.
	 */
	private $parent_slug;

	public function __construct( $slug, $title, $parent_slug = false, $is_upsell_link = false ) {
		parent::__construct( $slug, $title, $parent_slug, false, $is_upsell_link );

		if ( $is_upsell_link ) {
			$this->parent_slug = $parent_slug;
			add_action( 'admin_head', array( $this, 'adjust_upsell_submenu' ) );
		}
	}

	public function adjust_upsell_submenu() {
		$submenu_selector = "#toplevel_page_{$this->parent_slug} li:last-child a";
		?>
		<style>
			<?php echo esc_html( $submenu_selector ); ?> {
				background-color: #8d00b1 !important;
				color: #fff !important;
				font-weight: 500 !important;
				white-space: nowrap;
			}
		</style>
		<script>
			window.addEventListener( 'load', function() {
				document.querySelector( '<?php echo esc_html( $submenu_selector ); ?>' ).target="_blank";
			} );
		</script>
		<?php
	}

	/**
	 * Render the page.
	 */
	public function render() {
		?>
		<div class="<?php echo $this->settings->get( 'accessible_colors' ) ? 'sui-wrap sui-color-accessible' : 'sui-wrap'; ?>">
			<?php $this->render_inner_content(); ?>
		</div>
		<?php
	}

	/**
	 * Render inner content.
	 */
	public function render_inner_content() {
		$this->view( 'smush-upgrade-page' );
	}

	/**
	 * On load actions.
	 */
	public function on_load() {
		add_action(
			'admin_enqueue_scripts',
			function() {
				wp_enqueue_script( 'smush-sui', WP_SMUSH_URL . 'app/assets/js/smush-sui.min.js', array( 'jquery', 'clipboard' ), WP_SHARED_UI_VERSION, true );
				wp_enqueue_script( 'smush-wistia', '//fast.wistia.com/assets/external/E-v1.js', array(), WP_SMUSH_VERSION, true );
				wp_enqueue_style( 'smush-admin', WP_SMUSH_URL . 'app/assets/css/smush-admin.min.css', array(), WP_SMUSH_VERSION );
			}
		);
	}

	/**
	 * Common hooks for all screens.
	 */
	public function add_action_hooks() {
		add_filter( 'admin_body_class', array( $this, 'smush_body_classes' ) );
	}

}
