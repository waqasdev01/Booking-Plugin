<?php
/**
 * Fires only when the plugin is deleted from wp-admin (not on deactivate).
 * Removes plugin options and, optionally, the custom tables.
 *
 * @package Wise_Mirror_Booking
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

$options = array(
	'wise_mirror_general_settings',
	'wise_mirror_pricing_map',
	'wise_mirror_stripe_settings',
	'wise_mirror_email_settings',
	'wise_mirror_email_template',
	'wise_mirror_form_html',
	'wise_mirror_form_css',
	'wise_mirror_form_js',
	'wise_mirror_upload_settings',
	'wise_mirror_logs',
	'wise_mirror_db_version',
	'wise_mirror_schedule_settings',
	'wise_mirror_sessions',
	'wise_mirror_ai_settings',
	'wise_mirror_system_settings',
	'wise_mirror_webhooks',
	'wise_mirror_api_credentials',
);

foreach ( $options as $option ) {
	delete_option( $option );
}

global $wpdb;
$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '\\_transient\\_wise\\_mirror\\_cache\\_%' OR option_name LIKE '\\_transient\\_timeout\\_wise\\_mirror\\_cache\\_%'" ); // phpcs:ignore

// Booking + payment records are kept by default even on uninstall, since
// they're business records (client bookings, payment history). Uncomment
// below to also drop the custom tables when the plugin is deleted.
//
// global $wpdb;
// $wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}wise_submissions" );
// $wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}wise_payments" );
