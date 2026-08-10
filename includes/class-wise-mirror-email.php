<?php
/**
 * Confirmation email rendering + sending. Only ever called after a
 * Stripe payment has been verified as paid.
 *
 * @package Wise_Mirror_Booking
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Wise_Mirror_Email {

	/**
	 * Hook SMTP overrides for the duration of a single wp_mail() call.
	 */
	public static function init() {
		add_action( 'phpmailer_init', array( __CLASS__, 'maybe_configure_smtp' ) );
		add_action( 'wp_mail_failed', array( __CLASS__, 'log_mail_failure' ) );
	}

	/**
	 * Surface the real reason an email failed (bad SMTP auth, rejected
	 * recipient, etc.) in the Logs tab instead of it just silently
	 * not arriving with no clue why.
	 *
	 * @param WP_Error $error Error from wp_mail().
	 */
	public static function log_mail_failure( $error ) {
		if ( ! is_wp_error( $error ) ) {
			return;
		}
		Wise_Mirror_Logger::error( 'Email failed to send: ' . $error->get_error_message(), array( 'data' => $error->get_error_data() ) );
	}

	public static function maybe_configure_smtp( $phpmailer ) {
		$settings = Wise_Mirror_Settings::email_settings();

		if ( 'smtp' !== $settings['method'] || empty( $settings['smtp_host'] ) ) {
			return;
		}

		$phpmailer->isSMTP();
		$phpmailer->Host       = $settings['smtp_host'];
		$phpmailer->Port       = $settings['smtp_port'];
		$phpmailer->SMTPAuth   = true;
		$phpmailer->Username   = $settings['smtp_user'];
		$phpmailer->Password   = $settings['smtp_pass'];
		$phpmailer->SMTPSecure = 'none' === $settings['smtp_secure'] ? '' : $settings['smtp_secure'];
		if ( ! empty( $settings['from_email'] ) ) {
			$phpmailer->setFrom( $settings['from_email'], $settings['from_name'] ?: get_bloginfo( 'name' ) );
		}
	}

	/**
	 * Send the booking confirmation email after verified payment.
	 *
	 * @param array $submission Submission row.
	 * @param array $payment    Payment row.
	 * @return bool
	 */
	public static function send_confirmation( array $submission, array $payment ) {
		$template = Wise_Mirror_Settings::email_template();
		$settings = Wise_Mirror_Settings::email_settings();

		$replacements = array(
			'{full_name}'      => $submission['full_name'],
			'{booking_id}'     => $submission['booking_id'],
			'{package_label}'  => $payment['package_label'],
			'{email}'          => $submission['email'],
			'{phone}'          => $submission['phone'] ?: 'Not provided',
			'{contact_method}' => $submission['contact_method'] ?: 'Not specified',
			'{booking_date}'   => $submission['booking_date'],
			'{booking_time}'   => self::format_time_12h( $submission['booking_time'] ),
		);

		$subject = strtr( $template['subject'], $replacements );
		$body    = strtr( $template['body'], $replacements );

		$html = self::render_html( $template, $subject, $body );

		add_filter( 'wp_mail_content_type', array( __CLASS__, 'html_content_type' ) );

		$headers = array();
		if ( ! empty( $settings['from_name'] ) && ! empty( $settings['from_email'] ) ) {
			$headers[] = 'From: ' . $settings['from_name'] . ' <' . $settings['from_email'] . '>';
		}

		$sent = wp_mail( $submission['email'], $subject, $html, $headers );

		remove_filter( 'wp_mail_content_type', array( __CLASS__, 'html_content_type' ) );

		if ( $sent ) {
			Wise_Mirror_Logger::info( 'Confirmation email sent', array( 'booking_id' => $submission['booking_id'], 'to' => $submission['email'] ) );
		} else {
			Wise_Mirror_Logger::error( 'Confirmation email failed to send', array( 'booking_id' => $submission['booking_id'], 'to' => $submission['email'] ) );
		}

		return (bool) $sent;
	}

	public static function html_content_type() {
		return 'text/html';
	}

	/**
	 * Format a stored 24-hour "HH:MM" booking time as 12-hour with AM/PM,
	 * matching how times are shown on the booking form itself.
	 *
	 * @param string $time24 e.g. "14:30".
	 * @return string e.g. "2:30 PM". Returns the input unchanged if it
	 *                doesn't look like a time (defensive — never blank the email).
	 */
	private static function format_time_12h( $time24 ) {
		if ( ! preg_match( '/^(\d{1,2}):(\d{2})$/', (string) $time24, $m ) ) {
			return (string) $time24;
		}
		$hour   = (int) $m[1];
		$minute = $m[2];
		$suffix = $hour >= 12 ? 'PM' : 'AM';
		$hour12 = $hour % 12;
		if ( 0 === $hour12 ) {
			$hour12 = 12;
		}
		return $hour12 . ':' . $minute . ' ' . $suffix;
	}

	private static function render_html( array $template, $subject, $body_text ) {
		$body_html = nl2br( esc_html( $body_text ) );

		ob_start();
		?>
		<!DOCTYPE html>
		<html>
		<head><meta charset="utf-8"><title><?php echo esc_html( $subject ); ?></title></head>
		<body style="margin:0;padding:0;background:<?php echo esc_attr( $template['background_color'] ); ?>;font-family:Arial,Helvetica,sans-serif;">
			<table role="presentation" width="100%" cellpadding="0" cellspacing="0">
				<tr>
					<td align="center" style="padding:32px 16px;">
						<table role="presentation" width="100%" style="max-width:520px;background:#ffffff;border-radius:10px;overflow:hidden;">
							<tr>
								<td style="background:<?php echo esc_attr( $template['primary_color'] ); ?>;padding:24px 32px;">
									<h1 style="margin:0;color:#ffffff;font-size:22px;"><?php echo esc_html( $template['heading'] ); ?></h1>
								</td>
							</tr>
							<tr>
								<td style="padding:32px;color:<?php echo esc_attr( $template['text_color'] ); ?>;font-size:15px;line-height:1.6;">
									<?php echo wp_kses_post( $body_html ); ?>
									<?php if ( ! empty( $template['button_text'] ) && ! empty( $template['button_url'] ) ) : ?>
										<p style="margin-top:28px;">
											<a href="<?php echo esc_url( $template['button_url'] ); ?>"
											   style="background:<?php echo esc_attr( $template['primary_color'] ); ?>;color:#ffffff;text-decoration:none;padding:12px 24px;border-radius:999px;display:inline-block;">
												<?php echo esc_html( $template['button_text'] ); ?>
											</a>
										</p>
									<?php endif; ?>
								</td>
							</tr>
							<tr>
								<td style="padding:16px 32px;background:#F5EBDD;color:#4C4C4C;font-size:12px;">
									<?php echo esc_html( $template['footer'] ); ?>
								</td>
							</tr>
						</table>
					</td>
				</tr>
			</table>
		</body>
		</html>
		<?php
		return ob_get_clean();
	}
}
