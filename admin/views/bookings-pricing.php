<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
$sessions = Wise_Mirror_Sessions::get_all();
$general  = Wise_Mirror_Settings::general();
?>
<div class="wise-mirror-page-header">
	<h2>Pricing</h2>
	<p>A quick reference of what each session costs. To change a price, edit the session itself under <strong>Session Management</strong> — pricing lives with the session so it never gets out of sync.</p>
</div>

<div class="wise-mirror-panel">
	<div class="wise-mirror-table-scroll">
	<table class="widefat striped">
		<thead><tr><th>Session</th><th>Price</th><th>Currency</th><th>Status</th></tr></thead>
		<tbody>
			<?php if ( empty( $sessions ) ) : ?>
				<tr><td colspan="4">No sessions yet.</td></tr>
			<?php endif; ?>
			<?php foreach ( $sessions as $s ) : ?>
				<tr>
					<td><?php echo esc_html( $s['name'] ); ?></td>
					<td><?php echo esc_html( $s['price'] > 0 ? '$' . number_format( $s['price'] / 100, 2 ) : 'Free' ); ?></td>
					<td><?php echo esc_html( strtoupper( $s['currency'] ) ); ?></td>
					<td><span class="wise-mirror-status wise-mirror-status-<?php echo 'active' === $s['status'] ? 'paid' : 'failed'; ?>"><?php echo esc_html( ucfirst( $s['status'] ) ); ?></span></td>
				</tr>
			<?php endforeach; ?>
		</tbody>
	</table>
	</div>
	<p class="description" style="margin-top:16px;">Site default currency: <strong><?php echo esc_html( strtoupper( $general['currency'] ) ); ?></strong> (set under <a href="<?php echo esc_url( admin_url( 'admin.php?page=wise-mirror-booking&tab=bookings-mapping' ) ); ?>">Booking Mapping</a>).</p>
</div>
