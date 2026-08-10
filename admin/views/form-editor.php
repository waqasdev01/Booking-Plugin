<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
$html = get_option( 'wise_mirror_form_html', Wise_Mirror_Settings::default_form_html() );
$css  = get_option( 'wise_mirror_form_css', Wise_Mirror_Settings::default_form_css() );
$js   = get_option( 'wise_mirror_form_js', Wise_Mirror_Settings::default_form_js() );
$sessions = Wise_Mirror_Sessions::get_active();
$sessions_by_key = array();
foreach ( $sessions as $s ) {
	$sessions_by_key[ $s['key'] ] = $s;
}
?>
<div class="wise-mirror-page-header">
	<h2>Form Editor</h2>
	<p>Everything about the booking form — markup, styling, behavior, and a live preview — lives on this one screen. Rendered wherever <code>[wise_booking_form]</code> is placed.</p>
</div>

<div class="wise-mirror-panel">
	<div class="wise-mirror-subtabs" id="wise-fe-subtabs">
		<button type="button" class="wise-active" data-target="fe-html">HTML</button>
		<button type="button" data-target="fe-css">CSS</button>
		<button type="button" data-target="fe-js">JavaScript</button>
		<button type="button" data-target="fe-preview">Live Preview</button>
	</div>

	<form method="post" id="wise-form-editor-form">
		<?php wp_nonce_field( 'wise_mirror_save_html_editor', 'wise_mirror_nonce' ); ?>
		<input type="hidden" name="wise_mirror_action" value="html_editor">

		<div class="wise-mirror-subtab-panel" data-panel="fe-html">
			<p class="description">Field <code>name</code> attributes must stay the same for submissions to be captured correctly.</p>
			<textarea id="wise-fe-html" name="html" class="wise-mirror-code-editor"><?php echo esc_textarea( $html ); ?></textarea>
		</div>

		<div class="wise-mirror-subtab-panel" data-panel="fe-css" hidden>
			<textarea id="wise-fe-css" name="css" class="wise-mirror-code-editor"><?php echo esc_textarea( $css ); ?></textarea>
		</div>

		<div class="wise-mirror-subtab-panel" data-panel="fe-js" hidden>
			<p class="description">Controls the wizard steps, drag &amp; drop uploads, and checkout handoff to Stripe. Edit carefully.</p>
			<textarea id="wise-fe-js" name="js" class="wise-mirror-code-editor"><?php echo esc_textarea( $js ); ?></textarea>
		</div>

		<div class="wise-mirror-subtab-panel" data-panel="fe-preview" hidden>
			<p class="description">A sandboxed, client-side preview using sample data (Stripe checkout and photo uploads are disabled here). Click "Refresh Preview" after editing to see changes.</p>
			<button type="button" class="button" id="wise-fe-refresh-preview">Refresh Preview</button>
			<iframe id="wise-fe-preview-frame" class="wise-mirror-preview-frame"></iframe>
		</div>

		<div class="wise-mirror-form-actions">
			<?php submit_button( 'Save Changes', 'primary', 'submit', false ); ?>
		</div>
	</form>

	<form method="post" onsubmit="return confirm('Reset the form HTML, CSS, and JS back to the original defaults? This cannot be undone.');" class="wise-mirror-reset-form">
		<?php wp_nonce_field( 'wise_mirror_save_reset_form', 'wise_mirror_nonce' ); ?>
		<input type="hidden" name="wise_mirror_action" value="reset_form">
		<?php submit_button( 'Reset Changes', 'secondary', 'submit', false ); ?>
	</form>
</div>

<script>
(function(){
	var sampleSessions = <?php echo wp_json_encode( $sessions_by_key ); ?>;
	var sampleOrder = <?php echo wp_json_encode( array_keys( $sessions_by_key ) ); ?>;

	// Sub-tab switching
	document.querySelectorAll('#wise-fe-subtabs button').forEach(function(btn){
		btn.addEventListener('click', function(){
			document.querySelectorAll('#wise-fe-subtabs button').forEach(function(b){ b.classList.remove('wise-active'); });
			btn.classList.add('wise-active');
			document.querySelectorAll('.wise-mirror-subtab-panel').forEach(function(p){ p.hidden = (p.dataset.panel !== btn.dataset.target); });
			if (btn.dataset.target === 'fe-preview') { buildPreview(); }
		});
	});

	var editors = {};

	function buildPreview(){
		if (editors.html) editors.html.codemirror.save();
		if (editors.css) editors.css.codemirror.save();
		if (editors.js) editors.js.codemirror.save();

		var html = document.getElementById('wise-fe-html').value;
		var css = document.getElementById('wise-fe-css').value;
		var js = document.getElementById('wise-fe-js').value;
		var frame = document.getElementById('wise-fe-preview-frame');

		var doc = '<!DOCTYPE html><html><head><meta charset="utf-8">' +
			'<style>body{font-family:sans-serif;margin:16px;background:#fafafa;}' + css + '</style></head><body>' +
			html +
			'<script>window.WiseMirrorBooking = { ajaxUrl:"#", restUrl:"#", nonce:"preview", restNonce:"preview",' +
			' sessions: ' + JSON.stringify(sampleSessions) + ', sessionsOrder: ' + JSON.stringify(sampleOrder) + ',' +
			' includesItems: ["Sample included item one","Sample included item two"], supportEmail:"", scheduleAdvanceDays:30,' +
			' uploadMaxSizeMb:20, uploadMaxImages:5 };<\/script>' +
			'<script>try{' + js + '}catch(e){document.body.innerHTML += "<pre style=color:red>"+e+"<\/pre>";}<\/script>' +
			'</body></html>';

		frame.srcdoc = doc;
	}

	document.getElementById('wise-fe-refresh-preview').addEventListener('click', buildPreview);

	document.getElementById('wise-form-editor-form').addEventListener('submit', function(){
		if (editors.html) editors.html.codemirror.save();
		if (editors.css) editors.css.codemirror.save();
		if (editors.js) editors.js.codemirror.save();
	});

	if (window.wp && wp.codeEditor) {
		editors.html = wp.codeEditor.initialize(document.getElementById('wise-fe-html'), { codemirror: { mode: 'htmlmixed', lineNumbers: true, lineWrapping: true } });
		editors.css = wp.codeEditor.initialize(document.getElementById('wise-fe-css'), { codemirror: { mode: 'css', lineNumbers: true, lineWrapping: true } });
		editors.js = wp.codeEditor.initialize(document.getElementById('wise-fe-js'), { codemirror: { mode: 'javascript', lineNumbers: true, lineWrapping: true } });
	}
})();
</script>
