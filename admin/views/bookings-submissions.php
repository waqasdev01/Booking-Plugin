<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
$page   = isset( $_GET['paged'] ) ? max( 1, absint( $_GET['paged'] ) ) : 1; // phpcs:ignore
$limit  = 25;
$rows   = Wise_Mirror_DB::get_recent_submissions( $limit, ( $page - 1 ) * $limit );
$sessions = Wise_Mirror_Sessions::get_all();
$sessions_by_key = array();
foreach ( $sessions as $s ) { $sessions_by_key[ $s['key'] ] = $s['name']; }
?>
<div class="wise-mirror-page-header">
	<h2>Submissions</h2>
	<p>Every booking form submission, most recent first.</p>
</div>

<div class="wise-mirror-panel">
	<div class="wise-mirror-table-scroll">
	<table class="widefat striped">
		<thead>
			<tr>
				<th>Booking ID</th><th>Name</th><th>Email</th><th>Package</th>
				<th>Booking Date/Time</th><th>Birth Date</th><th>Status</th><th>Photos</th><th>Submitted</th>
			</tr>
		</thead>
		<tbody>
			<?php if ( empty( $rows ) ) : ?>
				<tr><td colspan="9">No submissions yet.</td></tr>
			<?php endif; ?>
			<?php foreach ( $rows as $row ) : ?>
				<tr>
					<td><code><?php echo esc_html( $row['booking_id'] ); ?></code></td>
					<td><?php echo esc_html( $row['full_name'] ); ?></td>
					<td><?php echo esc_html( $row['email'] ); ?><?php if ( $row['phone'] ) : ?><br><small><?php echo esc_html( $row['phone'] ); ?></small><?php endif; ?><?php if ( ! empty( $row['contact_method'] ) ) : ?><br><small>Prefers: <?php echo esc_html( $row['contact_method'] ); ?></small><?php endif; ?></td>
					<td><?php echo esc_html( $sessions_by_key[ $row['package_key'] ] ?? $row['package_key'] ); ?></td>
					<td><?php echo esc_html( $row['booking_date'] ); ?><?php if ( $row['booking_time'] ) : ?><br><small><?php echo esc_html( $row['booking_time'] ); ?></small><?php endif; ?></td>
					<td><?php echo esc_html( $row['birth_date'] ); ?></td>
					<td><span class="wise-mirror-status wise-mirror-status-<?php echo esc_attr( $row['status'] ); ?>"><?php echo esc_html( ucwords( str_replace( '_', ' ', $row['status'] ) ) ); ?></span></td>
					<td>
						<?php
						foreach ( array( 'photo_smiling', 'photo_unsmiling', 'photo_profile' ) as $f ) :
							$urls = json_decode( (string) $row[ $f ], true );
							if ( ! is_array( $urls ) ) { $urls = $row[ $f ] ? array( $row[ $f ] ) : array(); }
							foreach ( $urls as $url ) :
								if ( ! $url ) { continue; }
								?>
								<a href="<?php echo esc_url( $url ); ?>" target="_blank" rel="noopener">view</a>&nbsp;
								<?php
							endforeach;
						endforeach;
						?>
					</td>
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
