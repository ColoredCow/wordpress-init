<?php

namespace Smush\Core\Media;

use Smush\Core\Array_Utils;
use Smush\Core\Smush\Smush_Optimization;
use Smush\Core\Smush_File;
use Smush\Core\Url_Utils;

class Media_Item_Query {
	/**
	 * @var Url_Utils
	 */
	private $url_utils;
	/**
	 * @var Array_Utils
	 */
	private $array_utils;

	public function __construct() {
		$this->url_utils   = new Url_Utils();
		$this->array_utils = new Array_Utils();
	}

	public function fetch( $offset = 0, $limit = - 1 ) {
		global $wpdb;
		$query = $this->make_query( 'ID', $offset, $limit );

		return $wpdb->get_col( $query );
	}

	public function fetch_slice_post_meta( $slice, $slice_size ) {
		global $wpdb;

		$offset = $this->get_offset( $slice, $slice_size );
		$limit  = (int) $slice_size;

		$ids_query = $this->make_query( 'ID', $offset, $limit );
		$query     = "SELECT CONCAT(post_id, '-', meta_key), post_id, meta_key, meta_value FROM $wpdb->postmeta WHERE post_id IN (SELECT * FROM ($ids_query) AS slice_ids);";

		return $wpdb->get_results( $query, OBJECT_K );
	}

	public function fetch_slice_posts( $slice, $slice_size ) {
		global $wpdb;

		$offset      = $this->get_offset( $slice, $slice_size );
		$limit       = (int) $slice_size;
		$posts_query = $this->make_query( '*', $offset, $limit );

		return $wpdb->get_results( $posts_query, OBJECT_K );
	}

	public function fetch_slice_ids( $slice, $slice_size ) {
		$offset = $this->get_offset( $slice, $slice_size );
		$limit  = (int) $slice_size;

		return $this->fetch( $offset, $limit );
	}

	public function get_slice_count( $slice_size ) {
		if ( empty( $slice_size ) ) {
			return 0;
		}

		$image_attachment_count = $this->get_image_attachment_count();

		return (int) ceil( $image_attachment_count / $slice_size );
	}

	public function get_image_attachment_count() {
		global $wpdb;
		$query = $this->make_query( 'COUNT(*)' );

		return (int) $wpdb->get_var( $query );
	}

	/**
	 * @param $select
	 * @param $offset
	 * @param $limit
	 *
	 * @return string|null
	 */
	private function make_query( $select, $offset = 0, $limit = - 1 ) {
		global $wpdb;
		$mime_types   = ( new Smush_File() )->get_supported_mime_types();
		$placeholders = implode( ',', array_fill( 0, count( $mime_types ), '%s' ) );
		$column       = $select;

		$query = "SELECT %s FROM $wpdb->posts WHERE post_type = 'attachment' AND post_mime_type IN (%s)";
		$query = sprintf( $query, $column, $placeholders );
		$args  = $mime_types;

		if ( $limit > 0 ) {
			$query  = "$query LIMIT %d";
			$args[] = $limit;

			if ( $offset >= 0 ) {
				$query  = "$query OFFSET %d";
				$args[] = $offset;
			}
		}

		return $wpdb->prepare( $query, $args );
	}

	public function get_lossy_count() {
		global $wpdb;

		$query = $wpdb->prepare( "SELECT COUNT(DISTINCT post_id) FROM $wpdb->postmeta WHERE meta_key = %s AND meta_value = 1", Smush_Optimization::LOSSY_META_KEY );

		return $wpdb->get_var( $query );
	}

	public function get_smushed_count() {
		global $wpdb;

		$query = $wpdb->prepare(
			"SELECT COUNT(DISTINCT post_meta_optimized.post_id) FROM $wpdb->postmeta as post_meta_optimized
			LEFT JOIN $wpdb->postmeta as post_meta_ignored ON post_meta_optimized.post_id = post_meta_ignored.post_id AND post_meta_ignored.meta_key= %s
			WHERE post_meta_optimized.meta_key = %s AND post_meta_ignored.meta_value IS NULL",
			Media_Item::IGNORED_META_KEY,
			Smush_Optimization::SMUSH_META_KEY
		);

		return $wpdb->get_var( $query );
	}

	public function get_ignored_count() {
		global $wpdb;

		$query = $wpdb->prepare( "SELECT COUNT(DISTINCT post_id) FROM $wpdb->postmeta WHERE meta_key = %s", Media_Item::IGNORED_META_KEY );

		return $wpdb->get_var( $query );
	}

	/**
	 * @param $slice
	 * @param $slice_size
	 *
	 * @return float|int
	 */
	private function get_offset( $slice, $slice_size ) {
		$slice      = (int) $slice;
		$slice_size = (int) $slice_size;

		return ( $slice - 1 ) * $slice_size;
	}

