<?php
/**
 * Categorized activity log (Activity / Error / Debug / AI / API), shown
 * under System Settings → Logs with search, filter, export, and clear.
 *
 * @package Wise_Mirror_Booking
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Wise_Mirror_Logger {

	const OPTION_KEY  = 'wise_mirror_logs';
	const MAX_ENTRIES = 300;

	const CATEGORIES = array( 'info', 'error', 'debug', 'ai', 'api' );

	/**
	 * @param string $level   One of self::CATEGORIES (loosely enforced — unknown values are kept as-is).
	 * @param string $message Human-readable log line.
	 * @param array  $context Extra structured data.
	 */
	public static function log( $level, $message, array $context = array() ) {
		$entries = get_option( self::OPTION_KEY, array() );

		array_unshift(
			$entries,
			array(
				'time'    => current_time( 'mysql' ),
				'level'   => $level,
				'message' => $message,
				'context' => $context,
			)
		);

		$entries = array_slice( $entries, 0, self::MAX_ENTRIES );
		update_option( self::OPTION_KEY, $entries, false );
	}

	public static function info( $message, array $context = array() ) {
		self::log( 'info', $message, $context );
	}

	public static function error( $message, array $context = array() ) {
		self::log( 'error', $message, $context );
	}

	public static function debug( $message, array $context = array() ) {
		$system = Wise_Mirror_Settings::system_settings();
		if ( ! empty( $system['debug_mode'] ) ) {
			self::log( 'debug', $message, $context );
		}
	}

	/**
	 * @param string $category '' for all, or one of self::CATEGORIES.
	 * @param string $search   Free-text search against message + context.
	 * @return array
	 */
	public static function get_entries( $category = '', $search = '' ) {
		$entries = get_option( self::OPTION_KEY, array() );

		if ( '' !== $category ) {
			$entries = array_filter( $entries, function ( $e ) use ( $category ) {
				return ( $e['level'] ?? '' ) === $category;
			} );
		}

		if ( '' !== $search ) {
			$needle = strtolower( $search );
			$entries = array_filter( $entries, function ( $e ) use ( $needle ) {
				$haystack = strtolower( $e['message'] . ' ' . wp_json_encode( $e['context'] ?? array() ) );
				return false !== strpos( $haystack, $needle );
			} );
		}

		return array_values( $entries );
	}

	public static function clear() {
		delete_option( self::OPTION_KEY );
	}

	/**
	 * Build a CSV string of the current (optionally filtered) log for export.
	 *
	 * @param string $category Optional category filter.
	 * @param string $search   Optional search filter.
	 * @return string
	 */
	public static function to_csv( $category = '', $search = '' ) {
		$entries = self::get_entries( $category, $search );

		$out = fopen( 'php://temp', 'w+' );
		fputcsv( $out, array( 'Time', 'Category', 'Message', 'Context' ) );
		foreach ( $entries as $e ) {
			fputcsv( $out, array( $e['time'], $e['level'], $e['message'], wp_json_encode( $e['context'] ?? array() ) ) );
		}
		rewind( $out );
		$csv = stream_get_contents( $out );
		fclose( $out );

		return $csv;
	}
}
