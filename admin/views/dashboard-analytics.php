<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$by_package = Wise_Mirror_DB::count_by_package();
$by_day     = Wise_Mirror_DB::count_by_day( 14 );
$sessions   = Wise_Mirror_Sessions::get_all();
$sessions_by_key = array();
foreach ( $sessions as $s ) {
	$sessions_by_key[ $s['key'] ] = $s['name'];
}

$max_package = 1;
foreach ( $by_package as $row ) {
	$max_package = max( $max_package, (int) $row['total'] );
}
$max_day = max( 1, ! empty( $by_day ) ? max( $by_day ) : 1 );
?>
<div class="wise-mirror-page-header">
	<h2>Analytics</h2>
	<p>Booking volume, by package and by day, computed from your actual submissions.</p>
</div>

<div class="wise-mirror-panel">
	<h3>Bookings by Package</h3>
	<?php if ( empty( $by_package ) ) : ?>
		<p class="description">No bookings yet.</p>
	<?php else : ?>
		<div class="wise-mirror-bar-chart">
			<?php foreach ( $by_package as $row ) : ?>
				<div class="wise-mirror-bar-row">
					<span class="wise-mirror-bar-label"><?php echo esc_html( $sessions_by_key[ $row['package_key'] ] ?? $row['package_key'] ); ?></span>
					<div class="wise-mirror-bar-track">
						<div class="wise-mirror-bar-fill" style="width:<?php echo esc_attr( round( ( (int) $row['total'] / $max_package ) * 100 ) ); ?>%"></div>
					</div>
					<span class="wise-mirror-bar-value"><?php echo esc_html( $row['total'] ); ?></span>
				</div>
			<?php endforeach; ?>
		</div>
	<?php endif; ?>
</div>

<div class="wise-mirror-panel">
	<h3>Bookings — Last 14 Days</h3>
	<?php if ( empty( $by_day ) ) : ?>
		<p class="description">No bookings in this window yet.</p>
	<?php else : ?>
		<div class="wise-mirror-bar-chart wise-mirror-bar-chart-vertical">
			<?php foreach ( $by_day as $day => $count ) : ?>
				<div class="wise-mirror-vbar" title="<?php echo esc_attr( $day . ': ' . $count ); ?>">
					<div class="wise-mirror-vbar-fill" style="height:<?php echo esc_attr( round( ( $count / $max_day ) * 100 ) ); ?>%"></div>
					<span><?php echo esc_html( gmdate( 'M j', strtotime( $day ) ) ); ?></span>
				</div>
			<?php endforeach; ?>
		</div>
	<?php endif; ?>
</div>
