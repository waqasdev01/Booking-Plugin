<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
$schedule = Wise_Mirror_Settings::schedule_settings();
$day_labels = array(
	'mon' => 'Monday', 'tue' => 'Tuesday', 'wed' => 'Wednesday', 'thu' => 'Thursday',
	'fri' => 'Friday', 'sat' => 'Saturday', 'sun' => 'Sunday',
);
?>
<div class="wise-mirror-page-header">
	<h2>Availability</h2>
	<p>Controls the date/time picker on the booking form. Customers can only pick a day you've enabled, within the hours you set, and can't double-book a slot someone else already holds.</p>
</div>

<div class="wise-mirror-panel">
	<form method="post">
		<?php wp_nonce_field( 'wise_mirror_save_schedule_settings', 'wise_mirror_nonce' ); ?>
		<input type="hidden" name="wise_mirror_action" value="schedule_settings">

		<div class="wise-mirror-table-scroll">
		<table class="widefat wise-mirror-schedule-table">
			<thead><tr><th>Day</th><th>Open</th><th>Opens At</th><th>Closes At</th></tr></thead>
			<tbody>
				<?php foreach ( $day_labels as $key => $label ) : $d = $schedule['days'][ $key ]; ?>
					<tr>
						<td><?php echo esc_html( $label ); ?></td>
						<td><input type="checkbox" name="schedule[days][<?php echo esc_attr( $key ); ?>][enabled]" value="1" <?php checked( $d['enabled'] ); ?>></td>
						<td><input type="time" name="schedule[days][<?php echo esc_attr( $key ); ?>][open]" value="<?php echo esc_attr( $d['open'] ); ?>"></td>
						<td><input type="time" name="schedule[days][<?php echo esc_attr( $key ); ?>][close]" value="<?php echo esc_attr( $d['close'] ); ?>"></td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
		</div>

		<table class="form-table">
			<tr>
				<th><label for="wise-slot-duration">Default Session Length</label></th>
				<td><input type="number" id="wise-slot-duration" min="5" step="5" name="schedule[slot_duration_minutes]" class="small-text" value="<?php echo esc_attr( $schedule['slot_duration_minutes'] ); ?>"> minutes
					<p class="description">Used to space out time slots. Individual sessions can still show their own duration on the booking card.</p>
				</td>
			</tr>
			<tr>
				<th><label for="wise-buffer-hours">Minimum Notice</label></th>
				<td><input type="number" id="wise-buffer-hours" min="0" name="schedule[buffer_hours]" class="small-text" value="<?php echo esc_attr( $schedule['buffer_hours'] ); ?>"> hours</td>
			</tr>
			<tr>
				<th><label for="wise-advance-days">Booking Window</label></th>
				<td><input type="number" id="wise-advance-days" min="1" name="schedule[advance_days]" class="small-text" value="<?php echo esc_attr( $schedule['advance_days'] ); ?>"> days ahead</td>
			</tr>
			<tr>
				<th><label for="wise-blocked-dates">Blocked Dates</label></th>
				<td>
					<textarea id="wise-blocked-dates" name="schedule[blocked_dates]" rows="5" class="regular-text" placeholder="2026-12-25&#10;2026-01-01"><?php echo esc_textarea( implode( "\n", $schedule['blocked_dates'] ) ); ?></textarea>
					<p class="description">One date per line, format <code>YYYY-MM-DD</code>.</p>
				</td>
			</tr>
		</table>

		<?php submit_button( 'Save Availability' ); ?>
	</form>
</div>
