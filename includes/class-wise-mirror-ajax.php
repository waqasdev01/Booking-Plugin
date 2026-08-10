<?php
/**
 * Two AJAX endpoints:
 *  - wise_upload_photo:   uploads ONE photo immediately (used by the
 *    drag & drop uploader for real progress + instant preview) and
 *    returns its URL. Nothing is "booked" yet.
 *  - wise_submit_booking: final submission — takes the already-uploaded
 *    photo URLs (not files) plus the rest of the form, validates the
 *    date/time slot again server-side, stores the booking, and creates
 *    the Stripe Checkout Session.
 *
 * No booking is treated as confirmed here — that only happens after
 * Stripe verification (see class-wise-mirror-rest-api.php).
 *
 * @package Wise_Mirror_Booking
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Wise_Mirror_Ajax {

	public static function init() {
		add_action( 'wp_ajax_wise_upload_photo', array( __CLASS__, 'handle_upload_photo' ) );
		add_action( 'wp_ajax_nopriv_wise_upload_photo', array( __CLASS__, 'handle_upload_photo' ) );

		add_action( 'wp_ajax_wise_submit_booking', array( __CLASS__, 'handle_submit' ) );
		add_action( 'wp_ajax_nopriv_wise_submit_booking', array( __CLASS__, 'handle_submit' ) );
	}

	/**
	 * Upload a single photo immediately (drag & drop / file-picker flow).
	 * Returns { url, field } on success.
	 */
	public static function handle_upload_photo() {
		check_ajax_referer( 'wise_booking_submit', 'nonce' );

		$field = isset( $_POST['field'] ) ? sanitize_key( wp_unslash( $_POST['field'] ) ) : '';
		$allowed_fields = array( 'photo_smiling', 'photo_unsmiling', 'photo_profile' );

		if ( ! in_array( $field, $allowed_fields, true ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid upload field.', 'wise-mirror-booking' ) ), 400 );
		}

		if ( empty( $_FILES['file'] ) ) {
			wp_send_json_error( array( 'message' => __( 'No file received.', 'wise-mirror-booking' ) ), 400 );
		}

		$uploaded = self::handle_photo_upload( $_FILES['file'] ); // phpcs:ignore WordPress.Security.NonceVerification

		if ( is_wp_error( $uploaded ) ) {
			wp_send_json_error( array( 'message' => $uploaded->get_error_message() ), 400 );
		}

		wp_send_json_success( array( 'url' => $uploaded, 'field' => $field ) );
	}

	public static function handle_submit() {
		check_ajax_referer( 'wise_booking_submit', 'nonce' );

		$package_key = isset( $_POST['package_key'] ) ? sanitize_key( wp_unslash( $_POST['package_key'] ) ) : '';
		$session     = Wise_Mirror_Sessions::get( $package_key );

		if ( ! $session || 'active' !== $session['status'] ) {
			wp_send_json_error( array( 'message' => __( 'Please select a valid package.', 'wise-mirror-booking' ) ), 400 );
		}

		$full_name = isset( $_POST['full_name'] ) ? sanitize_text_field( wp_unslash( $_POST['full_name'] ) ) : '';
		$email     = isset( $_POST['email'] ) ? sanitize_email( wp_unslash( $_POST['email'] ) ) : '';
		$phone     = isset( $_POST['phone'] ) ? sanitize_text_field( wp_unslash( $_POST['phone'] ) ) : '';
		$country   = isset( $_POST['country'] ) ? sanitize_text_field( wp_unslash( $_POST['country'] ) ) : '';
		$contact_method = isset( $_POST['contact_method'] ) ? sanitize_text_field( wp_unslash( $_POST['contact_method'] ) ) : '';

		$month = isset( $_POST['birth_month'] ) ? absint( $_POST['birth_month'] ) : 0;
		$day   = isset( $_POST['birth_day'] ) ? absint( $_POST['birth_day'] ) : 0;
		$year  = isset( $_POST['birth_year'] ) ? absint( $_POST['birth_year'] ) : 0;

		$booking_date = isset( $_POST['booking_date'] ) ? sanitize_text_field( wp_unslash( $_POST['booking_date'] ) ) : '';
		$booking_time = isset( $_POST['booking_time'] ) ? sanitize_text_field( wp_unslash( $_POST['booking_time'] ) ) : '';

		$concerns      = isset( $_POST['concerns'] ) ? sanitize_textarea_field( wp_unslash( $_POST['concerns'] ) ) : '';
		$notes         = isset( $_POST['notes'] ) ? sanitize_textarea_field( wp_unslash( $_POST['notes'] ) ) : '';

		$categories = array();
		if ( ! empty( $_POST['concern_categories'] ) && is_array( $_POST['concern_categories'] ) ) {
			$categories = array_map( 'sanitize_text_field', wp_unslash( $_POST['concern_categories'] ) );
		}

		$errors = array();
		if ( '' === $full_name ) {
			$errors[] = __( 'Full name is required.', 'wise-mirror-booking' );
		}
		if ( ! is_email( $email ) ) {
			$errors[] = __( 'A valid email address is required.', 'wise-mirror-booking' );
		}
		if ( ! $month || ! $day || ! $year ) {
			$errors[] = __( 'Birth date is required.', 'wise-mirror-booking' );
		}

		$date_check = Wise_Mirror_Schedule::validate_date( $booking_date );
		if ( is_wp_error( $date_check ) ) {
			$errors[] = $date_check->get_error_message();
		} elseif ( ! preg_match( '/^\d{2}:\d{2}$/', $booking_time ) ) {
			$errors[] = __( 'Please choose a booking time.', 'wise-mirror-booking' );
		} else {
			$available = Wise_Mirror_Schedule::get_available_slots( $booking_date );
			if ( is_wp_error( $available ) || ! in_array( $booking_time, $available, true ) ) {
				$errors[] = __( 'That time slot is no longer available. Please choose another.', 'wise-mirror-booking' );
			}
		}

		// Photos were already uploaded individually via wise_upload_photo —
		// we just receive their URLs here as JSON arrays.
		$photo_fields  = array( 'photo_smiling', 'photo_unsmiling', 'photo_profile' );
		$upload_limits = Wise_Mirror_Settings::upload_settings();
		$photo_urls    = array();

		foreach ( $photo_fields as $field ) {
			$raw = isset( $_POST[ $field . '_urls' ] ) ? wp_unslash( $_POST[ $field . '_urls' ] ) : '[]'; // phpcs:ignore
			$urls = json_decode( $raw, true );
			$urls = is_array( $urls ) ? array_values( array_filter( array_map( 'esc_url_raw', $urls ) ) ) : array();

			if ( empty( $urls ) ) {
				$errors[] = __( 'At least one photo is required for each category.', 'wise-mirror-booking' );
			} elseif ( count( $urls ) > (int) $upload_limits['max_images_per_field'] ) {
				$urls = array_slice( $urls, 0, (int) $upload_limits['max_images_per_field'] );
			}

			$photo_urls[ $field ] = $urls;
		}

		if ( ! empty( $errors ) ) {
			wp_send_json_error( array( 'message' => implode( ' ', $errors ) ), 400 );
		}

		$birth_date = sprintf( '%04d-%02d-%02d', $year, $month, $day );
		$booking_id = Wise_Mirror_DB::generate_booking_id();

		Wise_Mirror_DB::insert_submission(
			array(
				'booking_id'         => $booking_id,
				'package_key'        => $package_key,
				'full_name'          => $full_name,
				'email'              => $email,
				'phone'              => $phone,
				'contact_method'     => $contact_method,
				'birth_date'         => $birth_date,
				'booking_date'       => $booking_date,
				'booking_time'       => $booking_time,
				'concerns'           => trim( $concerns . ( $notes ? "\n\nAdditional notes: {$notes}" : '' ) ),
				'concern_categories' => implode( ', ', $categories ),
				'photo_smiling'      => wp_json_encode( $photo_urls['photo_smiling'] ),
				'photo_unsmiling'    => wp_json_encode( $photo_urls['photo_unsmiling'] ),
				'photo_profile'      => wp_json_encode( $photo_urls['photo_profile'] ),
				'status'             => 'awaiting_payment',
				'ip_address'         => self::client_ip(),
			)
		);

		Wise_Mirror_Logger::info( 'New booking submission received', array( 'booking_id' => $booking_id, 'package' => $package_key, 'date' => $booking_date, 'time' => $booking_time ) );

		Wise_Mirror_Webhooks::dispatch( 'booking.created', array(
			'booking_id' => $booking_id,
			'package'    => $package_key,
			'email'      => $email,
			'date'       => $booking_date,
			'time'       => $booking_time,
		) );

		self::maybe_notify_admin( $booking_id, $full_name, $email, $session['name'], $booking_date, $booking_time, $phone, $contact_method, $photo_urls );

		$general      = Wise_Mirror_Settings::general();
		$booking_page = ! empty( $general['booking_page_url'] ) ? $general['booking_page_url'] : wp_get_referer();
		$booking_page = $booking_page ? $booking_page : home_url( '/' );

		if ( (int) $session['price'] <= 0 ) {
			Wise_Mirror_DB::insert_payment(
				array(
					'booking_id'        => $booking_id,
					'customer_name'     => $full_name,
					'customer_email'    => $email,
					'package_key'       => $package_key,
					'package_label'     => $session['name'],
					'amount'            => 0,
					'currency'          => $session['currency'],
					'mode'              => Wise_Mirror_Settings::is_live_mode() ? 'live' : 'test',
					'stripe_session_id' => 'free_' . $booking_id,
					'payment_status'    => 'paid',
				)
			);
			Wise_Mirror_DB::update_submission_status( $booking_id, 'confirmed' );

			$submission = Wise_Mirror_DB::get_submission_by_booking_id( $booking_id );
			$payment    = Wise_Mirror_DB::get_payment_by_session_id( 'free_' . $booking_id );
			Wise_Mirror_Email::send_confirmation( $submission, $payment );
			Wise_Mirror_Webhooks::dispatch( 'payment.confirmed', array( 'booking_id' => $booking_id, 'amount' => 0 ) );

			$redirect = add_query_arg( 'wise_session_id', 'free_' . $booking_id, $booking_page );
			wp_send_json_success( array( 'checkout_url' => $redirect ) );
		}

		$secret_key = Wise_Mirror_Settings::active_secret_key();
		if ( '' === $secret_key ) {
			Wise_Mirror_Logger::error( 'Stripe secret key missing for active mode', array( 'booking_id' => $booking_id ) );
			wp_send_json_error( array( 'message' => __( 'Payments are not configured yet. Please contact us directly.', 'wise-mirror-booking' ) ), 500 );
		}

		$client = new Wise_Mirror_Stripe_Client( $secret_key );

		$success_url = add_query_arg( 'wise_session_id', '{CHECKOUT_SESSION_ID}', $booking_page );
		$success_url = str_replace( '%7BCHECKOUT_SESSION_ID%7D', '{CHECKOUT_SESSION_ID}', $success_url );
		$cancel_url  = add_query_arg( 'wise_cancelled', '1', $booking_page );

		$checkout_session = $client->create_checkout_session(
			array(
				'amount'         => $session['price'],
				'currency'       => $session['currency'],
				'product_name'   => $session['name'] . ' — The Wise Mirror',
				'success_url'    => $success_url,
				'cancel_url'     => $cancel_url,
				'customer_email' => $email,
				'metadata'       => array( 'booking_id' => $booking_id ),
			)
		);

		if ( is_wp_error( $checkout_session ) ) {
			Wise_Mirror_Logger::error( 'Stripe Checkout Session creation failed', array( 'booking_id' => $booking_id, 'error' => $checkout_session->get_error_message() ) );
			wp_send_json_error( array( 'message' => __( 'We could not start checkout. Please try again shortly.', 'wise-mirror-booking' ) ), 502 );
		}

		Wise_Mirror_DB::insert_payment(
			array(
				'booking_id'        => $booking_id,
				'customer_name'     => $full_name,
				'customer_email'    => $email,
				'package_key'       => $package_key,
				'package_label'     => $session['name'],
				'amount'            => $session['price'],
				'currency'          => $session['currency'],
				'mode'              => Wise_Mirror_Settings::is_live_mode() ? 'live' : 'test',
				'stripe_session_id' => $checkout_session['id'],
				'payment_status'    => 'pending',
			)
		);

		wp_send_json_success( array( 'checkout_url' => $checkout_session['url'] ) );
	}

	/**
	 * Notify the admin of a brand-new booking submission. This fires at
	 * submission time (same moment as the "booking.created" webhook) —
	 * before payment is verified — and is always a separate email from
	 * the customer's own confirmation (see Wise_Mirror_Email::send_confirmation(),
	 * which only fires later, once Stripe verifies payment).
	 */
	private static function maybe_notify_admin( $booking_id, $full_name, $email, $package_label, $booking_date = '', $booking_time = '', $phone = '', $contact_method = '', $photo_urls = array() ) {
		$system = Wise_Mirror_Settings::system_settings();
		if ( empty( $system['notify_admin_on_booking'] ) || empty( $system['notify_admin_email'] ) ) {
			return;
		}

		$time_display = $booking_time;
		if ( preg_match( '/^(\d{1,2}):(\d{2})$/', (string) $booking_time, $m ) ) {
			$hour   = (int) $m[1];
			$suffix = $hour >= 12 ? 'PM' : 'AM';
			$hour12 = $hour % 12 ?: 12;
			$time_display = $hour12 . ':' . $m[2] . ' ' . $suffix;
		}

		$photo_labels = array(
			'photo_smiling'   => 'Full Face (Smiling)',
			'photo_unsmiling' => 'Full Face (Unsmiling)',
			'photo_profile'   => 'Side Profile',
		);

		$rows = array(
			'Name'                     => $full_name,
			'Email'                    => $email,
			'Phone'                    => $phone ?: 'Not provided',
			'Preferred Contact Method' => $contact_method ?: 'Not specified',
			'Package'                  => $package_label,
			'Booking Reference'        => $booking_id,
			'Booking Date'             => $booking_date,
			'Session Time'             => $time_display,
		);

		ob_start();
		?>
		<!DOCTYPE html>
		<html>
		<head><meta charset="utf-8"><title>New User Booking</title></head>
		<body style="margin:0;padding:0;background:#F5EBDD;font-family:Arial,Helvetica,sans-serif;">
			<table role="presentation" width="100%" cellpadding="0" cellspacing="0">
				<tr>
					<td align="center" style="padding:32px 16px;">
						<table role="presentation" width="100%" style="max-width:560px;background:#ffffff;border-radius:10px;overflow:hidden;">
							<tr>
								<td style="background:#0F0F0F;padding:22px 32px;">
									<h1 style="margin:0;color:#ffffff;font-size:20px;">New User Booking</h1>
									<p style="margin:4px 0 0;color:#E8D8C3;font-size:13px;">A new user has just made a booking.</p>
								</td>
							</tr>
							<tr>
								<td style="padding:28px 32px;color:#0F0F0F;font-size:14.5px;line-height:1.7;">
									<table role="presentation" width="100%" style="border-collapse:collapse;">
										<?php foreach ( $rows as $label => $value ) : ?>
											<tr>
												<td style="padding:6px 0;color:#4C4C4C;width:190px;vertical-align:top;"><strong><?php echo esc_html( $label ); ?></strong></td>
												<td style="padding:6px 0;"><?php echo esc_html( $value ); ?></td>
											</tr>
										<?php endforeach; ?>
									</table>

									<h2 style="font-size:15px;color:#C47A2C;text-transform:uppercase;letter-spacing:.03em;margin:26px 0 14px;">Uploaded Photos</h2>

									<?php foreach ( $photo_labels as $field => $label ) : ?>
										<p style="margin:0 0 6px;font-weight:bold;color:#3A2A1E;font-size:13.5px;"><?php echo esc_html( $label ); ?></p>
										<?php $urls = $photo_urls[ $field ] ?? array(); ?>
										<?php if ( empty( $urls ) ) : ?>
											<p style="margin:0 0 18px;color:#4C4C4C;font-size:13px;">None uploaded</p>
										<?php else : ?>
											<table role="presentation" cellpadding="0" cellspacing="0" style="margin:0 0 20px;">
												<tr>
													<?php foreach ( $urls as $url ) : ?>
														<td style="padding:0 8px 8px 0;">
															<a href="<?php echo esc_url( $url ); ?>">
																<img src="<?php echo esc_url( $url ); ?>" alt="<?php echo esc_attr( $label ); ?>" width="140" style="width:140px;height:140px;object-fit:cover;border-radius:8px;border:1px solid #E8D8C3;display:block;">
															</a>
														</td>
													<?php endforeach; ?>
												</tr>
											</table>
										<?php endif; ?>
									<?php endforeach; ?>
								</td>
							</tr>
							<tr>
								<td style="padding:14px 32px;background:#F5EBDD;color:#4C4C4C;font-size:11.5px;">
									The Wise Mirror — Booking System
								</td>
							</tr>
						</table>
					</td>
				</tr>
			</table>
		</body>
		</html>
		<?php
		$html_body = ob_get_clean();

		$email_settings = Wise_Mirror_Settings::email_settings();
		$headers = array();
		if ( ! empty( $email_settings['from_name'] ) && ! empty( $email_settings['from_email'] ) ) {
			$headers[] = 'From: ' . $email_settings['from_name'] . ' <' . $email_settings['from_email'] . '>';
		}
		if ( is_email( $email ) ) {
			$headers[] = 'Reply-To: ' . $email;
		}

		add_filter( 'wp_mail_content_type', array( 'Wise_Mirror_Email', 'html_content_type' ) );

		$sent = wp_mail(
			$system['notify_admin_email'],
			'New User Booking — ' . $booking_id,
			$html_body,
			$headers
		);

		remove_filter( 'wp_mail_content_type', array( 'Wise_Mirror_Email', 'html_content_type' ) );

		if ( $sent ) {
			Wise_Mirror_Logger::info( 'Admin notification email sent', array( 'booking_id' => $booking_id, 'to' => $system['notify_admin_email'] ) );
		} else {
			Wise_Mirror_Logger::error( 'Admin notification email FAILED to send — check System Settings → Email (delivery method/SMTP) and that the Notify Email address is valid.', array( 'booking_id' => $booking_id, 'to' => $system['notify_admin_email'] ) );
		}
	}

	/**
	 * Handle a single uploaded photo file using WP's own upload handler.
	 *
	 * @param array $file Single-file $_FILES-style array.
	 * @return string|WP_Error Uploaded file URL, or WP_Error.
	 */
	private static function handle_photo_upload( array $file ) {
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';

		$settings  = Wise_Mirror_Settings::upload_settings();
		$max_bytes = $settings['max_size_mb'] * MB_IN_BYTES;
		$allowed   = $settings['allowed_types'];

		if ( ( $file['size'] ?? 0 ) > $max_bytes ) {
			/* translators: 1: max size in MB */
			return new WP_Error( 'wise_file_too_large', sprintf( __( 'Each photo must be smaller than %d MB.', 'wise-mirror-booking' ), $settings['max_size_mb'] ) );
		}

		$ext = strtolower( pathinfo( $file['name'] ?? '', PATHINFO_EXTENSION ) );
		if ( ! in_array( $ext, $allowed, true ) ) {
			return new WP_Error( 'wise_file_type', __( 'Unsupported photo file type.', 'wise-mirror-booking' ) );
		}

		add_filter( 'upload_dir', array( __CLASS__, 'redirect_upload_dir' ) );

		$overrides = array(
			'test_form' => false,
			'mimes'     => array(
				'jpg|jpeg' => 'image/jpeg',
				'png'      => 'image/png',
				'webp'     => 'image/webp',
				'heic'     => 'image/heic',
			),
		);

		$result = wp_handle_upload( $file, $overrides );

		remove_filter( 'upload_dir', array( __CLASS__, 'redirect_upload_dir' ) );

		if ( isset( $result['error'] ) ) {
			return new WP_Error( 'wise_upload_failed', $result['error'] );
		}

		return $result['url'];
	}

	/**
	 * Store booking photos in a dedicated, non-guessable subfolder rather
	 * than mixing into the general media library uploads.
	 */
	public static function redirect_upload_dir( $dirs ) {
		$custom_subdir  = '/wise-mirror-bookings' . $dirs['subdir'];
		$dirs['subdir'] = $custom_subdir;
		$dirs['path']   = $dirs['basedir'] . $custom_subdir;
		$dirs['url']    = $dirs['baseurl'] . $custom_subdir;
		return $dirs;
	}

	private static function client_ip() {
		$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
		return $ip;
	}
}
