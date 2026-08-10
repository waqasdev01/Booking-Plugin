<?php
/**
 * Core plugin bootstrap / singleton.
 *
 * @package Wise_Mirror_Booking
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Wise_Mirror_Plugin {

	private static $instance = null;

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		Wise_Mirror_Shortcode::init();
		Wise_Mirror_Ajax::init();
		Wise_Mirror_Rest_Api::init();
		Wise_Mirror_Email::init();

		if ( is_admin() ) {
			Wise_Mirror_Admin::init();
		}

		add_action( 'wp_enqueue_scripts', array( $this, 'maybe_enqueue_frontend_assets' ) );
	}

	/**
	 * Only enqueue the base fallback assets when the shortcode is present
	 * on the page (form CSS/JS itself is inlined via the shortcode so it
	 * stays fully editable from the dashboard without a cache-busting file).
	 */
	public function maybe_enqueue_frontend_assets() {
		global $post;
		if ( is_a( $post, 'WP_Post' ) && has_shortcode( $post->post_content, 'wise_booking_form' ) ) {
			wp_enqueue_style( 'wise-mirror-frontend-base', WISE_MIRROR_URL . 'assets/frontend.css', array(), WISE_MIRROR_VERSION );
		}
	}
}
