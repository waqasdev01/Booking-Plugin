<?php
/**
 * Authenticates requests to the plugin's internal REST API using the
 * auto-generated API Key + Secret pair (see class-wise-mirror-api-manager.php).
 *
 * @package Wise_Mirror_Booking
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Wise_Mirror_Api_Auth {

	/**
	 * REST permission_callback for every internal API route.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return true|WP_Error
	 */
	public static function check( WP_REST_Request $request ) {
		$api = Wise_Mirror_Api_Manager::get_credentials();

		if ( empty( $api['enabled'] ) ) {
			return new WP_Error( 'wise_api_disabled', __( 'The internal API is currently disabled.', 'wise-mirror-booking' ), array( 'status' => 503 ) );
		}

		$key    = $request->get_header( 'x-wise-api-key' );
		$secret = $request->get_header( 'x-wise-api-secret' );

		// Also accept a single bearer token of the form "key.secret" for convenience.
		if ( ( ! $key || ! $secret ) && $request->get_header( 'authorization' ) ) {
			$auth = $request->get_header( 'authorization' );
			if ( 0 === stripos( $auth, 'Bearer ' ) ) {
				$token = substr( $auth, 7 );
				$parts = explode( '.', $token, 2 );
				if ( 2 === count( $parts ) ) {
					list( $key, $secret ) = $parts;
				}
			}
		}

		if ( ! $key || ! $secret || ! hash_equals( $api['api_key'], $key ) || ! hash_equals( $api['api_secret'], $secret ) ) {
			return new WP_Error( 'wise_api_unauthorized', __( 'Invalid API credentials.', 'wise-mirror-booking' ), array( 'status' => 401 ) );
		}

		Wise_Mirror_Api_Manager::record_usage();

		return true;
	}
}
