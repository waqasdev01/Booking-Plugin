<?php
/**
 * Outgoing webhooks. Admin registers one or more URLs per event under
 * AI/API settings; this fires a signed POST to each when the event
 * actually happens (wired into the AJAX submit handler and the payment
 * verification/webhook handlers).
 *
 * @package Wise_Mirror_Booking
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Wise_Mirror_Webhooks {

	const OPTION_KEY = 'wise_mirror_webhooks';

	public static function get_all() {
		return get_option( self::OPTION_KEY, array() );
	}

	public static function sanitize( $raw ) {
		$clean = array();
		if ( ! is_array( $raw ) ) {
			return $clean;
		}
		foreach ( $raw as $row ) {
			$url = esc_url_raw( $row['url'] ?? '' );
			if ( ! $url ) {
				continue;
			}
			$events = isset( $row['events'] ) && is_array( $row['events'] ) ? array_map( 'sanitize_key', $row['events'] ) : array();
			$clean[] = array(
				'url'     => $url,
				'events'  => $events,
				'enabled' => ! empty( $row['enabled'] ),
			);
		}
		return $clean;
	}

	/**
	 * Fire an event to every webhook subscribed to it.
	 *
	 * @param string $event Event key, e.g. "booking.created", "payment.confirmed".
	 * @param array  $payload Event data.
	 */
	public static function dispatch( $event, array $payload ) {
		foreach ( self::get_all() as $hook ) {
			if ( empty( $hook['enabled'] ) || ! in_array( $event, $hook['events'], true ) ) {
				continue;
			}
			self::send( $hook['url'], $event, $payload );
		}
	}

	/**
	 * Send a single webhook POST. Used both by dispatch() and the
	 * "Send Test" button in the dashboard.
	 *
	 * @param string $url     Destination URL.
	 * @param string $event   Event key.
	 * @param array  $payload Event data.
	 * @return true|WP_Error
	 */
	public static function send( $url, $event, array $payload ) {
		$body = array(
			'event'     => $event,
			'timestamp' => current_time( 'mysql' ),
			'data'      => $payload,
		);

		$response = wp_remote_post(
			$url,
			array(
				'timeout' => 15,
				'headers' => array( 'Content-Type' => 'application/json' ),
				'body'    => wp_json_encode( $body ),
			)
		);

		if ( is_wp_error( $response ) ) {
			Wise_Mirror_Logger::log( 'api', 'Webhook delivery failed', array( 'url' => $url, 'event' => $event, 'error' => $response->get_error_message() ) );
			return $response;
		}

		$code = (int) wp_remote_retrieve_response_code( $response );
		Wise_Mirror_Logger::log( 'api', 'Webhook delivered', array( 'url' => $url, 'event' => $event, 'status' => $code ) );

		return true;
	}
}
