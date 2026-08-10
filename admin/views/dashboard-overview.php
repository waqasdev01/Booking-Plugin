<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$total_submissions = Wise_Mirror_DB::count_submissions();
$paid_count    = Wise_Mirror_DB::count_payments_by_status( 'paid' );
$pending_count = Wise_Mirror_DB::count_payments_by_status( 'pending' );
$failed_count  = Wise_Mirror_DB::count_payments_by_status( 'failed' );
$total_paid_cents = Wise_Mirror_DB::sum_paid_amount();
$stripe = Wise_Mirror_Settings::stripe();
$api    = Wise_Mirror_Api_Manager::get_credentials();
?>
<div class="wise-mirror-page-header">
	<h2>Overview</h2>
	<p>Everything at a glance. Head to <strong>Bookings</strong> to manage sessions and pricing, or <strong>Form Editor</strong> to change how the booking form looks.</p>
</div>

<div class="wise-mirror-cards">
	<div class="wise-mirror-card">
		<span class="wise-mirror-card-label">Total Bookings</span>
		<span class="wise-mirror-card-value"><?php echo esc_html( $total_submissions ); ?></span>
	</div>
	<div class="wise-mirror-card">
		<span class="wise-mirror-card-label">Payments Confirmed</span>
		<span class="wise-mirror-card-value"><?php echo esc_html( $paid_count ); ?></span>
	</div>
	<div class="wise-mirror-card">
		<span class="wise-mirror-card-label">Awaiting Payment</span>
		<span class="wise-mirror-card-value"><?php echo esc_html( $pending_count ); ?></span>
	</div>
	<div class="wise-mirror-card">
		<span class="wise-mirror-card-label">Failed / Cancelled</span>
		<span class="wise-mirror-card-value"><?php echo esc_html( $failed_count ); ?></span>
	</div>
	<div class="wise-mirror-card">
		<span class="wise-mirror-card-label">Total Collected</span>
		<span class="wise-mirror-card-value">$<?php echo esc_html( number_format( $total_paid_cents / 100, 2 ) ); ?></span>
	</div>
	<div class="wise-mirror-card">
		<span class="wise-mirror-card-label">Stripe Mode</span>
		<span class="wise-mirror-card-value wise-mirror-badge-<?php echo esc_attr( $stripe['mode'] ); ?>"><?php echo esc_html( strtoupper( $stripe['mode'] ) ); ?></span>
	</div>
	<div class="wise-mirror-card">
		<span class="wise-mirror-card-label">Internal API</span>
		<span class="wise-mirror-card-value wise-mirror-badge-<?php echo $api['enabled'] ? 'test' : 'live'; ?>"><?php echo $api['enabled'] ? 'ENABLED' : 'DISABLED'; ?></span>
	</div>
</div>

<div class="wise-mirror-panel">
	<h3>Quick Start</h3>
	<ol>
		<li>Add your Stripe keys under <strong>System Settings → Payments</strong> and confirm you're in Test mode while you set things up.</li>
		<li>Review your sessions under <strong>Bookings → Session Management</strong> and copy each booking link from <strong>Booking Mapping</strong> into your pricing buttons.</li>
		<li>Set working hours under <strong>Bookings → Availability</strong>.</li>
		<li>Adjust the booking form under <strong>Form Editor</strong> if needed.</li>
		<li>Place <code>[wise_booking_form]</code> on your booking page.</li>
		<li>Run a full test booking in Test mode, confirm the email arrives, then switch to Live mode when ready.</li>
	</ol>
</div>
