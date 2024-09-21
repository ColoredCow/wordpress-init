<?php

namespace Smush\Core\S3;

use DeliciousBrains\WP_Offload_Media\Items\Item_Handler;
use DeliciousBrains\WP_Offload_Media\Items\Media_Library_Item;
use Smush\Core\Helper;
use WDEV_Logger;

/**
 * @method Media_Library_Item|null is_attachment_served_by_provider( int $attachment_id, true $skip_rewrite_check )
 * @method string|bool copy_provider_file_to_server( Media_Library_Item $media_library_item, string $file_path )
 * @method get_setting( string $string )
 * @method Item_Handler get_item_handler( string $string )
 * @method string get_stream_wrapper_file( $url, $file, $attachment_id, Media_Library_Item $as3cf_item )
 */
class WP_Offload_Media_Api {
	private $return_value = null;
	/**
	 * @var WDEV_Logger
	 */
	private $logger;

	public function __construct() {
		$this->logger = Helper::logger()->integrations();
	}

	public function __call( $method_name, $arguments ) {
		$this->return_value = null;

		if ( $this->call_method_if_exists( $method_name, $arguments ) ) {
			return $this->return_value;
		}

		if ( ! $this->try_alternative_method( $method_name, $arguments ) ) {
			// Still nothing? Better add a log entry
			$alt_method = $this->get_alt_method_name( $method_name );

			$this->logger->error( "Method $method_name and alt method $alt_method do not exist." );
		}

		return $this->return_value;
	}

	private function try_alternative_method( $method_name, $args ) {
		$alt_method = $this->get_alt_method_name( $method_name );

		return $this->call_method_if_exists( $alt_method, $args );
	}

	private function call_method_if_exists( $method_name, $arguments ) {
		global $as3cf;

		if ( empty( $as3cf ) ) {
			return false;
		}

		if ( method_exists( $as3cf, $method_name ) ) {
			$this->return_value = call_user_func_array( array( $as3cf, $method_name ), $arguments );

			return true;
		}

		if ( ! empty( $as3cf->plugin_compat ) && method_exists( $as3cf->plugin_compat, $method_name ) ) {
			$this->return_value = call_user_func_array( array( $as3cf->plugin_compat, $method_name ), $arguments );

			return true;
		}

		return false;
	}

	/**
	 * @param $method_name
	 *
	 * @return array|string|string[]
	 */
	private function get_alt_method_name( $method_name ) {
		return str_replace( '_provider_', '_s3_', $method_name );
	}

	public function delete_remote_files( $local_file_paths, $attachment_id ) {
		if ( empty( $local_file_paths ) ) {
			return;
		}

		$attached_file = $this->get_remote_file_path( $attachment_id );
		if ( ! $attached_file ) {
			return;
		}

		$attached_file_name = wp_basename( $attached_file );

		if ( is_string( $local_file_paths ) ) {
			$local_file_paths = array( $local_file_paths );
		}

		foreach ( $local_file_paths as $local_file_path ) {
			$size_file_name   = basename( $local_file_path );
			$remote_file_path = str_replace( $attached_file_name, $size_file_name, $attached_file );
			if ( $remote_file_path ) {
				wp_delete_file( $remote_file_path );
			}
		}
	}

	private function get_remote_file_path( $attachment_id ) {
		if ( ! method_exists( '\\DeliciousBrains\WP_Offload_Media\Items\Media_Library_Item', 'get_by_source_id' ) ) {
			return false;
		}

		$as3cf_item = Media_Library_Item::get_by_source_id( $attachment_id );
		if ( empty( $as3cf_item ) || is_wp_error( $as3cf_item ) ) {
			return false;
		}

		$attached_file = get_attached_file( $attachment_id );

		return $this->get_stream_wrapper_file( $attached_file, null, $attachment_id, $as3cf_item );
	}
}
