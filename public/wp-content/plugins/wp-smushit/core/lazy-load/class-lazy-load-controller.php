<?php
/**
 * Lazy load images class: Lazy
 *
 * @since 3.2.0
 * @package Smush\Core\Modules
 */

namespace Smush\Core\Lazy_Load;

use Smush\Core\Controller;
use Smush\Core\Parser\Page_Parser;
use Smush\Core\Server_Utils;
use Smush\Core\Settings;

if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Class Lazy
 */
class Lazy_Load_Controller extends Controller {
	const LAZY_LOAD_TRANSFORM_PRIORITY = 20;

	/**
	 * Module slug.
	 *
	 * @var string
	 */
	protected $slug = 'lazy_load';

	/**
	 * Lazy loading settings.
	 *
	 * @since 3.2.0
	 * @var array $settings
	 */
	private $options;

	/**
	 * Excluded classes list.
	 *
	 * @since 3.6.2
	 * @var array
	 */
	private $excluded_classes = array(
		'no-lazyload', // Internal class to skip images.
		'skip-lazy',
		'rev-slidebg', // Skip Revolution slider images.
		'soliloquy-preload', // Soliloquy slider.
	);

	/**
	 * Static instance
	 *
	 * @var self
	 */
	private static $instance;
	/**
	 * @var Settings
	 */
	private $settings;

	/**
	 * @var Lazy_Load_Helper
	 */
	private $helper;

