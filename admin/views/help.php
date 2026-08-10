<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="wise-mirror-page-header">
	<h2>Help &amp; Documentation</h2>
	<p>Quick reference for how the plugin fits together.</p>
</div>

<div class="wise-mirror-panel">
	<h3>Getting Started</h3>
	<ol>
		<li>Set up your sessions under <strong>Bookings → Session Management</strong>.</li>
		<li>Copy each session's booking link from <strong>Bookings → Booking Mapping</strong> into your site's pricing buttons.</li>
		<li>Set your working hours under <strong>Bookings → Availability</strong>.</li>
		<li>Add Stripe keys under <strong>System Settings → Payments</strong>, starting in Test mode.</li>
		<li>Place <code>[wise_booking_form]</code> on your booking page.</li>
		<li>Run a full test booking, confirm the email arrives, then switch to Live mode.</li>
	</ol>
</div>

<div class="wise-mirror-panel">
	<h3>How Payment Verification Works</h3>
	<p>The booking form never shows "Payment Successful" based on anything the browser reports. After Stripe redirects the customer back, the plugin calls Stripe's API directly to confirm the payment status, and Stripe's webhook provides a second, independent confirmation. Only then is the booking marked confirmed and the email sent.</p>
</div>

<div class="wise-mirror-panel">
	<h3>Where Things Live</h3>
	<ul class="wise-mirror-link-list">
		<li><strong>Form Editor</strong> — the booking form's HTML, CSS, JavaScript, and a live preview, all in one place.</li>
		<li><strong>Bookings</strong> — sessions, pricing, availability, booking links, and the raw submission/payment records.</li>
		<li><strong>AI Configuration</strong> — connect an external AI provider for future features and the internal API's <code>/ai/generate</code> endpoint.</li>
		<li><strong>API Manager</strong> — credentials and documentation for the plugin's internal REST API, plus outgoing webhooks.</li>
		<li><strong>System Settings</strong> — payments, email, notifications, debug, cache, logs, security, and performance, all on one page.</li>
		<li><strong>Tools</strong> — export/import and a full settings reset.</li>
	</ul>
</div>

<div class="wise-mirror-panel">
	<h3>Support</h3>
	<p>For anything not covered here, contact your developer with the relevant Booking ID from <strong>Bookings → Submissions</strong> — that's the fastest way to look up what happened with a specific customer.</p>
</div>
