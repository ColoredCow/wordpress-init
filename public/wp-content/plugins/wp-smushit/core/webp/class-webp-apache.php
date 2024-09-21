<?php

namespace Smush\Core\Webp;

use Smush\Core\Server_Utils;

class Webp_Apache {
	/**
	 * @var Webp_Dir
	 */
	private $webp_dir;

	public function __construct() {
		$this->webp_dir = new Webp_Dir();
	}

	public function get_rewrite_rules() {
		$location = is_multisite() ? 'uploads' : 'root';

		$code = $this->marker_line() . "\n";
		$code .= $this->get_apache_code( $location );
		$code .= "\n" . $this->marker_line( true );

		return $code;
	}

	/**
	 * Code to use on Apache servers.
	 *
	 * @param string $location Where the .htaccess file is.
	 *
	 * @return string
	 * @since 3.8.0
	 *
	 * @todo Find out what's wrong with the rules. We shouldn't need these two different RewriteRule.
	 *
	 */
	private function get_apache_code( $location ) {
		$uploads_rel_path = trim( $this->webp_dir->get_upload_rel_path(), '/\\' );
		$webp_rel_path    = trim( $this->webp_dir->get_webp_rel_path(), '/\\' );
		$webp_path        = untrailingslashit( $this->webp_dir->get_webp_path() );

		$rewrite_path = $this->is_document_root_matched() ? '%{DOCUMENT_ROOT}/' . $webp_rel_path : $webp_path;

		$code = '<IfModule mod_rewrite.c>
 RewriteEngine On
 RewriteCond ' . $rewrite_path . '/disable_smush_webp !-f
 RewriteCond %{HTTP_ACCEPT} image/webp' . "\n";

		if ( 'root' === $location ) {
			// This works on single sites at root.
			$code .= ' RewriteCond ' . $rewrite_path . '/$1.webp -f
 RewriteRule ' . $uploads_rel_path . '/(.*\.(?:png|jpe?g))$ ' . $webp_rel_path . '/$1.webp [NC,T=image/webp]';
		} else {
			// This works at /uploads/.
			$code .= ' RewriteCond ' . $rewrite_path . '/$1.$2.webp -f
 RewriteRule ^/?(.+)\.(jpe?g|png)$ /' . $webp_rel_path . '/$1.$2.webp [NC,T=image/webp]';
		}

		$code .= "\n" . '</IfModule>

<IfModule mod_headers.c>
 Header append Vary Accept env=WEBP_image
</IfModule>

<IfModule mod_mime.c>
 AddType image/webp .webp
</IfModule>';

		return apply_filters( 'smush_apache_webp_rules', $code );
	}

	private function is_document_root_matched() {
		$document_root  = ( new Server_Utils() )->get_document_root();
		$webp_rel_path  = trim( $this->webp_dir->get_webp_rel_path(), '/\\' );
		$webp_path      = trailingslashit( $document_root ) . $webp_rel_path;
		$real_webp_path = untrailingslashit( $this->webp_dir->get_webp_path() );
		return $webp_path === $real_webp_path;
	}

	/**
	 * Get unique string to use as marker comment line in .htaccess or nginx config file.
	 *
	 * @param bool $end whether to use marker after end of the config code.
	 *
	 * @return string
	 */
	private function marker_line( $end = false ) {
		if ( true === $end ) {
			return '# END ' . $this->marker_suffix();
		} else {
			return '# BEGIN ' . $this->marker_suffix();
		}
	}

	/**
	 * Get unique string to use at marker comment line in .htaccess or nginx config file.
	 *
	 * @return string
	 * @since 3.8.0
	 *
	 */
	private function marker_suffix() {
		return 'SMUSH-WEBP';
	}

	/**
	 * Gets the path of .htaccess file for the given location.
	 *
	 * @param string $location Location of the .htaccess file to retrieve. root|uploads.
	 *
	 * @return string
	 */
	private function get_htaccess_file_path( $location ) {
		$base_dir = 'root' === $location ? get_home_path() : $this->webp_dir->get_upload_path();

		return rtrim( $base_dir, '/' ) . '/.htaccess';
	}

	/**
	 * Check if .htaccess has rules for this module in place.
	 *
	 * @param bool|string $location Location of the .htaccess to check.
	 *
	 * @return bool
	 * @since 3.8.0
	 *
	 */
	public function is_htaccess_written( $location = false ) {
		if ( ! function_exists( 'extract_from_markers' ) ) {
			require_once ABSPATH . 'wp-admin/includes/misc.php';
		}

		$has_rules = false;

		// Remove the rules from all the possible places if not specified.
		$locations = ! $location ? $this->get_htaccess_locations() : array( $location );

		foreach ( $locations as $name ) {
			$htaccess  = $this->get_htaccess_file_path( $name );
			$has_rules = ! empty( $has_rules ) || array_filter( extract_from_markers( $htaccess, $this->marker_suffix() ) );
		}

		return $has_rules;
	}

	/**
	 * Returns the handled locations for the .htaccess.
	 *
	 * @return array
	 * @since 3.8.3
	 *
	 */
	public function get_htaccess_locations() {
		if ( ! is_multisite() ) {
			$locations[] = 'root';
		}
		$locations[] = 'uploads';

		return $locations;
	}

	public function save_htaccess( $location ) {
		$htaccess = $this->get_htaccess_file_path( $location );
		$code     = $this->get_apache_code( $location );
		$code     = explode( "\n", $code );
		return insert_with_markers( $htaccess, $this->marker_suffix(), $code );
	}

	/**
	 * Remove rules from .htaccess file.
	 *
	 * @param bool|string $location Location of the htaccess to unsave. uploads|root.
	 *
	 * @return string|null Error message or empty on success.
	 * @since 3.8.0
	 */
	public function unsave_htaccess( $location = false ) {
		if ( ! $this->is_htaccess_written( $location ) ) {
			return esc_html__( "The .htaccess file doesn't contain the WebP rules from Smush.", 'wp-smushit' );
		}

		$markers_inserted = false;

		// Remove the rules from all the possible places if not specified.
		$locations = ! $location ? $this->get_htaccess_locations() : array( $location );

		foreach ( $locations as $name ) {
			$htaccess         = $this->get_htaccess_file_path( $name );
			$markers_inserted = insert_with_markers( $htaccess, $this->marker_suffix(), '' ) || ! empty( $markers_inserted );
		}

		if ( ! $markers_inserted ) {
			return esc_html__( 'We were unable to automatically remove the rules. We recommend trying to remove the rules manually. If you don’t have access to the .htaccess file to remove it manually, please consult with your hosting provider to change the configuration on the server.', 'wp-smushit' );
		}
	}
}
