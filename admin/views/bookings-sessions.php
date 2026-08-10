<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
$sessions = Wise_Mirror_Sessions::get_all();
?>
<div class="wise-mirror-page-header">
	<h2>Session Management</h2>
	<p>Every bookable session is fully editable here — nothing is hardcoded. Drag the handle to reorder, duplicate a session as a starting point for a new one, or delete it entirely.</p>
</div>

<div class="wise-mirror-panel">
	<form method="post" id="wise-sessions-form">
		<?php wp_nonce_field( 'wise_mirror_save_sessions', 'wise_mirror_nonce' ); ?>
		<input type="hidden" name="wise_mirror_action" value="sessions">

		<div class="wise-mirror-sessions-list" id="wise-sessions-list">
			<?php foreach ( $sessions as $i => $s ) : ?>
				<?php echo Wise_Mirror_Sessions::render_admin_row( $s, $i ); // phpcs:ignore ?>
			<?php endforeach; ?>
		</div>

		<button type="button" class="button" id="wise-add-session">+ Add Session</button>
		<div class="wise-mirror-form-actions">
			<?php submit_button( 'Save Sessions', 'primary', 'submit', false ); ?>
		</div>
	</form>
</div>

<template id="wise-session-row-template">
	<?php echo Wise_Mirror_Sessions::render_admin_row( null, '__INDEX__' ); // phpcs:ignore ?>
</template>

<script>
(function(){
	var list = document.getElementById('wise-sessions-list');
	var template = document.getElementById('wise-session-row-template');
	var counter = <?php echo (int) count( $sessions ); ?>;

	document.getElementById('wise-add-session').addEventListener('click', function(){
		var html = template.innerHTML.replace(/__INDEX__/g, counter);
		var wrap = document.createElement('div');
		wrap.innerHTML = html.trim();
		list.appendChild(wrap.firstChild);
		counter++;
	});

	list.addEventListener('click', function(e){
		if (e.target.classList.contains('wise-session-delete')){
			if (confirm('Delete this session?')) e.target.closest('.wise-session-row').remove();
		}
		if (e.target.classList.contains('wise-session-duplicate')){
			var row = e.target.closest('.wise-session-row');
			var clone = row.cloneNode(true);
			clone.querySelectorAll('input, textarea').forEach(function(el){
				if (el.name.indexOf('[key]') > -1) el.value = '';
				if (el.name.indexOf('[name]') > -1) el.value = el.value + ' (Copy)';
			});
			list.insertBefore(clone, row.nextSibling);
		}
		if (e.target.classList.contains('wise-session-toggle')){
			var body = e.target.closest('.wise-session-row').querySelector('.wise-session-body');
			body.hidden = !body.hidden;
			e.target.textContent = body.hidden ? 'Expand' : 'Collapse';
		}
	});

	if (window.jQuery && jQuery.fn.sortable) {
		jQuery(list).sortable({ handle: '.wise-drag-handle', axis: 'y' });
	}
})();
</script>
