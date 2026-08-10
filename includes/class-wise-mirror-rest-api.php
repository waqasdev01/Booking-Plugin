<?php
/**
 * REST routes:
 *  - GET  /wise/v1/verify-payment  → called by the booking page right after
 *    Stripe redirects the customer back. Retrieves the session from Stripe
 *    directly and reports paid/not-paid. This is what the frontend shows
 *    "Payment Successful" from — never a locally-guessed status.
 *  - POST /wise/v1/stripe-webhook  → Stripe's own async confirmation,
 *    verified via signature. Source of truth that keeps payment_status
 *    correct even if the customer closes the tab before the redirect.
 *
 * @package Wise_Mirror_Booking
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Wise_Mirror_Rest_Api {

	public static function init() {
		add_action( 'rest_api_init', array( __CLASS__, 'register_routes' ) );
		add_action( 'rest_api_init', array( 'Wise_Mirror_Api_Registry', 'register_routes' ) );
	}

	public static function register_routes() {
		register_rest_route(
			'wise/v1',
			'/verify-payment',
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'verify_payment' ),
				'permission_callback' => '__return_true',
				'args'                => array(
					'session_id' => array( 'required' => true ),
				),
			)
		);

		register_rest_route(
			'wise/v1',
			'/stripe-webhook',
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'stripe_webhook' ),
				'permission_callback' => '__return_true',
			)
		);

		register_rest_route(
			'wise/v1',
			'/available-slots',
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'available_slots' ),
				'permission_callback' => '__return_true',
				'args'                => array(
					'date' => array( 'required' => true ),
				),
			)
		);
	}

	/**
	 * Return available booking time slots for a given date.
	 */
	public static function available_slots( WP_REST_Request $request ) {
		$date  = sanitize_text_field( $request->get_param( 'date' ) );
		$slots = Wise_Mirror_Schedule::get_available_slots( $date );

		if ( is_wp_error( $slots ) ) {
			return rest_ensure_response( array( 'slots' => array(), 'message' => $slots->get_error_message() ) );
		}

		return rest_ensure_response( array( 'slots' => $slots ) );
	}

	/**
	 * Verify a Checkout Session directly against Stripe. This is the ONLY
	 * path that is allowed to report "paid": true to the frontend.
	 */
	public static function verify_payment( WP_REST_Request $request ) {
		$session_id = sanitize_text_field( $request->get_param( 'session_id' ) );

		// Free bookings never touch Stripe — verify against our own record instead.
		if ( 0 === strpos( $session_id, 'free_' ) ) {
			$payment = Wise_Mirror_DB::get_payment_by_session_id( $session_id );
			$paid    = $payment && 'paid' === $payment['payment_status'];
			return rest_ensure_response( array( 'paid' => (bool) $paid ) );
		}

		$secret_key = Wise_Mirror_Settings::active_secret_key();
		if ( '' === $secret_key ) {
			return rest_ensure_response( array( 'paid' => false, 'message' => 'Stripe not configured.' ) );
		}

		$client  = new Wise_Mirror_Stripe_Client( $secret_key );
		$session = $client->get_checkout_session( $session_id );

		if ( is_wp_error( $session ) ) {
			Wise_Mirror_Logger::error( 'verify-payment: Stripe lookup failed', array( 'session_id' => $session_id, 'error' => $session->get_error_message() ) );
			return rest_ensure_response( array( 'paid' => false ) );
		}

		$paid = isset( $session['payment_status'] ) && 'paid' === $session['payment_status'];
		$payment_intent = is_array( $session['payment_intent'] ?? null )
			? ( $session['payment_intent']['id'] ?? '' )
			: (string) ( $session['payment_intent'] ?? '' );

		$status = $paid ? 'paid' : ( 'expired' === ( $session['status'] ?? '' ) ? 'expired' : 'failed' );

		$became_paid = Wise_Mirror_DB::mark_payment_status( $session_id, $payment_intent, $status );

		if ( $became_paid ) {
			self::finalize_booking( $session_id );
		}

		Wise_Mirror_Logger::info( 'verify-payment checked', array( 'session_id' => $session_id, 'paid' => $paid ) );

		return rest_ensure_response( array( 'paid' => (bool) $paid ) );
	}

	/**
	 * Stripe webhook receiver — verifies the signature, then treats
	 * checkout.session.completed / async payment succeeded events as
	 * confirmation, mirroring verify_payment()'s finalize step.
	 */
	public static function stripe_webhook( WP_REST_Request $request ) {
		$payload   = $request->get_body();
		$sig_header = $request->get_header( 'stripe-signature' );
		$secret     = Wise_Mirror_Settings::active_webhook_secret();

		if ( '' !== $secret ) {
			if ( ! self::verify_signature( $payload, $sig_header, $secret ) ) {
				Wise_Mirror_Logger::error( 'Webhook signature verification failed' );
				return new WP_REST_Response( array( 'error' => 'invalid_signature' ), 400 );
			}
		}

		$event = json_decode( $payload, true );
		if ( ! is_array( $event ) ) {
			return new WP_REST_Response( array( 'error' => 'invalid_payload' ), 400 );
		}

		$type = $event['type'] ?? '';

		if ( in_array( $type, array( 'checkout.session.completed', 'checkout.session.async_payment_succeeded' ), true ) ) {
			$session_obj    = $event['data']['object'] ?? array();
			$session_id     = $session_obj['id'] ?? '';
			$payment_intent = $session_obj['payment_intent'] ?? '';
			$paid           = 'paid' === ( $session_obj['payment_status'] ?? '' );

			if ( $session_id && $paid ) {
				$became_paid = Wise_Mirror_DB::mark_payment_status( $session_id, $payment_intent, 'paid' );
				if ( $became_paid ) {
					self::finalize_booking( $session_id );
				}
			}
		} elseif ( 'checkout.session.async_payment_failed' === $type || 'checkout.session.expired' === $type ) {
			$session_obj = $event['data']['object'] ?? array();
			$session_id  = $session_obj['id'] ?? '';
			if ( $session_id ) {
				Wise_Mirror_DB::mark_payment_status( $session_id, '', 'failed' );
			}
		}

		Wise_Mirror_Logger::info( 'Stripe webhook received', array( 'type' => $type ) );

		return new WP_REST_Response( array( 'received' => true ), 200 );
	}

	/**
	 * Once a payment is confirmed paid (first time only), mark the
	 * submission confirmed and send the confirmation email.
	 */
	private static function finalize_booking( $session_id ) {
		$payment = Wise_Mirror_DB::get_payment_by_session_id( $session_id );
		if ( ! $payment ) {
			return;
		}

		$submission = Wise_Mirror_DB::get_submission_by_booking_id( $payment['booking_id'] );
		if ( ! $submission ) {
			return;
		}

		Wise_Mirror_DB::update_submission_status( $payment['booking_id'], 'confirmed' );
		Wise_Mirror_Email::send_confirmation( $submission, $payment );
		Wise_Mirror_Webhooks::dispatch( 'payment.confirmed', array(
			'booking_id' => $payment['booking_id'],
			'amount'     => $payment['amount'],
			'currency'   => $payment['currency'],
			'email'      => $submission['email'],
		) );

		Wise_Mirror_Logger::info( 'Booking finalized after verified payment', array( 'booking_id' => $payment['booking_id'] ) );
	}

	/**
	 * Verify Stripe's webhook signature (HMAC-SHA256) without the Stripe SDK.
	 *
	 * @param string $payload    Raw request body.
	 * @param string $sig_header Value of the Stripe-Signature header.
	 * @param string $secret     Webhook signing secret.
	 * @return bool
	 */
	private static function verify_signature( $payload, $sig_header, $secret ) {
		if ( ! $sig_header ) {
			return false;
		}

		$parts = array();
		foreach ( explode( ',', $sig_header ) as $pair ) {
			$kv = explode( '=', $pair, 2 );
			if ( 2 === count( $kv ) ) {
				$parts[ $kv[0] ] = $kv[1];
			}
		}

		if ( empty( $parts['t'] ) || empty( $parts['v1'] ) ) {
			return false;
		}

		$signed_payload = $parts['t'] . '.' . $payload;
		$expected_sig   = hash_hmac( 'sha256', $signed_payload, $secret );

		return hash_equals( $expected_sig, $parts['v1'] );
	}
}
