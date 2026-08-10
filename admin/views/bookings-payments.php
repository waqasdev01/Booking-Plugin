<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
$page  = isset( $_GET['paged'] ) ? max( 1, absint( $_GET['paged'] ) ) : 1; // phpcs:ignore
$limit = 25;
$rows  = Wise_Mirror_DB::get_recent_payments( $limit, ( $page - 1 ) * $limit );
?>
<div class="wise-mirror-page-header">
	<h2>Payments</h2>
	<p>Every Stripe payment attempt and its verified status.</p>
</div>

<div class="wise-mirror-panel">
	<div class="wise-mirror-table-scroll">
	<table class="widefat striped">
		<thead>
			<tr>
				<th>Booking ID</th><th>Customer</th><th>Package</th><th>Amount</th><th>Mode</th>
				<th>Status</th><th>Stripe Session</th><th>Payment Intent</th><th>Verified</th><th>Created</th>
			</tr>
		</thead>
		<tbody>
			<?php if ( empty( $rows ) ) : ?>
				<tr><td colspan="10">No payments yet.</td></tr>
			<?php endif; ?>
			<?php foreach ( $rows as $row ) : ?>
				<tr>
					<td><code><?php echo esc_html( $row['booking_id'] ); ?></code></td>
					<td><?php echo esc_html( $row['customer_name'] ); ?><br><small><?php echo esc_html( $row['customer_email'] ); ?></small></td>
					<td><?php echo esc_html( $row['package_label'] ); ?></td>
					<td><?php echo esc_html( $row['amount'] > 0 ? '$' . number_format( $row['amount'] / 100, 2 ) . ' ' . strtoupper( $row['currency'] ) : 'Free' ); ?></td>
					<td><span class="wise-mirror-badge-<?php echo esc_attr( $row['mode'] ); ?>"><?php echo esc_html( strtoupper( $row['mode'] ) ); ?></span></td>
					<td><span class="wise-mirror-status wise-mirror-status-<?php echo esc_attr( $row['payment_status'] ); ?>"><?php echo esc_html( ucfirst( $row['payment_status'] ) ); ?></span></td>
					<td><code><?php echo esc_html( $row['stripe_session_id'] ); ?></code></td>
					<td><code><?php echo esc_html( $row['stripe_payment_intent'] ); ?></code></td>
					<td><?php echo esc_html( $row['verified_at'] ?: '—' ); ?></td>
					<td><?php echo esc_html( $row['created_at'] ); ?></td>
				</tr>
			<?php endforeach; ?>
		</tbody>
	</table>
	</div>
	<p class="wise-mirror-pagination">
		<?php if ( $page > 1 ) : ?><a class="button" href="<?php echo esc_url( add_query_arg( 'paged', $page - 1 ) ); ?>">&laquo; Previous</a><?php endif; ?>
		<?php if ( count( $rows ) === $limit ) : ?><a class="button" href="<?php echo esc_url( add_query_arg( 'paged', $page + 1 ) ); ?>">Next &raquo;</a><?php endif; ?>
	</p>
</div>
