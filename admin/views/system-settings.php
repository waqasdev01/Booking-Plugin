<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
$system  = Wise_Mirror_Settings::system_settings();
$stripe  = Wise_Mirror_Settings::stripe();
$email   = Wise_Mirror_Settings::email_settings();
$template = Wise_Mirror_Settings::email_template();
$uploads = Wise_Mirror_Settings::upload_settings();

$log_category = isset( $_GET['log_cat'] ) ? sanitize_key( wp_unslash( $_GET['log_cat'] ) ) : ''; // phpcs:ignore
$log_search   = isset( $_GET['log_search'] ) ? sanitize_text_field( wp_unslash( $_GET['log_search'] ) ) : ''; // phpcs:ignore
$log_entries  = Wise_Mirror_Logger::get_entries( $log_category, $log_search );

if ( isset( $_GET['wise_export_logs'] ) ) { // phpcs:ignore
	nocache_headers();
	header( 'Content-Type: text/csv' );
	header( 'Content-Disposition: attachment; filename="wise-mirror-logs.csv"' );
	echo Wise_Mirror_Logger::to_csv( $log_category, $log_search ); // phpcs:ignore
	exit;
}
?>
<div class="wise-mirror-page-header">
	<h2>System Settings</h2>
	<p>Everything system-level — general options, payments, email delivery, notifications, debug mode, caching, logs, security, and performance — lives on this one screen.</p>
</div>

