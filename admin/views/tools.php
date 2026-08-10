<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( isset( $_GET['wise_export'] ) && current_user_can( 'manage_options' ) ) { // phpcs:ignore
	$what = sanitize_key( wp_unslash( $_GET['wise_export'] ) ); // phpcs:ignore
	nocache_headers();
	if ( 'sessions' === $what ) {
		header( 'Content-Type: application/json' );
		header( 'Content-Disposition: attachment; filename="wise-mirror-sessions.json"' );
		echo wp_json_encode( Wise_Mirror_Sessions::get_all(), JSON_PRETTY_PRINT ); // phpcs:ignore
		exit;
	} elseif ( 'submissions' === $what ) {
		global $wpdb;
		$rows = $wpdb->get_results( 'SELECT * FROM ' . Wise_Mirror_DB::submissions_table(), ARRAY_A ); // phpcs:ignore
		header( 'Content-Type: text/csv' );
		header( 'Content-Disposition: attachment; filename="wise-mirror-submissions.csv"' );
		if ( ! empty( $rows ) ) {
			$out = fopen( 'php://output', 'w' );
			fputcsv( $out, array_keys( $rows[0] ) );
			foreach ( $rows as $r ) {
				fputcsv( $out, $r );
			}
			fclose( $out );
		}
		exit;
	} elseif ( 'payments' === $what ) {
		global $wpdb;
		$rows = $wpdb->get_results( 'SELECT * FROM ' . Wise_Mirror_DB::payments_table(), ARRAY_A ); // phpcs:ignore
		header( 'Content-Type: text/csv' );
		header( 'Content-Disposition: attachment; filename="wise-mirror-payments.csv"' );
		if ( ! empty( $rows ) ) {
			$out = fopen( 'php://output', 'w' );
			fputcsv( $out, array_keys( $rows[0] ) );
			foreach ( $rows as $r ) {
				fputcsv( $out, $r );
			}
			fclose( $out );
		}
		exit;
	}
}

if ( isset( $_POST['wise_import_sessions'] ) && check_admin_referer( 'wise_mirror_import_sessions' ) && ! empty( $_FILES['sessions_file']['tmp_name'] ) ) {
	$json = file_get_contents( $_FILES['sessions_file']['tmp_name'] ); // phpcs:ignore
	$data = json_decode( $json, true );
	if ( is_array( $data ) ) {
		Wise_Mirror_Sessions::save_all( $data );
		echo '<div class="notice notice-success"><p>Sessions imported.</p></div>';
	} else {
		echo '<div class="notice notice-error"><p>Could not read that file — expected the JSON exported from this page.</p></div>';
	}
}
?>
<div class="wise-mirror-page-header">
	<h2>Tools</h2>
	<p>Export your data, import sessions from a backup, or reset the plugin's settings.</p>
</div>

<div class="wise-mirror-panel">
	<h3>Export</h3>
	<p><a class="button" href="<?php echo esc_url( add_query_arg( 'wise_export', 'sessions' ) ); ?>">Export Sessions (JSON)</a>
	<a class="button" href="<?php echo esc_url( add_query_arg( 'wise_export', 'submissions' ) ); ?>">Export Submissions (CSV)</a>
	<a class="button" href="<?php echo esc_url( add_query_arg( 'wise_export', 'payments' ) ); ?>">Export Payments (CSV)</a></p>
</div>

<div class="wise-mirror-panel">
	<h3>Import</h3>
	<form method="post" enctype="multipart/form-data">
		<?php wp_nonce_field( 'wise_mirror_import_sessions' ); ?>
		<input type="file" name="sessions_file" accept="application/json" required>
		<button type="submit" name="wise_import_sessions" value="1" class="button">Import Sessions</button>
		<p class="description">Upload a sessions JSON file previously exported from this page. This replaces your current session list.</p>
	</form>
</div>

<div class="wise-mirror-panel">
	<h3>Reset</h3>
	<p class="description">Resets all plugin settings (Stripe keys, email, AI config, sessions, form HTML/CSS/JS, schedule, webhooks) back to defaults. Bookings and payment records are <strong>not</strong> deleted.</p>
	<form method="post" onsubmit="return confirm('This resets ALL plugin settings to defaults. Are you absolutely sure?');">
		<?php wp_nonce_field( 'wise_mirror_save_reset_plugin', 'wise_mirror_nonce' ); ?>
		<input type="hidden" name="wise_mirror_action" value="reset_plugin">
		<p>Type <code>RESET</code> to confirm: <input type="text" name="confirm" required></p>
		<?php submit_button( 'Reset Plugin Settings', 'delete' ); ?>
	</form>
</div>
