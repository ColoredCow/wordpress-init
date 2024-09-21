<?php
namespace Smush\Core\Webp;

class Webp_Nginx {
	/**
	 * Code to use on Nginx servers.
	 *
	 * @param bool $marker whether to wrap code with marker comment lines.
	 * @return string
	 */
	public function get_rewrite_rules( $marker = true ) {
		$webp_dir       = new Webp_Dir();
		$upload_relpath = $webp_dir->get_upload_rel_path();
		$webp_path      = trailingslashit( $webp_dir->get_webp_path() );
		$webp_rel_path  = trailingslashit( $webp_dir->get_webp_rel_path() );

		$base       = trailingslashit( dirname( $upload_relpath ) );
		$directory  = trailingslashit( basename( $upload_relpath ) );
		$regex_base = ltrim( $base, '/\\' ) . '(' . $directory . ')';
		$regex_base = str_replace( '/', '\/', $regex_base );

		/**
		 * We often need to remove WebP file extension from Nginx cache rule in order to make Smush WebP work,
		 * so always add expiry header rule for Nginx.
		 *
		 * @since 3.9.8
		 * @see https://incsub.atlassian.net/browse/SMUSH-1072
		 */

		$code = <<<WEBP_RULES
        location ~* "{$regex_base}(.*\.(?:png|jpe?g))" {
            add_header Vary Accept;
            set \$image_path \$2;
            if (-f "{$webp_path}disable_smush_webp") {
                break;
            }
            if (\$http_accept !~* "webp") {
                break;
            }
            expires	max;
            try_files {$webp_rel_path}\$image_path.webp \$uri =404;
        }
        WEBP_RULES;

		if ( true === $marker ) {
			$code  = "# BEGIN SMUSH-WEBP\n" . $code;
			$code .= "\n# END SMUSH-WEBP";
		}
		return apply_filters( 'smush_nginx_webp_rules', $code );
	}
}
