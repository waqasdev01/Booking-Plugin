<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
$api      = Wise_Mirror_Api_Manager::get_credentials();
$token    = Wise_Mirror_Api_Manager::get_auth_token();
$endpoint = Wise_Mirror_Api_Manager::get_endpoint_base();
$webhooks = Wise_Mirror_Webhooks::get_all();
$events   = array( 'booking.created' => 'Booking Created', 'payment.confirmed' => 'Payment Confirmed' );
?>
<div class="wise-mirror-page-header">
	<h2>API Manager</h2>
	<p>The plugin's own internal REST API — for pulling bookings, customers, sessions, and payments into other systems, or triggering an AI response. Every request must include the API Key and Secret below.</p>
</div>

<div class="wise-mirror-cards">
	<div class="wise-mirror-card">
		<span class="wise-mirror-card-label">Status</span>
		<span class="wise-mirror-card-value wise-mirror-badge-<?php echo $api['enabled'] ? 'test' : 'live'; ?>"><?php echo $api['enabled'] ? 'ENABLED' : 'DISABLED'; ?></span>
	</div>
	<div class="wise-mirror-card">
		<span class="wise-mirror-card-label">Requests Served</span>
		<span class="wise-mirror-card-value"><?php echo esc_html( $api['request_count'] ); ?></span>
	</div>
	<div class="wise-mirror-card">
		<span class="wise-mirror-card-label">Last Used</span>
		<span class="wise-mirror-card-value" style="font-size:15px;"><?php echo esc_html( $api['last_used'] ?: 'Never' ); ?></span>
	</div>
	<div class="wise-mirror-card">
		<span class="wise-mirror-card-label">Created</span>
		<span class="wise-mirror-card-value" style="font-size:15px;"><?php echo esc_html( $api['created_date'] ); ?></span>
	</div>
</div>

<div class="wise-mirror-panel">
	<h3>Credentials</h3>
	<table class="form-table">
		<tr>
			<th>API Endpoint</th>
			<td><code class="wise-mirror-code-block"><?php echo esc_html( $endpoint ); ?></code>
				<button type="button" class="button button-small wise-copy-btn" data-copy="<?php echo esc_attr( $endpoint ); ?>">Copy</button>
				API Version: <strong>v1</strong>
			</td>
		</tr>
		<tr>
			<th>API Key</th>
			<td><code class="wise-mirror-code-block"><?php echo esc_html( $api['api_key'] ); ?></code>
				<button type="button" class="button button-small wise-copy-btn" data-copy="<?php echo esc_attr( $api['api_key'] ); ?>">Copy</button>
			</td>
		</tr>
		<tr>
			<th>API Secret</th>
			<td><code class="wise-mirror-code-block"><?php echo esc_html( $api['api_secret'] ); ?></code>
				<button type="button" class="button button-small wise-copy-btn" data-copy="<?php echo esc_attr( $api['api_secret'] ); ?>">Copy</button>
			</td>
		</tr>
		<tr>
			<th>Authentication Token</th>
			<td><code class="wise-mirror-code-block"><?php echo esc_html( $token ); ?></code>
				<button type="button" class="button button-small wise-copy-btn" data-copy="<?php echo esc_attr( $token ); ?>">Copy</button>
				<p class="description">Shortcut for callers that prefer one bearer token: <code>Authorization: Bearer <?php echo esc_html( $token ); ?></code> — equivalent to sending the key and secret as separate headers.</p>
			</td>
		</tr>
	</table>

	<div class="wise-mirror-form-actions">
		<form method="post" style="display:inline;">
			<?php wp_nonce_field( 'wise_mirror_save_api_toggle', 'wise_mirror_nonce' ); ?>
			<input type="hidden" name="wise_mirror_action" value="api_toggle">
			<input type="hidden" name="enabled" value="<?php echo $api['enabled'] ? '0' : '1'; ?>">
			<?php submit_button( $api['enabled'] ? 'Disable API' : 'Enable API', 'secondary', 'submit', false ); ?>
		</form>
		<form method="post" style="display:inline;" onsubmit="return confirm('Regenerating will invalidate the current key/secret immediately. Continue?');">
			<?php wp_nonce_field( 'wise_mirror_save_api_regenerate', 'wise_mirror_nonce' ); ?>
			<input type="hidden" name="wise_mirror_action" value="api_regenerate">
			<?php submit_button( 'Regenerate API Key', 'secondary', 'submit', false ); ?>
		</form>
	</div>
</div>