	/**
	 * @see attachment_url_to_postid()
	 */
	public function attachment_urls_to_ids( $urls ) {
		if ( empty( $urls ) ) {
			return array();
		}

		$relative_urls = array_map( array( $this, 'convert_attachment_url_to_relative' ), $urls );
		$escaped_urls  = array_map( function ( $url ) {
			return "'" . esc_sql( $url ) . "'";
		}, $relative_urls );
		$in            = join( ',', $escaped_urls );

		global $wpdb;
		$sql = "SELECT post_id, meta_value FROM $wpdb->postmeta WHERE meta_key = '_wp_attached_file' AND meta_value IN ({$in})";

		$results = $wpdb->get_results( $sql, ARRAY_A );
		if ( empty( $results ) ) {
			return array();
		}

		$ids = array();
		foreach ( $results as $result ) {
			$meta_value   = $result['meta_value'];
			$index        = array_search( $meta_value, $relative_urls, true );
			$original_url = $urls[ $index ];

			$ids[ $original_url ] = $result['post_id'];
		}
		return $ids;
	}

	public function urls_to_size_data( $urls ) {
		if ( empty( $urls ) || ! is_array( $urls ) ) {
			return array();
		}

		global $wpdb;

		$wild             = '%';
		$meta_value_likes = [];
		foreach ( $urls as $url ) {
			$meta_value_likes[] = $wpdb->prepare( "meta_value LIKE %s", $wild . $wpdb->esc_like( basename( $url ) ) . $wild );
		}
		$where      = join( ' OR ', $meta_value_likes );
		$sql        = "SELECT post_id, meta_value FROM $wpdb->postmeta WHERE meta_key = '_wp_attachment_metadata' AND ({$where})";
		$db_results = $wpdb->get_results( $sql, ARRAY_A );
		if ( empty( $db_results ) ) {
			return array();
		}

		return $this->prepare_urls_to_size_data_result( $urls, $db_results );
	}

	private function get_main_file_data_from_wp_attachment_metadata( $attachment_id, $meta_value, $absolute_url ) {
		if ( empty( $meta_value ) ) {
			return array();
		}

		$file = $this->array_utils->get_array_value( $meta_value, 'file' );
		if ( $this->convert_attachment_url_to_relative( $absolute_url ) === $file ) {
			$width  = $this->array_utils->get_array_value( $meta_value, 'width' );
			$height = $this->array_utils->get_array_value( $meta_value, 'height' );
			if ( $width && $height ) {
				return array(
					'id'     => (int) $attachment_id,
					'width'  => (int) $width,
					'height' => (int) $height,
				);
			}
		}

		return array();
	}

	private function get_size_data_from_wp_attachment_metadata( $attachment_id, $meta_value, $absolute_url ) {
		if ( empty( $meta_value ) ) {
			return array();
		}

		$sizes = $this->array_utils->get_array_value( $meta_value, 'sizes' );
		$sizes = $this->array_utils->ensure_array( $sizes );
		if ( empty( $sizes ) ) {
			return array();
		}

		foreach ( $sizes as $size ) {
			$file = $this->array_utils->get_array_value( $size, 'file' );
			if ( basename( $absolute_url ) === $file ) {
				$width  = $this->array_utils->get_array_value( $size, 'width' );
				$height = $this->array_utils->get_array_value( $size, 'height' );

				if ( $file && $width && $height ) {
					return array(
						'id'     => (int) $attachment_id,
						'width'  => (int) $width,
						'height' => (int) $height,
					);
				}
			}
		}
		return array();
	}

	private function convert_attachment_url_to_relative( $url ) {
		return $this->url_utils->make_media_url_relative( $url );
	}

	/**
	 * @param $urls
	 * @param array $db_results
	 *
	 * @return array
	 */
	private function prepare_urls_to_size_data_result( $urls, $db_results ) {
		$return = array();
		foreach ( $urls as $url ) {
			if ( $this->is_non_media_library_url( $url ) ) {
				continue;
			}

			foreach ( $db_results as $result ) {
				$attachment_id = $this->array_utils->get_array_value( $result, 'post_id' );
				$meta_value    = $this->array_utils->get_array_value( $result, 'meta_value' );
				if ( empty( $attachment_id ) || empty( $meta_value ) ) {
					continue;
				}

				$file_name = basename( $url );
				if ( strpos( $meta_value, $file_name ) === false ) {
					continue;
				}

				$meta_value     = maybe_unserialize( $meta_value );
				$main_file_data = $this->get_main_file_data_from_wp_attachment_metadata( $attachment_id, $meta_value, $url );
				if ( ! empty( $main_file_data ) ) {
					$return[ $url ] = $main_file_data;
					break;
				} else {
					// Look for a size
					$size_data = $this->get_size_data_from_wp_attachment_metadata( $attachment_id, $meta_value, $url );
					if ( ! empty( $size_data ) ) {
						$return[ $url ] = $size_data;
						break;
					}
				}
			}
		}
		return $return;
	}

	/**
	 * @param $url
	 *
	 * @return bool
	 */
	private function is_non_media_library_url( $url ): bool {
		return $this->convert_attachment_url_to_relative( $url ) === $url;
	}
}
