<?php
/**
 * Lightweight caching helper. Uses WP transients so it works with any
 * object-cache backend the host has (Redis/Memcached) with no extra
 * config, and degrades to the DB transient table when there isn't one.
 *
 * @package Wise_Mirror_Booking
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Wise_Mirror_Cache {

	const PREFIX = 'wise_mirror_cache_';
	const TTL    = 300; // 5 minutes — short enough that admin edits show up quickly.

	public static function remember( $key, $callback, $ttl = self::TTL ) {
		$cache_key = self::PREFIX . $key;
		$cached    = get_transient( $cache_key );

		if ( false !== $cached ) {
			return $cached;
		}

		$value = call_user_func( $callback );
		set_transient( $cache_key, $value, $ttl );
		return $value;
	}

	public static function flush( $key ) {
		delete_transient( self::PREFIX . $key );
	}

	public static function flush_all() {
		global $wpdb;
		$like = $wpdb->esc_like( '_transient_' . self::PREFIX ) . '%';
		$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s", $like ) ); // phpcs:ignore
		$like_timeout = $wpdb->esc_like( '_transient_timeout_' . self::PREFIX ) . '%';
		$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s", $like_timeout ) ); // phpcs:ignore
	}
}