<div class="wise-mirror-panel">
	<div class="wise-mirror-subtabs wise-mirror-subtabs-wrap" id="wise-ss-subtabs">
		<button type="button" class="wise-active" data-target="ss-general">General</button>
		<button type="button" data-target="ss-payments">Payments</button>
		<button type="button" data-target="ss-email">Email</button>
		<button type="button" data-target="ss-notifications">Notifications</button>
		<button type="button" data-target="ss-uploads">Uploads</button>
		<button type="button" data-target="ss-debug">Debug</button>
		<button type="button" data-target="ss-cache">Cache</button>
		<button type="button" data-target="ss-logs">Logs</button>
		<button type="button" data-target="ss-security">Security</button>
		<button type="button" data-target="ss-performance">Performance</button>
		<button type="button" data-target="ss-license">License</button>
		<button type="button" data-target="ss-version">Version</button>
	</div>

	<div class="wise-mirror-subtab-panel" data-panel="ss-general">
		<form method="post">
			<?php wp_nonce_field( 'wise_mirror_save_system_general', 'wise_mirror_nonce' ); ?>
			<input type="hidden" name="wise_mirror_action" value="system_general">
			<table class="form-table">
				<tr><th>Debug Mode</th><td><label><input type="checkbox" name="system[debug_mode]" value="1" <?php checked( $system['debug_mode'] ); ?>> Log extra debug entries</label></td></tr>
				<tr><th>License Key</th><td><input type="text" name="system[license_key]" class="regular-text" value="<?php echo esc_attr( $system['license_key'] ); ?>"></td></tr>
			</table>
			<?php submit_button( 'Save General Settings' ); ?>
		</form>
	</div>

	<div class="wise-mirror-subtab-panel" data-panel="ss-payments" hidden>
		<form method="post">
			<?php wp_nonce_field( 'wise_mirror_save_stripe_settings', 'wise_mirror_nonce' ); ?>
			<input type="hidden" name="wise_mirror_action" value="stripe_settings">
			<table class="form-table">
				<tr>
					<th>Mode</th>
					<td>
						<label><input type="radio" class="wise-mode-radio" name="stripe[mode]" value="test" <?php checked( $stripe['mode'], 'test' ); ?>> Test</label>
						<label><input type="radio" class="wise-mode-radio" name="stripe[mode]" value="live" <?php checked( $stripe['mode'], 'live' ); ?>> Live</label>
					</td>
				</tr>
			</table>
			<div class="wise-mode-fields" data-mode="test">
				<h4>Test Mode</h4>
				<table class="form-table">
					<tr><th>Publishable Key</th><td><input type="text" name="stripe[test_publishable_key]" class="regular-text" value="<?php echo esc_attr( $stripe['test_publishable_key'] ); ?>" placeholder="pk_test_…"></td></tr>
					<tr><th>Secret Key</th><td><input type="password" name="stripe[test_secret_key]" class="regular-text" value="<?php echo esc_attr( $stripe['test_secret_key'] ); ?>" placeholder="sk_test_…" autocomplete="new-password"></td></tr>
					<tr><th>Webhook Secret</th><td><input type="password" name="stripe[webhook_secret_test]" class="regular-text" value="<?php echo esc_attr( $stripe['webhook_secret_test'] ); ?>" placeholder="whsec_…" autocomplete="new-password"></td></tr>
				</table>
			</div>
			<div class="wise-mode-fields" data-mode="live">
				<h4>Live Mode</h4>
				<table class="form-table">
					<tr><th>Publishable Key</th><td><input type="text" name="stripe[live_publishable_key]" class="regular-text" value="<?php echo esc_attr( $stripe['live_publishable_key'] ); ?>" placeholder="pk_live_…"></td></tr>
					<tr><th>Secret Key</th><td><input type="password" name="stripe[live_secret_key]" class="regular-text" value="<?php echo esc_attr( $stripe['live_secret_key'] ); ?>" placeholder="sk_live_…" autocomplete="new-password"></td></tr>
					<tr><th>Webhook Secret</th><td><input type="password" name="stripe[webhook_secret_live]" class="regular-text" value="<?php echo esc_attr( $stripe['webhook_secret_live'] ); ?>" placeholder="whsec_…" autocomplete="new-password"></td></tr>
				</table>
			</div>
			<p class="wise-mirror-inline-note"><strong>Webhook URL:</strong> <code class="wise-mirror-code-block"><?php echo esc_html( rest_url( 'wise/v1/stripe-webhook' ) ); ?></code></p>
			<?php submit_button( 'Save Payment Settings' ); ?>
		</form>
	</div>

	<div class="wise-mirror-subtab-panel" data-panel="ss-email" hidden>
		<form method="post">
			<?php wp_nonce_field( 'wise_mirror_save_email_settings', 'wise_mirror_nonce' ); ?>
			<input type="hidden" name="wise_mirror_action" value="email_settings">
			<table class="form-table">
				<tr><th>Method</th><td>
					<label><input type="radio" name="email[method]" value="wp_mail" <?php checked( $email['method'], 'wp_mail' ); ?>> WordPress Default</label>
					<label><input type="radio" name="email[method]" value="smtp" <?php checked( $email['method'], 'smtp' ); ?>> SMTP</label>
				</td></tr>
				<tr><th>From Name</th><td><input type="text" name="email[from_name]" class="regular-text" value="<?php echo esc_attr( $email['from_name'] ); ?>"></td></tr>
				<tr><th>From Email</th><td><input type="email" name="email[from_email]" class="regular-text" value="<?php echo esc_attr( $email['from_email'] ); ?>"></td></tr>
				<tr><th>SMTP Host</th><td><input type="text" name="email[smtp_host]" class="regular-text" value="<?php echo esc_attr( $email['smtp_host'] ); ?>"></td></tr>
				<tr><th>SMTP Port</th><td><input type="text" name="email[smtp_port]" class="small-text" value="<?php echo esc_attr( $email['smtp_port'] ); ?>"></td></tr>
				<tr><th>Encryption</th><td>
					<select name="email[smtp_secure]">
						<option value="tls" <?php selected( $email['smtp_secure'], 'tls' ); ?>>TLS</option>
						<option value="ssl" <?php selected( $email['smtp_secure'], 'ssl' ); ?>>SSL</option>
						<option value="none" <?php selected( $email['smtp_secure'], 'none' ); ?>>None</option>
					</select>
				</td></tr>
				<tr><th>SMTP Username</th><td><input type="text" name="email[smtp_user]" class="regular-text" value="<?php echo esc_attr( $email['smtp_user'] ); ?>"></td></tr>
				<tr><th>SMTP Password</th><td><input type="password" name="email[smtp_pass]" class="regular-text" value="<?php echo esc_attr( $email['smtp_pass'] ); ?>" autocomplete="new-password"></td></tr>
			</table>
			<?php submit_button( 'Save Email Settings', 'primary', 'submit', false ); ?>
		</form>

		<h4 style="margin-top:28px;">Confirmation Email Template</h4>
		<form method="post">
			<?php wp_nonce_field( 'wise_mirror_save_email_template', 'wise_mirror_nonce' ); ?>
			<input type="hidden" name="wise_mirror_action" value="email_template">
			<p class="description">Sent only after Stripe verifies payment. Tokens: <code>{full_name}</code> <code>{booking_id}</code> <code>{package_label}</code> <code>{email}</code> <code>{phone}</code> <code>{contact_method}</code> <code>{booking_date}</code> <code>{booking_time}</code></p>
			<table class="form-table">
				<tr><th>Subject</th><td><input type="text" name="template[subject]" class="large-text" value="<?php echo esc_attr( $template['subject'] ); ?>"></td></tr>
				<tr><th>Heading</th><td><input type="text" name="template[heading]" class="large-text" value="<?php echo esc_attr( $template['heading'] ); ?>"></td></tr>
				<tr><th>Body</th><td><textarea name="template[body]" rows="6" class="large-text"><?php echo esc_textarea( $template['body'] ); ?></textarea></td></tr>
				<tr><th>Footer</th><td><input type="text" name="template[footer]" class="large-text" value="<?php echo esc_attr( $template['footer'] ); ?>"></td></tr>
				<tr><th>Button Text</th><td><input type="text" name="template[button_text]" class="regular-text" value="<?php echo esc_attr( $template['button_text'] ); ?>"></td></tr>
				<tr><th>Button URL</th><td><input type="url" name="template[button_url]" class="regular-text" value="<?php echo esc_attr( $template['button_url'] ); ?>"></td></tr>
				<tr><th>Colors</th><td>
					Primary <input type="text" name="template[primary_color]" value="<?php echo esc_attr( $template['primary_color'] ); ?>" class="wise-color-field">
					Background <input type="text" name="template[background_color]" value="<?php echo esc_attr( $template['background_color'] ); ?>" class="wise-color-field">
					Text <input type="text" name="template[text_color]" value="<?php echo esc_attr( $template['text_color'] ); ?>" class="wise-color-field">
				</td></tr>
			</table>
			<?php submit_button( 'Save Email Template', 'primary', 'submit', false ); ?>
		</form>
	</div>

	<div class="wise-mirror-subtab-panel" data-panel="ss-notifications" hidden>
		<form method="post">
			<?php wp_nonce_field( 'wise_mirror_save_system_general', 'wise_mirror_nonce' ); ?>
			<input type="hidden" name="wise_mirror_action" value="system_general">
			<input type="hidden" name="system[debug_mode]" value="<?php echo $system['debug_mode'] ? '1' : '0'; ?>">
			<input type="hidden" name="system[license_key]" value="<?php echo esc_attr( $system['license_key'] ); ?>">
			<table class="form-table">
				<tr><th>Notify Admin on New Booking</th><td><label><input type="checkbox" name="system[notify_admin_on_booking]" value="1" <?php checked( $system['notify_admin_on_booking'] ); ?>> Enabled</label></td></tr>
				<tr><th>Notify Email</th><td><input type="email" name="system[notify_admin_email]" class="regular-text" value="<?php echo esc_attr( $system['notify_admin_email'] ); ?>"></td></tr>
			</table>
			<?php submit_button( 'Save Notification Settings' ); ?>
		</form>

		<div class="wise-mirror-test-block">
			<h4>Test Admin Notification</h4>
			<p class="description">Sends a test email to the Notify Email address above — useful for confirming delivery without waiting for a real booking.</p>
			<button type="button" class="button" id="wise-test-notification-btn">Send Test Email</button>
			<div id="wise-test-notification-result" class="wise-mirror-inline-note" hidden></div>
		</div>
	</div>

	<div class="wise-mirror-subtab-panel" data-panel="ss-uploads" hidden>
		<form method="post">
			<?php wp_nonce_field( 'wise_mirror_save_upload_settings', 'wise_mirror_nonce' ); ?>
			<input type="hidden" name="wise_mirror_action" value="upload_settings">
			<table class="form-table">
				<tr><th>Max Photo Size (MB)</th><td><input type="number" min="1" name="uploads[max_size_mb]" class="small-text" value="<?php echo esc_attr( $uploads['max_size_mb'] ); ?>"></td></tr>
				<tr><th>Max Images per Category</th><td><input type="number" min="1" max="20" name="uploads[max_images_per_field]" class="small-text" value="<?php echo esc_attr( $uploads['max_images_per_field'] ); ?>"></td></tr>
				<tr><th>Allowed File Types</th><td><input type="text" name="uploads[allowed_types]" class="regular-text" value="<?php echo esc_attr( implode( ', ', $uploads['allowed_types'] ) ); ?>"></td></tr>
			</table>

			<h4>Example Photos</h4>
			<p class="description">Shown to customers next to each upload category so they know what's expected. Leave blank to show no example for that category.</p>
			<div class="wise-example-photos">
				<?php
				$examples = array(
					'example_smiling'   => 'Full Face (Smiling)',
					'example_unsmiling' => 'Full Face (Unsmiling)',
					'example_profile'   => 'Side Profile',
				);
				foreach ( $examples as $key => $label ) :
					?>
					<div class="wise-example-photo-field" data-example-field="<?php echo esc_attr( $key ); ?>">
						<span class="wise-example-photo-label"><?php echo esc_html( $label ); ?></span>
						<div class="wise-example-photo-preview">
							<img src="<?php echo esc_url( $uploads[ $key ] ); ?>" <?php echo $uploads[ $key ] ? '' : 'hidden'; ?>>
						</div>
						<input type="hidden" name="uploads[<?php echo esc_attr( $key ); ?>]" value="<?php echo esc_attr( $uploads[ $key ] ); ?>" class="wise-example-photo-input">
						<button type="button" class="button wise-example-photo-choose">Choose Image</button>
						<button type="button" class="button wise-example-photo-remove" <?php echo $uploads[ $key ] ? '' : 'hidden'; ?>>Remove</button>
					</div>
				<?php endforeach; ?>
			</div>

			<?php submit_button( 'Save Upload Settings' ); ?>
		</form>
	</div>

	<div class="wise-mirror-subtab-panel" data-panel="ss-debug" hidden>
		<p class="description">Debug mode (toggled under General) adds verbose entries to the log — useful when troubleshooting a specific booking or payment with your developer.</p>
		<table class="form-table">
			<tr><th>WP_DEBUG</th><td><?php echo defined( 'WP_DEBUG' ) && WP_DEBUG ? 'On' : 'Off'; ?></td></tr>
			<tr><th>Plugin Debug Mode</th><td><?php echo $system['debug_mode'] ? 'On' : 'Off'; ?></td></tr>
			<tr><th>PHP Version</th><td><?php echo esc_html( PHP_VERSION ); ?></td></tr>
			<tr><th>WordPress Version</th><td><?php echo esc_html( get_bloginfo( 'version' ) ); ?></td></tr>
		</table>
	</div>

	<div class="wise-mirror-subtab-panel" data-panel="ss-cache" hidden>
		<p class="description">Frontend session/pricing data is cached briefly to avoid recomputing it on every page load — clears automatically 5 minutes after any change, or immediately if you save from Session Management or the Form Editor.</p>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin.php?page=wise-mirror-booking&tab=system-settings' ) ); ?>">
			<button type="button" class="button" id="wise-clear-cache-btn">Clear Cache Now</button>
		</form>
	</div>

	<div class="wise-mirror-subtab-panel" data-panel="ss-logs" hidden>
		<form method="get" class="wise-mirror-log-filters">
			<input type="hidden" name="page" value="wise-mirror-booking">
			<input type="hidden" name="tab" value="system-settings">
			<select name="log_cat">
				<option value="">All Categories</option>
				<?php foreach ( Wise_Mirror_Logger::CATEGORIES as $cat ) : ?>
					<option value="<?php echo esc_attr( $cat ); ?>" <?php selected( $log_category, $cat ); ?>><?php echo esc_html( ucfirst( $cat ) ); ?></option>
				<?php endforeach; ?>
			</select>
			<input type="text" name="log_search" value="<?php echo esc_attr( $log_search ); ?>" placeholder="Search logs…">
			<button type="submit" class="button">Filter</button>
			<a class="button" href="<?php echo esc_url( add_query_arg( array( 'log_cat' => $log_category, 'log_search' => $log_search, 'wise_export_logs' => 1 ) ) ); ?>">Export CSV</a>
		</form>

		<form method="post" onsubmit="return confirm('Clear all logs?');" style="margin:10px 0;">
			<?php wp_nonce_field( 'wise_mirror_save_clear_logs', 'wise_mirror_nonce' ); ?>
			<input type="hidden" name="wise_mirror_action" value="clear_logs">
			<?php submit_button( 'Clear Logs', 'secondary', 'submit', false ); ?>
		</form>

		<div class="wise-mirror-table-scroll">
		<table class="widefat striped">
			<thead><tr><th style="width:160px;">Time</th><th style="width:90px;">Category</th><th>Message</th><th>Context</th></tr></thead>
			<tbody>
				<?php if ( empty( $log_entries ) ) : ?>
					<tr><td colspan="4">No log entries match.</td></tr>
				<?php endif; ?>
				<?php foreach ( $log_entries as $entry ) : ?>
					<tr>
						<td><?php echo esc_html( $entry['time'] ); ?></td>
						<td><span class="wise-mirror-status wise-mirror-status-<?php echo esc_attr( $entry['level'] ); ?>"><?php echo esc_html( strtoupper( $entry['level'] ) ); ?></span></td>
						<td><?php echo esc_html( $entry['message'] ); ?></td>
						<td><code><?php echo esc_html( wp_json_encode( $entry['context'] ) ); ?></code></td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
		</div>
	</div>

	<div class="wise-mirror-subtab-panel" data-panel="ss-security" hidden>
		<ul class="wise-mirror-link-list">
			<li>✓ Nonce verification on every form save and AJAX request</li>
			<li>✓ Capability checks (<code>manage_options</code>) on all admin actions</li>
			<li>✓ All inputs sanitized, all outputs escaped</li>
			<li>✓ Stripe payments verified server-side against Stripe's API — never trusted from the browser</li>
			<li>✓ Internal API requires a Key + Secret pair, checked with constant-time comparison</li>
			<li>✓ Stripe webhook signature verified (HMAC-SHA256) before trusting a payload</li>
		</ul>
	</div>

	<div class="wise-mirror-subtab-panel" data-panel="ss-performance" hidden>
		<ul class="wise-mirror-link-list">
			<li>✓ Session/pricing data cached via transients (see Cache tab)</li>
			<li>✓ Admin assets only load on the plugin's own dashboard page</li>
			<li>✓ Frontend form CSS/JS only enqueues on pages containing the shortcode</li>
			<li>✓ No external JS/CSS frameworks loaded on the frontend — plain CSS and vanilla JS</li>
		</ul>
	</div>

	<div class="wise-mirror-subtab-panel" data-panel="ss-license" hidden>
		<p class="description">This is a custom-built plugin, not a licensed commercial product — the license key field (under General) is a placeholder for your own tracking if useful; there's no license server to validate against.</p>
	</div>

	<div class="wise-mirror-subtab-panel" data-panel="ss-version" hidden>
		<table class="form-table">
			<tr><th>Plugin Version</th><td><?php echo esc_html( WISE_MIRROR_VERSION ); ?></td></tr>
			<tr><th>Database Tables</th><td><code><?php global $wpdb; echo esc_html( $wpdb->prefix . 'wise_submissions' ); ?></code>, <code><?php echo esc_html( $wpdb->prefix . 'wise_payments' ); ?></code></td></tr>
			<tr><th>REST Namespace</th><td><code>wise/v1</code></td></tr>
			<tr><th>Author</th><td>Waqas</td></tr>
		</table>
	</div>
