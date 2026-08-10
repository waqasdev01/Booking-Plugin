<?php
/**
 * CRUD helpers for the two custom tables.
 *
 * @package Wise_Mirror_Booking
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Wise_Mirror_DB {

	public static function submissions_table() {
		global $wpdb;
		return $wpdb->prefix . 'wise_submissions';
	}

	public static function payments_table() {
		global $wpdb;
		return $wpdb->prefix . 'wise_payments';
	}

	/**
	 * Generate a unique booking ID like WM-8F3K2Q.
	 *
	 * @return string
	 */
	public static function generate_booking_id() {
		return 'WM-' . strtoupper( wp_generate_password( 8, false, false ) );
	}

	/**
	 * Insert a new pending submission row.
	 *
	 * @param array $data Submission fields.
	 * @return int|false Inserted row ID or false.
	 */
	public static function insert_submission( array $data ) {
		global $wpdb;
		$now = current_time( 'mysql' );

		$defaults = array(
			'booking_id'         => self::generate_booking_id(),
			'package_key'        => '',
			'full_name'          => '',
			'email'              => '',
			'phone'              => '',
			'contact_method'     => '',
			'birth_date'         => '',
			'booking_date'       => '',
			'booking_time'       => '',
			'concerns'           => '',
			'concern_categories' => '',
			'photo_smiling'      => '',
			'photo_unsmiling'    => '',
			'photo_profile'      => '',
			'status'             => 'pending',
			'ip_address'         => '',
			'created_at'         => $now,
			'updated_at'         => $now,
		);

		$row = wp_parse_args( $data, $defaults );

		$result = $wpdb->insert( self::submissions_table(), $row ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery

		return $result ? $wpdb->insert_id : false;
	}

	/**
	 * Fetch a submission by booking_id.
	 *
	 * @param string $booking_id Booking ID.
	 * @return array|null
	 */
	public static function get_submission_by_booking_id( $booking_id ) {
		global $wpdb;
		$table = self::submissions_table();
		$row   = $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM {$table} WHERE booking_id = %s", $booking_id ), // phpcs:ignore
			ARRAY_A
		);
		return $row ?: null;
	}

	public static function update_submission_status( $booking_id, $status ) {
		global $wpdb;
		return $wpdb->update(
			self::submissions_table(),
			array(
				'status'     => $status,
				'updated_at' => current_time( 'mysql' ),
			),
			array( 'booking_id' => $booking_id )
		);
	}

	/**
	 * Insert a pending payment row.
	 *
	 * @param array $data Payment fields.
	 * @return int|false
	 */
	public static function insert_payment( array $data ) {
		global $wpdb;
		$now = current_time( 'mysql' );

		$defaults = array(
			'booking_id'            => '',
			'customer_name'         => '',
			'customer_email'        => '',
			'package_key'           => '',
			'package_label'         => '',
			'amount'                => 0,
			'currency'              => 'usd',
			'mode'                  => 'test',
			'stripe_session_id'     => '',
			'stripe_payment_intent' => '',
			'payment_status'        => 'pending',
			'created_at'            => $now,
			'updated_at'            => $now,
		);

		$row = wp_parse_args( $data, $defaults );

		$result = $wpdb->insert( self::payments_table(), $row ); // phpcs:ignore

		return $result ? $wpdb->insert_id : false;
	}

	/**
	 * Fetch a payment row by Stripe Checkout Session ID.
	 *
	 * @param string $session_id Stripe session id.
	 * @return array|null
	 */
	public static function get_payment_by_session_id( $session_id ) {
		global $wpdb;
		$table = self::payments_table();
		$row   = $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM {$table} WHERE stripe_session_id = %s", $session_id ), // phpcs:ignore
			ARRAY_A
		);
		return $row ?: null;
	}

	/**
	 * Mark a payment row as verified/paid (idempotent — only fires side
	 * effects the first time a session transitions into "paid").
	 *
	 * @param string $session_id      Stripe session id.
	 * @param string $payment_intent  Stripe payment intent id.
	 * @param string $status          New payment_status value.
	 * @return bool True if this call transitioned the row into paid for the first time.
	 */
	public static function mark_payment_status( $session_id, $payment_intent, $status ) {
		global $wpdb;
		$existing = self::get_payment_by_session_id( $session_id );
		if ( ! $existing ) {
			return false;
		}

		$already_paid = 'paid' === $existing['payment_status'];

		$wpdb->update(
			self::payments_table(),
			array(
				'stripe_payment_intent' => $payment_intent,
				'payment_status'        => $status,
				'verified_at'           => 'paid' === $status ? current_time( 'mysql' ) : $existing['verified_at'],
				'updated_at'            => current_time( 'mysql' ),
			),
			array( 'stripe_session_id' => $session_id )
		);

		return ( 'paid' === $status && ! $already_paid );
	}

	public static function get_recent_submissions( $limit = 50, $offset = 0 ) {
		global $wpdb;
		$table = self::submissions_table();
		return $wpdb->get_results(
			$wpdb->prepare( "SELECT * FROM {$table} ORDER BY id DESC LIMIT %d OFFSET %d", $limit, $offset ), // phpcs:ignore
			ARRAY_A
		);
	}

	public static function get_recent_payments( $limit = 50, $offset = 0 ) {
		global $wpdb;
		$table = self::payments_table();
		return $wpdb->get_results(
			$wpdb->prepare( "SELECT * FROM {$table} ORDER BY id DESC LIMIT %d OFFSET %d", $limit, $offset ), // phpcs:ignore
			ARRAY_A
		);
	}

	public static function count_submissions() {
		global $wpdb;
		$table = self::submissions_table();
		return (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" ); // phpcs:ignore
	}

	public static function count_payments_by_status( $status ) {
		global $wpdb;
		$table = self::payments_table();
		return (int) $wpdb->get_var(
			$wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE payment_status = %s", $status ) // phpcs:ignore
		);
	}

	public static function sum_paid_amount() {
		global $wpdb;
		$table = self::payments_table();
		return (int) $wpdb->get_var(
			$wpdb->prepare( "SELECT SUM(amount) FROM {$table} WHERE payment_status = %s", 'paid' ) // phpcs:ignore
		);
	}

	/**
	 * Bookings per package, for the Analytics page.
	 *
	 * @return array [ package_key => count ]
	 */
	public static function count_by_package() {
		global $wpdb;
		$table = self::submissions_table();
		$rows  = $wpdb->get_results( "SELECT package_key, COUNT(*) AS total FROM {$table} GROUP BY package_key ORDER BY total DESC", ARRAY_A ); // phpcs:ignore
		return $rows ? $rows : array();
	}

	/**
	 * Bookings per day for the last N days, for the Analytics page.
	 *
	 * @param int $days How many days back.
	 * @return array [ 'Y-m-d' => count ]
	 */
	public static function count_by_day( $days = 14 ) {
		global $wpdb;
		$table = self::submissions_table();
		$since = gmdate( 'Y-m-d', strtotime( "-{$days} days" ) );
		$rows  = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT DATE(created_at) AS day, COUNT(*) AS total FROM {$table} WHERE created_at >= %s GROUP BY DATE(created_at) ORDER BY day ASC", // phpcs:ignore
				$since
			),
			ARRAY_A
		);
		$map = array();
		foreach ( (array) $rows as $r ) {
			$map[ $r['day'] ] = (int) $r['total'];
		}
		return $map;
	}
}
