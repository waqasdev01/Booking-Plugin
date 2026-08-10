<?php
/**
 * Handles plugin activation: table creation + default options + upgrades.
 *
 * @package Wise_Mirror_Booking
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Wise_Mirror_Activator {

	/**
	 * Run activation tasks. Also re-run automatically on every version
	 * bump (see wise_mirror_maybe_upgrade() in the main plugin file) so
	 * existing installs get new tables/columns/settings without a manual
	 * deactivate/reactivate.
	 */
	public static function activate() {
		self::create_tables();
		self::seed_default_options();
		Wise_Mirror_Sessions::seed_defaults_or_migrate();
		self::maybe_refresh_stale_form_assets();
		self::maybe_add_booking_datetime_tokens();
		self::maybe_add_contact_detail_tokens();
		Wise_Mirror_Api_Manager::get_credentials(); // Lazily generates key/secret if missing.
		update_option( 'wise_mirror_db_version', WISE_MIRROR_VERSION );
		flush_rewrite_rules();
	}

	/**
	 * seed_default_options() only writes most form options if they don't
	 * exist yet — so a site that already had an earlier version installed
	 * keeps its saved copy forever after an upgrade (by design, so we
	 * never clobber a client's manual HTML/CSS/JS edits). The problem:
	 * that also means genuinely new markup never reaches sites that
	 * installed before it was added, even after upgrading plugin files.
	 *
	 * Fix: detect the OLD template specifically (missing the marker that
	 * only exists in the current markup) and refresh just that case. If
	 * the admin has since made their own edits that already reference the
	 * new marker, this correctly leaves them alone.
	 */
	private static function maybe_refresh_stale_form_assets() {
		$html = get_option( 'wise_mirror_form_html' );

		// Each release that changes the default markup adds its own marker
		// here — missing ANY of these means the site is running an older
		// template and should be refreshed. Once a site is current, none
		// of these checks fire again, so manual Form Editor customizations
		// made after that point are left alone.
		$current_markers = array( 'wise-wizard', 'wise-field-error' );

		if ( is_string( $html ) && '' !== $html ) {
			foreach ( $current_markers as $marker ) {
				if ( false === strpos( $html, $marker ) ) {
					update_option( 'wise_mirror_form_html', Wise_Mirror_Settings::default_form_html() );
					update_option( 'wise_mirror_form_css', Wise_Mirror_Settings::default_form_css() );
					update_option( 'wise_mirror_form_js', Wise_Mirror_Settings::default_form_js() );
					Wise_Mirror_Logger::info( 'Form HTML/CSS/JS auto-upgraded to the current template.', array( 'missing_marker' => $marker ) );
					break;
				}
			}
		}
	}

	/**
	 * Adds the {booking_date} / {booking_time} lines to an existing site's
	 * saved confirmation email body — but only when that body still
	 * contains the exact "Booking Reference: {booking_id}" line from the
	 * original default template, so a genuinely rewritten email isn't
	 * touched. If the admin has customized their copy beyond that, they
	 * add the two tokens themselves from System Settings → Email.
	 */
	private static function maybe_add_booking_datetime_tokens() {
		$template = get_option( 'wise_mirror_email_template' );

		if ( ! is_array( $template ) || empty( $template['body'] ) ) {
			return;
		}

		if ( false !== strpos( $template['body'], '{booking_date}' ) ) {
			return; // Already has it.
		}

		$anchor = 'Booking Reference: {booking_id}';
		if ( false === strpos( $template['body'], $anchor ) ) {
			return; // Body has been substantially customized — leave it alone.
		}

		$template['body'] = str_replace(
			$anchor,
			$anchor . "\nBooking Date: {booking_date}\nSession Time: {booking_time}",
			$template['body']
		);

		update_option( 'wise_mirror_email_template', $template );
		Wise_Mirror_Logger::info( 'Confirmation email template auto-updated to include booking date/time.' );
	}

	/**
	 * Adds the {phone} / {contact_method} details block to an existing
	 * site's saved confirmation email — same safe-anchor approach as
	 * maybe_add_booking_datetime_tokens(): only touches the body if it
	 * still contains the exact "Session Time: {booking_time}" line that
	 * migration added, so a customized email isn't touched.
	 */
	private static function maybe_add_contact_detail_tokens() {
		$template = get_option( 'wise_mirror_email_template' );

		if ( ! is_array( $template ) || empty( $template['body'] ) ) {
			return;
		}

		if ( false !== strpos( $template['body'], '{phone}' ) ) {
			return; // Already has it.
		}

		$anchor = 'Session Time: {booking_time}';
		if ( false === strpos( $template['body'], $anchor ) ) {
			return; // Body has been substantially customized — leave it alone.
		}

		$template['body'] = str_replace(
			$anchor,
			$anchor . "\n\nYour Details:\nEmail: {email}\nPhone: {phone}\nPreferred Contact Method: {contact_method}",
			$template['body']
		);

		update_option( 'wise_mirror_email_template', $template );
		Wise_Mirror_Logger::info( 'Confirmation email template auto-updated to include contact details.' );
	}

	/**
	 * Create the custom database tables:
	 * 1. wp_wise_submissions – every booking form submission (the "email submissions" table).
	 * 2. wp_wise_payments    – every Stripe payment attempt/result tied to a submission.
	 */
	private static function create_tables() {
		global $wpdb;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$charset_collate = $wpdb->get_charset_collate();

		$submissions_table = $wpdb->prefix . 'wise_submissions';
		$payments_table    = $wpdb->prefix . 'wise_payments';

		$sql_submissions = "CREATE TABLE {$submissions_table} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			booking_id VARCHAR(64) NOT NULL,
			package_key VARCHAR(64) DEFAULT '' NOT NULL,
			full_name VARCHAR(191) DEFAULT '' NOT NULL,
			email VARCHAR(191) DEFAULT '' NOT NULL,
			phone VARCHAR(64) DEFAULT '' NOT NULL,
			contact_method VARCHAR(20) DEFAULT '' NOT NULL,
			birth_date VARCHAR(20) DEFAULT '' NOT NULL,
			booking_date VARCHAR(10) DEFAULT '' NOT NULL,
			booking_time VARCHAR(5) DEFAULT '' NOT NULL,
			concerns TEXT NULL,
			concern_categories TEXT NULL,
			photo_smiling TEXT NULL,
			photo_unsmiling TEXT NULL,
			photo_profile TEXT NULL,
			status VARCHAR(20) DEFAULT 'pending' NOT NULL,
			ip_address VARCHAR(64) DEFAULT '' NOT NULL,
			created_at DATETIME NOT NULL,
			updated_at DATETIME NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY booking_id (booking_id),
			KEY email (email),
			KEY status (status),
			KEY booking_date (booking_date)
		) {$charset_collate};";

		$sql_payments = "CREATE TABLE {$payments_table} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			booking_id VARCHAR(64) NOT NULL,
			customer_name VARCHAR(191) DEFAULT '' NOT NULL,
			customer_email VARCHAR(191) DEFAULT '' NOT NULL,
			package_key VARCHAR(64) DEFAULT '' NOT NULL,
			package_label VARCHAR(191) DEFAULT '' NOT NULL,
			amount BIGINT DEFAULT 0 NOT NULL,
			currency VARCHAR(10) DEFAULT 'usd' NOT NULL,
			mode VARCHAR(10) DEFAULT 'test' NOT NULL,
			stripe_session_id VARCHAR(191) DEFAULT '' NOT NULL,
			stripe_payment_intent VARCHAR(191) DEFAULT '' NOT NULL,
			payment_status VARCHAR(20) DEFAULT 'pending' NOT NULL,
			verified_at DATETIME NULL,
			created_at DATETIME NOT NULL,
			updated_at DATETIME NOT NULL,
			PRIMARY KEY  (id),
			KEY booking_id (booking_id),
			KEY stripe_session_id (stripe_session_id),
			KEY payment_status (payment_status)
		) {$charset_collate};";

		dbDelta( $sql_submissions );
		dbDelta( $sql_payments );
	}

	/**
	 * Seed sensible defaults so the plugin works out of the box in test mode.
	 */
	private static function seed_default_options() {
		if ( false === get_option( 'wise_mirror_general_settings' ) ) {
			update_option( 'wise_mirror_general_settings', Wise_Mirror_Settings::general() );
		}

		if ( false === get_option( 'wise_mirror_stripe_settings' ) ) {
			update_option(
				'wise_mirror_stripe_settings',
				array(
					'mode'                 => 'test',
					'test_publishable_key' => '',
					'test_secret_key'      => '',
					'live_publishable_key' => '',
					'live_secret_key'      => '',
					'webhook_secret_test'  => '',
					'webhook_secret_live'  => '',
				)
			);
		}

		if ( false === get_option( 'wise_mirror_email_settings' ) ) {
			update_option( 'wise_mirror_email_settings', Wise_Mirror_Settings::email_settings() );
		}

		if ( false === get_option( 'wise_mirror_email_template' ) ) {
			update_option(
				'wise_mirror_email_template',
				array(
					'subject'     => 'Your Wise Mirror Booking is Confirmed',
					'heading'     => 'Booking Confirmed',
					'body'        => "Hi {full_name},\n\nThank you for booking your {package_label} with The Wise Mirror. Your payment has been received and your session is confirmed.\n\nBooking Reference: {booking_id}\nBooking Date: {booking_date}\nSession Time: {booking_time}\n\nYour Details:\nEmail: {email}\nPhone: {phone}\nPreferred Contact Method: {contact_method}\n\nWe'll be in touch shortly with next steps.",
					'footer'      => 'The Wise Mirror',
					'button_text' => 'Visit TheWiseMirror.com',
					'button_url'  => 'https://thewisemirror.com',
					'primary_color'   => '#5FA9B5',
					'background_color' => '#F5EBDD',
					'text_color'  => '#0F0F0F',
				)
			);
		}

		if ( false === get_option( 'wise_mirror_form_html' ) ) {
			update_option( 'wise_mirror_form_html', Wise_Mirror_Settings::default_form_html() );
		}

		if ( false === get_option( 'wise_mirror_form_css' ) ) {
			update_option( 'wise_mirror_form_css', Wise_Mirror_Settings::default_form_css() );
		}

		if ( false === get_option( 'wise_mirror_form_js' ) ) {
			update_option( 'wise_mirror_form_js', Wise_Mirror_Settings::default_form_js() );
		}

		if ( false === get_option( 'wise_mirror_upload_settings' ) ) {
			update_option( 'wise_mirror_upload_settings', Wise_Mirror_Settings::upload_settings() );
		}

		if ( false === get_option( 'wise_mirror_schedule_settings' ) ) {
			update_option( 'wise_mirror_schedule_settings', Wise_Mirror_Settings::default_schedule_settings() );
		}

		if ( false === get_option( 'wise_mirror_ai_settings' ) ) {
			update_option( 'wise_mirror_ai_settings', Wise_Mirror_Settings::ai_settings() );
		}

		if ( false === get_option( 'wise_mirror_system_settings' ) ) {
			update_option( 'wise_mirror_system_settings', Wise_Mirror_Settings::system_settings() );
		}

		if ( false === get_option( 'wise_mirror_webhooks' ) ) {
			update_option( 'wise_mirror_webhooks', array() );
		}
	}
}