</div>

<script>
jQuery(function($){
	$('#wise-ss-subtabs button').on('click', function(){
		$('#wise-ss-subtabs button').removeClass('wise-active');
		$(this).addClass('wise-active');
		$('.wise-mirror-subtab-panel').hide().attr('hidden', 'hidden');
		$('.wise-mirror-subtab-panel[data-panel="' + $(this).data('target') + '"]').show().removeAttr('hidden');
	});

	function syncModeFields(){
		var mode = $('.wise-mode-radio:checked').val();
		$('.wise-mode-fields').hide();
		$('.wise-mode-fields[data-mode="' + mode + '"]').show();
	}
	$('.wise-mode-radio').on('change', syncModeFields);
	syncModeFields();

	$('#wise-clear-cache-btn').on('click', function(){
		var btn = $(this);
		btn.prop('disabled', true).text('Clearing…');
		$.post(WiseMirrorAdmin.ajaxUrl, { action: 'wise_admin_clear_cache', nonce: WiseMirrorAdmin.nonce })
			.always(function(){ btn.prop('disabled', false).text('Cache Cleared ✓'); });
	});

	$('#wise-test-notification-btn').on('click', function(){
		var btn = $(this);
		var resultEl = $('#wise-test-notification-result');
		btn.prop('disabled', true).text('Sending…');
		resultEl.hide();
		$.post(WiseMirrorAdmin.ajaxUrl, { action: 'wise_admin_test_notification', nonce: WiseMirrorAdmin.nonce })
			.done(function(res){
				resultEl.show().text(res.success ? res.data.message : ('Error: ' + res.data.message));
			})
			.fail(function(){
				resultEl.show().text('Request failed.');
			})
			.always(function(){
				btn.prop('disabled', false).text('Send Test Email');
			});
	});

	<?php if ( '' !== $log_category || '' !== $log_search ) : ?>
	$('#wise-ss-subtabs button[data-target="ss-logs"]').click();
	<?php endif; ?>

	$('.wise-example-photo-choose').on('click', function(e){
		e.preventDefault();
		var wrap = $(this).closest('.wise-example-photo-field');
		var frame = wp.media({ title: 'Choose Example Photo', multiple: false, library: { type: 'image' } });
		frame.on('select', function(){
			var attachment = frame.state().get('selection').first().toJSON();
			wrap.find('.wise-example-photo-input').val(attachment.url);
			wrap.find('.wise-example-photo-preview img').attr('src', attachment.url).removeAttr('hidden');
			wrap.find('.wise-example-photo-remove').removeAttr('hidden');
		});
		frame.open();
	});

	$('.wise-example-photo-remove').on('click', function(e){
		e.preventDefault();
		var wrap = $(this).closest('.wise-example-photo-field');
		wrap.find('.wise-example-photo-input').val('');
		wrap.find('.wise-example-photo-preview img').attr('hidden', 'hidden');
		$(this).attr('hidden', 'hidden');
	});
});
</script>
