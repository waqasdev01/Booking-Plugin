<?php
/**
 * Thin Stripe API client using wp_remote_post / wp_remote_get.
 * No Stripe SDK dependency required.
 *
 * @package Wise_Mirror_Booking
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Wise_Mirror_Stripe_Client {

	const API_BASE = 'https://api.stripe.com/v1';

	/**
	 * @var string
	 */
	private $secret_key;

	public function __construct( $secret_key ) {
		$this->secret_key = (string) $secret_key;
	}

	public function has_secret_key() {
		return '' !== $this->secret_key;
	}

	public function post( $path, array $body ) {
		if ( ! $this->has_secret_key() ) {
			return new WP_Error( 'wise_missing_secret', __( 'Stripe secret key is not configured for the active mode.', 'wise-mirror-booking' ) );
		}

		$response = wp_remote_post(
			self::API_BASE . $path,
			array(
				'timeout' => 30,
				'headers' => array(
					'Authorization' => 'Bearer ' . $this->secret_key,
					'Content-Type'  => 'application/x-www-form-urlencoded',
				),
				'body'    => $body,
			)
		);

		return $this->handle_response( $response );
	}

	public function get( $path ) {
		if ( ! $this->has_secret_key() ) {
			return new WP_Error( 'wise_missing_secret', __( 'Stripe secret key is not configured for the active mode.', 'wise-mirror-booking' ) );
		}

		$response = wp_remote_get(
			self::API_BASE . $path,
			array(
				'timeout' => 30,
				'headers' => array(
					'Authorization' => 'Bearer ' . $this->secret_key,
				),
			)
		);

		return $this->handle_response( $response );
	}

	private function handle_response( $response ) {
		if ( is_wp_error( $response ) ) {
			return new WP_Error( 'wise_http_error', $response->get_error_message() );
		}

		$code = (int) wp_remote_retrieve_response_code( $response );
		$raw  = wp_remote_retrieve_body( $response );
		$data = json_decode( $raw, true );

		if ( ! is_array( $data ) ) {
			return new WP_Error( 'wise_invalid_response', __( 'Invalid response from Stripe.', 'wise-mirror-booking' ) );
		}

		if ( $code < 200 || $code >= 300 ) {
			$message = $data['error']['message'] ?? __( 'Stripe API request failed.', 'wise-mirror-booking' );
			return new WP_Error( 'wise_stripe_error', $message, array( 'stripe' => $data['error'] ?? null ) );
		}

		return $data;
	}

	/**
	 * Create a Checkout Session.
	 *
	 * @param array $args amount, currency, product_name, quantity, success_url, cancel_url, customer_email, metadata.
	 * @return array|WP_Error
	 */
	public function create_checkout_session( array $args ) {
		if ( (int) $args['amount'] <= 0 ) {
			// Free booking: still create a session so the flow (and Stripe's
			// own confirmation) stays consistent, Stripe allows $0 line items
			// only via a 100%-off scenario, so for free bookings we instead
			// short-circuit in the AJAX handler rather than calling Stripe.
			return new WP_Error( 'wise_zero_amount', __( 'Amount must be greater than zero to create a Checkout Session.', 'wise-mirror-booking' ) );
		}

		$body = array(
			'mode'                                            => 'payment',
			'success_url'                                     => $args['success_url'],
			'cancel_url'                                       => $args['cancel_url'],
			'line_items[0][quantity]'                          => (int) ( $args['quantity'] ?? 1 ),
			'line_items[0][price_data][currency]'              => strtolower( $args['currency'] ),
			'line_items[0][price_data][unit_amount]'           => (int) $args['amount'],
			'line_items[0][price_data][product_data][name]'    => $args['product_name'],
		);

		if ( ! empty( $args['customer_email'] ) ) {
			$body['customer_email'] = $args['customer_email'];
		}

		if ( ! empty( $args['metadata'] ) && is_array( $args['metadata'] ) ) {
			foreach ( $args['metadata'] as $key => $value ) {
				$body[ 'metadata[' . $key . ']' ] = (string) $value;
			}
		}

		return $this->post( '/checkout/sessions', $body );
	}

	public function get_checkout_session( $session_id ) {
		$session_id = rawurlencode( (string) $session_id );
		return $this->get( '/checkout/sessions/' . $session_id . '?expand[]=payment_intent' );
	}
}
