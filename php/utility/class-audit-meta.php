<?php
/**
 * This file has utility methods for Audit metadata.
 *
 * @package WP_Tide_API
 */

namespace WP_Tide_API\Utility;

use WP_Tide_API\API\Endpoint\Audit;

/**
 * Class Audit_Meta
 */
class Audit_Meta {

	/**
	 * Gets a list of filtered standards.
	 *
	 * @param int $post_id The ID of the post.
	 *
	 * @return array The list of standards for the given post.
	 */
	public static function get_filtered_standards( $post_id ) {

		$standards = get_post_meta( $post_id, 'standards', true );

		if ( empty( $standards ) ) {
			return $standards;
		} else {
			return static::filter_standards( $standards );
		}
	}

	/**
	 * Filter the given list of standards to allowed standards or executable standards if empty.
	 *
	 * @param array $standards The array of standards to filter.
	 *
	 * @return array The filtered standards.
	 */
	public static function filter_standards( $standards ) {
		$standards = (array) $standards;

		// Filter using the allowed standards.
		if ( ! empty( $standards ) ) {
			$allowed_standards = array_keys( Audit::allowed_standards() );

			$standards = array_filter( $standards, function ( $standard ) use ( $allowed_standards ) {
				return in_array( $standard, $allowed_standards, true );
			} );
		} else {
			$standards = array_keys( Audit::executable_audit_fields() );
		}

		return $standards;
	}

}
