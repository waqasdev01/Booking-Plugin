<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
$ai = Wise_Mirror_Settings::ai_settings();
?>
<div class="wise-mirror-page-header">
	<h2>AI Configuration</h2>
	<p>Connect an external AI provider. Nothing in the booking flow calls this automatically yet — it's available to anything using the internal API's <code>/ai/generate</code> endpoint (see <strong>API Manager</strong>), and to future features like automated image analysis.</p>
</div>

<div class="wise-mirror-panel">
	<form method="post" id="wise-ai-form">
		<?php wp_nonce_field( 'wise_mirror_save_ai_settings', 'wise_mirror_nonce' ); ?>
		<input type="hidden" name="wise_mirror_action" value="ai_settings">

		<table class="form-table">
			<tr>
				<th>Provider</th>
				<td>
					<label><input type="radio" class="wise-ai-provider-radio" name="ai[provider]" value="none" <?php checked( $ai['provider'], 'none' ); ?>> None</label>
					<label><input type="radio" class="wise-ai-provider-radio" name="ai[provider]" value="openai" <?php checked( $ai['provider'], 'openai' ); ?>> OpenAI</label>
					<label><input type="radio" class="wise-ai-provider-radio" name="ai[provider]" value="claude" <?php checked( $ai['provider'], 'claude' ); ?>> Claude</label>
					<label><input type="radio" class="wise-ai-provider-radio" name="ai[provider]" value="gemini" <?php checked( $ai['provider'], 'gemini' ); ?>> Gemini</label>
					<label><input type="radio" class="wise-ai-provider-radio" name="ai[provider]" value="custom" <?php checked( $ai['provider'], 'custom' ); ?>> Custom Provider</label>
				</td>
			</tr>
		</table>

		<div class="wise-ai-provider-fields" data-provider="openai">
			<h3>OpenAI</h3>
			<table class="form-table">
				<tr><th>API Key</th><td><input type="password" name="ai[openai_api_key]" class="regular-text" value="<?php echo esc_attr( $ai['openai_api_key'] ); ?>" placeholder="sk-…" autocomplete="new-password"></td></tr>
				<tr><th>Model</th><td><input type="text" name="ai[openai_model]" class="regular-text" value="<?php echo esc_attr( $ai['openai_model'] ); ?>"></td></tr>
			</table>
		</div>

		<div class="wise-ai-provider-fields" data-provider="claude">
			<h3>Claude</h3>
			<table class="form-table">
				<tr><th>API Key</th><td><input type="password" name="ai[claude_api_key]" class="regular-text" value="<?php echo esc_attr( $ai['claude_api_key'] ); ?>" placeholder="sk-ant-…" autocomplete="new-password"></td></tr>
				<tr><th>Model</th><td><input type="text" name="ai[claude_model]" class="regular-text" value="<?php echo esc_attr( $ai['claude_model'] ); ?>"></td></tr>
			</table>
		</div>

		<div class="wise-ai-provider-fields" data-provider="gemini">
			<h3>Gemini</h3>
			<table class="form-table">
				<tr><th>API Key</th><td><input type="password" name="ai[gemini_api_key]" class="regular-text" value="<?php echo esc_attr( $ai['gemini_api_key'] ); ?>" autocomplete="new-password"></td></tr>
				<tr><th>Model</th><td><input type="text" name="ai[gemini_model]" class="regular-text" value="<?php echo esc_attr( $ai['gemini_model'] ); ?>"></td></tr>
			</table>
		</div>

		<div class="wise-ai-provider-fields" data-provider="custom">
			<h3>Custom Provider</h3>
			<table class="form-table">
				<tr><th>Endpoint URL</th><td><input type="url" name="ai[custom_endpoint]" class="regular-text" value="<?php echo esc_attr( $ai['custom_endpoint'] ); ?>" placeholder="https://your-ai-provider.com/v1/complete"></td></tr>
				<tr><th>API Key</th><td><input type="password" name="ai[custom_api_key]" class="regular-text" value="<?php echo esc_attr( $ai['custom_api_key'] ); ?>" autocomplete="new-password"></td></tr>
			</table>
			<p class="description">Sent as <code>Authorization: Bearer …</code> with a JSON body of <code>{ prompt, system_prompt, max_tokens, temperature }</code>. Expects back JSON with a <code>text</code>, <code>response</code>, or <code>output</code> field.</p>
		</div>

		<h3>Prompts &amp; Response Settings</h3>
		<table class="form-table">
			<tr><th>System Prompt</th><td><textarea name="ai[system_prompt]" rows="3" class="large-text"><?php echo esc_textarea( $ai['system_prompt'] ); ?></textarea></td></tr>
			<tr>
				<th>Image Analysis</th>
				<td>
					<label><input type="checkbox" name="ai[image_analysis_enabled]" value="1" <?php checked( $ai['image_analysis_enabled'] ); ?>> Enabled</label>
					<p class="description">Foundation setting for a future feature — analyzing uploaded booking photos isn't wired up yet.</p>
				</td>
			</tr>
			<tr><th>Image Analysis Prompt</th><td><textarea name="ai[image_analysis_prompt]" rows="2" class="large-text"><?php echo esc_textarea( $ai['image_analysis_prompt'] ); ?></textarea></td></tr>
			<tr><th>Max Tokens</th><td><input type="number" min="50" name="ai[max_tokens]" class="small-text" value="<?php echo esc_attr( $ai['max_tokens'] ); ?>"></td></tr>
			<tr><th>Temperature</th><td><input type="number" min="0" max="2" step="0.1" name="ai[temperature]" class="small-text" value="<?php echo esc_attr( $ai['temperature'] ); ?>"></td></tr>
			<tr><th>AI Logging</th><td><label><input type="checkbox" name="ai[logging_enabled]" value="1" <?php checked( $ai['logging_enabled'] ); ?>> Log AI requests under System Settings → Logs</label></td></tr>
		</table>

		<?php submit_button( 'Save AI Configuration' ); ?>
	</form>

	<div class="wise-mirror-test-block">
		<h3>Test Connection</h3>
		<textarea id="wise-ai-test-prompt" rows="2" class="large-text" placeholder="Say hello in one sentence.">Say hello in one sentence.</textarea>
		<button type="button" class="button" id="wise-ai-test-btn">Send Test Prompt</button>
		<div id="wise-ai-test-result" class="wise-mirror-inline-note" hidden></div>
	</div>
</div>

<script>
jQuery(function($){
	function syncProvider(){
		var provider = $('.wise-ai-provider-radio:checked').val();
		$('.wise-ai-provider-fields').hide();
		$('.wise-ai-provider-fields[data-provider="' + provider + '"]').show();
	}
	$('.wise-ai-provider-radio').on('change', syncProvider);
	syncProvider();

	$('#wise-ai-test-btn').on('click', function(){
		var btn = $(this);
		var resultEl = $('#wise-ai-test-result');
		btn.prop('disabled', true).text('Sending…');
		resultEl.hide();

		$.post(WiseMirrorAdmin.ajaxUrl, {
			action: 'wise_admin_ai_test',
			nonce: WiseMirrorAdmin.nonce,
			prompt: $('#wise-ai-test-prompt').val()
		}).done(function(res){
			resultEl.show().text(res.success ? res.data.response : ('Error: ' + res.data.message));
		}).fail(function(){
			resultEl.show().text('Request failed.');
		}).always(function(){
			btn.prop('disabled', false).text('Send Test Prompt');
		});
	});
});
</script>
