<?php
/**
 * Plugin Name: Wise Mirror Booking
 * Plugin URI:  https://thewisemirror.com
 * Description: Dynamic booking + Stripe payment plugin built for TheWiseMirror.com. Manages the booking form, pricing-to-booking mapping, Stripe Checkout, payment verification, confirmation emails, and submissions — all editable from the WordPress admin dashboard.
 * Version:     1.3.4
 * Author:      Waqas
 * Text Domain: wise-mirror-booking
 * Requires PHP: 7.4
 *
 * @package Wise_Mirror_Booking
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // No direct access.
}

define( 'WISE_MIRROR_VERSION', '1.3.4' );
define( 'WISE_MIRROR_FILE', __FILE__ );
define( 'WISE_MIRROR_DIR', plugin_dir_path( __FILE__ ) );
define( 'WISE_MIRROR_URL', plugin_dir_url( __FILE__ ) );
define( 'WISE_MIRROR_BASENAME', plugin_basename( __FILE__ ) );

/**
 * Autoload plugin classes on demand.
 *
 * @param string $class_name Class being requested.
 */
function wise_mirror_autoload( $class_name ) {
	if ( strpos( $class_name, 'Wise_Mirror_' ) !== 0 ) {
		return;
	}

	$slug = strtolower( str_replace( '_', '-', $class_name ) );
	$path = WISE_MIRROR_DIR . 'includes/class-' . $slug . '.php';

	if ( file_exists( $path ) ) {
		require_once $path;
	}
}
spl_autoload_register( 'wise_mirror_autoload' );

/**
 * Activation: create custom tables and seed default options.
 */
function wise_mirror_activate() {
	require_once WISE_MIRROR_DIR . 'includes/class-wise-mirror-activator.php';
	Wise_Mirror_Activator::activate();
}
register_activation_hook( __FILE__, 'wise_mirror_activate' );

/**
 * Deactivation: keep data, just flush rewrite rules.
 */
function wise_mirror_deactivate() {
	flush_rewrite_rules();
}
register_deactivation_hook( __FILE__, 'wise_mirror_deactivate' );

/**
 * Boot the plugin once all plugins are loaded.
 */
function wise_mirror_run() {
	Wise_Mirror_Plugin::instance();
}
add_action( 'plugins_loaded', 'wise_mirror_run' );

/**
 * Catch upgrades on existing installs (no deactivate/reactivate needed) —
 * dbDelta() is safe to re-run and only adds what's missing.
 */
function wise_mirror_maybe_upgrade() {
	if ( get_option( 'wise_mirror_db_version' ) !== WISE_MIRROR_VERSION ) {
		require_once WISE_MIRROR_DIR . 'includes/class-wise-mirror-activator.php';
		Wise_Mirror_Activator::activate();
	}
}
add_action( 'plugins_loaded', 'wise_mirror_maybe_upgrade', 5 );
