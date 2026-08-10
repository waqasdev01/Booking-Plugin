<?php
/**
 * Computes bookable time slots for a given date from the admin's working
 * hours, slot duration, blocked dates, and advance-notice buffer — minus
 * whatever's already booked (pending or confirmed) that day.
 *
 * @package Wise_Mirror_Booking
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Wise_Mirror_Schedule {

	const DAY_KEYS = array( 'sun', 'mon', 'tue', 'wed', 'thu', 'fri', 'sat' );

	/**
	 * Validate that a date is inside the bookable window, on an open day,
	 * and not blocked. Returns a WP_Error on failure, true otherwise.
	 *
	 * @param string $date Y-m-d.
	 * @return true|WP_Error
	 */
	public static function validate_date( $date ) {
		if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date ) ) {
			return new WP_Error( 'wise_invalid_date', __( 'Invalid date.', 'wise-mirror-booking' ) );
		}

		$schedule = Wise_Mirror_Settings::schedule_settings();
		$today    = current_time( 'Y-m-d' );

		if ( $date < $today ) {
			return new WP_Error( 'wise_past_date', __( 'That date has already passed.', 'wise-mirror-booking' ) );
		}

		$max_date = date( 'Y-m-d', strtotime( $today . ' +' . (int) $schedule['advance_days'] . ' days' ) ); // phpcs:ignore
		if ( $date > $max_date ) {
			return new WP_Error( 'wise_too_far', __( 'That date is too far in advance.', 'wise-mirror-booking' ) );
		}

		if ( in_array( $date, $schedule['blocked_dates'], true ) ) {
			return new WP_Error( 'wise_blocked_date', __( 'That date is not available for booking.', 'wise-mirror-booking' ) );
		}

		$day_key = self::DAY_KEYS[ (int) date( 'w', strtotime( $date ) ) ]; // phpcs:ignore
		if ( empty( $schedule['days'][ $day_key ]['enabled'] ) ) {
			return new WP_Error( 'wise_day_closed', __( 'Not open for bookings on that day.', 'wise-mirror-booking' ) );
		}

		return true;
	}

	/**
	 * Get the list of available "HH:MM" slots for a date.
	 *
	 * @param string $date Y-m-d.
	 * @return array|WP_Error List of "HH:MM" strings, or WP_Error if the date itself is invalid.
	 */
	public static function get_available_slots( $date ) {
		$valid = self::validate_date( $date );
		if ( is_wp_error( $valid ) ) {
			return $valid;
		}

		$schedule = Wise_Mirror_Settings::schedule_settings();
		$day_key  = self::DAY_KEYS[ (int) date( 'w', strtotime( $date ) ) ]; // phpcs:ignore
		$day      = $schedule['days'][ $day_key ];
		$duration = (int) $schedule['slot_duration_minutes'];

		$open  = strtotime( $date . ' ' . $day['open'] );
		$close = strtotime( $date . ' ' . $day['close'] );

		if ( ! $open || ! $close || $open >= $close ) {
			return array();
		}

		$earliest_allowed = strtotime( current_time( 'mysql' ) ) + ( (int) $schedule['buffer_hours'] * HOUR_IN_SECONDS );

		$taken = self::get_taken_times( $date );

		$slots = array();
		for ( $t = $open; $t + ( $duration * 60 ) <= $close; $t += $duration * 60 ) {
			if ( $t < $earliest_allowed ) {
				continue;
			}
			$label = date( 'H:i', $t ); // phpcs:ignore WordPress.DateTime.RestrictedFunctions -- kept consistent with the local timestamps used throughout this method.
			if ( ! in_array( $label, $taken, true ) ) {
				$slots[] = $label;
			}
		}

		return $slots;
	}

	/**
	 * Times already booked (pending or confirmed — pending holds the slot
	 * until Stripe expires the session) for a given date.
	 *
	 * @param string $date Y-m-d.
	 * @return array
	 */
	private static function get_taken_times( $date ) {
		global $wpdb;
		$table = Wise_Mirror_DB::submissions_table();

		$rows = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT booking_time FROM {$table} WHERE booking_date = %s AND status IN ('awaiting_payment','confirmed')", // phpcs:ignore
				$date
			)
		);

		return array_filter( (array) $rows );
	}
}
