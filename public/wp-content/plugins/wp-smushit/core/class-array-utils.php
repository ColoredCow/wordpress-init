<?php

namespace Smush\Core;

class Array_Utils {
	public function array_hash( $array, $keys = array() ) {
		$hash = 0;
		if ( is_array( $array ) ) {
			foreach ( $array as $key => $value ) {
				if ( is_array( $value ) ) {
					$value_hash = $this->array_hash(
						$value,
						array_merge( $keys, array( $key ) )
					);
				} else {
					$prefix     = join( '~', $keys );
					$value_hash = crc32( $prefix . $value );
				}

				$hash += $value_hash;
			}
		}

		return $hash;
	}

	public function get_array_value( $haystack, $key, $default_value = null ) {
		if ( ! is_array( $key ) ) {
			$key = array( $key );
		}

		if ( ! is_array( $haystack ) ) {
			return $default_value;
		}

		$value = $haystack;
		foreach ( $key as $key_part ) {
			$value = isset( $value[ $key_part ] ) ? $value[ $key_part ] : $default_value;
		}

		return $value;
	}

	public function put_array_value( &$haystack, $value, $keys ) {
		if ( ! is_array( $keys ) ) {
			$keys = array( $keys );
		}

		$pointer = &$haystack;
		foreach ( $keys as $key ) {
			if ( ! isset( $pointer[ $key ] ) ) {
				$pointer         = empty( $pointer ) ? array() : $pointer;
				$pointer[ $key ] = array();
			}
			$pointer = &$pointer[ $key ];
		}
		$pointer = $value;
	}

	public function ensure_array( $array ) {
		return empty( $array ) || ! is_array( $array )
			? array()
			: $array;
	}

	/**
	 * WARNING: This trick works only for arrays in which all the values are valid keys.
	 * @see https://stackoverflow.com/a/8321701
	 *
	 * @param $array scalar[]
	 *
	 * @return array Unique array
	 */
	public function fast_array_unique( $array ) {
		if ( ! is_array( $array ) ) {
			return array();
		}

		return array_keys( array_flip( $array ) );
	}
}
