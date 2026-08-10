<?php
/**
 * Owns the internal API's Key/Secret/Token, endpoint metadata, enable
 * state, and usage stats (last used, request count). This is what the
 * API Manager dashboard page reads and writes.
 *
 * @package Wise_Mirror_Booking
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Wise_Mirror_Api_Manager {

	const OPTION_KEY = 'wise_mirror_api_credentials';
	const VERSION    = 'v1';

	/**
	 * Get (or lazily generate) the API credentials.
	 *
	 * @return array
	 */
	public static function get_credentials() {
		$creds = get_option( self::OPTION_KEY );

		if ( ! is_array( $creds ) || empty( $creds['api_key'] ) ) {
			$creds = self::generate( true );
		}

		return $creds;
	}

	/**
	 * Generate a fresh key/secret pair. Called on activation, and again
	 * whenever the admin clicks "Regenerate".
	 *
	 * @param bool $keep_stats Preserve created_date/last_used/request_count if they already exist.
	 * @return array
	 */
	public static function generate( $keep_stats = false ) {
		$existing = $keep_stats ? get_option( self::OPTION_KEY, array() ) : array();

		$creds = array(
			'api_key'       => 'wm_key_' . wp_generate_password( 32, false, false ),
			'api_secret'    => 'wm_sec_' . wp_generate_password( 48, false, false ),
			'enabled'       => $existing['enabled'] ?? true,
			'created_date'  => $existing['created_date'] ?? current_time( 'mysql' ),
			'last_used'     => $existing['last_used'] ?? '',
			'request_count' => $existing['request_count'] ?? 0,
		);

		update_option( self::OPTION_KEY, $creds );
		Wise_Mirror_Logger::log( 'api', 'API credentials ' . ( $keep_stats ? 'generated' : 'regenerated' ) );

		return $creds;
	}

	public static function set_enabled( $enabled ) {
		$creds = get_option( self::OPTION_KEY, array() );
		$creds['enabled'] = (bool) $enabled;
		update_option( self::OPTION_KEY, $creds );
	}

	/**
	 * The authentication "token" shown in the dashboard — a deterministic
	 * derivation of the key+secret, handy for systems that want a single
	 * bearer value instead of two headers.
	 *
	 * @return string
	 */
	public static function get_auth_token() {
		$creds = self::get_credentials();
		return $creds['api_key'] . '.' . $creds['api_secret'];
	}

	public static function get_endpoint_base() {
		return rest_url( 'wise/v1/api/' );
	}

	/**
	 * Record a successful authenticated request.
	 */
	public static function record_usage() {
		$creds = get_option( self::OPTION_KEY, array() );
		if ( empty( $creds ) ) {
			return;
		}
		$creds['last_used']     = current_time( 'mysql' );
		$creds['request_count'] = (int) ( $creds['request_count'] ?? 0 ) + 1;
		update_option( self::OPTION_KEY, $creds );
	}
}
