<?php
/**
 * [wise_booking_form] shortcode — the ONE booking form the plugin provides.
 *
 * @package Wise_Mirror_Booking
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Wise_Mirror_Shortcode {

	public static function init() {
		add_shortcode( 'wise_booking_form', array( __CLASS__, 'render' ) );
	}

	public static function render( $atts = array() ) {
		$html = get_option( 'wise_mirror_form_html', Wise_Mirror_Settings::default_form_html() );
		$css  = get_option( 'wise_mirror_form_css', Wise_Mirror_Settings::default_form_css() );
		$js   = get_option( 'wise_mirror_form_js', Wise_Mirror_Settings::default_form_js() );

		$sessions = Wise_Mirror_Cache::remember( 'active_sessions', function () {
			return Wise_Mirror_Sessions::get_active();
		} );

		$general        = Wise_Mirror_Settings::general();
		$schedule       = Wise_Mirror_Settings::schedule_settings();
		$uploads        = Wise_Mirror_Settings::upload_settings();
		$includes_items = array_filter( array_map( 'trim', explode( "\n", $general['includes_items'] ?? '' ) ) );

		// Keyed by session key for quick lookup on the frontend.
		$sessions_by_key = array();
		foreach ( $sessions as $s ) {
			$sessions_by_key[ $s['key'] ] = $s;
		}

		ob_start();
		?>
		<style id="wise-mirror-form-css"><?php echo $css; /* phpcs:ignore -- admin-controlled, capability gated on save */ ?></style>

		<?php echo $html; /* phpcs:ignore -- admin-controlled markup by design (Form Editor requirement) */ ?>

		<script>
			window.WiseMirrorBooking = window.WiseMirrorBooking || {};
			window.WiseMirrorBooking.ajaxUrl   = <?php echo wp_json_encode( admin_url( 'admin-ajax.php' ) ); ?>;
			window.WiseMirrorBooking.restUrl   = <?php echo wp_json_encode( esc_url_raw( rest_url( 'wise/v1/' ) ) ); ?>;
			window.WiseMirrorBooking.nonce     = <?php echo wp_json_encode( wp_create_nonce( 'wise_booking_submit' ) ); ?>;
			window.WiseMirrorBooking.restNonce = <?php echo wp_json_encode( wp_create_nonce( 'wp_rest' ) ); ?>;
			window.WiseMirrorBooking.sessions  = <?php echo wp_json_encode( $sessions_by_key ); ?>;
			window.WiseMirrorBooking.sessionsOrder = <?php echo wp_json_encode( array_keys( $sessions_by_key ) ); ?>;
			window.WiseMirrorBooking.includesItems = <?php echo wp_json_encode( array_values( $includes_items ) ); ?>;
			window.WiseMirrorBooking.supportEmail  = <?php echo wp_json_encode( $general['support_email'] ?? '' ); ?>;
			window.WiseMirrorBooking.scheduleAdvanceDays = <?php echo wp_json_encode( (int) $schedule['advance_days'] ); ?>;
			window.WiseMirrorBooking.uploadMaxSizeMb = <?php echo wp_json_encode( (int) $uploads['max_size_mb'] ); ?>;
			window.WiseMirrorBooking.uploadMaxImages = <?php echo wp_json_encode( (int) $uploads['max_images_per_field'] ); ?>;
			window.WiseMirrorBooking.uploadAllowedTypes = <?php echo wp_json_encode( $uploads['allowed_types'] ); ?>;
			window.WiseMirrorBooking.examplePhotos = {
				photo_smiling: <?php echo wp_json_encode( $uploads['example_smiling'] ?? '' ); ?>,
				photo_unsmiling: <?php echo wp_json_encode( $uploads['example_unsmiling'] ?? '' ); ?>,
				photo_profile: <?php echo wp_json_encode( $uploads['example_profile'] ?? '' ); ?>
			};
		</script>
		<script id="wise-mirror-form-js"><?php echo $js; /* phpcs:ignore -- admin-controlled, capability gated on save */ ?></script>
		<?php
		return ob_get_clean();
	}
}
