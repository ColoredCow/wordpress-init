<?php

namespace Smush\Core\Webp;

use Smush\Core\File_System;
use Smush\Core\Media\Media_Item;
use Smush\Core\Smush\Smush_Optimization;

class Webp_Helper {
	const WEBP_FLAG = 'webp_flag';
	/**
	 * @var Webp_Dir
	 */
	private $webp_dir;
	/**
	 * @var File_System
	 */
	private $fs;

	public function __construct() {
		$this->webp_dir = new Webp_Dir();
		$this->fs       = new File_System();
	}

	/**
	 * Converts the URL of a media item/media item size to a webp URL.
	 *
	 * @param $url string Original URL.
	 *
	 * @return false|string
	 */
	public function get_webp_file_url( $url ) {
		$upload_dir_url  = $this->webp_dir->get_upload_url();
		$upload_dir_path = $this->webp_dir->get_upload_path();
		$is_ssl          = str_starts_with( $url, 'https:' );

		// Temporarily add the same scheme to both URLs
		$url            = set_url_scheme( $url, 'http' );
		$upload_dir_url = set_url_scheme( $upload_dir_url, 'http' );

		$is_media_lib_file = strpos( $url, $upload_dir_url ) !== false;
		if ( ! $is_media_lib_file ) {
			return false;
		}

		$file_path = str_replace( $upload_dir_url, $upload_dir_path, $url );
		if ( ! file_exists( $file_path ) ) {
			return false;
		}

		$webp_path = $this->get_webp_file_path( $file_path );
		if ( ! file_exists( $webp_path ) ) {
			return false;
		}

		$webp_url = str_replace(
			$this->webp_dir->get_webp_path(),
			$this->webp_dir->get_webp_url(),
			$webp_path
		);

		return set_url_scheme( $webp_url, $is_ssl ? 'https' : 'http' );
	}

	public function get_webp_file_path( $file_path, $make = false ) {
		$file_rel_path  = substr( $file_path, strlen( $this->webp_dir->get_upload_path() ) );
		$webp_file_path = $this->webp_dir->get_webp_path() . $file_rel_path . '.webp';

		if ( $make ) {
			$webp_file_dir = dirname( $webp_file_path );
			if ( ! $this->fs->is_dir( $webp_file_dir ) ) {
				wp_mkdir_p( $webp_file_dir );
			}
		}

		return $webp_file_path;
	}

	public function supported_mime_types() {
		return array(
			'image/jpg',
			'image/jpeg',
			'image/x-citrix-jpeg',
			'image/png',
			'image/x-png',
		);
	}

	public function get_webp_flag( $attachment_id ) {
		$meta = $this->get_smush_meta( $attachment_id );

		return empty( $meta[ self::WEBP_FLAG ] ) ? '' : $meta[ self::WEBP_FLAG ];
	}

	public function update_webp_flag( $attachment_id, $value ) {
		$meta = $this->get_smush_meta( $attachment_id );
		if ( empty( $value ) ) {
			unset( $meta[ self::WEBP_FLAG ] );
		} else {
			$meta[ self::WEBP_FLAG ] = $value;
		}
		update_post_meta( $attachment_id, Smush_Optimization::SMUSH_META_KEY, $meta );
	}

	public function unset_webp_flag( $attachment_id ) {
		$this->update_webp_flag( $attachment_id, false );
	}

	/**
	 * @return array|mixed
	 */
	private function get_smush_meta( $attachment_id ) {
		$meta = get_post_meta( $attachment_id, Smush_Optimization::SMUSH_META_KEY, true );

		return empty( $meta ) ? array() : $meta;
	}

	/**
	 * @param $media_item Media_Item
	 *
	 * @return void
	 */
	public function delete_media_item_webp_versions( $media_item ) {
		foreach ( $media_item->get_sizes() as $size ) {
			$webp_file_path = $this->get_webp_file_path( $size->get_file_path() );
			if ( $this->fs->file_exists( $webp_file_path ) ) {
				$this->fs->unlink( $webp_file_path );
			}
		}
	}

	public function delete_all_webp_files() {
		$webp_path = $this->webp_dir->get_webp_path();

		// Delete the whole webp directory only when on single install or network admin.
		$this->fs->get_wp_filesystem()->delete( $webp_path, true );

		do_action( 'wp_smush_after_delete_all_webp_files' );
	}
}
