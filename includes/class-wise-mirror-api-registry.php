<?php
/**
 * Registers the internal API's REST routes and doubles as the metadata
 * source for the auto-generated API documentation page — add an entry
 * here and it shows up in both places.
 *
 * @package Wise_Mirror_Booking
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Wise_Mirror_Api_Registry {

	/**
	 * Endpoint metadata: method, path (relative to wise/v1/api/), description,
	 * callback, and status ("live" or "coming_soon"). "coming_soon" entries
	 * are documented but not registered as working routes.
	 *
	 * @return array
	 */
	public static function endpoints() {
		return array(
			array(
				'method'      => 'GET',
				'path'        => 'bookings',
				'description' => 'List booking submissions (paginated).',
				'callback'    => array( __CLASS__, 'get_bookings' ),
				'status'      => 'live',
			),
			array(
				'method'      => 'GET',
				'path'        => 'bookings/(?P<booking_id>[A-Za-z0-9\-]+)',
				'description' => 'Get a single booking by booking ID, including its uploaded images.',
				'callback'    => array( __CLASS__, 'get_booking' ),
				'status'      => 'live',
			),
			array(
				'method'      => 'GET',
				'path'        => 'customers',
				'description' => 'List unique customers derived from bookings (name, email, phone).',
				'callback'    => array( __CLASS__, 'get_customers' ),
				'status'      => 'live',
			),
			array(
				'method'      => 'GET',
				'path'        => 'sessions',
				'description' => 'List all bookable sessions/packages and their pricing.',
				'callback'    => array( __CLASS__, 'get_sessions' ),
				'status'      => 'live',
			),
			array(
				'method'      => 'GET',
				'path'        => 'payments',
				'description' => 'List Stripe payment records (paginated).',
				'callback'    => array( __CLASS__, 'get_payments' ),
				'status'      => 'live',
			),
			array(
				'method'      => 'POST',
				'path'        => 'ai/generate',
				'description' => 'Send a prompt to the configured AI provider and get a text response back.',
				'callback'    => array( __CLASS__, 'post_ai_generate' ),
				'status'      => 'live',
			),
			array(
				'method'      => 'POST',
				'path'        => 'webhooks/test',
				'description' => 'Send a test payload to a configured webhook URL.',
				'callback'    => array( __CLASS__, 'post_webhook_test' ),
				'status'      => 'live',
			),
			array(
				'method'      => 'GET/POST',
				'path'        => 'crm',
				'description' => 'Sync bookings/customers to an external CRM.',
				'callback'    => null,
				'status'      => 'coming_soon',
			),
		);
	}

	public static function register_routes() {
		foreach ( self::endpoints() as $endpoint ) {
			if ( 'live' !== $endpoint['status'] || ! $endpoint['callback'] ) {
				continue;
			}
			$methods = explode( '/', $endpoint['method'] );
			foreach ( $methods as $method ) {
				register_rest_route(
					'wise/v1',
					'/api/' . $endpoint['path'],
					array(
						'methods'             => $method,
						'callback'            => $endpoint['callback'],
						'permission_callback' => array( 'Wise_Mirror_Api_Auth', 'check' ),
					)
				);
			}
		}
	}

	/* ---------------------------------------------------------------- *
	 * Endpoint handlers
	 * ---------------------------------------------------------------- */

	public static function get_bookings( WP_REST_Request $request ) {
		$page  = max( 1, (int) $request->get_param( 'page' ) ?: 1 );
		$limit = min( 100, max( 1, (int) $request->get_param( 'limit' ) ?: 25 ) );
		$rows  = Wise_Mirror_DB::get_recent_submissions( $limit, ( $page - 1 ) * $limit );

		return rest_ensure_response( array_map( array( __CLASS__, 'format_booking' ), $rows ) );
	}

	public static function get_booking( WP_REST_Request $request ) {
		$row = Wise_Mirror_DB::get_submission_by_booking_id( $request->get_param( 'booking_id' ) );
		if ( ! $row ) {
			return new WP_Error( 'wise_not_found', 'Booking not found.', array( 'status' => 404 ) );
		}
		return rest_ensure_response( self::format_booking( $row ) );
	}

	private static function format_booking( array $row ) {
		$images = array();
		foreach ( array( 'photo_smiling', 'photo_unsmiling', 'photo_profile' ) as $field ) {
			$urls = json_decode( (string) $row[ $field ], true );
			$images[ $field ] = is_array( $urls ) ? $urls : array_filter( array( $row[ $field ] ) );
		}

		return array(
			'booking_id'   => $row['booking_id'],
			'package_key'  => $row['package_key'],
			'customer'     => array(
				'full_name' => $row['full_name'],
				'email'     => $row['email'],
				'phone'     => $row['phone'],
				'contact_method' => $row['contact_method'] ?? '',
			),
			'birth_date'   => $row['birth_date'],
			'booking_date' => $row['booking_date'],
			'booking_time' => $row['booking_time'],
			'concerns'     => $row['concerns'],
			'categories'   => $row['concern_categories'],
			'status'       => $row['status'],
			'images'       => $images,
			'created_at'   => $row['created_at'],
		);
	}

	public static function get_customers( WP_REST_Request $request ) {
		global $wpdb;
		$table = Wise_Mirror_DB::submissions_table();
		$rows  = $wpdb->get_results( "SELECT DISTINCT full_name, email, phone FROM {$table} ORDER BY full_name ASC", ARRAY_A ); // phpcs:ignore
		return rest_ensure_response( $rows );
	}

	public static function get_sessions( WP_REST_Request $request ) {
		return rest_ensure_response( Wise_Mirror_Sessions::get_all() );
	}

	public static function get_payments( WP_REST_Request $request ) {
		$page  = max( 1, (int) $request->get_param( 'page' ) ?: 1 );
		$limit = min( 100, max( 1, (int) $request->get_param( 'limit' ) ?: 25 ) );
		return rest_ensure_response( Wise_Mirror_DB::get_recent_payments( $limit, ( $page - 1 ) * $limit ) );
	}

	public static function post_ai_generate( WP_REST_Request $request ) {
		$body   = $request->get_json_params();
		$prompt = sanitize_textarea_field( $body['prompt'] ?? '' );

		if ( '' === $prompt ) {
			return new WP_Error( 'wise_missing_prompt', 'A "prompt" field is required.', array( 'status' => 400 ) );
		}

		$result = Wise_Mirror_Ai_Client::generate( $prompt );
		if ( is_wp_error( $result ) ) {
			return new WP_Error( $result->get_error_code(), $result->get_error_message(), array( 'status' => 502 ) );
		}

		return rest_ensure_response( array( 'response' => $result ) );
	}

	public static function post_webhook_test( WP_REST_Request $request ) {
		$body = $request->get_json_params();
		$url  = esc_url_raw( $body['url'] ?? '' );

		if ( ! $url ) {
			return new WP_Error( 'wise_missing_url', 'A "url" field is required.', array( 'status' => 400 ) );
		}

		$result = Wise_Mirror_Webhooks::send( $url, 'webhook.test', array( 'message' => 'This is a test payload from Wise Mirror Booking.' ) );

		if ( is_wp_error( $result ) ) {
			return new WP_Error( $result->get_error_code(), $result->get_error_message(), array( 'status' => 502 ) );
		}

		return rest_ensure_response( array( 'sent' => true ) );
	}
}
