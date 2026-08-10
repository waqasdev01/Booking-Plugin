<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
$general  = Wise_Mirror_Settings::general();
$sessions = Wise_Mirror_Sessions::get_all();
$booking_page = $general['booking_page_url'] ?: home_url( '/booking/' );
?>
<div class="wise-mirror-page-header">
	<h2>Booking Mapping</h2>
	<p>Where the booking form lives, and the unique link generated for each session — point your pricing buttons at these.</p>
</div>

<div class="wise-mirror-panel">
	<form method="post">
		<?php wp_nonce_field( 'wise_mirror_save_booking_mapping', 'wise_mirror_nonce' ); ?>
		<input type="hidden" name="wise_mirror_action" value="booking_mapping">

		<table class="form-table">
			<tr>
				<th><label for="wise-booking-page-url">Booking Page URL</label></th>
				<td>
					<input type="url" id="wise-booking-page-url" name="general[booking_page_url]" class="regular-text"
						value="<?php echo esc_attr( $general['booking_page_url'] ); ?>" placeholder="https://thewisemirror.com/booking/">
					<p class="description">The page where you placed <code>[wise_booking_form]</code>. Stripe redirects customers back here after payment.</p>
				</td>
			</tr>
			<tr>
				<th><label for="wise-currency">Default Currency</label></th>
				<td><input type="text" id="wise-currency" name="general[currency]" class="small-text" value="<?php echo esc_attr( $general['currency'] ); ?>" maxlength="3"></td>
			</tr>
			<tr>
				<th><label for="wise-support-email">Support Email</label></th>
				<td>
					<input type="email" id="wise-support-email" name="general[support_email]" class="regular-text" value="<?php echo esc_attr( $general['support_email'] ); ?>" placeholder="contact@thewisemirror.com">
					<p class="description">Shown on the booking form's payment panel. Leave blank to hide that line.</p>
				</td>
			</tr>
			<tr>
				<th><label for="wise-includes-items">"What's Included" List</label></th>
				<td>
					<textarea id="wise-includes-items" name="general[includes_items]" rows="4" class="large-text"><?php echo esc_textarea( $general['includes_items'] ); ?></textarea>
					<p class="description">One item per line — shown as a checklist on the booking form sidebar.</p>
				</td>
			</tr>
		</table>

		<?php submit_button( 'Save' ); ?>
	</form>
</div>

<div class="wise-mirror-panel">
	<h3>Booking Links</h3>
	<div class="wise-mirror-table-scroll">
	<table class="widefat wise-mirror-pricing-table">
		<thead><tr><th>Session</th><th>Status</th><th>Booking Link</th></tr></thead>
		<tbody>
			<?php if ( empty( $sessions ) ) : ?>
				<tr><td colspan="3">No sessions yet — add one under <strong>Session Management</strong>.</td></tr>
			<?php endif; ?>
			<?php foreach ( $sessions as $s ) : $link = add_query_arg( 'package', $s['key'], $booking_page ); ?>
				<tr>
					<td><?php echo esc_html( $s['name'] ); ?></td>
					<td><span class="wise-mirror-status wise-mirror-status-<?php echo 'active' === $s['status'] ? 'paid' : 'failed'; ?>"><?php echo esc_html( ucfirst( $s['status'] ) ); ?></span></td>
					<td>
						<code class="wise-mirror-code-block"><?php echo esc_html( $link ); ?></code>
						<button type="button" class="button button-small wise-copy-btn" data-copy="<?php echo esc_attr( $link ); ?>">Copy</button>
					</td>
				</tr>
			<?php endforeach; ?>
		</tbody>
	</table>
	</div>
</div>
