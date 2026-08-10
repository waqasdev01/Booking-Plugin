jQuery(function ($) {
	// Copy-to-clipboard for booking links, API keys, endpoints, etc.
	$(document).on('click', '.wise-copy-btn', function () {
		var text = $(this).data('copy');
		var $btn = $(this);
		if (navigator.clipboard) {
			navigator.clipboard.writeText(String(text)).then(function () {
				var original = $btn.text();
				$btn.text('Copied!');
				setTimeout(function () { $btn.text(original); }, 1500);
			});
		}
	});

	// Sidebar group accordion (Dashboard, Bookings).
	$('.wise-mirror-nav-group-toggle').on('click', function () {
		$(this).closest('.wise-mirror-nav-group').toggleClass('wise-open');
	});

	// Dark/light mode toggle, persisted per-browser.
	var root = document.getElementById('wise-mirror-admin-root');
	var toggleBtn = document.getElementById('wise-mirror-theme-toggle');
	var STORAGE_KEY = 'wiseMirrorAdminTheme';

	function applyTheme(theme) {
		if (!root) return;
		root.classList.toggle('wise-dark-mode', theme === 'dark');
	}

	var saved = null;
	try { saved = window.localStorage.getItem(STORAGE_KEY); } catch (e) {}
	applyTheme(saved || 'light');

	if (toggleBtn) {
		toggleBtn.addEventListener('click', function () {
			var isDark = root.classList.contains('wise-dark-mode');
			var next = isDark ? 'light' : 'dark';
			applyTheme(next);
			try { window.localStorage.setItem(STORAGE_KEY, next); } catch (e) {}
		});
	}
});