	/**
	 * Static instance getter
	 */
	public static function get_instance() {
		if ( empty( self::$instance ) ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Initialize module actions.
	 *
	 * @since 3.2.0
	 */
	public function __construct() {
		$this->settings = Settings::get_instance();
		$this->helper   = Lazy_Load_Helper::get_instance();

		$this->register_action( 'wp_smush_content_transforms', array(
			$this,
			'register_lazy_load_transform',
		), self::LAZY_LOAD_TRANSFORM_PRIORITY );

		// Only run on front end and if lazy loading is enabled.
		if ( is_admin() || ! $this->settings->is_module_active( 'lazy_load' ) ) {
			return;
		}

		$this->options = $this->settings->get_setting( 'wp-smush-lazy_load' );

		// Enabled without settings? Don't think so... Exit.
		if ( ! $this->options ) {
			return;
		}

		// Disable WordPress native lazy load.
		$this->register_filter( 'wp_lazy_loading_enabled', array( $this, 'maybe_disable_wordpress_native_lazyload' ) );

		// Load js file that is required in public facing pages.
		$this->register_action( 'wp_head', array( $this, 'add_inline_styles' ) );
		$this->register_action( 'wp_enqueue_scripts', array( $this, 'enqueue_assets' ), 99 );
		if ( defined( 'WP_SMUSH_ASYNC_LAZY' ) && WP_SMUSH_ASYNC_LAZY ) {
			$this->register_filter( 'script_loader_tag', array( $this, 'async_load' ), 10, 2 );
		}

		// Allow lazy load attributes in img tag.
		$this->register_filter( 'wp_kses_allowed_html', array( $this, 'add_lazy_load_attributes' ) );

		// Filter images.
		if ( ! isset( $this->options['output']['content'] ) || ! $this->options['output']['content'] ) {
			$this->register_filter( 'the_content', array( $this, 'exclude_from_lazy_loading' ), 100 );
		}
		if ( ! isset( $this->options['output']['thumbnails'] ) || ! $this->options['output']['thumbnails'] ) {
			$this->register_filter( 'post_thumbnail_html', array( $this, 'exclude_from_lazy_loading' ), 100 );
		}
		if ( ! isset( $this->options['output']['gravatars'] ) || ! $this->options['output']['gravatars'] ) {
			$this->register_filter( 'get_avatar', array( $this, 'exclude_from_lazy_loading' ), 100 );
		}
		if ( ! isset( $this->options['output']['widgets'] ) || ! $this->options['output']['widgets'] ) {
			$this->register_action( 'dynamic_sidebar_before', array( $this, 'filter_sidebar_content_start' ), 0 );
			$this->register_action( 'dynamic_sidebar_after', array( $this, 'filter_sidebar_content_end' ), 1000 );
		}
	}

	/**
	 * Add inline styles at the top of the page for pre-loaders and effects.
	 *
	 * @since 3.2.0
	 */
	public function add_inline_styles() {
		if ( $this->helper->should_skip_lazyload() ) {
			return;
		}
		// Fix for poorly coded themes that do not remove the no-js in the HTML class.
		?>
		<script>
			document.documentElement.className = document.documentElement.className.replace('no-js', 'js');
		</script>
		<?php
		if ( empty( $this->options['animation']['selected'] ) || 'none' === $this->options['animation']['selected'] ) {
			return;
		}

		// Spinner.
		if ( 'spinner' === $this->options['animation']['selected'] ) {
			$loader = WP_SMUSH_URL . 'app/assets/images/smush-lazyloader-' . $this->options['animation']['spinner']['selected'] . '.gif';
			if ( isset( $this->options['animation']['spinner']['selected'] ) && 5 < (int) $this->options['animation']['spinner']['selected'] ) {
				$loader = wp_get_attachment_image_src( $this->options['animation']['spinner']['selected'], 'full' );
				$loader = $loader[0];
			}
			$background = 'rgba(255, 255, 255, 0)';
		} else {
			// Placeholder.
			$loader     = WP_SMUSH_URL . 'app/assets/images/smush-placeholder.png';
			$background = '#FAFAFA';
			if ( isset( $this->options['animation']['placeholder']['selected'] ) && 2 === (int) $this->options['animation']['placeholder']['selected'] ) {
				$background = '#333333';
			}
			if ( isset( $this->options['animation']['placeholder']['selected'] ) && 2 < (int) $this->options['animation']['placeholder']['selected'] ) {
				$loader = wp_get_attachment_image_src( (int) $this->options['animation']['placeholder']['selected'], 'full' );

				// Can't find a loader on multisite? Try main site.
				if ( ! $loader && is_multisite() ) {
					switch_to_blog( 1 );
					$loader = wp_get_attachment_image_src( (int) $this->options['animation']['placeholder']['selected'], 'full' );
					restore_current_blog();
				}

				$loader = $loader[0];
			}
			if ( isset( $this->options['animation']['placeholder']['color'] ) ) {
				$background = $this->options['animation']['placeholder']['color'];
			}
		}

		// Fade in.
		$fadein = isset( $this->options['animation']['fadein']['duration'] ) ? $this->options['animation']['fadein']['duration'] : 0;
		$delay  = isset( $this->options['animation']['fadein']['delay'] ) ? $this->options['animation']['fadein']['delay'] : 0;
		?>
		<style>
			.no-js img.lazyload {
				display: none;
			}

			figure.wp-block-image img.lazyloading {
				min-width: 150px;
			}

			<?php if ( 'fadein' === $this->options['animation']['selected'] ) : ?>
			.lazyload, .lazyloading {
				opacity: 0;
			}

			.lazyloaded {
				opacity: 1;
				transition: opacity <?php echo esc_html( $fadein ); ?>ms;
				transition-delay: <?php echo esc_html( $delay ); ?>ms;
			}

			<?php else : ?>
			.lazyload {
				opacity: 0;
			}

			.lazyloading {
				border: 0 !important;
				opacity: 1;
				background: <?php echo esc_attr( $background ); ?> url('<?php echo esc_url( $loader ); ?>') no-repeat center !important;
				background-size: 16px auto !important;
				min-width: 16px;
			}

			.lazyload,
			.lazyloading {
				--smush-placeholder-width: 100px;
				--smush-placeholder-aspect-ratio: 1/1;
				width: var(--smush-placeholder-width) !important;
				aspect-ratio: var(--smush-placeholder-aspect-ratio) !important;
			}

			<?php endif; ?>
		</style>
		<?php
	}

	/**
	 * Enqueue JS files required in public pages.
	 *
	 * @since 3.2.0
	 */
	public function enqueue_assets() {
		if ( $this->helper->should_skip_lazyload() || $this->helper->is_native_lazy_loading_enabled() ) {
			return;
		}

		$script = WP_SMUSH_URL . 'app/assets/js/smush-lazy-load.min.js';

		$in_footer = isset( $this->options['footer'] ) ? $this->options['footer'] : true;

		wp_enqueue_script(
			'smush-lazy-load',
			$script,
			array(),
			WP_SMUSH_VERSION,
			$in_footer
		);

		$this->add_masonry_support();
		if ( defined( 'WP_SMUSH_LAZY_LOAD_AVADA' ) && WP_SMUSH_LAZY_LOAD_AVADA ) {
			$this->add_avada_support();
		}
		$this->add_divi_support();
		$this->add_soliloquy_support();
	}

	/**
	 * Async load the lazy load scripts.
	 *
	 * @param string $tag The <script> tag for the enqueued script.
	 * @param string $handle The script's registered handle.
	 *
	 * @return string
	 * @since 3.7.0
	 *
	 */
	public function async_load( $tag, $handle ) {
		if ( 'smush-lazy-load' === $handle ) {
			return str_replace( ' src', ' async="async" src', $tag );
		}

		return $tag;
	}

	/**
	 * Add support for plugins that use the masonry grid system (Block Gallery and CoBlocks plugins).
	 *
	 * @since 3.5.0
	 *
	 * @see https://wordpress.org/plugins/coblocks/
	 * @see https://github.com/godaddy/block-gallery
	 * @see https://masonry.desandro.com/methods.html#layout-masonry
	 */
	private function add_masonry_support() {
		if ( ! function_exists( 'has_block' ) ) {
			return;
		}

		// None of the supported blocks are active - exit.
		if ( ! has_block( 'blockgallery/masonry' ) && ! has_block( 'coblocks/gallery-masonry' ) ) {
			return;
		}

		$js = "var e = jQuery( '.wp-block-coblocks-gallery-masonry ul' );";
		if ( has_block( 'blockgallery/masonry' ) ) {
			$js = "var e = jQuery( '.wp-block-blockgallery-masonry ul' );";
		}

		$block_gallery_compat = "jQuery(document).on('lazyloaded', function(){{$js} if ('function' === typeof e.masonry) e.masonry();});";

		wp_add_inline_script( 'smush-lazy-load', $block_gallery_compat );
	}

	/**
	 * Add fusion gallery support in Avada theme.
	 *
	 * @since 3.7.0
	 */
	private function add_avada_support() {
		if ( ! defined( 'FUSION_BUILDER_VERSION' ) ) {
			return;
		}

		$js = "var e = jQuery( '.fusion-gallery' );";

		$block_gallery_compat = "jQuery(document).on('lazyloaded', function(){{$js} if ('function' === typeof e.isotope) e.isotope();});";

		wp_add_inline_script( 'smush-lazy-load', $block_gallery_compat );
	}

	/**
	 * Adds lazyload support to Divi & it's Waypoint library.
	 *
	 * @since 3.9.0
	 */
	private function add_divi_support() {
		if ( ! defined( 'ET_BUILDER_THEME' ) || ! ET_BUILDER_THEME ) {
			return;
		}

		$script = "function rw() { Waypoint.refreshAll(); } window.addEventListener( 'lazybeforeunveil', rw, false); window.addEventListener( 'lazyloaded', rw, false);";

		wp_add_inline_script( 'smush-lazy-load', $script );
	}

	/**
	 * Prevents the navigation from being missaligned in Soliloquy when lazy loading.
	 *
	 * @since 3.7.0
	 */
	private function add_soliloquy_support() {
		if ( ! function_exists( 'soliloquy' ) ) {
			return;
		}

		$js = "var e = jQuery( '.soliloquy-image:not(.lazyloaded)' );";

		$soliloquy = "jQuery(document).on('lazybeforeunveil', function(){{$js}e.each(function(){lazySizes.loader.unveil(this);});});";

		wp_add_inline_script( 'smush-lazy-load', $soliloquy );
	}

	/**
	 * Make sure WordPress does not filter out img elements with lazy load attributes.
	 *
	 * @param array $allowedposttags Allowed post tags.
	 *
	 * @return mixed
	 * @since 3.2.0
	 *
	 */
	public function add_lazy_load_attributes( $allowedposttags ) {
		if ( ! isset( $allowedposttags['img'] ) ) {
			return $allowedposttags;
		}

		$smush_attributes = array(
			'data-src'    => true,
			'data-srcset' => true,
			'data-sizes'  => true,
		);

		$img_attributes = array_merge( $allowedposttags['img'], $smush_attributes );

		$allowedposttags['img'] = $img_attributes;

		return $allowedposttags;
	}

	/**
	 * Get images from content and add exclusion class.
	 *
	 * @param string $content Page/block content.
	 *
	 * @return string
	 * @since 3.2.2
	 *
	 */
	public function exclude_from_lazy_loading( $content ) {
		$server_utils = new Server_Utils();
		$page         = new Page_Parser( $server_utils->get_request_uri(), $content );
		$images       = $page->parse_page()->get_elements();

		if ( empty( $images ) ) {
			return $content;
		}

		foreach ( $images as $image ) {
			// Add .no-lazyload class.
			$image->append_attribute_value( 'class', 'no-lazyload' );

			/**
			 * Filters the no-lazyload image.
			 *
			 * @param string $text The image that can be filtered.
			 *
			 * @since 3.8.5
			 *
			 */
			$new_markup = apply_filters( 'wp_smush_filter_no_lazyload_image', $image->get_updated_markup() );

			$content = str_replace( $image->get_markup(), $new_markup, $content );
		}

		return $content;
	}

	/**
	 * Buffer sidebar content.
	 *
	 * @since 3.2.0
	 */
	public function filter_sidebar_content_start() {
		ob_start();
	}

	/**
	 * Process buffered content.
	 *
	 * @since 3.2.0
	 */
	public function filter_sidebar_content_end() {
		$content = ob_get_clean();

		echo $this->exclude_from_lazy_loading( $content );

		unset( $content );
	}

	public function register_lazy_load_transform( $transforms ) {
		$transforms['lazy_load'] = new Lazy_Load_Transform();

		return $transforms;
	}

	public function maybe_disable_wordpress_native_lazyload() {
		return ! $this->helper->is_native_lazy_loading_enabled();
	}
}