<div class="wise-mirror-panel">
	<h3>Webhooks</h3>
	<p class="description">Fire a POST to your own URL when something happens — no polling needed.</p>
	<form method="post" id="wise-webhooks-form">
		<?php wp_nonce_field( 'wise_mirror_save_webhooks', 'wise_mirror_nonce' ); ?>
		<input type="hidden" name="wise_mirror_action" value="webhooks">
		<div id="wise-webhooks-list">
			<?php foreach ( $webhooks as $i => $hook ) : ?>
				<div class="wise-webhook-row">
					<input type="url" name="webhooks[<?php echo (int) $i; ?>][url]" value="<?php echo esc_attr( $hook['url'] ); ?>" class="regular-text" placeholder="https://example.com/webhook">
					<?php foreach ( $events as $ekey => $elabel ) : ?>
						<label><input type="checkbox" name="webhooks[<?php echo (int) $i; ?>][events][]" value="<?php echo esc_attr( $ekey ); ?>" <?php checked( in_array( $ekey, $hook['events'], true ) ); ?>> <?php echo esc_html( $elabel ); ?></label>
					<?php endforeach; ?>
					<label><input type="checkbox" name="webhooks[<?php echo (int) $i; ?>][enabled]" value="1" <?php checked( $hook['enabled'] ); ?>> Enabled</label>
					<button type="button" class="button button-small wise-webhook-remove">Remove</button>
					<button type="button" class="button button-small wise-webhook-test" data-url-field="prev">Send Test</button>
				</div>
			<?php endforeach; ?>
		</div>
		<button type="button" class="button" id="wise-webhook-add">+ Add Webhook</button>
		<div class="wise-mirror-form-actions"><?php submit_button( 'Save Webhooks', 'primary', 'submit', false ); ?></div>
	</form>
</div>

<div class="wise-mirror-panel">
	<h3>Endpoints &amp; Documentation</h3>
	<p class="description">Auto-generated from the plugin's active route list — this is always in sync with what's actually available.</p>
	<div class="wise-mirror-table-scroll">
	<table class="widefat striped">
		<thead><tr><th>Method</th><th>Path</th><th>Description</th><th>Status</th></tr></thead>
		<tbody>
			<?php foreach ( Wise_Mirror_Api_Registry::endpoints() as $ep ) : ?>
				<tr>
					<td><code><?php echo esc_html( $ep['method'] ); ?></code></td>
					<td><code><?php echo esc_html( rtrim( $endpoint, '/' ) . '/' . $ep['path'] ); ?></code></td>
					<td><?php echo esc_html( $ep['description'] ); ?></td>
					<td><span class="wise-mirror-status wise-mirror-status-<?php echo 'live' === $ep['status'] ? 'paid' : 'pending'; ?>"><?php echo esc_html( 'live' === $ep['status'] ? 'Live' : 'Coming Soon' ); ?></span></td>
				</tr>
			<?php endforeach; ?>
		</tbody>
	</table>
	</div>
	<p class="description" style="margin-top:14px;">Example request:</p>
	<code class="wise-mirror-code-block" style="display:block;white-space:pre-wrap;">curl <?php echo esc_html( rtrim( $endpoint, '/' ) ); ?>/bookings \
  -H "X-Wise-Api-Key: <?php echo esc_html( $api['api_key'] ); ?>" \
  -H "X-Wise-Api-Secret: <?php echo esc_html( $api['api_secret'] ); ?>"</code>

	<div class="wise-mirror-inline-note" style="margin-top:16px;">
		<strong>Coming soon:</strong> CRM integration — connect this booking data to a specific CRM once you tell us which one you use.
	</div>
</div>

<script>
jQuery(function($){
	var events = <?php echo wp_json_encode( $events ); ?>;
	var idx = <?php echo (int) count( $webhooks ); ?>;

	$('#wise-webhook-add').on('click', function(){
		var row = $('<div class="wise-webhook-row"></div>');
		row.append('<input type="url" name="webhooks[' + idx + '][url]" class="regular-text" placeholder="https://example.com/webhook">');
		$.each(events, function(key, label){
			row.append('<label><input type="checkbox" name="webhooks[' + idx + '][events][]" value="' + key + '"> ' + label + '</label>');
		});
		row.append('<label><input type="checkbox" name="webhooks[' + idx + '][enabled]" value="1" checked> Enabled</label>');
		row.append('<button type="button" class="button button-small wise-webhook-remove">Remove</button>');
		row.append('<button type="button" class="button button-small wise-webhook-test">Send Test</button>');
		$('#wise-webhooks-list').append(row);
		idx++;
	});

	$('#wise-webhooks-list').on('click', '.wise-webhook-remove', function(){
		$(this).closest('.wise-webhook-row').remove();
	});

	$('#wise-webhooks-list').on('click', '.wise-webhook-test', function(){
		var btn = $(this);
		var url = btn.closest('.wise-webhook-row').find('input[type=url]').val();
		if (!url) { alert('Enter a URL first.'); return; }
		btn.prop('disabled', true).text('Sending…');
		$.post(WiseMirrorAdmin.ajaxUrl, { action: 'wise_admin_webhook_test', nonce: WiseMirrorAdmin.nonce, url: url })
			.done(function(res){ alert(res.success ? 'Test payload sent.' : ('Failed: ' + res.data.message)); })
			.fail(function(){ alert('Request failed.'); })
			.always(function(){ btn.prop('disabled', false).text('Send Test'); });
	});
});
</script>
