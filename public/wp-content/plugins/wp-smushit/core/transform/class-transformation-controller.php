<?php

namespace Smush\Core\Transform;

use Smush\Core\Controller;
use Smush\Core\Server_Utils;
use Smush\Core\Url_Utils;

class Transformation_Controller extends Controller {

	/**
	 * @var Transformer
	 */
	private $transformer;
	/**
	 * @var Server_Utils
	 */
	private $server_utils;

	public function __construct() {
		$this->transformer  = new Transformer();
		$this->server_utils = new Server_Utils();

		$this->register_action( 'template_redirect', array( $this, 'hook_transformation_method' ), 1 );
		$this->register_filter( 'rest_pre_echo_response', array( $this, 'transform_rest_response' ), 10, 3 );
		$this->register_filter( 'wp_smush_transform_url', array( $this->transformer, 'transform_url' ) );
	}

	public function should_run() {
		return ! is_admin() &&
				! wp_doing_ajax() &&
				! wp_doing_cron();
	}

	public function hook_transformation_method() {
		if ( ! $this->should_transform_page() ) {
			return;
		}

		ob_start( array( $this, 'transform_content' ) );
	}

	private function should_transform_page() {
		$should_transform = ! is_customize_preview() &&
							$this->is_allowed_request_method() &&
							! $this->is_file_404();
		return apply_filters( 'wp_smush_should_transform_page', $should_transform );
	}

	private function is_allowed_request_method() {
		$allowed = array( 'GET', 'HEAD' );

		return in_array( $this->server_utils->get_request_method(), $allowed, true );
	}

	private function is_file_404() {
		if ( ! is_404() ) {
			return false;
		}

		$request_uri = $this->server_utils->get_request_uri();
		$extension   = ( new Url_Utils() )->get_extension( $request_uri );

		if ( empty( $extension ) ) {
			return false;
		}

		$allowed_404_extensions = array(
			'html',
			'htm',
		);

		return ! isset( $allowed_404_extensions[ $extension ] );
	}

	public function transform_content( $content ) {
		if ( ! $this->should_parse_content( $content ) ) {
			return $content;
		}

		return $this->transformer->transform_content(
			$content,
			$this->server_utils->get_current_url()
		);
	}

	private function should_parse_content( $content ) {
		$should_parse = $this->is_html( $content );
		return apply_filters( 'wp_smush_should_parse_content', $should_parse, $content );
	}

	private function is_html( $content ) {
		return (bool) preg_match( '/<\s*\/\s*html\s*>/i', $content );
	}

	/**
	 * @param $response array
	 * @param $server \WP_REST_Server
	 * @param $request \WP_REST_Request
	 *
	 * @return array
	 */
	public function transform_rest_response( $response, $server, $request ) {
		if ( ! $this->should_transform_rest( $response, $request ) ) {
			return $response;
		}

		return $this->transformer->transform_rest_response( $response, $request );
	}

	private function should_transform_rest( $response, $request ) {
		$context               = $request->get_param( 'context' );
		$referer               = $request->get_header( 'referer' );
		$referer_from_admin    = $referer && ( false !== strpos( $referer, admin_url() ) || false !== strpos( $referer, network_admin_url() ) );
		$should_transform_rest = 'view' === $context && ! $referer_from_admin;

		return apply_filters( 'wp_smush_should_transform_rest', $should_transform_rest, $response, $request );
	}
}
